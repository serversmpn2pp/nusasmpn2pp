<?php

namespace App\Support;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use InvalidArgumentException;

class QrCodeNisn
{
    public static function svg(string $nisn): string
    {
        $nisn = trim($nisn);

        if (! preg_match('/^[0-9]{1,41}$/', $nisn)) {
            throw new InvalidArgumentException('QR NISN hanya mendukung angka 1 sampai 41 digit.');
        }

        return (new Writer(new ImageRenderer(
            new RendererStyle(size: 232, margin: 4),
            new SvgImageBackEnd(),
        )))->writeString($nisn);
    }
}
