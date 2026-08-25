<?php

namespace FriendsOfRedaxo\Api\RoutePackage;

use Exception;
use FriendsOfRedaxo\Api\Auth\BearerAuth;
use FriendsOfRedaxo\Api\RouteCollection;
use FriendsOfRedaxo\Api\RoutePackage;
use InvalidArgumentException;
use rex;
use rex_dir;
use rex_file;
use rex_media_category;
use rex_media_service;
use rex_mediapool;
use rex_path;
use rex_user;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Route;

use function count;
use function is_array;
use function strlen;

use const JSON_PRETTY_PRINT;
use const UPLOAD_ERR_OK;

/**
 * Chunked Upload: eine Datei in mehreren Requests uebertragen und erst am Ende
 * in den Medienpool legen.
 *
 * Das Anlegen selbst bleibt vollstaendig Core: `finalize` uebergibt die
 * zusammengesetzte Datei an rex_media_service::addMedia() als `file.path`.
 * Der Service verschiebt mit rex_file::move() und nicht mit move_uploaded_file()
 * (mediapool/lib/service_media.php:88) -- genauso wie die Sync-Seite des
 * Medienpools. Damit greifen Endungs-Blockliste, MIME-Allowlist,
 * rex_mediapool::filename(), sanitizeMedia() und die EPs MEDIA_ADD_FILE und
 * MEDIA_ADDED unveraendert. Es entsteht kein zweiter Pfad ins Medienverzeichnis.
 */
class MediaUpload extends RoutePackage
{
    /** Groesste Datei, die in Summe ueber alle Chunks entstehen darf. */
    public const MaxTotalSize = 2147483648; // 2 GiB

    /** Obergrenze fuer den Chunk-Index, damit ein Client kein Verzeichnis flutet. */
    public const MaxChunks = 20000;

    /** Nach dieser Zeit gilt ein begonnener Upload als verwaist und wird aufgeraeumt. */
    public const Ttl = 86400; // 24 Stunden

    /**
     * Alle fuenf Routen teilen sich einen Scope. Einzeln sind sie unbrauchbar --
     * init ohne chunk, chunk ohne finalize --, und eine Teilvergabe wuerde erst
     * mitten in der Uebertragung auffallen, wenn die Daten schon geschickt sind.
     */
    public const Scope = 'media/upload';

    public function loadRoutes(): void
    {
        // Upload beginnen ✅
        RouteCollection::registerRoute(
            'media/upload/init',
            new Route(
                'media/upload',
                [
                    '_controller' => 'FriendsOfRedaxo\Api\RoutePackage\MediaUpload::handleInit',
                    'Body' => [
                        'filename' => [
                            'type' => 'string',
                            'required' => true,
                            'default' => null,
                        ],
                        'size' => [
                            'type' => 'int',
                            'required' => true,
                            'default' => null,
                        ],
                        'category_id' => [
                            'type' => 'int',
                            'required' => false,
                            'default' => 0,
                        ],
                        'title' => [
                            'type' => 'string',
                            'required' => false,
                            'default' => '',
                        ],
                    ],
                ],
                [],
                [],
                '',
                [],
                ['POST']),
            'Start a chunked upload. Returns an upload_id and the maximum size per chunk.',
            null,
            new BearerAuth(true, self::Scope),
        );

        // Stand des Uploads ✅
        RouteCollection::registerRoute(
            'media/upload/status',
            new Route(
                'media/upload/{upload_id}',
                ['_controller' => 'FriendsOfRedaxo\Api\RoutePackage\MediaUpload::handleStatus'],
                ['upload_id' => '[a-f0-9]{32}'],
                [],
                '',
                [],
                ['GET']),
            'Status of a chunked upload: which chunks arrived, how many bytes are missing.',
            null,
            new BearerAuth(true, self::Scope),
        );

        // Einzelnen Chunk uebertragen ✅
        RouteCollection::registerRoute(
            'media/upload/chunk',
            new Route(
                'media/upload/{upload_id}/chunk/{index}',
                [
                    '_controller' => 'FriendsOfRedaxo\Api\RoutePackage\MediaUpload::handleChunk',
                    'Body' => [
                        'chunk' => [
                            'type' => 'file',
                            'required' => false,
                            'description' => 'Chunk-Daten. Alternativ direkt als Request-Body (application/octet-stream).',
                        ],
                    ],
                ],
                [
                    'upload_id' => '[a-f0-9]{32}',
                    'index' => '\d+',
                ],
                [],
                '',
                [],
                ['POST', 'PUT']),
            'Send one chunk. Index is zero-based; sending the same index again replaces it.',
            null,
            new BearerAuth(true, self::Scope),
        );

        // Upload abschliessen ✅
        RouteCollection::registerRoute(
            'media/upload/finalize',
            new Route(
                'media/upload/{upload_id}/finalize',
                ['_controller' => 'FriendsOfRedaxo\Api\RoutePackage\MediaUpload::handleFinalize'],
                ['upload_id' => '[a-f0-9]{32}'],
                [],
                '',
                [],
                ['POST']),
            'Assemble the chunks and add the file to the media pool.',
            null,
            new BearerAuth(true, self::Scope),
        );

        // Upload abbrechen ✅
        RouteCollection::registerRoute(
            'media/upload/delete',
            new Route(
                'media/upload/{upload_id}',
                ['_controller' => 'FriendsOfRedaxo\Api\RoutePackage\MediaUpload::handleAbort'],
                ['upload_id' => '[a-f0-9]{32}'],
                [],
                '',
                [],
                ['DELETE']),
            'Abort a chunked upload and discard the chunks already received.',
            null,
            new BearerAuth(true, self::Scope),
        );
    }

