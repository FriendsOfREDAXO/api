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
        $this->assertIsArray($response['data']);
    }

    public function testGetMediaListWithPagination(): void
    {
        $response = $this->get('media', [
            'page' => 1,
            'per_page' => 5,
        ]);

        $this->assertSuccess($response);
        $this->assertIsArray($response['data']);
        $this->assertLessThanOrEqual(5, count($response['data']));
    }

    public function testGetMediaListWithFilter(): void
    {
        $response = $this->get('media', [
            'filter[filetype]' => 'image/png',
        ]);

        $this->assertSuccess($response);
        $this->assertIsArray($response['data']);

        // Alle Ergebnisse sollten PNG sein
        foreach ($response['data'] as $media) {
            if (isset($media['filetype'])) {
                $this->assertSame('image/png', $media['filetype']);
            }
        }
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

        if ($response['success']) {
            $this->assertStatus(201, $response);
            $this->assertHasField($response, 'filename');

            // Für Aufräumen registrieren
            $this->trackResource('media', $response['data']['filename'] . '/delete');
        } else {
            // Upload kann fehlschlagen wenn z.B. Dateityp nicht erlaubt
            $this->assertContains($response['status'], [400, 500]);
        }
    }

    public function testGetMediaInfo(): void
    {
        // Erst Liste abrufen um einen existierenden Dateinamen zu bekommen
        $listResponse = $this->get('media', ['per_page' => 1]);

        if (empty($listResponse['data'])) {
            $this->markTestSkipped('Keine Medien in der Datenbank vorhanden.');
        }

        $filename = $listResponse['data'][0]['filename'];
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

        if (empty($listResponse['data'])) {
            $this->markTestSkipped('Keine Medien in der Datenbank vorhanden.');
        }

        $filename = $listResponse['data'][0]['filename'];
        $response = $this->get('media/' . $filename . '/file');

        $this->assertSuccess($response);
        // Raw-Response sollte Binärdaten enthalten
        $this->assertNotEmpty($response['raw']);
    }

    public function testUpdateMedia(): void
    {
        // Erst Liste abrufen
        $listResponse = $this->get('media', ['per_page' => 1]);

        if (empty($listResponse['data'])) {
            $this->markTestSkipped('Keine Medien in der Datenbank vorhanden.');
        }

        $filename = $listResponse['data'][0]['filename'];
        $newTitle = $this->generateTestName('updated_title');

        $response = $this->put('media/' . $filename . '/update', [
            'title' => $newTitle,
        ]);

        $this->assertSuccess($response);
        $this->assertHasField($response, 'message');
    }

    // ==================== MEDIA CATEGORY TESTS ====================

    public function testGetMediaCategoryList(): void
    {
        $response = $this->get('media/category');

        $this->assertSuccess($response);
        $this->assertIsArray($response['data']);
    }

    public function testGetMediaCategoryListWithFilter(): void
    {
        $response = $this->get('media/category', [
            'filter[category_id]' => 0,
        ]);

        $this->assertSuccess($response);
        $this->assertIsArray($response['data']);
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
