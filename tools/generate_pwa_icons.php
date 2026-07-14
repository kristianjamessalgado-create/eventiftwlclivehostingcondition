<?php
/**
 * One-time helper: crop source PNG to square and export 192 + 512 PWA icons.
 * Usage: php tools/generate_pwa_icons.php
 */
$src = __DIR__ . '/../assets/pwa/icon-192.png';
$out192 = __DIR__ . '/../assets/pwa/icon-192.png';
$out512 = __DIR__ . '/../assets/pwa/icon-512.png';

if (!is_file($src)) {
    fwrite(STDERR, "Source missing: $src\n");
    exit(1);
}

if (!extension_loaded('gd')) {
    fwrite(STDERR, "PHP GD extension required.\n");
    exit(1);
}

$img = imagecreatefrompng($src);
if (!$img) {
    fwrite(STDERR, "Could not read PNG.\n");
    exit(1);
}

$w = imagesx($img);
$h = imagesy($img);
$size = min($w, $h);
$srcX = (int) floor(($w - $size) / 2);
$srcY = (int) floor(($h - $size) / 2);

function export_square($src, int $srcX, int $srcY, int $srcSize, int $target, string $path): void
{
    $dst = imagecreatetruecolor($target, $target);
    imagealphablending($dst, false);
    imagesavealpha($dst, true);
    $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
    imagefilledrectangle($dst, 0, 0, $target, $target, $transparent);
    imagecopyresampled($dst, $src, 0, 0, $srcX, $srcY, $target, $target, $srcSize, $srcSize);
    if (!imagepng($dst, $path, 9)) {
        imagedestroy($dst);
        throw new RuntimeException("Failed to write $path");
    }
    imagedestroy($dst);
}

// Backup original once
$backup = __DIR__ . '/../assets/pwa/icon-source-backup.png';
if (!is_file($backup)) {
    copy($src, $backup);
    echo "Backed up original to assets/pwa/icon-source-backup.png\n";
}

export_square($img, $srcX, $srcY, $size, 192, $out192);
export_square($img, $srcX, $srcY, $size, 512, $out512);
imagedestroy($img);

foreach ([192 => $out192, 512 => $out512] as $dim => $path) {
    [$w, $h] = getimagesize($path);
    echo basename($path) . ": {$w}x{$h}, " . filesize($path) . " bytes\n";
}

echo "Done.\n";
