<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

#[Fillable([
    'name', 'slug', 'email', 'phone', 'address', 'city', 'state', 'country',
    'postal_code', 'currency_code', 'timezone', 'logo_path', 'status',
])]
class Business extends Model
{
    use HasFactory, SoftDeletes;

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * Generates a URL-safe slug guaranteed unique among businesses
     * (including soft-deleted ones), appending "-1", "-2", ... on
     * collision. Shared by the public registration endpoint and the
     * `app:create-admin` console command.
     */
    public static function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'business';
        $slug = $base;
        $suffix = 1;

        while (static::withTrashed()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