    /** @api */
    public static function handleInit($Parameter, array $Route = []): Response
    {
        $Data = json_decode(rex::getRequest()->getContent(), true);
        if (!is_array($Data)) {
            return new JsonResponse(['error' => 'Invalid input'], 400);
        }

        try {
            $Data = RouteCollection::getQuerySet($Data, $Parameter['Body'], true);
        } catch (InvalidArgumentException $e) {
            // Strikte Pruefung: unbekannte Felder sind keine fehlenden Pflichtfelder.
            return new JsonResponse(['error' => $e->getMessage()], 400);
        } catch (Exception $e) {
            return new JsonResponse(['error' => 'Body field: `' . $e->getMessage() . '` is required'], 400);
        }

        $filename = trim((string) $Data['filename']);
        $size = (int) $Data['size'];
        $categoryId = (int) $Data['category_id'];

        if ('' === $filename) {
            return new JsonResponse(['error' => 'filename must not be empty'], 400);
        }

        // Die Endung schon hier pruefen und nicht erst beim Zusammensetzen: sonst
        // bricht der Upload erst ab, nachdem die ganze Datei uebertragen wurde.
        if (!rex_mediapool::isAllowedExtension($filename)) {
            return new JsonResponse([
                'error' => 'File extension is not allowed',
                'filename' => $filename,
            ], 400);
        }

        if (1 > $size) {
            return new JsonResponse(['error' => 'size must be greater than 0'], 400);
        }
        if ($size > self::MaxTotalSize) {
            return new JsonResponse([
                'error' => 'File too large for chunked upload',
                'size' => $size,
                'max_total_size' => self::MaxTotalSize,
            ], 413);
        }

        $user = RouteCollection::getBackendUser($Route);
        $permResponse = self::checkMediaPerm($user, $categoryId);
        if (null !== $permResponse) {
            return $permResponse;
        }

        if (0 !== $categoryId && !rex_media_category::get($categoryId)) {
            return new JsonResponse(['error' => 'Category not found'], 404);
        }

        self::collectGarbage();

        $uploadId = bin2hex(random_bytes(16));
        $dir = self::uploadDir($uploadId);

        if (!rex_dir::create($dir)) {
            return new JsonResponse(['error' => 'Could not create upload directory'], 500);
        }

        $manifest = [
            'upload_id' => $uploadId,
            'filename' => $filename,
            'size' => $size,
            'category_id' => $categoryId,
            'title' => (string) $Data['title'],
            'owner' => self::ownerKey($Route),
            'created' => time(),
        ];

        if (!self::writeManifest($uploadId, $manifest)) {
            rex_dir::delete($dir);
            return new JsonResponse(['error' => 'Could not store upload manifest'], 500);
        }

        return new JsonResponse([
            'message' => 'Upload started',
            'upload_id' => $uploadId,
            'filename' => $filename,
            'size' => $size,
            'chunk_size_max' => self::maxChunkSize(),
            'max_chunks' => self::MaxChunks,
            'expires_at' => date('c', $manifest['created'] + self::Ttl),
        ], 201);
    }

