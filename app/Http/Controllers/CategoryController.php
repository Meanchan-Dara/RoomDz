<?php

namespace App\Http\Controllers;

use App\Http\Resources\CategoryResource;
use App\Models\Category;
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    /**
     * Upload an image file to Cloudinary, with fallback to public disk URL.
     *
     * @param UploadedFile $file
     * @param string $folder
     * @return string
     */
    private function uploadImageFile(UploadedFile $file, string $folder = 'categories'): string
    {
        try {
            if (env('CLOUDINARY_URL')) {
                return Cloudinary::upload($file->getRealPath(), [
                    'folder' => $folder,
                ])->getSecurePath();
            }
        } catch (\Throwable $e) {
            Log::warning('Cloudinary upload error: ' . $e->getMessage());
        }

        $path = $file->store($folder, 'public');
        return url('storage/' . $path);
    }

    /**
     * Display a listing of categories.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Category::withCount('rooms');

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where('name', 'ilike', "%{$search}%");
        }

        $categories = $query->orderBy('name', 'asc')->get();

        return CategoryResource::collection($categories);
    }

    /**
     * Store a newly created category.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:categories,slug',
            'image' => 'nullable',
            'description' => 'nullable|string',
        ]);

        $baseSlug = !empty($validated['slug']) ? Str::slug($validated['slug']) : Str::slug($validated['name']);
        $slug = $baseSlug;
        $counter = 1;
        while (Category::where('slug', $slug)->exists()) {
            $slug = "{$baseSlug}-{$counter}";
            $counter++;
        }
        $validated['slug'] = $slug;

        $imageUrl = null;
        if ($request->hasFile('image')) {
            $imageUrl = $this->uploadImageFile($request->file('image'), 'categories');
        } elseif (!empty($validated['image']) && is_string($validated['image'])) {
            $imageUrl = $validated['image'];
        }

        $category = Category::create([
            'name' => $validated['name'],
            'slug' => $validated['slug'],
            'image' => $imageUrl,
            'description' => $validated['description'] ?? null,
        ]);

        return (new CategoryResource($category))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Display the specified category.
     */
    public function show(string $id): CategoryResource
    {
        $category = Category::withCount('rooms')
            ->with('rooms')
            ->where('id', $id)
            ->orWhere('slug', $id)
            ->firstOrFail();

        return new CategoryResource($category);
    }

    /**
     * Update the specified category.
     */
    public function update(Request $request, string $id): CategoryResource
    {
        $category = Category::where('id', $id)
            ->orWhere('slug', $id)
            ->firstOrFail();

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:categories,slug,' . $category->id,
            'image' => 'nullable',
            'description' => 'nullable|string',
        ]);

        if (isset($validated['name']) && empty($validated['slug'])) {
            $validated['slug'] = Str::slug($validated['name']);
        }

        $imageUrl = $category->image;
        if ($request->hasFile('image')) {
            $imageUrl = $this->uploadImageFile($request->file('image'), 'categories');
        } elseif (array_key_exists('image', $validated) && is_string($validated['image'])) {
            $imageUrl = $validated['image'];
        }

        $category->update(array_filter([
            'name' => $validated['name'] ?? null,
            'slug' => $validated['slug'] ?? null,
            'image' => $imageUrl,
            'description' => $validated['description'] ?? null,
        ], fn ($val) => !is_null($val)));

        return new CategoryResource($category);
    }

    /**
     * Remove the specified category.
     */
    public function destroy(string $id): JsonResponse
    {
        $category = Category::where('id', $id)
            ->orWhere('slug', $id)
            ->firstOrFail();

        $category->delete();

        return response()->json([
            'message' => 'Category deleted successfully',
        ]);
    }
}
