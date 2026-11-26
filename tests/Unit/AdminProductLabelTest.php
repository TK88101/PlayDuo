<?php

declare(strict_types=1);

namespace PlayDuo\Tests\Unit;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../includes/admin_product.php';

/** A03 一覧の表示値（テスト仕様書（管理画面）A03-002・A03-004、Plan §2.0 商品の表示 ID） */
final class AdminProductLabelTest extends TestCase
{
    public function testProductCodeComesFromStrictFilePath(): void
    {
        self::assertSame('g01', product_code(['product_id' => 1, 'file_path' => 'downloads/g01.zip']));
        self::assertSame('g07', product_code(['product_id' => 6, 'file_path' => 'downloads/g07.zip']));
        self::assertSame('p12', product_code(['product_id' => 12, 'file_path' => 'downloads/p12.zip']));
        self::assertSame('p12', product_code(['product_id' => 12, 'file_path' => 'downloads/p12_0123456789abcdef.zip']));
        foreach (['downloads/../g01.zip', 'g01.zip', 'downloads/G01.zip', 'downloads/g01.zip.bak', "downloads/g01.zip\n"] as $path) {
            self::assertSame('#3', product_code(['product_id' => 3, 'file_path' => $path]), $path);
        }
    }

    public function testOsColumn(): void
    {
        self::assertSame('Win・Mac', admin_os_label(['os_windows' => 1, 'os_mac' => 1]));
        self::assertSame('Win', admin_os_label(['os_windows' => 1, 'os_mac' => 0]));
        self::assertSame('Mac', admin_os_label(['os_windows' => 0, 'os_mac' => 1]));
    }

    public function testCrossplayColumnIsBlankForSingleOs(): void
    {
        self::assertSame('○', admin_crossplay_mark(['os_windows' => 1, 'os_mac' => 1, 'crossplay_flag' => 1]));
        self::assertSame('×', admin_crossplay_mark(['os_windows' => 1, 'os_mac' => 1, 'crossplay_flag' => 0]));
        self::assertSame('', admin_crossplay_mark(['os_windows' => 1, 'os_mac' => 0, 'crossplay_flag' => 0]));
    }

    public function testStatusLabel(): void
    {
        self::assertSame('公開中', public_status_label(['public_flag' => 1]));
        self::assertSame('非公開', public_status_label(['public_flag' => 0]));
    }
}
