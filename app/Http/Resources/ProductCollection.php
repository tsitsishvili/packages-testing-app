<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class ProductCollection extends ResourceCollection
{
    /**
     * @var class-string
     */
    public $collects = ProductResource::class;

    /**
     * Drop the pagination link blocks, keeping only data + meta counts.
     *
     * @param  array<string, mixed>  $paginated
     * @param  array<string, mixed>  $default
     * @return array<string, mixed>
     */
    public function paginationInformation(Request $request, array $paginated, array $default): array
    {
        unset($default['links'], $default['meta']['links'], $default['meta']['path']);

        return $default;
    }
}
