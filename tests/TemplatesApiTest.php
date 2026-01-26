<?php

declare(strict_types=1);

namespace FriendsOfRedaxo\Api\Tests;

/**
 * Tests für die Templates API.
 */
class TemplatesApiTest extends ApiTestCase
{
    // ==================== TEMPLATE LIST TESTS ====================

    public function testGetTemplateList(): void
    {
        $response = $this->get('templates');

        $this->assertSuccess($response);
        $this->assertIsArray($response['data']);
    }

    // ==================== TEMPLATE CRUD TESTS ====================

    public function testGetTemplate(): void
    {
        $templateId = self::$config['test_data']['existing_template_id'];
        $response = $this->get('templates/' . $templateId);

        if (404 === $response['status']) {
            $this->markTestSkipped('Template mit ID ' . $templateId . ' existiert nicht.');
        }

        $this->assertSuccess($response);
        $this->assertHasField($response, 'id');
        $this->assertHasField($response, 'name');
    }

    public function testGetTemplateNotFound(): void
    {
        $response = $this->get('templates/999999');

        $this->assertStatus(404, $response);
        $this->assertError($response);
    }

    public function testCreateTemplate(): void
    {
        $name = $this->generateTestName('template');

        $response = $this->post('templates', [
            'name' => $name,
            'content' => '<?php echo "Test"; ?>',
            'active' => 0,
        ]);

        $this->assertStatus(201, $response);
        $this->assertHasField($response, 'id');

        $this->trackResource('templates', $response['data']['id']);
    }

    public function testCreateTemplateValidation(): void
    {
        // Ohne Pflichtfeld 'name'
        $response = $this->post('templates', [
            'content' => '<?php echo "Test"; ?>',
        ]);

        $this->assertStatus(400, $response);
        $this->assertError($response);
    }

    public function testUpdateTemplate(): void
    {
        // Erst Template erstellen
        $name = $this->generateTestName('template');
        $createResponse = $this->post('templates', [
            'name' => $name,
            'content' => '<?php echo "Original"; ?>',
            'active' => 0,
        ]);

        $this->assertStatus(201, $createResponse);
        $templateId = $createResponse['data']['id'];
        $this->trackResource('templates', $templateId);

        // Dann updaten
        $newName = $this->generateTestName('template_updated');
        $updateResponse = $this->put('templates/' . $templateId, [
            'name' => $newName,
            'content' => '<?php echo "Updated"; ?>',
        ]);

        $this->assertSuccess($updateResponse);
    }

    public function testDeleteTemplate(): void
    {
        // Erst Template erstellen
        $name = $this->generateTestName('template_delete');
        $createResponse = $this->post('templates', [
            'name' => $name,
            'content' => '<?php echo "Delete"; ?>',
            'active' => 0,
        ]);

        $this->assertStatus(201, $createResponse);
        $templateId = $createResponse['data']['id'];

        // Dann löschen
        $deleteResponse = $this->delete('templates/' . $templateId);

        $this->assertSuccess($deleteResponse);

        // Prüfen ob wirklich gelöscht
        $getResponse = $this->get('templates/' . $templateId);
        $this->assertStatus(404, $getResponse);
    }

    public function testDeleteTemplateNotFound(): void
    {
        $response = $this->delete('templates/999999');

        $this->assertStatus(404, $response);
        $this->assertError($response);
    }
}
