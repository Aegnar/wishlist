<?php
declare(strict_types=1);

/** Échappe une valeur pour une sortie HTML (texte ou attribut). */
function e(mixed $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** 1234.5 → "1 234,50 €" (espaces insécables) ; null ou '' → "—". */
function format_price(mixed $amount): string
{
    if ($amount === null || $amount === '') {
        return '—';
    }
    return number_format((float) $amount, 2, ',', "\u{202F}") . "\u{00A0}€";
}

/** Valeur de prix pour un champ de formulaire : 12.5 → "12,50" ; null → "". */
function format_price_input(mixed $amount): string
{
    if ($amount === null || $amount === '') {
        return '';
    }
    return number_format((float) $amount, 2, ',', '');
}

function format_date(?string $date): string
{
    $ts = $date ? strtotime($date) : false;
    return $ts === false ? '—' : date('d/m/Y', $ts);
}

function format_datetime(?string $date): string
{
    $ts = $date ? strtotime($date) : false;
    return $ts === false ? '—' : date('d/m/Y \à H:i', $ts);
}

function priority_label(string $priority): string
{
    return ['high' => 'Haute', 'none' => 'Aucune', 'low' => 'Basse'][$priority] ?? $priority;
}

/** Construit une URL en ignorant les paramètres vides. */
function url(string $path, array $query = []): string
{
    $query = array_filter($query, static fn (mixed $v): bool => $v !== null && $v !== '' && $v !== []);
    return $path . ($query === [] ? '' : '?' . http_build_query($query));
}

/** URL d'une photo (ou de sa miniature) servie par MediaController. */
function media_url(string $name, bool $thumb = false): string
{
    return '/media/' . ($thumb ? (string) preg_replace('/\.webp$/', '_t.webp', $name) : $name);
}

/** URL d'un fichier de public/assets avec un paramètre de version (cache busting). */
function asset(string $path): string
{
    $file = dirname(__DIR__) . '/public/assets/' . $path;
    return '/assets/' . $path . (is_file($file) ? '?v=' . filemtime($file) : '');
}
