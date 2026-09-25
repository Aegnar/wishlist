<?php
declare(strict_types=1);

/** Échappe une valeur pour une sortie HTML (texte ou attribut). */
function e(mixed $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
