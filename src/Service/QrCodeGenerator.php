<?php

namespace App\Service;

use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\SvgWriter;

/**
 * QR code du recu, encode en data URI.
 *
 * Le format SVG est choisi a dessein : il ne demande pas l'extension GD, reste
 * net a l'impression quelle que soit la resolution, et le data URI evite toute
 * requete sortante au moment de l'affichage ou de l'impression du recu.
 */
class QrCodeGenerator
{
    private const SIZE = 160;

    public function dataUri(string $content): ?string
    {
        if ($content === '') {
            return null;
        }

        try {
            $qrCode = new QrCode(data: $content, size: self::SIZE, margin: 0);

            return (new SvgWriter())->write($qrCode)->getDataUri();
        } catch (\Throwable) {
            // Un recu doit rester imprimable meme si le QR ne peut pas etre
            // produit : le code de verification en clair suffit alors.
            return null;
        }
    }
}
