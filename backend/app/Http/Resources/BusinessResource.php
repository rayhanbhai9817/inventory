<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BusinessResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'email' => $this->email,
            'currency_code' => $this->currency_code,
            'timezone' => $this->timezone,
            'logo_path' => $this->logo_path,
            'status' => $this->status,
        ];
    }
}
