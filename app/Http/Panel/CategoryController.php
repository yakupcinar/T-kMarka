<?php

namespace App\Http\Panel;

use App\Domain\Catalog\CategoryService;
use App\Http\Controllers\Controller;
use App\Http\Panel\Requests\CategoryMoveRequest;
use App\Http\Panel\Requests\CategoryRequest;
use App\Models\Category;
use Illuminate\Http\JsonResponse;

/**
 * Kategori ağacı — panel ucu. `izin:product.write` arkasında.
 *
 * ⚠️ İş kuralları [App\Domain\Catalog\CategoryService]'te: döngü engeli ve
 * alt ağaç bakımı buradan çağrılıyor ama burada YAZILI DEĞİL.
 */
class CategoryController extends Controller
{
    public function __construct(private readonly CategoryService $kategoriler) {}

    /**
     * Düz liste, `path` sırasında — yani ata hep çocuğundan önce.
     *
     * Ağacı panel kuruyor: iç içe JSON döndürseydik derin ağaçlarda cevap
     * şişer ve panel "şu dalı aç" gibi kısmi istek yapamazdı.
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'categories' => $this->kategoriler->listele()->map(fn (Category $k) => $this->goster($k)),
        ]);
    }

    public function store(CategoryRequest $istek): JsonResponse
    {
        $kategori = $this->kategoriler->olustur(
            (string) $istek->validated('name'),
            $this->ustuBul($istek->validated('parent_uuid')),
            (int) ($istek->validated('position') ?? 0),
        );

        return response()->json(['category' => $this->goster($kategori)], 201);
    }

    public function update(CategoryRequest $istek, Category $category): JsonResponse
    {
        $kategori = $this->kategoriler->guncelle(
            $category,
            (string) $istek->validated('name'),
            (int) ($istek->validated('position') ?? $category->position),
        );

        return response()->json(['category' => $this->goster($kategori)]);
    }

    /** Taşıma ayrı uçta — gerekçesi [CategoryMoveRequest]'te. */
    public function move(CategoryMoveRequest $istek, Category $category): JsonResponse
    {
        $kategori = $this->kategoriler->tasi($category, $this->ustuBul($istek->validated('parent_uuid')));

        return response()->json(['category' => $this->goster($kategori)]);
    }

    public function destroy(Category $category): JsonResponse
    {
        $this->kategoriler->sil($category);

        return response()->json(['message' => 'Kategori silindi.']);
    }

    private function ustuBul(mixed $uuid): ?Category
    {
        if (! is_string($uuid) || $uuid === '') {
            return null;
        }

        return Category::where('uuid', $uuid)->firstOrFail();
    }

    /** @return array<string, mixed> */
    private function goster(Category $kategori): array
    {
        return [
            'uuid' => $kategori->uuid,
            'name' => $kategori->name,
            'slug' => $kategori->slug,
            'level' => $kategori->level,
            'position' => $kategori->position,

            // Panel ağacı bunlarla kuruyor; `path` istemciye de lazım
            // çünkü sıralama ve girinti ondan çıkıyor.
            'path' => $kategori->path,
            'parent_uuid' => $kategori->parent?->uuid,
        ];
    }
}
