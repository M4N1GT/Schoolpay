<?php

namespace App\Service;

/**
 * Resultat d'une pagination : la tranche demandee et de quoi construire la
 * navigation. Volontairement immuable et sans dependance a Doctrine pour
 * rester utilisable avec une liste calculee en PHP.
 */
final class Pagination
{
    /**
     * @param array<int, mixed> $items
     */
    public function __construct(
        public readonly array $items,
        public readonly int $total,
        public readonly int $page,
        public readonly int $perPage,
    ) {
    }

    public function pages(): int
    {
        return max(1, (int) ceil($this->total / $this->perPage));
    }

    public function hasPrevious(): bool
    {
        return $this->page > 1;
    }

    public function hasNext(): bool
    {
        return $this->page < $this->pages();
    }

    public function firstResult(): int
    {
        return $this->total === 0 ? 0 : ($this->page - 1) * $this->perPage + 1;
    }

    public function lastResult(): int
    {
        return min($this->total, $this->page * $this->perPage);
    }
}
