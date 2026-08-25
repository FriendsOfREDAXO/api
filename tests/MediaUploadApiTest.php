<?php

declare(strict_types=1);

namespace FriendsOfRedaxo\Api\Tests;

/**
 * Tests für den Chunked Upload (`media/upload/*`).
 *
 * Voraussetzung am API-Token: der Scope `media/upload`. Alle fünf Endpunkte
 * teilen sich diesen einen Scope — einzeln sind sie unbrauchbar.
 *
 * Fehlt der Scope, überspringen sich die Tests statt fehlzuschlagen.
 */
class MediaUploadApiTest extends ApiTestCase
{
    /** Nutzlast, die in mehrere Chunks zerlegt wird. */
    private const PayloadBytes = 300000;

    public function testChunkedUploadRoundtrip(): void
    {
        $payload = self::pngPayload(self::PayloadBytes);
        $upload = $this->initUpload('chunked-roundtrip.png', strlen($payload));

        $chunks = str_split($payload, 100000);
        $this->assertGreaterThan(1, count($chunks), 'Die Nutzlast muss in mehrere Chunks fallen.');

        foreach ($chunks as $index => $chunk) {
            $response = $this->postRaw('media/upload/' . $upload['upload_id'] . '/chunk/' . $index, $chunk);
            $this->assertSuccess($response);
        }

        $status = $this->get('media/upload/' . $upload['upload_id']);
        $this->assertSuccess($status);
        $this->assertSame(range(0, count($chunks) - 1), $status['data']['chunks']);
        $this->assertTrue($status['data']['complete']);
        $this->assertSame(0, $status['data']['bytes_missing']);

        $finalize = $this->post('media/upload/' . $upload['upload_id'] . '/finalize');
        $this->assertStatus(201, $finalize);
        $filename = $finalize['data']['filename'];
        $this->trackResource('media', $filename . '/delete');

        // Die abgelegte Datei muss genau so groß sein wie die Nutzlast -- sonst wurden
        // Chunks in falscher Reihenfolge oder doppelt zusammengesetzt.
        $info = $this->get('media/' . $filename . '/info');
        $this->assertSuccess($info);
        $this->assertSame(strlen($payload), (int) $info['data']['filesize']);

        // Nach dem Abschluss darf der Upload nicht mehr auffindbar sein.
        $gone = $this->get('media/upload/' . $upload['upload_id']);
        $this->assertStatus(404, $gone);
    }

    public function testInitRejectsDisallowedExtension(): void
    {
        $response = $this->post('media/upload', ['filename' => 'evil.php', 'size' => 100]);
        if (401 === $response['status']) {
            $this->markTestSkipped('Dem Token fehlt der Scope media/upload.');
        }

        $this->assertStatus(400, $response);
        $this->assertSame('File extension is not allowed', $response['data']['error']);
    }

    public function testInitRejectsUnknownBodyField(): void
    {
        $response = $this->post('media/upload', ['filename' => 'a.png', 'size' => 100, 'foo' => 1]);
        if (401 === $response['status']) {
            $this->markTestSkipped('Dem Token fehlt der Scope media/upload.');
        }

        $this->assertStatus(400, $response);
        $this->assertStringContainsString('Unknown field', (string) $response['data']['error']);
    }

    public function testInitRejectsInvalidSize(): void
    {
        $zero = $this->post('media/upload', ['filename' => 'a.png', 'size' => 0]);
        if (401 === $zero['status']) {
            $this->markTestSkipped('Dem Token fehlt der Scope media/upload.');
        }
        $this->assertStatus(400, $zero);

        $huge = $this->post('media/upload', ['filename' => 'a.png', 'size' => 9999999999]);
        $this->assertStatus(413, $huge);
        $this->assertArrayHasKey('max_total_size', $huge['data']);
    }

    public function testChunksMayNotExceedAnnouncedSize(): void
    {
        $upload = $this->initUpload('too-big.png', 200);

        try {
            $ok = $this->postRaw('media/upload/' . $upload['upload_id'] . '/chunk/0', str_repeat('x', 100));
            $this->assertSuccess($ok);

            $tooBig = $this->postRaw('media/upload/' . $upload['upload_id'] . '/chunk/1', str_repeat('x', 500));
            $this->assertStatus(400, $tooBig);

            // Der abgelehnte Chunk darf nicht gespeichert worden sein.
            $status = $this->get('media/upload/' . $upload['upload_id']);
            $this->assertSame([0], $status['data']['chunks']);
            $this->assertSame(100, $status['data']['bytes_received']);
        } finally {
            $this->delete('media/upload/' . $upload['upload_id']);
        }
    }

