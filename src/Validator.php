<?php
declare(strict_types=1);

namespace App;

use DateTimeImmutable;

final class Validator
{
    public const PRIORITIES = ['high', 'none', 'low'];
    public const MAX_TAGS = 20;
    public const MAX_COMMENT = 2000;

    /** @return array{data: array, errors: array<string, string>} */
    public static function item(array $in): array
    {
        $errors = [];

        $title = self::line($in['title'] ?? '');
        if ($title === '') {
            $errors['title'] = 'Le titre est obligatoire.';
        } elseif (mb_strlen($title) > 200) {
            $errors['title'] = 'Le titre ne doit pas dépasser 200 caractères.';
        }

        $description = self::multiline($in['description'] ?? '');
        if (mb_strlen($description) > 10000) {
            $errors['description'] = 'La description ne doit pas dépasser 10 000 caractères.';
        }

        $url = self::line($in['url'] ?? '');
        if ($url !== '' && !self::isHttpUrl($url)) {
            $errors['url'] = 'Lien invalide (http ou https uniquement).';
        }

        $store = self::line($in['store'] ?? '');
        if (mb_strlen($store) > 100) {
            $errors['store'] = 'Le magasin ne doit pas dépasser 100 caractères.';
        }

        $price = self::parsePrice($in['price_estimated'] ?? '');
        if ($price === false) {
            $errors['price_estimated'] = 'Prix invalide (exemple : 12,50).';
        }

        $quantityRaw = self::line($in['quantity'] ?? '');
        $quantity = $quantityRaw === '' ? 1 : (ctype_digit($quantityRaw) ? (int) $quantityRaw : 0);
        if ($quantity < 1 || $quantity > 999) {
            $errors['quantity'] = 'La quantité doit être comprise entre 1 et 999.';
        }

        $priority = $in['priority'] ?? 'none';
        if (!in_array($priority, self::PRIORITIES, true)) {
            $errors['priority'] = 'Priorité invalide.';
            $priority = 'none';
        }

        $tags = self::tagList($in['tags'] ?? '');
        if (count($tags) > self::MAX_TAGS) {
            $errors['tags'] = self::MAX_TAGS . ' tags maximum.';
        }

        $imageUrl = self::line($in['image_url'] ?? '');
        if ($imageUrl !== '' && !self::isHttpUrl($imageUrl)) {
            $errors['image_url'] = "URL d'image invalide (http ou https uniquement).";
        }

        return [
            'data' => [
                'title' => $title,
                'description' => $description === '' ? null : $description,
                'url' => $url === '' ? null : $url,
                'store' => $store === '' ? null : $store,
                'price_estimated' => $price === false ? null : $price,
                'quantity' => $quantity,
                'priority' => $priority,
                'tags' => $tags,
                'image_url' => $imageUrl === '' ? null : $imageUrl,
            ],
            'errors' => $errors,
        ];
    }

    /** @return array{data: array{purchased_at: string, price_paid: ?string}, errors: array<string, string>} */
    public static function purchase(array $in, string $today): array
    {
        $errors = [];
        $date = self::line($in['purchased_at'] ?? '');
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if ($date === '') {
            $errors['purchased_at'] = "La date d'achat est obligatoire.";
        } elseif ($parsed === false || $parsed->format('Y-m-d') !== $date) {
            $errors['purchased_at'] = 'Date invalide.';
        } elseif ($date > $today) {
            $errors['purchased_at'] = "La date d'achat ne peut pas être dans le futur.";
        }
        $price = self::parsePrice($in['price_paid'] ?? '');
        if ($price === false) {
            $errors['price_paid'] = 'Prix invalide (exemple : 12,50).';
        }
        return [
            'data' => ['purchased_at' => $date, 'price_paid' => $price === false ? null : $price],
            'errors' => $errors,
        ];
    }

    /** @return array{data: array{body: string}, errors: array<string, string>} */
    public static function comment(mixed $raw): array
    {
        $body = self::multiline($raw);
        $errors = [];
        if ($body === '') {
            $errors['body'] = 'Le commentaire est vide.';
        } elseif (mb_strlen($body) > self::MAX_COMMENT) {
            $errors['body'] = 'Le commentaire ne doit pas dépasser ' . self::MAX_COMMENT . ' caractères.';
        }
        return ['data' => ['body' => $body], 'errors' => $errors];
    }

    public static function username(string $username): ?string
    {
        return preg_match('/^[a-z0-9._-]{3,50}$/', $username) === 1
            ? null
            : "L'identifiant doit contenir de 3 à 50 caractères : lettres minuscules, chiffres, point, tiret ou tiret bas.";
    }

    public static function displayName(string $name): ?string
    {
        $name = self::line($name);
        return $name !== '' && mb_strlen($name) <= 100 ? null : 'Le nom affiché doit contenir de 1 à 100 caractères.';
    }

    public static function password(string $password, string $confirm): ?string
    {
        if (mb_strlen($password) < 10) {
            return 'Le mot de passe doit contenir au moins 10 caractères.';
        }
        if (mb_strlen($password) > 200) {
            return 'Le mot de passe ne doit pas dépasser 200 caractères.';
        }
        return hash_equals($password, $confirm) ? null : 'Les mots de passe ne correspondent pas.';
    }

    /** "1 299,9 €" → "1299.90" ; vide → null ; invalide → false. */
    public static function parsePrice(mixed $raw): string|false|null
    {
        if ($raw === null) {
            return null;
        }
        if (!is_string($raw) && !is_int($raw) && !is_float($raw)) {
            return false;
        }
        $value = str_replace([' ', "\u{00A0}", "\u{202F}", '€'], '', trim((string) $raw));
        if ($value === '') {
            return null;
        }
        $value = str_replace(',', '.', $value);
        if (preg_match('/^\d{1,7}(\.\d{1,2})?$/', $value) !== 1) {
            return false;
        }
        return number_format((float) $value, 2, '.', '');
    }

    /** Normalise un nom de tag : espaces et virgules réduits à un espace, 50 caractères max. */
    public static function tagName(string $name): string
    {
        $name = trim((string) preg_replace('/[\s,]+/u', ' ', $name));
        return trim(mb_substr($name, 0, 50));
    }

    /** @return list<string> tags uniques (insensible à la casse), dans l'ordre de saisie */
    public static function tagList(mixed $raw): array
    {
        $parts = is_array($raw) ? $raw : explode(',', is_string($raw) ? $raw : '');
        $out = [];
        foreach ($parts as $part) {
            if (!is_string($part)) {
                continue;
            }
            $name = self::tagName($part);
            $key = mb_strtolower($name);
            if ($name !== '' && !isset($out[$key])) {
                $out[$key] = $name;
            }
        }
        return array_values($out);
    }

    private static function isHttpUrl(string $url): bool
    {
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        return mb_strlen($url) <= 2048
            && filter_var($url, FILTER_VALIDATE_URL) !== false
            && in_array($scheme, ['http', 'https'], true);
    }

    /** Texte sur une ligne : caractères de contrôle remplacés par des espaces, trim. */
    private static function line(mixed $value): string
    {
        return is_string($value) ? trim((string) preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $value)) : '';
    }

    /** Texte multiligne : fins de ligne normalisées en \n, trim. */
    private static function multiline(mixed $value): string
    {
        return is_string($value) ? trim(str_replace(["\r\n", "\r"], "\n", $value)) : '';
    }
}
