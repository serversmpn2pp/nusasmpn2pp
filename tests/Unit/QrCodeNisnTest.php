<?php

namespace Tests\Unit;

use App\Support\QrCodeNisn;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class QrCodeNisnTest extends TestCase
{
    public function test_qr_nisn_menghasilkan_svg_standar(): void
    {
        $svg = QrCodeNisn::svg('123456789012345678');

        $this->assertStringContainsString('<svg', $svg);
        $this->assertStringContainsString('viewBox=', $svg);
        $this->assertStringContainsString('<path', $svg);
    }

    public function test_qr_nisn_menolak_karakter_bukan_angka(): void
    {
        $this->expectException(InvalidArgumentException::class);

        QrCodeNisn::svg('NUSA-001');
    }
}