    public function testResendingSameChunkReplacesItInsteadOfAdding(): void
    {
        $upload = $this->initUpload('replace.png', 200);

        try {
            $first = $this->postRaw('media/upload/' . $upload['upload_id'] . '/chunk/0', str_repeat('x', 100));
            $this->assertSame(100, $first['data']['bytes_received']);

            $again = $this->postRaw('media/upload/' . $upload['upload_id'] . '/chunk/0', str_repeat('y', 100));
            $this->assertSuccess($again);
            $this->assertSame(100, $again['data']['bytes_received'], 'Derselbe Index darf die Summe nicht doppelt belasten.');
        } finally {
            $this->delete('media/upload/' . $upload['upload_id']);
        }
    }

    public function testFinalizeRejectsGapInChunks(): void
    {
        $upload = $this->initUpload('gap.png', 200);

        try {
            $this->postRaw('media/upload/' . $upload['upload_id'] . '/chunk/0', str_repeat('x', 100));
            $this->postRaw('media/upload/' . $upload['upload_id'] . '/chunk/2', str_repeat('x', 100));

            $status = $this->get('media/upload/' . $upload['upload_id']);
            $this->assertFalse($status['data']['contiguous']);

            $finalize = $this->post('media/upload/' . $upload['upload_id'] . '/finalize');
            $this->assertStatus(400, $finalize);
            $this->assertStringContainsString('contiguous', (string) $finalize['data']['error']);
        } finally {
            $this->delete('media/upload/' . $upload['upload_id']);
        }
    }

    public function testFinalizeRejectsSizeMismatch(): void
    {
        $upload = $this->initUpload('short.png', 300);

        try {
            $this->postRaw('media/upload/' . $upload['upload_id'] . '/chunk/0', str_repeat('x', 100));

            $finalize = $this->post('media/upload/' . $upload['upload_id'] . '/finalize');
            $this->assertStatus(400, $finalize);
            $this->assertSame(100, $finalize['data']['bytes_received']);
        } finally {
            $this->delete('media/upload/' . $upload['upload_id']);
        }
    }

    public function testAbortRemovesUpload(): void
    {
        $upload = $this->initUpload('abort.png', 200);
        $this->postRaw('media/upload/' . $upload['upload_id'] . '/chunk/0', str_repeat('x', 100));

        $abort = $this->delete('media/upload/' . $upload['upload_id']);
        $this->assertSuccess($abort);

        $status = $this->get('media/upload/' . $upload['upload_id']);
        $this->assertStatus(404, $status);
    }

    public function testUnknownUploadIdReturnsNotFound(): void
    {
        $response = $this->get('media/upload/' . str_repeat('a', 32));
        if (401 === $response['status']) {
            $this->markTestSkipped('Dem Token fehlt der Scope media/upload.');
        }

        $this->assertStatus(404, $response);
        // Auf den Text pruefen, nicht nur auf 404: eine nicht registrierte Route
        // antwortet ebenfalls mit 404, der Test wuerde sonst nichts zeigen.
        $this->assertSame('Upload not found', $response['data']['error']);
    }

    /**
     * Startet einen Upload und liefert die Antwortdaten, oder überspringt den Test,
     * wenn dem Token die Scopes fehlen.
     *
     * @return array<string, mixed>
     */
    private function initUpload(string $filename, int $size): array
    {
        $response = $this->post('media/upload', [
            'filename' => $filename,
            'size' => $size,
            'category_id' => 0,
            'title' => $this->generateTestName('upload'),
        ]);

        if (401 === $response['status']) {
            $this->markTestSkipped('Dem Token fehlt der Scope media/upload.');
        }

        $this->assertStatus(201, $response);

        return $response['data'];
    }

    /** Gültiges PNG mit tEXt-Ballast, damit die Datei die Zielgröße erreicht. */
    private static function pngPayload(int $bytes): string
    {
        $chunk = static function (string $type, string $data): string {
            return pack('N', strlen($data)) . $type . $data . pack('N', crc32($type . $data));
        };

        return "\x89PNG\r\n\x1a\n"
            . $chunk('IHDR', pack('NNCCCCC', 1, 1, 8, 2, 0, 0, 0))
            . $chunk('IDAT', gzcompress("\x00\x00\x00\x00"))
            . $chunk('tEXt', "Comment\x00" . str_repeat('x', $bytes))
            . $chunk('IEND', '');
    }
}
