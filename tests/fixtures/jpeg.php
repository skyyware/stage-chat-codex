<?php

declare(strict_types=1);

function jpegFixture(int $width = 96, int $height = 32, int $variant = 0): string
{
    if ($width < 1 || $height < 1 || $variant < 0 || $variant > 25) {
        throw new InvalidArgumentException('Invalid synthetic image dimensions or variant.');
    }
    $image = imagecreatetruecolor($width, $height);
    if ($image === false) {
        throw new RuntimeException('Cannot create synthetic image.');
    }
    foreach ([[220, 30 + $variant, 30], [30, 180, 30 + $variant], [30 + $variant, 30, 220]] as $index => $rgb) {
        $color = imagecolorallocate($image, ...$rgb);
        if ($color === false) {
            throw new RuntimeException('Cannot allocate fixture color.');
        }
        imagefilledrectangle($image, (int) ($index * $width / 3), 0, (int) (($index + 1) * $width / 3) - 1, $height - 1, $color);
    }
    ob_start();
    imagejpeg($image, null, 90);
    $bytes = ob_get_clean();
    if ($bytes === false) {
        throw new RuntimeException('Cannot encode synthetic image.');
    }

    return $bytes;
}
