<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuditLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $module = $this->auditable_type ? class_basename($this->auditable_type) : null;

        return [
            'id' => $this->id,
            'action' => $this->action,
            'module' => $module,
            'reference' => $module && $this->auditable_id ? "{$module} #{$this->auditable_id}" : null,
            'description' => $this->describe(),
            'old_values' => $this->old_values,
            'new_values' => $this->new_values,
            'user' => $this->whenLoaded('user', fn () => $this->user?->name),
            'ip_address' => $this->ip_address,
            'created_at' => $this->created_at,
        ];
    }

    private function describe(): string
    {
        $actor = $this->user?->name ?? 'System';
        $action = str_replace('_', ' ', $this->action);

        return "{$actor} — {$action}";
    }
}
