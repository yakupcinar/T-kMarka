<?php

namespace App\Http\Panel;

use App\Domain\Catalog\OptionService;
use App\Http\Controllers\Controller;
use App\Http\Panel\Requests\OptionRequest;
use App\Http\Panel\Requests\OptionValueRequest;
use App\Models\Option;
use App\Models\OptionValue;
use Illuminate\Http\JsonResponse;

/**
 * Varyant eksenleri — panel ucu. `izin:product.write` arkasında.
 *
 * Eksenler MAĞAZA seviyesinde (1B-K3): "Renk" bir kez tanımlanır, bütün
 * ürünler aynı listeden seçer.
 *
 * ⚠️ İş kuralları [App\Domain\Catalog\OptionService]'te; bu sınıf yalnızca
 * HTTP'ye çeviriyor.
 */
class OptionController extends Controller
{
    public function __construct(private readonly OptionService $eksenler) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'options' => $this->eksenler->listele()->map(fn (Option $o) => $this->goster($o)),
        ]);
    }

    public function store(OptionRequest $istek): JsonResponse
    {
        $option = $this->eksenler->olustur(
            (string) $istek->validated('name'),
            (int) ($istek->validated('position') ?? 0),
        );

        return response()->json(['option' => $this->goster($option)], 201);
    }

    public function update(OptionRequest $istek, Option $option): JsonResponse
    {
        $option = $this->eksenler->guncelle(
            $option,
            (string) $istek->validated('name'),
            (int) ($istek->validated('position') ?? $option->position),
        );

        return response()->json(['option' => $this->goster($option)]);
    }

    public function destroy(Option $option): JsonResponse
    {
        $this->eksenler->sil($option);

        return response()->json(['message' => 'Eksen silindi.']);
    }

    public function storeValue(OptionValueRequest $istek, Option $option): JsonResponse
    {
        $deger = $this->eksenler->degerEkle(
            $option,
            (string) $istek->validated('value'),
            $istek->validated('swatch'),
            (int) ($istek->validated('position') ?? 0),
        );

        return response()->json(['value' => $this->degerGoster($deger)], 201);
    }

    /**
     * ⚠️ Değer, EKSENE DARALTILMIŞ sorgudan çözülüyor — 1A.5'in deseni.
     * İki ayrı örtük bağlama kullanılsaydı `/options/renk/values/{beden-degeri}`
     * gibi tutarsız bir adres 200 dönerdi.
     */
    public function updateValue(OptionValueRequest $istek, Option $option, string $deger): JsonResponse
    {
        $kayit = $this->degeriBul($option, $deger);

        $kayit = $this->eksenler->degerGuncelle(
            $kayit,
            (string) $istek->validated('value'),
            $istek->validated('swatch'),
            (int) ($istek->validated('position') ?? $kayit->position),
        );

        return response()->json(['value' => $this->degerGoster($kayit)]);
    }

    public function destroyValue(Option $option, string $deger): JsonResponse
    {
        $this->eksenler->degerSil($this->degeriBul($option, $deger));

        return response()->json(['message' => 'Değer silindi.']);
    }

    private function degeriBul(Option $option, string $uuid): OptionValue
    {
        /** @var OptionValue $deger */
        $deger = $option->values()->where('uuid', $uuid)->firstOrFail();

        return $deger;
    }

    /** @return array<string, mixed> */
    private function goster(Option $option): array
    {
        return [
            'uuid' => $option->uuid,
            'name' => $option->name,
            'slug' => $option->slug,
            'position' => $option->position,
            'values' => $option->values->map(fn (OptionValue $d) => $this->degerGoster($d)),
        ];
    }

    /** @return array<string, mixed> */
    private function degerGoster(OptionValue $deger): array
    {
        return [
            'uuid' => $deger->uuid,
            'value' => $deger->value,
            'slug' => $deger->slug,
            'swatch' => $deger->swatch,
            'position' => $deger->position,
        ];
    }
}