    /** @api */
    public static function handleStatus($Parameter, array $Route = []): Response
    {
        $uploadId = (string) $Parameter['upload_id'];
        $manifest = self::loadManifest($uploadId);
        if (null === $manifest) {
            return new JsonResponse(['error' => 'Upload not found'], 404);
        }
        if (!self::isOwner($manifest, $Route)) {
            return new JsonResponse(['error' => 'Upload not found'], 404);
        }

        $chunks = self::chunkSizes($uploadId);
        $received = array_sum($chunks);
        $indices = array_keys($chunks);
        sort($indices);

        return new JsonResponse(json_encode([
            'upload_id' => $uploadId,
            'filename' => $manifest['filename'],
            'size' => (int) $manifest['size'],
            'bytes_received' => $received,
            'bytes_missing' => max(0, (int) $manifest['size'] - $received),
            'chunks' => $indices,
            'contiguous' => self::isContiguous($indices),
            'complete' => $received === (int) $manifest['size'] && self::isContiguous($indices),
            'expires_at' => date('c', (int) $manifest['created'] + self::Ttl),
        ], JSON_PRETTY_PRINT), 200, [], true);
    }

    /** @api */
    public static function handleChunk($Parameter, array $Route = []): Response
    {
        $uploadId = (string) $Parameter['upload_id'];
        $index = (int) $Parameter['index'];

        $manifest = self::loadManifest($uploadId);
        if (null === $manifest || !self::isOwner($manifest, $Route)) {
            return new JsonResponse(['error' => 'Upload not found'], 404);
        }

        if ($index >= self::MaxChunks) {
            return new JsonResponse([
                'error' => 'Chunk index out of range',
                'max_chunks' => self::MaxChunks,
            ], 400);
        }

        $data = self::readChunkPayload();
        if ('' === $data) {
            return new JsonResponse(['error' => 'Empty chunk'], 400);
        }

        // Bereits vorhandene Chunks zaehlen ohne den, der jetzt ersetzt wird --
        // ein erneut gesendeter Index darf die Summe nicht doppelt belasten.
        $sizes = self::chunkSizes($uploadId);
        unset($sizes[$index]);
        $total = array_sum($sizes) + strlen($data);

        if ($total > (int) $manifest['size']) {
            return new JsonResponse([
                'error' => 'Chunks exceed the size announced at init',
                'size' => (int) $manifest['size'],
                'would_be' => $total,
            ], 400);
        }

        if (false === rex_file::put(self::chunkPath($uploadId, $index), $data)) {
            return new JsonResponse(['error' => 'Could not store chunk'], 500);
        }

        return new JsonResponse([
            'message' => 'Chunk stored',
            'upload_id' => $uploadId,
            'index' => $index,
            'bytes_stored' => strlen($data),
            'bytes_received' => $total,
            'bytes_missing' => max(0, (int) $manifest['size'] - $total),
        ], 200);
    }

