<?php
// Génère les icônes PWA (sac de courses blanc sur fond vert) dans public/assets/icons/.
// Usage : php bin/make-icons.php — à relancer uniquement si le design change.
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit;
}

$dir = dirname(__DIR__) . '/public/assets/icons';
if (!is_dir($dir)) {
    mkdir($dir, 0755, true);
}

/** @param float $scale taille du motif (1.0 = pleine icône ; < 1 pour la zone sûre des icônes « maskable ») */
function drawIcon(int $size, float $scale, string $path): void
{
    $supersample = 4;
    $big = $size * $supersample;
    $img = imagecreatetruecolor($big, $big);
    $green = imagecolorallocate($img, 0x2f, 0x6f, 0x5e);
    $white = imagecolorallocate($img, 0xff, 0xff, 0xff);
    imagefilledrectangle($img, 0, 0, $big - 1, $big - 1, $green);

    $unit = $big / 100 * $scale;
    $center = $big / 2;
    $p = static fn (float $v): int => (int) round($center + $v * $unit);
    $len = static fn (float $v): int => (int) round($v * $unit);

    // Anse : anneau (ellipse blanche puis verte) dont le bas est caché par le corps du sac.
    imagefilledellipse($img, $p(0), $p(-14), $len(34), $len(36), $white);
    imagefilledellipse($img, $p(0), $p(-14), $len(22), $len(24), $green);
    // Corps du sac.
    imagefilledrectangle($img, $p(-26), $p(-10), $p(26), $p(30), $white);

    $out = imagecreatetruecolor($size, $size);
    imagecopyresampled($out, $img, 0, 0, 0, 0, $size, $size, $big, $big);
    imagepng($out, $path, 9);
}

$icons = [
    'favicon-32.png' => [32, 1.0],
    'apple-touch-icon.png' => [180, 0.9],
    'icon-192.png' => [192, 1.0],
    'icon-512.png' => [512, 1.0],
    'icon-maskable-512.png' => [512, 0.72],
];
foreach ($icons as $name => [$size, $scale]) {
    drawIcon($size, $scale, "$dir/$name");
    echo "$dir/$name\n";
}
