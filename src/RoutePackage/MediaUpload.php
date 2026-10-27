<?php

namespace FriendsOfRedaxo\Api\RoutePackage;

use Exception;
use FriendsOfRedaxo\Api\Auth\BearerAuth;
use FriendsOfRedaxo\Api\RouteCollection;
use FriendsOfRedaxo\Api\RoutePackage;
use FriendsOfRedaxo\Api\Token;
use Override;
use Redaxo\Core\Addon\Addon;
use Redaxo\Core\Core;
use Redaxo\Core\Exception\UserMessageException;
use Redaxo\Core\Filesystem\Dir;
use Redaxo\Core\Filesystem\File;
use Redaxo\Core\MediaPool\MediaCategory;
use Redaxo\Core\MediaPool\MediaHandler;
use Redaxo\Core\MediaPool\MediaPool;
use Redaxo\Core\Security\User;
use Symfony\Component\HttpFoundation\File\File as SymfonyFile;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Route;

use function count;
use function dirname;
use function ini_get;
use function is_array;
use function strlen;

use const GLOB_ONLYDIR;
use const JSON_PRETTY_PRINT;

/**
 * Chunked upload: a file is transferred in several requests and only added to the media pool at the end.
 *
 * Adding the file stays entirely with the core: `finalize` hands the assembled file to `MediaHandler::add()`, which
 * moves it like the sync page of the media pool does. So the extension block list, the MIME allowlist, the file name
 * normalisation, the sanitizing and the `MediaAdding`/`MediaAdded` extension points apply unchanged.
 */
class MediaUpload extends RoutePackage
{
    /** Largest file the chunks may add up to. */
    public const int MAX_TOTAL_SIZE = 2147483648; // 2 GiB

    /** Upper bound of the chunk index, so that a client cannot flood a directory. */
    public const int MAX_CHUNKS = 20000;

    /** After this time a started upload counts as orphaned and is cleaned up. */
    public const int TTL = 86400; // 24 hours

    /**
     * All five routes share one scope. Alone they are useless — init without chunk, chunk without finalize — and a
     * partial grant would only show in the middle of a transfer, after the data has been sent.
     */
    public const string SCOPE = 'media/upload';

    #[Override]
    public function loadRoutes(): void
    {
        $id = ['upload_id' => '[a-f0-9]{32}'];

        self::route('media/upload/init', 'media/upload', 'handleInit', ['POST'], 'Start a chunked upload. Returns an upload_id and the maximum size per chunk.', [], [
            'filename' => ['type' => 'string', 'required' => true],
            'size' => ['type' => 'integer', 'required' => true],
            'category_id' => ['type' => 'integer', 'required' => false, 'default' => 0, 'description' => '0 for the root level'],
            'title' => ['type' => 'string', 'required' => false, 'default' => '', 'description' => 'Title in all languages'],
        ]);
        self::route('media/upload/status', 'media/upload/{upload_id}', 'handleStatus', ['GET'], 'Status of a chunked upload: which chunks arrived, how many bytes are missing.', $id);
        self::route('media/upload/chunk', 'media/upload/{upload_id}/chunk/{index}', 'handleChunk', ['POST', 'PUT'], 'Send one chunk, as request body (application/octet-stream) or as multipart field `chunk`. The index is zero-based; sending the same index again replaces it.', $id + ['index' => '\d+'], [
            'chunk' => ['type' => 'file', 'required' => false, 'description' => 'Chunk data, alternatively the raw request body'],
        ]);
        self::route('media/upload/finalize', 'media/upload/{upload_id}/finalize', 'handleFinalize', ['POST'], 'Assemble the chunks and add the file to the media pool.', $id);
        self::route('media/upload/delete', 'media/upload/{upload_id}', 'handleAbort', ['DELETE'], 'Abort a chunked upload and discard the chunks received so far.', $id);
    }

    /**
     * @param list<string> $methods
     * @param array<string, string> $requirements
     * @param array<string, mixed>|null $body
     */
    private static function route(string $scope, string $path, string $handler, array $methods, string $description, array $requirements = [], ?array $body = null): void
    {
        $defaults = ['_controller' => self::class . '::' . $handler];
        if (null !== $body) {
            $defaults['Body'] = $body;
        }

        RouteCollection::registerRoute($scope, new Route($path, $defaults, $requirements, [], '', [], $methods), $description, null, new BearerAuth(self::SCOPE));
    }