    /** @api */
    public static function handleFinalize($Parameter, array $Route = []): Response
    {
        $uploadId = (string) $Parameter['upload_id'];

        $manifest = self::loadManifest($uploadId);
        if (null === $manifest || !self::isOwner($manifest, $Route)) {
            return new JsonResponse(['error' => 'Upload not found'], 404);
        }

        // Rechte erneut pruefen: zwischen init und finalize kann sich die
        // Berechtigung geaendert haben.
        $categoryId = (int) $manifest['category_id'];
        $user = RouteCollection::getBackendUser($Route);
        $permResponse = self::checkMediaPerm($user, $categoryId);
        if (null !== $permResponse) {
            return $permResponse;
        }

        $sizes = self::chunkSizes($uploadId);
        $indices = array_keys($sizes);
        sort($indices);

        if (0 === count($indices)) {
            return new JsonResponse(['error' => 'No chunks received'], 400);
        }
        if (!self::isContiguous($indices)) {
            return new JsonResponse([
                'error' => 'Missing chunks: the received indices are not contiguous starting at 0',
                'chunks' => $indices,
            ], 400);
        }

        $received = array_sum($sizes);
        if ($received !== (int) $manifest['size']) {
            return new JsonResponse([
                'error' => 'Assembled size does not match the size announced at init',
                'size' => (int) $manifest['size'],
                'bytes_received' => $received,
            ], 400);
        }

        $assembled = self::uploadDir($uploadId) . 'assembled';
        if (!self::assemble($uploadId, $indices, $assembled)) {
            rex_file::delete($assembled);
            return new JsonResponse(['error' => 'Could not assemble the chunks'], 500);
        }

        try {
            $result = rex_media_service::addMedia([
                'category_id' => $categoryId,
                'title' => (string) $manifest['title'],
                'file' => [
                    'name' => (string) $manifest['filename'],
                    'path' => $assembled,
                ],
            ], true);
        } catch (Exception $e) {
            rex_file::delete($assembled);
            return new JsonResponse(['error' => $e->getMessage()], 400);
        }

        // addMedia() verschiebt die Datei; bleibt sie liegen, war der Aufruf erfolglos.
        rex_file::delete($assembled);
        rex_dir::delete(self::uploadDir($uploadId));

        if (empty($result['ok'])) {
            return new JsonResponse(['error' => $result['msg'] ?? 'Unknown error'], 400);
        }

        return new JsonResponse([
            'message' => 'Media created',
            'filename' => $result['filename'],
        ], 201);
    }

    /** @api */
    public static function handleAbort($Parameter, array $Route = []): Response
    {
        $uploadId = (string) $Parameter['upload_id'];

        $manifest = self::loadManifest($uploadId);
        if (null === $manifest || !self::isOwner($manifest, $Route)) {
            return new JsonResponse(['error' => 'Upload not found'], 404);
        }

        rex_dir::delete(self::uploadDir($uploadId));

        return new JsonResponse(['message' => 'Upload aborted', 'upload_id' => $uploadId], 200);
    }

    // =====================================================================
    // Internals
    // =====================================================================

    /**
     * Chunk-Daten aus dem Request: bevorzugt der rohe Body
     * (application/octet-stream), alternativ ein Multipart-Feld `chunk`, damit
     * der Endpunkt auch aus Swagger UI heraus bedienbar ist.
     */
    private static function readChunkPayload(): string
    {
        if (isset($_FILES['chunk']['tmp_name']) && '' !== $_FILES['chunk']['tmp_name'] && UPLOAD_ERR_OK === (int) $_FILES['chunk']['error']) {
            $content = file_get_contents($_FILES['chunk']['tmp_name']);
            return false === $content ? '' : $content;
        }

        return rex::getRequest()->getContent();
    }

    private static function baseDir(): string
    {
        return rex_path::addonData('api', 'upload/');
    }

    private static function uploadDir(string $uploadId): string
    {
        // Der Aufrufer liefert die Id ausschliesslich ueber die Route-Requirement
        // [a-f0-9]{32}; der Test hier ist die zweite Schranke gegen Path Traversal.
        if (1 !== preg_match('/^[a-f0-9]{32}$/', $uploadId)) {
            return self::baseDir() . 'invalid/';
        }
        return self::baseDir() . $uploadId . '/';
    }

    private static function chunkPath(string $uploadId, int $index): string
    {
        return self::uploadDir($uploadId) . 'chunk_' . $index;
    }

