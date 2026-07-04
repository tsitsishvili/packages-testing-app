<?php

namespace App\Repositories;

use App\Models\Product;
use Illuminate\Support\Collection;

class ProductRepository
{
    /**
     * Fetch the given products keyed by id, for line-item pricing.
     *
     * @param  array<int, int>  $ids
     * @return Collection<int, Product>
     */
    public function findManyByIds(array $ids): Collection
    {
        return Product::query()
            ->whereIn('id', array_unique($ids))
            ->get()
            ->keyBy('id');
    }
}
