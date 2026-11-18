<?php

namespace App\Services;

use App\Models\Collection;

class CollectionDiscovery
{
    /** @return list<array{id: int, name: string, slug: string, description: ?string, display_order: int, href: string}> */
    public function all(): array
    {
        return Collection::query()
            ->where('is_active', true)
            ->orderBy('display_order')
            ->orderBy('id')
            ->get(['id', 'name', 'slug', 'description', 'display_order'])
            ->map(fn (Collection $collection): array => [
                'id' => $collection->id,
                'name' => $collection->name,
                'slug' => $collection->slug,
                'description' => $collection->description,
                'display_order' => $collection->display_order,
                'href' => "/collections/{$collection->slug}",
            ])
            ->all();
    }
}
