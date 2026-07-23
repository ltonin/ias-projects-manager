<?php

declare(strict_types=1);

namespace App\Models;

final class ProjectParticipantPage
{
    /** @param list<ProjectParticipant> $items */
    public function __construct(
        public readonly array $items,
        public readonly int $total,
        public readonly int $page,
        public readonly int $perPage,
    ) {
    }

    public function pageCount(): int { return max(1, (int) ceil($this->total / $this->perPage)); }
}
