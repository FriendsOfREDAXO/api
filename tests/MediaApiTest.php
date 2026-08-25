<?php

declare(strict_types=1);

namespace FriendsOfRedaxo\Api\Tests;

/**
 * Tests für die Media API.
 */
class MediaApiTest extends ApiTestCase
{
    private static string $testImagePath;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        // Test-Bild erstellen
        self::$testImagePath = sys_get_temp_dir() . '/api_test_image.png';
        self::createTestImage(self::$testImagePath);
    }

    public static function tearDownAfterClass(): void
    {
        // Test-Bild löschen
        if (file_exists(self::$testImagePath)) {
            unlink(self::$testImagePath);
        }

        parent::tearDownAfterClass();
    }

    /**
     * Erstellt ein einfaches Test-Bild.
     */
    private static function createTestImage(string $path): void
    {
        $image = imagecreatetruecolor(100, 100);
        $bgColor = imagecolorallocate($image, 255, 255, 255);
        imagefill($image, 0, 0, $bgColor);
        imagepng($image, $path);
        imagedestroy($image);
    }

    // ==================== MEDIA LIST TESTS ====================

    public function testGetMediaList(): void
    {
        $response = $this->get('media');

        $this->assertSuccess($response);
        $this->assertIsArray($response['data']['data']);
        $this->assertArrayHasKey('meta', $response['data']);
        $this->assertArrayHasKey('total', $response['data']['meta']);
    }

    public function testGetMediaListWithPagination(): void
    {
        $response = $this->get('media', [
            'page' => 1,
            'per_page' => 5,
        ]);

        $this->assertSuccess($response);
        $this->assertIsArray($response['data']['data']);
        $this->assertLessThanOrEqual(5, count($response['data']['data']));
        $this->assertSame(1, $response['data']['meta']['page']);
        $this->assertSame(5, $response['data']['meta']['per_page']);
    }

    public function testGetMediaListWithFilter(): void
    {
        $response = $this->get('media', [
            'filter[filetype]' => 'image/png',
        ]);

        $this->assertSuccess($response);
        $this->assertIsArray($response['data']['data']);

        // Alle Ergebnisse sollten PNG sein
        foreach ($response['data']['data'] as $media) {
            if (isset($media['filetype'])) {
                $this->assertSame('image/png', $media['filetype']);
            }
        }
    }

    /**
     * filter[term] sucht in filename ODER title -- anders als filter[filename], das exakt
     * matcht. Der Teilstring des eigenen Uploads muss genau einen Treffer liefern, waehrend
     * filter[filename] mit demselben Teilstring leer ausgeht.
     */
    public function testGetMediaListWithTermSearch(): void
    {
        if (!file_exists(self::$testImagePath)) {
            $this->markTestSkipped('Test-Bild konnte nicht erstellt werden.');
        }

        $upload = $this->postMultipart('media', [
            'category_id' => 0,
            'title' => $this->generateTestName('media'),
        ], ['file' => self::$testImagePath]);
        $this->assertStatus(201, $upload);
        $filename = $upload['data']['filename'];
        $this->trackResource('media', $filename . '/delete');

        $fragment = substr($filename, 0, max(4, strlen($filename) - 6));

        // Genau ein Treffer, nicht "mindestens einer": unbekannte Filter werden still
        // ignoriert und liefern dann die Gesamtmenge -- eine >=1-Pruefung waere zahnlos.
        $term = $this->get('media', ['filter[term]' => $fragment, 'per_page' => 1]);
        $this->assertSuccess($term);
        $this->assertSame(1, $term['data']['meta']['total'], 'filter[term] muss genau den eigenen Upload finden.');

        $exact = $this->get('media', ['filter[filename]' => $fragment, 'per_page' => 1]);
        $this->assertSuccess($exact);
        $this->assertSame(0, $exact['data']['meta']['total'], 'filter[filename] matcht exakt und darf den Teilstring nicht finden.');
    }

    /** `type:` im Suchbegriff und filter[types] filtern beide die Dateiendung. */
    public function testGetMediaListWithTypeFilters(): void
    {
        $all = $this->get('media', ['per_page' => 1]);
        $this->assertSuccess($all);

        $png = $this->get('media', ['filter[types]' => 'png', 'per_page' => 1]);
        $this->assertSuccess($png);

        $pngViaTerm = $this->get('media', ['filter[term]' => 'type:png', 'per_page' => 1]);
        $this->assertSuccess($pngViaTerm);

        $this->assertSame(
            $png['data']['meta']['total'],
            $pngViaTerm['data']['meta']['total'],
            'filter[types]=png und filter[term]=type:png muessen dieselbe Menge liefern.',
        );
        // Echt kleiner als die Gesamtmenge -- sonst wuerde der Test auch bestehen, wenn
        // der Filter gar nicht greift und beide Male alles zurueckkommt. Ob es ueberhaupt
        // etwas anderes als PNG gibt, wird unabhaengig ermittelt, damit "alles gleich"
        // nicht faelschlich als "nichts zu messen" durchgeht.
        $jpg = $this->get('media', ['filter[types]' => 'jpg,jpeg', 'per_page' => 1]);
        $this->assertSuccess($jpg);

        if (0 === $jpg['data']['meta']['total'] && $all['data']['meta']['total'] === $png['data']['meta']['total']) {
            $this->markTestSkipped('In dieser Installation gibt es ausser PNG keine Bildformate.');
        }
        $this->assertLessThan($all['data']['meta']['total'], $png['data']['meta']['total']);
    }

    /**
     * filter[category_id_path] schliesst Unterkategorien ein, filter[category_id] nicht.
     * Der Vergleich braucht ein Medium, das wirklich in der Unterkategorie liegt --
     * sonst waeren beide Werte gleich und der Test wuerde nichts zeigen.
     */
    public function testGetMediaListWithCategoryPath(): void
    {
        if (!file_exists(self::$testImagePath)) {
            $this->markTestSkipped('Test-Bild konnte nicht erstellt werden.');
        }

        $parent = $this->post('media/category', ['name' => $this->generateTestName('path_parent'), 'parent_id' => 0]);
        $this->assertStatus(201, $parent);
        $parentId = (int) $parent['data']['id'];

        $child = $this->post('media/category', ['name' => $this->generateTestName('path_child'), 'parent_id' => $parentId]);
        $this->assertStatus(201, $child);
        $childId = (int) $child['data']['id'];

        $filename = null;

        try {
            $upload = $this->postMultipart('media', [
                'category_id' => $childId,
                'title' => $this->generateTestName('media'),
            ], ['file' => self::$testImagePath]);
            $this->assertStatus(201, $upload);
            $filename = $upload['data']['filename'];

            $direct = $this->get('media', ['filter[category_id]' => $parentId, 'per_page' => 1]);
            $recursive = $this->get('media', ['filter[category_id_path]' => $parentId, 'per_page' => 1]);

            $this->assertSame(0, $direct['data']['meta']['total'], 'Die Eltern-Kategorie selbst enthaelt kein Medium.');
            $this->assertSame(1, $recursive['data']['meta']['total'], 'Der Pfad-Filter muss das Medium der Unterkategorie einschliessen.');
        } finally {
            if (null !== $filename) {
                $this->delete('media/' . $filename . '/delete');
            }
            $this->delete('media/category/' . $childId);
            $this->delete('media/category/' . $parentId);
        }
    }

    public function testGetMediaListWithUnknownCategoryPath(): void
    {
        $response = $this->get('media', ['filter[category_id_path]' => 999999]);
        $this->assertStatus(404, $response);
    }

    // ==================== MEDIA CRUD TESTS ====================

    public function testUploadMedia(): void
    {
        if (!file_exists(self::$testImagePath)) {
            $this->markTestSkipped('Test-Bild konnte nicht erstellt werden.');
        }

        $response = $this->postMultipart('media', [
            'category_id' => 0,
            'title' => $this->generateTestName('media'),
        ], [
            'file' => self::$testImagePath,
        ]);

        $this->assertStatus(201, $response);
        $this->assertHasField($response, 'filename');

        // Für Aufräumen registrieren
        $this->trackResource('media', $response['data']['filename'] . '/delete');
    }

    public function testUploadWithoutFileReturns400(): void
    {
        $response = $this->postMultipart('media', ['category_id' => 0, 'title' => 'kein Upload']);

        $this->assertStatus(400, $response);
        $this->assertSame('No file uploaded', $response['data']['error']);
    }

    /**
     * Ueberschreitet der Upload ein PHP-Limit, muss das als 413 samt Limits erkennbar sein --
     * vorher kamen "keine Datei", "Datei zu gross" und "Body zu gross" alle als dasselbe
     * nichtssagende 400 zurueck. Erlaubt die Installation 3 MB, ist der Fall hier nicht
     * messbar und der Test uebersprungen statt vorgetaeuscht.
     */
    public function testUploadExceedingPhpLimitReturns413(): void
    {
        $path = rtrim(sys_get_temp_dir(), '/') . '/api-test-oversized-' . uniqid() . '.png';
        if (false === file_put_contents($path, self::oversizedPngPayload(3 * 1024 * 1024))) {
            $this->markTestSkipped('Testdatei konnte nicht erstellt werden.');
        }

        try {
            $response = $this->postMultipart('media', [
                'category_id' => 0,
                'title' => $this->generateTestName('media'),
            ], ['file' => $path]);

            if (201 === $response['status']) {
                $this->trackResource('media', $response['data']['filename'] . '/delete');
                $this->markTestSkipped('Diese Installation erlaubt Uploads von 3 MB; das Limit ist so nicht messbar.');
            }

            $this->assertStatus(413, $response);
            $this->assertArrayHasKey('limits', $response['data']);
            $this->assertGreaterThan(0, (int) $response['data']['limits']['upload_max_filesize']);
            $this->assertGreaterThan(0, (int) $response['data']['limits']['post_max_size']);
        } finally {
            @unlink($path);
        }
    }

    /** Gueltiges PNG mit einem tEXt-Chunk als Ballast, damit die Datei die Zielgroesse erreicht. */
    private static function oversizedPngPayload(int $bytes): string
    {
        $chunk = static function (string $type, string $data): string {
            return pack('N', strlen($data)) . $type . $data . pack('N', crc32($type . $data));
        };

        return "PNG

"
            . $chunk('IHDR', pack('NNCCCCC', 1, 1, 8, 2, 0, 0, 0))
            . $chunk('IDAT', gzcompress("    "))
            . $chunk('tEXt', "Comment " . str_repeat('x', $bytes))
            . $chunk('IEND', '');
    }

    public function testGetMediaInfo(): void
    {
        // Erst Liste abrufen um einen existierenden Dateinamen zu bekommen
        $listResponse = $this->get('media', ['per_page' => 1]);

        if (empty($listResponse['data']['data'])) {
            $this->markTestSkipped('Keine Medien in der Datenbank vorhanden.');
        }

        $filename = $listResponse['data']['data'][0]['filename'];
        $response = $this->get('media/' . $filename . '/info');

        $this->assertSuccess($response);
        $this->assertHasField($response, 'filename');
        $this->assertHasField($response, 'filetype');
        $this->assertHasField($response, 'filesize');
    }

    public function testGetMediaInfoNotFound(): void
    {
        $response = $this->get('media/nicht_existierende_datei_12345.jpg/info');

        $this->assertStatus(404, $response);
        $this->assertError($response);
    }

    public function testGetMediaFile(): void
    {
        // Erst Liste abrufen um einen existierenden Dateinamen zu bekommen
        $listResponse = $this->get('media', ['per_page' => 1]);

        if (empty($listResponse['data']['data'])) {
            $this->markTestSkipped('Keine Medien in der Datenbank vorhanden.');
        }

        $filename = $listResponse['data']['data'][0]['filename'];
        $response = $this->get('media/' . $filename . '/file');

        $this->assertSuccess($response);
        // Raw-Response sollte Binärdaten enthalten
        $this->assertNotEmpty($response['raw']);
    }

    public function testUpdateMedia(): void
    {
        // Erst Liste abrufen
        $listResponse = $this->get('media', ['per_page' => 1]);

        if (empty($listResponse['data']['data'])) {
            $this->markTestSkipped('Keine Medien in der Datenbank vorhanden.');
        }

        $filename = $listResponse['data']['data'][0]['filename'];
        $newTitle = $this->generateTestName('updated_title');

        $response = $this->put('media/' . $filename . '/update', [
            'title' => $newTitle,
        ]);

        $this->assertSuccess($response);
        $this->assertHasField($response, 'message');
    }

    public function testDeleteMedia(): void
    {
        if (!file_exists(self::$testImagePath)) {
            $this->markTestSkipped('Test-Bild konnte nicht erstellt werden.');
        }

        // Erst Datei hochladen
        $response = $this->postMultipart('media', [
            'category_id' => 0,
            'title' => $this->generateTestName('media_delete'),
        ], [
            'file' => self::$testImagePath,
        ]);

        if (!$response['success']) {
            $this->markTestSkipped('Upload fehlgeschlagen, Delete-Test nicht möglich.');
        }

        $filename = $response['data']['filename'];

        // Dann löschen
        $deleteResponse = $this->delete('media/' . $filename . '/delete');

        $this->assertSuccess($deleteResponse);
        $this->assertHasField($deleteResponse, 'message');

        // Prüfen ob wirklich gelöscht
        $getResponse = $this->get('media/' . $filename . '/info');
        $this->assertStatus(404, $getResponse);
    }

    public function testDeleteMediaNotFound(): void
    {
        $response = $this->delete('media/nicht_existierende_datei_12345.jpg/delete');

        $this->assertStatus(404, $response);
        $this->assertError($response);
    }

    // ==================== MEDIA CATEGORY TESTS ====================

    public function testGetMediaCategoryList(): void
    {
        $response = $this->get('media/category');

        $this->assertSuccess($response);
        $this->assertIsArray($response['data']['data']);
        $this->assertArrayHasKey('meta', $response['data']);
    }

    public function testGetMediaCategoryListWithFilter(): void
    {
        $response = $this->get('media/category', [
            'filter[category_id]' => 0,
        ]);

        $this->assertSuccess($response);
        $this->assertIsArray($response['data']['data']);
    }

    public function testCreateMediaCategory(): void
    {
        $name = $this->generateTestName('media_cat');

        $response = $this->post('media/category', [
            'name' => $name,
            'parent_id' => 0,
        ]);

        $this->assertStatus(201, $response);
        $this->assertHasField($response, 'id');

        $this->trackResource('media/category', $response['data']['id']);
    }

    public function testCreateMediaCategoryValidation(): void
    {
        // Ohne Pflichtfeld 'name'
        $response = $this->post('media/category', [
            'parent_id' => 0,
        ]);

        $this->assertStatus(400, $response);
        $this->assertError($response);
    }

    public function testUpdateMediaCategory(): void
    {
        // Erst Kategorie erstellen
        $name = $this->generateTestName('media_cat');
        $createResponse = $this->post('media/category', [
            'name' => $name,
            'parent_id' => 0,
        ]);

        $this->assertStatus(201, $createResponse);
        $categoryId = $createResponse['data']['id'];
        $this->trackResource('media/category', $categoryId);

        // Dann updaten
        $newName = $this->generateTestName('media_cat_updated');
        $updateResponse = $this->put('media/category/' . $categoryId, [
            'name' => $newName,
        ]);

        $this->assertSuccess($updateResponse);
    }

    public function testDeleteMediaCategory(): void
    {
        // Erst Kategorie erstellen
        $name = $this->generateTestName('media_cat_delete');
        $createResponse = $this->post('media/category', [
            'name' => $name,
            'parent_id' => 0,
        ]);

        $this->assertStatus(201, $createResponse);
        $categoryId = $createResponse['data']['id'];

        // Dann löschen
        $deleteResponse = $this->delete('media/category/' . $categoryId);

        $this->assertSuccess($deleteResponse);
    }

    public function testDeleteMediaCategoryWithChildren(): void
    {
        // Eltern-Kategorie erstellen
        $parentName = $this->generateTestName('parent_cat');
        $parentResponse = $this->post('media/category', [
            'name' => $parentName,
            'parent_id' => 0,
        ]);

        $this->assertStatus(201, $parentResponse);
        $parentId = $parentResponse['data']['id'];

        // Kind-Kategorie erstellen
        $childName = $this->generateTestName('child_cat');
        $childResponse = $this->post('media/category', [
            'name' => $childName,
            'parent_id' => $parentId,
        ]);

        $this->assertStatus(201, $childResponse);
        $childId = $childResponse['data']['id'];

        // Versuchen Eltern zu löschen (sollte fehlschlagen)
        $deleteResponse = $this->delete('media/category/' . $parentId);
        $this->assertStatus(409, $deleteResponse);

        // Aufräumen: Erst Kind, dann Eltern löschen
        $this->delete('media/category/' . $childId);
        $this->delete('media/category/' . $parentId);
    }
}
