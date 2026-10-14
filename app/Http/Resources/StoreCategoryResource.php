<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StoreCategoryResource extends JsonResource
{
    /**
     * Public shape: the id is the slug, because the shop filters use it in the URL.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->slug,
            'label' => $this->name,
            'sizeScale' => $this->size_scale,
            'image' => $this->image,
        ];
    }
}