    /**
     * @param array<string, mixed> $parameter
     * @param array<string, mixed> $route
     * @api
     */
    public static function handleInit(array $parameter, array $route = []): Response
    {
        $data = json_decode(Core::getRequest()->getContent(), true);
        if (!is_array($data)) {
            return new JsonResponse(['error' => 'Invalid input'], 400);
        }

        try {
            $data = RouteCollection::getQuerySet($data, $parameter['Body']);
        } catch (Exception $e) {
            return new JsonResponse(['error' => 'Body field: `' . $e->getMessage() . '` is required'], 400);
        }

        $filename = trim(basename(str_replace('\\', '/', (string) $data['filename'])));
        $size = (int) $data['size'];
        $categoryId = (int) $data['category_id'] ?: null;

        if ('' === $filename || '.' === $filename[0]) {
            return new JsonResponse(['error' => 'filename must not be empty'], 400);
        }

        // checked here already, not only when assembling: otherwise the upload fails after the whole file was sent
        if (!MediaPool::isAllowedExtension($filename)) {
            return new JsonResponse(['error' => 'File extension is not allowed', 'filename' => $filename], 400);
        }

        if ($size < 1) {
            return new JsonResponse(['error' => 'size must be greater than 0'], 400);
        }
        if ($size > self::MAX_TOTAL_SIZE) {
            return new JsonResponse(['error' => 'File too large for chunked upload', 'size' => $size, 'max_total_size' => self::MAX_TOTAL_SIZE], 413);
        }

        if (null !== $response = self::checkMediaPerm(RouteCollection::getBackendUser($route), $categoryId)) {
            return $response;
        }

        if (null !== $categoryId && null === MediaCategory::get($categoryId)) {
            return new JsonResponse(['error' => 'Category not found'], 404);
        }

        self::collectGarbage();

        $uploadId = bin2hex(random_bytes(16));
        $manifest = [
            'upload_id' => $uploadId,
            'filename' => $filename,
            'size' => $size,
            'category_id' => $categoryId,
            'title' => (string) $data['title'],
            'owner' => self::ownerKey($route),
            'created' => time(),
        ];

        if (!Dir::create(self::uploadDir($uploadId)) || !File::put(self::uploadDir($uploadId) . 'manifest.json', (string) json_encode($manifest))) {
            Dir::delete(self::uploadDir($uploadId));

            return new JsonResponse(['error' => 'Could not store the upload'], 500);
        }

        return new JsonResponse([
            'message' => 'Upload started',
            'upload_id' => $uploadId,
            'filename' => $filename,
            'size' => $size,
            'chunk_size_max' => self::maxChunkSize(),
            'max_chunks' => self::MAX_CHUNKS,
            'expires_at' => date('c', $manifest['created'] + self::TTL),
        ], 201);
    }

