<?php

declare(strict_types=1);

namespace FriendsOfRedaxo\Api\Tests;

/**
 * `media/*` — media pool files and media categories.
 * @internal
 */
final class MediaApiTest extends ApiTestCase
{
    /** @var list<string> Uploaded filenames, deleted again in tearDown() */
    private array $uploadedFiles = [];

    /** @var list<string> Temp files created for uploads */
    private array $tempFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->uploadedFiles as $filename) {
            $this->delete('media/' . $filename . '/delete');
        }
        $this->uploadedFiles = [];

        foreach ($this->tempFiles as $path) {
            if (file_exists($path)) {
                unlink($path);
            }
        }
        $this->tempFiles = [];

        parent::tearDown();
    }

    public function testMediaListReturnsEnvelope(): void
    {
        $response = $this->get('media');
        $this->assertListResponse($response);
    }

    public function testMediaListFilterByUnknownCategoryReturnsNotFound(): void
    {
        $this->assertStatus(404, $this->get('media', ['filter' => ['category_id' => 999999]]));
    }

    public function testMediaListInvalidSortIsRejected(): void
    {
        $this->assertStatus(400, $this->get('media', ['sort' => 'nope:asc']));
    }

    public function testCategoryListReturnsEnvelope(): void
    {
        $this->assertListResponse($this->get('media/category'));
    }

    public function testCategoryLifecycle(): void
    {
        $name = $this->generateTestName('mediacat');

        $created = $this->post('media/category', ['name' => $name, 'parent_id' => 0]);
        $this->assertStatus(201, $created);
        self::assertIsArray($created['data']);

        $categoryId = (int) $created['data']['id'];
        self::assertGreaterThan(0, $categoryId);

        try {
            $categories = $this->assertListResponse($this->get('media/category'));
            $names = array_column($categories, 'name');
            self::assertContains($name, $names);

            $renamed = $name . '_updated';
            $this->assertSuccess($this->patch('media/category/' . $categoryId, ['name' => $renamed]));

            $names = array_column($this->assertListResponse($this->get('media/category')), 'name');
            self::assertContains($renamed, $names);
        } finally {
            $this->assertSuccess($this->delete('media/category/' . $categoryId));
        }
    }

    public function testCategoryWithUnknownParentIsRejected(): void
    {
        $response = $this->post('media/category', ['name' => $this->generateTestName('orphan'), 'parent_id' => 999999]);

        $this->assertStatus(404, $response);
    }

    public function testUnknownCategoryCannotBeUpdatedOrDeleted(): void
    {
        $this->assertStatus(404, $this->patch('media/category/999999', ['name' => 'x']));
        $this->assertStatus(404, $this->delete('media/category/999999'));
    }

    public function testUploadInfoDownloadAndDelete(): void
    {
        $filename = $this->uploadTestImage();

        $info = $this->get('media/' . $filename . '/info');
        $this->assertSuccess($info);

        foreach (['id', 'category_id', 'filetype', 'filename', 'filesize', 'width', 'height', 'title', 'is_in_use', 'is_image', 'file_exists'] as $field) {
            self::assertArrayHasKey($field, $info['data']);
        }

        self::assertTrue($info['data']['is_image']);
        self::assertTrue($info['data']['file_exists']);
        self::assertFalse($info['data']['is_in_use']);
        self::assertSame('image/png', $info['data']['filetype']);

        // The raw file is served as-is, so the response is not JSON.
        $file = $this->get('media/' . $filename . '/file');
        $this->assertSuccess($file);
        self::assertStringStartsWith("\x89PNG", (string) ($file['raw'] ?? ''));
    }

    public function testListCanBeSearchedLikeTheMediaPool(): void
    {
        $filename = $this->uploadTestImage();
        $title = (string) $this->get('media/' . $filename . '/info')['data']['title'];
        $found = fn (array $filter): array => array_column($this->assertListResponse($this->get('media', ['filter' => $filter, 'per_page' => 1000])), 'filename');

        // the title is searched as well as the file name
        self::assertContains($filename, $found(['term' => $title]));
        self::assertContains($filename, $found(['term' => $title . ' type:png']));
        self::assertNotContains($filename, $found(['term' => $title . ' type:jpg']));
        self::assertContains($filename, $found(['term' => $title, 'types' => 'gif,png']));
        self::assertNotContains($filename, $found(['term' => $title, 'types' => 'pdf']));
        // `%` is no wildcard
        self::assertSame([], $found(['term' => '%%' . $title]));
    }

    public function testMetadataUpdateViaJson(): void
    {
        $filename = $this->uploadTestImage();
        $title = $this->generateTestName('title');

        $this->assertSuccess($this->patch('media/' . $filename . '/update', ['title' => $title]));
        self::assertSame($title, $this->get('media/' . $filename . '/info')['data']['title'] ?? null);
    }

    public function testFileReplacementViaMultipartPost(): void
    {
        $filename = $this->uploadTestImage(8);

        $before = $this->get('media/' . $filename . '/info');
        self::assertSame(8, (int) $before['data']['width']);

        // Replacing the file itself goes through POST: PHP does not populate $_FILES for PUT/PATCH.
        $replacement = $this->createTempPng(16);
        $this->tempFiles[] = $replacement;

        $response = $this->postMultipart('media/' . $filename . '/update', ['title' => 'replaced'], ['file' => $replacement]);
        $this->assertSuccess($response);

        $after = $this->get('media/' . $filename . '/info');
        self::assertSame(16, (int) $after['data']['width'], 'The replaced file should have the new dimensions');
        self::assertSame('replaced', $after['data']['title']);
    }

    public function testUploadIntoCategory(): void
    {
        $created = $this->post('media/category', ['name' => $this->generateTestName('uploadcat'), 'parent_id' => 0]);
        $this->assertStatus(201, $created);
        $categoryId = (int) $created['data']['id'];

        try {
            $filename = $this->uploadTestImage(8, $categoryId);
            self::assertSame($categoryId, (int) $this->get('media/' . $filename . '/info')['data']['category_id']);

            // A category holding files is refused, mirroring the media pool page.
            $this->assertStatus(409, $this->delete('media/category/' . $categoryId));

            $this->assertSuccess($this->delete('media/' . $filename . '/delete'));
            $this->uploadedFiles = array_values(array_diff($this->uploadedFiles, [$filename]));
        } finally {
            $this->delete('media/category/' . $categoryId);
        }
    }

    public function testUploadWithoutFileIsRejected(): void
    {
        $this->assertStatus(400, $this->postMultipart('media', ['title' => 'no file here']));
    }

    public function testUnknownMediaReturnsNotFound(): void
    {
        $this->assertStatus(404, $this->get('media/this_file_does_not_exist.png/info'));
        $this->assertStatus(404, $this->delete('media/this_file_does_not_exist.png/delete'));
        $this->assertStatus(404, $this->patch('media/this_file_does_not_exist.png/update', ['title' => 'x']));
    }

    private function uploadTestImage(int $size = 8, int $categoryId = 0): string
    {
        $path = $this->createTempPng($size);
        $this->tempFiles[] = $path;

        $response = $this->postMultipart('media', [
            'category_id' => $categoryId,
            'title' => $this->generateTestName('media'),
        ], ['file' => $path]);

        $this->assertStatus(201, $response, 'Media upload failed');
        self::assertIsArray($response['data']);

        $filename = (string) $response['data']['filename'];
        $this->uploadedFiles[] = $filename;

        return $filename;
    }
}
