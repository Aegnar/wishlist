<?php
declare(strict_types=1);

namespace App\Schema;

use PDO;

/**
 * Un fichier migrations/NNN_description.php retourne une instance de cette interface.
 * Ne JAMAIS modifier une migration déjà appliquée : en créer une nouvelle.
 */
interface Migration
{
    /** true si la migration peut perdre des données (DROP, changement de type…). */
    public function isDestructive(): bool;

    public function up(PDO $db): void;
}