    /**
     * @param array<string, mixed> $parameter
     * @param array<string, mixed> $route
     * @api
     */
    public static function handleStatus(array $parameter, array $route = []): Response
    {
        $uploadId = (string) $parameter['upload_id'];
        $manifest = self::loadManifest($uploadId, $route);
        if (null === $manifest) {
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
            'expires_at' => date('c', (int) $manifest['created'] + self::TTL),
        ], JSON_PRETTY_PRINT), 200, [], true);
    }

    /**
     * @param array<string, mixed> $parameter
     * @param array<string, mixed> $route
     * @api
     */
    public static function handleChunk(array $parameter, array $route = []): Response
    {
        $uploadId = (string) $parameter['upload_id'];
        $index = (int) $parameter['index'];

        $manifest = self::loadManifest($uploadId, $route);
        if (null === $manifest) {
            return new JsonResponse(['error' => 'Upload not found'], 404);
        }

        if ($index >= self::MAX_CHUNKS) {
            return new JsonResponse(['error' => 'Chunk index out of range', 'max_chunks' => self::MAX_CHUNKS], 400);
        }

        $data = self::readChunkPayload();
        if ('' === $data) {
            return new JsonResponse(['error' => 'Empty chunk'], 400);
        }

        // the chunk that is replaced does not count, a resent index must not be added twice
        $sizes = self::chunkSizes($uploadId);
        unset($sizes[$index]);
        $total = array_sum($sizes) + strlen($data);

        if ($total > (int) $manifest['size']) {
            return new JsonResponse(['error' => 'Chunks exceed the size announced at init', 'size' => (int) $manifest['size'], 'would_be' => $total], 400);
        }

        if (!File::put(self::uploadDir($uploadId) . 'chunk_' . $index, $data)) {
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

    /**
     * @param array<string, mixed> $parameter
     * @param array<string, mixed> $route
     * @api
     */
    public static function handleFinalize(array $parameter, array $route = []): Response
    {
        $uploadId = (string) $parameter['upload_id'];

        $manifest = self::loadManifest($uploadId, $route);
        if (null === $manifest) {
            return new JsonResponse(['error' => 'Upload not found'], 404);
        }

        // checked again: the permissions may have changed since init
        $categoryId = null === $manifest['category_id'] ? null : (int) $manifest['category_id'];
        if (null !== $response = self::checkMediaPerm(RouteCollection::getBackendUser($route), $categoryId)) {
            return $response;
        }

        $sizes = self::chunkSizes($uploadId);
        $indices = array_keys($sizes);
        sort($indices);

        if (0 === count($indices)) {
            return new JsonResponse(['error' => 'No chunks received'], 400);
        }
        if (!self::isContiguous($indices)) {
            return new JsonResponse(['error' => 'Missing chunks: the received indices are not contiguous starting at 0', 'chunks' => $indices], 400);
        }

        $received = array_sum($sizes);
        if ($received !== (int) $manifest['size']) {
            return new JsonResponse(['error' => 'Assembled size does not match the size announced at init', 'size' => (int) $manifest['size'], 'bytes_received' => $received], 400);
        }

        // the core takes the name of a file that is no upload as its original name
        $assembled = self::uploadDir($uploadId) . 'assembled/' . $manifest['filename'];
        if (!Dir::create(dirname($assembled)) || !self::assemble($uploadId, $indices, $assembled)) {
            Dir::delete(self::uploadDir($uploadId));

            return new JsonResponse(['error' => 'Could not assemble the chunks'], 500);
        }

        try {
            $media = MediaHandler::add(['file' => new SymfonyFile($assembled), 'category_id' => $categoryId, 'title' => (string) $manifest['title']]);
        } catch (UserMessageException $e) {
            return new JsonResponse(['error' => self::plain($e->getMessage())], 400);
        } finally {
            Dir::delete(self::uploadDir($uploadId));
        }

        return new JsonResponse(['message' => 'Media created', 'filename' => $media->fileName], 201);
    }

    /**
     * @param array<string, mixed> $parameter
     * @param array<string, mixed> $route
     * @api
     */
    public static function handleAbort(array $parameter, array $route = []): Response
    {
        $uploadId = (string) $parameter['upload_id'];

        if (null === self::loadManifest($uploadId, $route)) {
            return new JsonResponse(['error' => 'Upload not found'], 404);
        }

        Dir::delete(self::uploadDir($uploadId));

        return new JsonResponse(['message' => 'Upload aborted', 'upload_id' => $uploadId], 200);
    }

    /**
     * The chunk data: the raw request body (application/octet-stream), or a multipart field `chunk`, so that the
     * endpoint can be used from Swagger UI as well.
     */
    private static function readChunkPayload(): string
    {
        $file = Core::getRequest()->files->get('chunk');
        if ($file instanceof UploadedFile && $file->isValid()) {
            return (string) file_get_contents($file->getPathname());
        }

        return Core::getRequest()->getContent();
    }

    private static function baseDir(): string
    {
        return Addon::require('api')->getDataPath('upload/');
    }

    private static function uploadDir(string $uploadId): string
    {
        // the id comes from the route requirement [a-f0-9]{32}; this is the second barrier against path traversal
        return self::baseDir() . (1 === preg_match('/^[a-f0-9]{32}$/', $uploadId) ? $uploadId : 'invalid') . '/';
    }

    /**
     * The manifest of the upload, or `null` if it does not exist or belongs to another caller (a guessed id of another
     * token must reveal nothing).
     *
     * @param array<string, mixed> $route
     * @return array<string, mixed>|null
     */
    private static function loadManifest(string $uploadId, array $route): ?array
    {
        $content = File::get(self::uploadDir($uploadId) . 'manifest.json');
        $manifest = null === $content ? null : json_decode($content, true);

        return is_array($manifest) && ($manifest['owner'] ?? null) === self::ownerKey($route) ? $manifest : null;
    }

    /**
     * The size of each chunk received, keyed by index.
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

    /** @param list<int> $indices sorted ascending */
    private static function isContiguous(array $indices): bool
    {
        foreach ($indices as $position => $index) {
            if ($index !== $position) {
                return false;
            }
        }

        return true;
    }

    /** @param list<int> $indices */
    private static function assemble(string $uploadId, array $indices, string $target): bool
    {
        $out = fopen($target, 'w');
        if (false === $out) {
            return false;
        }

        try {
            foreach ($indices as $index) {
                $in = fopen(self::uploadDir($uploadId) . 'chunk_' . $index, 'r');
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
     * Binds the upload to its caller.
     *
     * @param array<string, mixed> $route
     */
    private static function ownerKey(array $route): string
    {
        $object = ($route['authorization'] ?? null)?->getAuthorizationObject();

        return match (true) {
            $object instanceof User => 'user:' . $object->id,
            $object instanceof Token => 'token:' . $object->getId(),
            default => 'anonymous',
        };
    }

    /** Cleans up orphaned uploads — without a cronjob, when a new upload starts. */
    private static function collectGarbage(): void
    {
        $deadline = time() - self::TTL;

        foreach (glob(self::baseDir() . '*', GLOB_ONLYDIR) ?: [] as $dir) {
            $manifest = json_decode((string) File::get($dir . '/manifest.json'), true);
            $created = is_array($manifest) ? (int) ($manifest['created'] ?? 0) : (int) filemtime($dir);

            if ($created < $deadline) {
                Dir::delete($dir);
            }
        }
    }

    /** What actually gets through per chunk request: the smaller PHP limit, with a reserve for headers and multipart. */
    private static function maxChunkSize(): int
    {
        $limits = array_filter([
            Media::iniBytes((string) ini_get('post_max_size')),
            Media::iniBytes((string) ini_get('upload_max_filesize')),
        ], static fn (int $limit): bool => $limit > 0);

        return [] === $limits ? 1048576 : max(65536, min($limits) - 65536);
    }

    private static function checkMediaPerm(?User $user, ?int $categoryId): ?Response
    {
        if (null === $user || $user->admin || $user->getComplexPerm('media')->hasCategoryPerm($categoryId)) {
            return null;
        }

        return new JsonResponse(['error' => 'Permission denied'], 403);
    }
}
