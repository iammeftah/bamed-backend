<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\StoreCategoryResource;
use App\Models\Category;

class CategoryController extends Controller
{
    /** Public: GET /categories */
    public function index()
    {
        return StoreCategoryResource::collection(Category::orderBy('id')->get());
    }

    /** Admin: GET /admin/categories */
    public function adminIndex()
    {
        return CategoryResource::collection(Category::orderBy('id')->get());
    }

    public function store(CategoryRequest $request)
    {
        $category = Category::create($request->toModelData());

        return (new CategoryResource($category))->response()->setStatusCode(201);
    }

    public function update(CategoryRequest $request, Category $category)
    {
        $category->update($request->toModelData());

        return new CategoryResource($category);
    }

    public function destroy(Category $category)
    {
        $used = $category->productsCount();

        if ($used > 0) {
            $s = $used > 1 ? 's' : '';
            $nt = $used > 1 ? 'nt' : '';

            return response()->json([
                'message' => "Impossible : {$used} produit{$s} utilise{$nt} cette catégorie.",
            ], 422);
        }

        $category->delete();

        return response()->noContent();
    }
}