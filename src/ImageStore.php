<?php
declare(strict_types=1);

namespace App;

use GdImage;
use InvalidArgumentException;

final class ImageStore
{
    public const NAME_PATTERN = '/^[a-f0-9]{32}(_t)?\.webp\z/';
    public const MAX_UPLOAD = 8 * 1024 * 1024;
    public const MAX_DOWNLOAD = 5 * 1024 * 1024;
    private const MAX_WIDTH = 1600;
    private const THUMB_WIDTH = 400;
    private const MAX_PIXELS = 24_000_000;
    private const QUALITY = 85;
    private const TOTAL_TIMEOUT = 15.0;
    private const MIMES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

    public function __construct(private string $dir, private UrlGuard $guard)
    {
    }

    /** @param array{tmp_name: string, error: int, size: int} $file entrée de $_FILES */
    public function storeUpload(array $file): string
    {
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error !== UPLOAD_ERR_OK) {
            throw new ImageException(match ($error) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Photo trop lourde (8 Mo maximum).',
                UPLOAD_ERR_NO_FILE => 'Aucune photo reçue.',
                default => "Échec de l'envoi de la photo.",
            });
        }
        if ((int) $file['size'] > self::MAX_UPLOAD) {
            throw new ImageException('Photo trop lourde (8 Mo maximum).');
        }
        $bytes = @file_get_contents($file['tmp_name']);
        if ($bytes === false) {
            throw new ImageException("Échec de l'envoi de la photo.");
        }
        return $this->storeBytes($bytes);
    }

    public function storeFromUrl(string $url): string
    {
        return $this->storeBytes($this->download($url));
    }

    /** Décode, redimensionne et réencode en WebP (supprime EXIF et contenu piégé). */
    public function storeBytes(string $bytes): string
    {
        if (strlen($bytes) > self::MAX_UPLOAD) {
            throw new ImageException('Photo trop lourde (8 Mo maximum).');
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
        if (!in_array($mime, self::MIMES, true)) {
            throw new ImageException('Format non supporté (JPEG, PNG, WebP ou GIF).');
        }
        $size = @getimagesizefromstring($bytes);
        if ($size === false || $size[0] < 1 || $size[1] < 1 || $size[0] * $size[1] > self::MAX_PIXELS) {
            throw new ImageException('Image illisible ou trop grande.');
        }
        $image = @imagecreatefromstring($bytes);
        if ($image === false) {
            throw new ImageException('Image illisible.');
        }
        if ($mime === 'image/jpeg') {
            $image = $this->applyExifOrientation($image, $bytes);
        }
        if (!is_dir($this->dir) && !mkdir($this->dir, 0750, true) && !is_dir($this->dir)) {
            throw new ImageException("Impossible d'enregistrer la photo.");
        }
        $base = bin2hex(random_bytes(16));
        $mainPath = "$this->dir/$base.webp";
        $this->saveResized($image, self::MAX_WIDTH, $mainPath);
        try {
            $this->saveResized($image, self::THUMB_WIDTH, "$this->dir/{$base}_t.webp");
        } catch (ImageException $e) {
            @unlink($mainPath);
            throw $e;
        }
        return "$base.webp";
    }

    public function delete(?string $name): void
    {
        if ($name === null || preg_match(self::NAME_PATTERN, $name) !== 1) {
            return;
        }
        // Accepte indifféremment le nom principal ou celui de la miniature :
        // on retrouve toujours la base commune avant de cibler les deux fichiers
        // (sinon "<hex>_t.webp" donnerait à tort "<hex>_t_t.webp").
        $base = preg_replace('/_t\.webp\z/', '.webp', $name);
        foreach ([$base, str_replace('.webp', '_t.webp', $base)] as $file) {
            if (is_file("$this->dir/$file")) {
                @unlink("$this->dir/$file");
            }
        }
    }

    public function path(string $name): ?string
    {
        if (preg_match(self::NAME_PATTERN, $name) !== 1) {
            return null;
        }
        $path = "$this->dir/$name";
        return is_file($path) ? $path : null;
    }

    private function saveResized(GdImage $source, int $maxWidth, string $path): void
    {
        $width = imagesx($source);
        $height = imagesy($source);
        $newWidth = min($width, $maxWidth);
        $newHeight = max(1, (int) round($height * $newWidth / $width));
        $target = imagecreatetruecolor($newWidth, $newHeight);
        imagealphablending($target, false);
        imagesavealpha($target, true);
        imagecopyresampled($target, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        if (!imagewebp($target, $path, self::QUALITY)) {
            throw new ImageException("Impossible d'enregistrer la photo.");
        }
    }

    /** Redresse les photos de téléphone (orientation EXIF), si l'extension exif est disponible. */
    private function applyExifOrientation(GdImage $image, string $bytes): GdImage
    {
        if (!function_exists('exif_read_data')) {
            return $image;
        }
        $exif = @exif_read_data('data://image/jpeg;base64,' . base64_encode($bytes));
        $angle = match ((int) ($exif['Orientation'] ?? 1)) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };
        if ($angle === 0) {
            return $image;
        }
        $rotated = imagerotate($image, $angle, 0);
        if ($rotated === false) {
            return $image;
        }
        // La copie pivotée existe désormais : on relâche la référence à la source
        // pleine résolution pour qu'une seule image de cette taille reste vivante
        // à la fois (imagerotate() double sinon le pic mémoire, jusqu'à provoquer
        // un "Allowed memory size exhausted" fatal sur les gros JPEG).
        unset($image);
        return $rotated;
    }

    private function download(string $url): string
    {
        $deadline = microtime(true) + self::TOTAL_TIMEOUT;
        for ($redirects = 0; $redirects <= 3; $redirects++) {
            $remaining = $deadline - microtime(true);
            if ($remaining <= 0) {
                throw new ImageException('Délai dépassé.');
            }
            try {
                $target = $this->guard->check($url);
            } catch (InvalidArgumentException $e) {
                throw new ImageException($e->getMessage());
            }
            $buffer = '';
            $ip = str_contains($target['ip'], ':') ? '[' . $target['ip'] . ']' : $target['ip'];
            $curl = curl_init($url);
            curl_setopt_array($curl, [
                // Force la connexion sur l'IP vérifiée, quels que soient l'hôte et le
                // port demandés par ce handle (empêche le « DNS rebinding » et tout
                // contournement du pin, par ex. via une conversion punycode faite par
                // curl lui-même — CURLOPT_RESOLVE ne pinnait que la chaîne d'hôte brute).
                CURLOPT_CONNECT_TO => ['::' . $ip . ':'],
                // Ignore http_proxy/https_proxy de l'environnement : un proxy pourrait
                // recevoir la requête et faire lui-même la résolution DNS.
                CURLOPT_PROXY => '',
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_TIMEOUT => max(1, (int) ceil($remaining)),
                CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; WishlistImageFetcher/1.0)',
                CURLOPT_HTTPHEADER => ['Accept: image/*'],
                CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use (&$buffer): int {
                    $buffer .= $chunk;
                    return strlen($buffer) > self::MAX_DOWNLOAD ? 0 : strlen($chunk);
                },
            ]);
            $ok = curl_exec($curl);
            $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
            $location = curl_getinfo($curl, CURLINFO_REDIRECT_URL);
            $primaryIp = (string) curl_getinfo($curl, CURLINFO_PRIMARY_IP);
            $errno = curl_errno($curl);

            if (strlen($buffer) > self::MAX_DOWNLOAD) {
                throw new ImageException('Image trop lourde (5 Mo maximum).');
            }
            if ($ok === false) {
                throw new ImageException('Image inaccessible (' . curl_strerror($errno) . ').');
            }
            // Vérifie que curl a bien connecté l'IP vérifiée (et non une autre,
            // ce qui indiquerait que CONNECT_TO n'a pas été honoré).
            $checkedPacked = @inet_pton($target['ip']);
            $primaryPacked = @inet_pton($primaryIp);
            if ($checkedPacked === false || $primaryPacked === false || $primaryPacked !== $checkedPacked) {
                throw new ImageException('Adresse IP modifiée pendant le téléchargement.');
            }
            if ($status >= 300 && $status < 400 && is_string($location) && $location !== '') {
                $url = $location;
                continue;
            }
            if ($status !== 200) {
                throw new ImageException("Image inaccessible (erreur HTTP $status).");
            }
            return $buffer;
        }
        throw new ImageException('Trop de redirections.');
    }
}
