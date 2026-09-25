<?php
declare(strict_types=1);

namespace App;

final class ItemFilter
{
    public const SORTS_TODO = [
        'priority' => 'Priorité',
        'created_desc' => 'Ajout le plus récent',
        'created_asc' => 'Ajout le plus ancien',
        'price_asc' => 'Prix croissant',
        'price_desc' => 'Prix décroissant',
    ];

    public const SORTS_PURCHASED = [
        'purchased_desc' => 'Achat le plus récent',
        'purchased_asc' => 'Achat le plus ancien',
        'price_asc' => 'Prix croissant',
        'price_desc' => 'Prix décroissant',
    ];

    /** @param list<string> $tags */
    public function __construct(
        public readonly bool $purchased,
        public readonly string $q = '',
        public readonly array $tags = [],
        public readonly ?string $priority = null,
        public readonly string $sort = '',
    ) {
    }

    public static function fromQuery(array $query, bool $purchased): self
    {
        $q = is_string($query['q'] ?? null) ? mb_substr(trim($query['q']), 0, 100) : '';
        $rawTags = $query['tag'] ?? [];
        $tags = Validator::tagList(is_string($rawTags) ? [$rawTags] : (is_array($rawTags) ? $rawTags : []));
        $priority = in_array($query['prio'] ?? null, Validator::PRIORITIES, true) ? $query['prio'] : null;
        $sorts = $purchased ? self::SORTS_PURCHASED : self::SORTS_TODO;
        $sort = is_string($query['sort'] ?? null) && isset($sorts[$query['sort']])
            ? $query['sort']
            : (string) array_key_first($sorts);
        return new self($purchased, $q, $tags, $priority, $sort);
    }

    /** @return array<string, string> */
    public function sorts(): array
    {
        return $this->purchased ? self::SORTS_PURCHASED : self::SORTS_TODO;
    }

    public function isActive(): bool
    {
        return $this->q !== '' || $this->tags !== [] || $this->priority !== null;
    }

    /** Paramètres d'URL (le tri par défaut est omis). */
    public function toQuery(): array
    {
        $query = [];
        if ($this->q !== '') {
            $query['q'] = $this->q;
        }
        if ($this->tags !== []) {
            $query['tag'] = $this->tags;
        }
        if ($this->priority !== null) {
            $query['prio'] = $this->priority;
        }
        if ($this->sort !== array_key_first($this->sorts())) {
            $query['sort'] = $this->sort;
        }
        return $query;
    }
}
