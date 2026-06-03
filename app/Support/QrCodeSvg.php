<?php

namespace App\Support;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use InvalidArgumentException;

class QrCodeSvg
{
    public static function svg(string $isi, int $ukuran = 232): string
    {
        $isi = trim($isi);

        if ($isi === '' || mb_strlen($isi) > 512) {
            throw new InvalidArgumentException('Isi QR harus tersedia dan maksimal 512 karakter.');
        }

        return (new Writer(new ImageRenderer(
            new RendererStyle(size: $ukuran, margin: 4),
            new SvgImageBackEnd(),
        )))->writeString($isi);
    }
}
