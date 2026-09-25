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

    // Anse : arc fin (anneau ellipse blanche puis verte) dont le bas est caché par le corps du sac.
    imagefilledellipse($img, $p(0), $p(-8), $len(28), $len(32), $white);
    imagefilledellipse($img, $p(0), $p(-8), $len(22), $len(26), $green);
    // Corps du sac : trapèze plus large en bas.
    imagefilledpolygon($img, [
        $p(-24), $p(-8),
        $p(24), $p(-8),
        $p(28), $p(30),
        $p(-28), $p(30),
    ], $white);
    // Œillets de l'anse.
    imagefilledellipse($img, $p(-14), $p(-2), $len(4), $len(4), $green);
    imagefilledellipse($img, $p(14), $p(-2), $len(4), $len(4), $green);

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
