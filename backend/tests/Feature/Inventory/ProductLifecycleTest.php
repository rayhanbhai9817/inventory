<?php

namespace Tests\Feature\Inventory;

use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private function registerOwner(): string
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'business_name' => 'Acme',
            'name' => 'Owner',
            'email' => 'owner@acme.test',
            'password' => 'correct-horse-battery-staple',
            'password_confirmation' => 'correct-horse-battery-staple',
        ])->assertCreated();

        return $response->json('token');
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
    }

    public function test_product_moves_through_active_archived_trashed_and_back(): void
    {
        $token = $this->registerOwner();
        $api = $this->asBearerToken($token);

        $product = $api->postJson('/api/v1/products', ['name' => 'Widget', 'sku' => 'W-1'])->assertCreated();
        $id = $product->json('data.id');

        $api->getJson('/api/v1/products?tab=active')->assertOk()->assertJsonCount(1, 'data');

        // Archive.
        $api->postJson("/api/v1/products/{$id}/archive")->assertOk();
        $api->getJson('/api/v1/products?tab=active')->assertOk()->assertJsonCount(0, 'data');
        $api->getJson('/api/v1/products?tab=archived')->assertOk()->assertJsonCount(1, 'data');

        // Restore from archive.
        $api->postJson("/api/v1/products/{$id}/restore")->assertOk();
        $api->getJson('/api/v1/products?tab=active')->assertOk()->assertJsonCount(1, 'data');

        // Trash.
        $api->deleteJson("/api/v1/products/{$id}")->assertOk();
        $api->getJson('/api/v1/products?tab=active')->assertOk()->assertJsonCount(0, 'data');
        $api->getJson('/api/v1/products?tab=trashed')->assertOk()->assertJsonCount(1, 'data');

        // Restore from trash.
        $api->postJson("/api/v1/products/trashed/{$id}/restore")->assertOk();
        $api->getJson('/api/v1/products?tab=active')->assertOk()->assertJsonCount(1, 'data');
        $api->getJson('/api/v1/products?tab=trashed')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_category_cannot_be_deleted_while_products_are_assigned(): void
    {
        $token = $this->registerOwner();
        $api = $this->asBearerToken($token);

        $category = $api->postJson('/api/v1/categories', ['name' => 'Dewormer'])->assertCreated();
        $categoryId = $category->json('data.id') ?? $category->json('id');

        $api->postJson('/api/v1/products', [
            'name' => 'Widget', 'sku' => 'W-1', 'category_id' => $categoryId,
        ])->assertCreated();

        $api->deleteJson("/api/v1/categories/{$categoryId}")->assertStatus(409);
    }
}
