<?php

declare(strict_types=1);

namespace FriendsOfRedaxo\Api\Tests;

use function strlen;

/**
 * `media/upload/*` — chunked upload of large files.
 * @internal
 */
final class MediaUploadApiTest extends ApiTestCase
{
    /** @var list<string> */
    private array $uploadedFiles = [];

    /** @var list<string> */
    private array $openUploads = [];

    protected function tearDown(): void
    {
        foreach ($this->openUploads as $uploadId) {
            $this->delete('media/upload/' . $uploadId);
        }
        foreach ($this->uploadedFiles as $filename) {
            $this->delete('media/' . $filename . '/delete');
        }
        $this->openUploads = $this->uploadedFiles = [];

        parent::tearDown();
    }

    public function testFileIsAssembledFromChunks(): void
    {
        $content = file_get_contents($this->createTempPng(64));
        self::assertIsString($content);

        $uploadId = $this->init($this->generateTestName('chunked') . '.png', strlen($content));

        // sent out of order, the index decides the position
        $parts = str_split($content, (int) ceil(strlen($content) / 3));
        foreach ([2, 0, 1] as $index) {
            $this->assertSuccess($this->request('PUT', 'media/upload/' . $uploadId . '/chunk/' . $index, ['body' => $parts[$index]]));
        }

        $status = $this->get('media/upload/' . $uploadId);
        $this->assertSuccess($status);
        self::assertSame([0, 1, 2], $status['data']['chunks']);
        self::assertTrue($status['data']['complete']);

        $finalized = $this->post('media/upload/' . $uploadId . '/finalize');
        $this->assertStatus(201, $finalized);
        $filename = (string) $finalized['data']['filename'];
        $this->uploadedFiles[] = $filename;
        $this->openUploads = [];

        $file = $this->get('media/' . $filename . '/file');
        self::assertSame($content, $file['raw'] ?? null);

        // the upload is gone after finalize
        $this->assertStatus(404, $this->get('media/upload/' . $uploadId));
    }

    public function testFinalizeWithMissingChunkIsRejected(): void
    {
        // the bytes add up to the announced size, only index 1 is missing
        $uploadId = $this->init($this->generateTestName('gap') . '.png', 10);

        $this->assertSuccess($this->request('POST', 'media/upload/' . $uploadId . '/chunk/0', ['body' => '12345']));
        $this->assertSuccess($this->request('POST', 'media/upload/' . $uploadId . '/chunk/2', ['body' => '67890']));

        $response = $this->post('media/upload/' . $uploadId . '/finalize');
        $this->assertStatus(400, $response);
        self::assertSame([0, 2], $response['data']['chunks'] ?? null);
    }

    public function testChunksMustNotExceedTheAnnouncedSize(): void
    {
        $uploadId = $this->init($this->generateTestName('size') . '.png', 4);

        $this->assertStatus(400, $this->request('POST', 'media/upload/' . $uploadId . '/chunk/0', ['body' => '12345']));
    }

    public function testBlockedExtensionIsRejectedAtInit(): void
    {
        $this->assertStatus(400, $this->post('media/upload', ['filename' => 'evil.php', 'size' => 10]));
    }

    public function testUnknownUploadReturnsNotFound(): void
    {
        $this->assertStatus(404, $this->get('media/upload/' . str_repeat('0', 32)));
    }

    private function init(string $filename, int $size): string
    {
        $response = $this->post('media/upload', ['filename' => $filename, 'size' => $size]);
        $this->assertStatus(201, $response);

        $uploadId = (string) $response['data']['upload_id'];
        $this->openUploads[] = $uploadId;

        return $uploadId;
    }
}