    /**
     * @param array<string, mixed> $manifest
     */
    private static function writeManifest(string $uploadId, array $manifest): bool
    {
        return false !== rex_file::put(self::uploadDir($uploadId) . 'manifest.json', json_encode($manifest));
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function loadManifest(string $uploadId): ?array
    {
        $content = rex_file::get(self::uploadDir($uploadId) . 'manifest.json');
        if (null === $content) {
            return null;
        }
        $manifest = json_decode($content, true);
        return is_array($manifest) ? $manifest : null;
    }

    /**
     * Groesse je vorhandenem Chunk, indiziert nach Chunk-Nummer.
     *
     * @return array<int, int>
     */
    private static function chunkSizes(string $uploadId): array
    {
        $sizes = [];
        foreach (glob(self::uploadDir($uploadId) . 'chunk_*') ?: [] as $path) {
            if (1 === preg_match('/chunk_(\d+)$/', $path, $match)) {
                $sizes[(int) $match[1]] = (int) filesize($path);
            }
        }
        return $sizes;
    }

    /**
     * @param list<int> $indices aufsteigend sortiert
     */
    private static function isContiguous(array $indices): bool
    {
        foreach ($indices as $position => $index) {
            if ($index !== $position) {
                return false;
            }
        }
        return true;
    }

    /**
     * @param list<int> $indices
     */
    private static function assemble(string $uploadId, array $indices, string $target): bool
    {
        $out = fopen($target, 'wb');
        if (false === $out) {
            return false;
        }

        try {
            foreach ($indices as $index) {
                $in = fopen(self::chunkPath($uploadId, $index), 'rb');
                if (false === $in) {
                    return false;
                }
                $copied = stream_copy_to_stream($in, $out);
                fclose($in);
                if (false === $copied) {
                    return false;
                }
            }
        } finally {
            fclose($out);
        }

        return true;
    }

    /**
     * Bindet den Upload an den Aufrufer, damit ein fremdes Token mit geratener
     * Id nichts anfangen kann.
     */
    private static function ownerKey(array $Route): string
    {
        $auth = $Route['authorization'] ?? null;
        $object = null === $auth ? null : $auth->getAuthorizationObject();

        if ($object instanceof rex_user) {
            return 'user:' . $object->getLogin();
        }
        if (null !== $object && method_exists($object, 'getId')) {
            return 'token:' . (string) $object->getId();
        }
        return 'anonymous';
    }

    /**
     * @param array<string, mixed> $manifest
     */
    private static function isOwner(array $manifest, array $Route): bool
    {
        return ($manifest['owner'] ?? null) === self::ownerKey($Route);
    }

    /** Verwaiste Uploads aufraeumen -- ohne Cronjob, beim Beginn eines neuen Uploads. */
    private static function collectGarbage(): void
    {
        $deadline = time() - self::Ttl;

        foreach (glob(self::baseDir() . '*', GLOB_ONLYDIR) ?: [] as $dir) {
            $manifest = rex_file::get(rtrim($dir, '/') . '/manifest.json');
            if (null === $manifest) {
                // Kein Manifest: nur aufraeumen, wenn das Verzeichnis selbst alt ist.
                if (filemtime($dir) < $deadline) {
                    rex_dir::delete($dir);
                }
                continue;
            }
            $decoded = json_decode($manifest, true);
            $created = is_array($decoded) ? (int) ($decoded['created'] ?? 0) : 0;
            if ($created < $deadline) {
                rex_dir::delete($dir);
            }
        }
    }

    /**
     * Was pro Chunk-Request tatsaechlich durchgeht -- die kleinere der beiden
     * PHP-Grenzen, mit Reserve fuer Header und Multipart-Rahmen.
     */
    private static function maxChunkSize(): int
    {
        $post = self::iniBytes((string) ini_get('post_max_size'));
        $upload = self::iniBytes((string) ini_get('upload_max_filesize'));

        $limits = array_filter([$post, $upload], static fn(int $v): bool => $v > 0);
        if (0 === count($limits)) {
            return 1048576;
        }

        $limit = min($limits);
        return max(65536, $limit - 65536);
    }

    private static function iniBytes(string $value): int
    {
        $value = trim($value);
        if ('' === $value) {
            return 0;
        }
        $number = (int) $value;
        return match (strtolower(substr($value, -1))) {
            'g' => $number * 1024 * 1024 * 1024,
            'm' => $number * 1024 * 1024,
            'k' => $number * 1024,
            default => $number,
        };
    }

    private static function checkMediaPerm(?rex_user $user, ?int $categoryId = null): ?Response
    {
        if (null === $user) {
            return null;
        }
        if ($user->isAdmin()) {
            return null;
        }
        $perm = $user->getComplexPerm('media');
        if (null !== $categoryId && !$perm->hasCategoryPerm($categoryId)) {
            return new JsonResponse(['error' => 'Permission denied'], 403);
        }
        if (null === $categoryId && !$perm->hasAll()) {
            return new JsonResponse(['error' => 'Permission denied'], 403);
        }
        return null;
    }
}
