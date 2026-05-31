<?php

namespace Tests\Unit;

use App\Support\BarcodeCode128;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class BarcodeCode128Test extends TestCase
{
    public function test_barcode_code_128_menghasilkan_svg_untuk_kode_inventaris(): void
    {
        $svg = BarcodeCode128::svg('INV-LPT-000001');

        $this->assertStringContainsString('<svg', $svg);
        $this->assertStringContainsString('Barcode INV-LPT-000001', $svg);
        $this->assertStringContainsString('<rect', $svg);
    }

    public function test_barcode_code_128_menolak_karakter_di_luar_ascii_cetak(): void
    {
        $this->expectException(InvalidArgumentException::class);

        BarcodeCode128::svg("INV\n001");
    }
}
