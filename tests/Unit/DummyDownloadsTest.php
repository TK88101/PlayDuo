<?php

declare(strict_types=1);

namespace PlayDuo\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ZipArchive;

require_once __DIR__ . '/../../includes/download.php';

/** seed.sql の配布ファイルがすべて downloads/ にあり、ZIP として開ける（Plan 2026-11-26 T3・Codex P2-15） */
final class DummyDownloadsTest extends TestCase
{
    public function testEverySeedFilePathHasAReadableZip(): void
    {
        preg_match_all("#'(downloads/[^']+)'#", (string) file_get_contents(__DIR__ . '/../../sql/seed.sql'), $m);
        $paths = array_unique($m[1]);
        self::assertCount(8, $paths);

        foreach ($paths as $path) {
            $file = download_file_path($path);
            self::assertNotNull($file, $path);
            $zip = new ZipArchive();
            self::assertTrue($zip->open($file) === true, $path);
            self::assertGreaterThan(0, $zip->numFiles, $path);
            $zip->close();
        }
    }
}
