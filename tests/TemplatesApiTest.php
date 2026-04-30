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
        $this->assertIsArray($response['data']['data']);
        $this->assertArrayHasKey('meta', $response['data']);
        $this->assertArrayHasKey('total', $response['data']['meta']);
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

    /**
     * Regression test for FriendsOfREDAXO/api#34: API-erstellte Templates müssen
     * im Strukturbaum auswählbar sein UND ihre Slices müssen editierbar sein.
     * Das schlägt fehl, wenn `attributes.categories.all` oder
     * `attributes.modules.1.all` auf 0 stehen — dann ist das Template global
     * unsichtbar bzw. der Modul-Check beim Slice-Save blockt.
     *
     * Wichtig: rex_article_service::addArticle() macht einen SILENT FALLBACK auf
     * das Default-Template, wenn das gewünschte Template nicht in
     * rex_template::getTemplatesForCategory($categoryId) auftaucht. Der Test muss
     * also explizit prüfen, ob der Artikel danach tatsächlich das angelegte
     * Template referenziert — sonst rutscht der Bug durch (Slice geht dann auch,
     * weil das Default-Template korrekt konfiguriert ist).
     */
    public function testCreatedTemplateIsUsableForArticleAndSlice(): void
    {
        $moduleId = self::$config['test_data']['existing_module_id'];
        $clangId = self::$config['test_data']['existing_clang_id'];

        $tplResponse = $this->post('templates', [
            'name' => $this->generateTestName('regression_tpl'),
            'content' => 'REX_ARTICLE_CONTENT_TYPE[ctype=1]',
            'active' => 1,
        ]);
        $this->assertStatus(201, $tplResponse);
        $templateId = (int) $tplResponse['data']['id'];
        $this->trackResource('templates', $templateId);

        $articleResponse = $this->post('structure/articles', [
            'name' => $this->generateTestName('regression_article'),
            'category_id' => 0,
            'priority' => 1,
            'status' => 0,
            'template_id' => $templateId,
        ]);
        $this->assertStatus(201, $articleResponse);
        $articleId = (int) $articleResponse['data']['id'];
        $this->trackResource('structure/articles', $articleId);

        // Verifiziert das eigentliche Symptom des Issues: der Artikel muss das
        // gewünschte Template behalten — sonst hat addArticle() es weggeworfen,
        // weil es nicht in getTemplatesForCategory() auftaucht (= attributes.categories.all=0).
        $articleGet = $this->get('structure/articles/' . $articleId);
        $this->assertSuccess($articleGet);
        $this->assertSame(
            $templateId,
            (int) $articleGet['data']['template_id'],
            'Artikel sollte das angelegte Template referenzieren. Wenn 0 oder eine andere ID → Template ist nicht in der Struktur auswählbar (Bug #34).',
        );

        $sliceResponse = $this->post('structure/articles/' . $articleId . '/slices', [
            'module_id' => $moduleId,
            'clang_id' => $clangId,
            'ctype_id' => 1,
            'value1' => 'regression test',
        ]);
        $this->assertStatus(
            201,
            $sliceResponse,
            'Slice-Add sollte 201 liefern. Wenn 404 mit "Template has no module in such ctype" → Bug aus Issue #34 ist zurück.',
        );
    }
}
