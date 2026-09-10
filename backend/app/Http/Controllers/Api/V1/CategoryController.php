<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\CategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function index(Request $request)
    {
        $categories = Category::query()
            ->withCount('products')
            ->when($request->string('search')->toString(), fn ($q, $search) => $q->where('name', 'like', "%{$search}%"))
            ->when($request->string('status')->toString(), fn ($q, $status) => $q->where('status', $status))
            ->orderBy('name')
            ->paginate($request->integer('per_page', 15));

        return CategoryResource::collection($categories);
    }

    public function store(CategoryRequest $request)
    {
        $category = Category::create($request->validated());
        $this->auditLogger->log('category_created', $category, null, $category->only(['name']), $request->user());

        return new CategoryResource($category->loadCount('products'));
    }

    public function show(Category $category)
    {
        return new CategoryResource($category->loadCount('products'));
    }

    public function update(CategoryRequest $request, Category $category)
    {
        $old = $category->only(['name', 'status']);
        $category->update($request->validated());
        $this->auditLogger->log('category_updated', $category, $old, $category->only(['name', 'status']), $request->user());

        return new CategoryResource($category->loadCount('products'));
    }

    public function destroy(Category $category, Request $request): JsonResponse
    {
        if ($category->products()->withTrashed()->exists()) {
            return response()->json([
                'message' => 'Cannot delete a category that still has products assigned to it. Reassign or remove those products first.',
            ], 409);
        }

        $category->delete();
        $this->auditLogger->log('category_deleted', $category, null, null, $request->user());

        return response()->json(['message' => 'Category deleted.']);
    }
}
