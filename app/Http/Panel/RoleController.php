<?php

namespace App\Http\Panel;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Panel\Requests\RoleRequest;
use App\Models\Role;
use Illuminate\Http\JsonResponse;

/**
 * Rol yönetimi — `sahip` middleware'i arkasında (izin DEĞİL, bkz.
 * [App\Http\Middleware\RequireOwner]).
 *
 * ⚠️ Neden marka kendi rolünü kurabiliyor:
 *
 * Katı rol listesi güvenlik üretmez, AŞIRI YETKİ üretir. Markanın
 * muhasebecisi yalnızca ciro raporu görecekse ve "sadece finans" diye bir
 * rol yoksa, marka Yönetici rolünü verir — muhasebeci ödeme sağlayıcı
 * anahtarlarını da görmeye başlar. Esneklik olmayınca kimse "yapamıyorum"
 * demiyor, herkes en büyük rolü dağıtıyor.
 *
 * Sınırlar: izinler sabit enum'dan seçiliyor · sistem rolleri silinemiyor ·
 * kapı `is_owner`.
 */
class RoleController extends Controller
{
    /** Roller, izinleri ve kaç personelde kullanıldığı. */
    public function index(): JsonResponse
    {
        $roller = Role::withCount('users')->orderBy('name')->get()
            ->map(fn (Role $rol) => [
                'id' => $rol->id,
                'name' => $rol->name,
                'is_system' => $rol->is_system,
                'permissions' => $rol->permissions(),

                // Sahip, silmeden önce kaç kişiyi taşıması gerektiğini
                // görsün diye.
                'staff_count' => $rol->users_count,
            ]);

        return response()->json([
            'roles' => $roller,

            // Panelin izin listesini kodla senkron tutmasına gerek kalmasın:
            // seçenekleri sunucudan alıyor.
            'available_permissions' => collect(Permission::cases())
                ->map(fn (Permission $izin) => ['value' => $izin->value, 'label' => $izin->etiket()]),
        ]);
    }

    public function store(RoleRequest $istek): JsonResponse
    {
        /** @var array{name: string, permissions: list<string>} $veri */
        $veri = $istek->validated();

        // `is_system` $fillable dışında — istekle "silinemez rol" üretilemesin.
        $rol = Role::create(['name' => $veri['name']]);

        /*
        | ⚠️ `refresh()` şart: `is_system` değeri VERİTABANI VARSAYILANINDAN
        | geliyor (`default(false)`), gönderilmediği için bellekteki nesnede
        | hiç yok — cevapta `null` dönerdi.
        |
        | 1A.2'de `accepts_marketing` ile aynı tuzağa düşmüştük: "kolonun
        | varsayılanı var" ile "modelin değeri var" ayrı şeyler.
        */
        $rol->refresh();

        $rol->syncPermissions($veri['permissions']);

        return response()->json(['role' => $this->goster($rol)], 201);
    }

    /**
     * Ad ve izinler güncellenir — SİSTEM ROLLERİ DAHİL.
     *
     * ⚠️ Sistem rolleri dondurulmuyor, yalnızca silinemiyor. "Yönetici'den
     * finans iznini alayım" meşru bir istek; yasaklasaydık marka rolü
     * kopyalayıp aynısını kurar, sonuç değişmez ama iki karışık rol olurdu.
     */
    public function update(RoleRequest $istek, Role $rol): JsonResponse
    {
        /** @var array{name: string, permissions: list<string>} $veri */
        $veri = $istek->validated();

        $rol->update(['name' => $veri['name']]);
        $rol->syncPermissions($veri['permissions']);

        return response()->json(['role' => $this->goster($rol)]);
    }

    public function destroy(Role $rol): JsonResponse
    {
        /*
        | ⚠️ Sistem rolü silinemez.
        |
        | Silinebilseydi marka bütün rollerini silip kimsenin hiçbir şey
        | yapamadığı bir panele düşebilirdi. (Sahip `is_owner` muafiyetiyle
        | kapıda kalır ama personelin tamamı dışarıda kalırdı.)
        */
        if ($rol->is_system) {
            return response()->json([
                'message' => 'Sistem rolleri silinemez.',
                'resolution' => 'İzinlerini düzenleyebilirsiniz.',
            ], 409);
        }

        /*
        | ⚠️ Üzerinde personel olan rol silinemez.
        |
        | Sessizce çözülseydi personel bir sabah yetkisiz uyanır ve kimse
        | sebebini bilmezdi. Sahip önce personeli taşımak zorunda — bilinçli
        | bir hamle gerekiyor.
        */
        $personelSayisi = $rol->users()->count();

        if ($personelSayisi > 0) {
            return response()->json([
                'message' => "Bu rol {$personelSayisi} personelde kullanılıyor.",
                'staff_count' => $personelSayisi,
                'resolution' => 'Önce personeli başka bir role taşıyın.',
            ], 409);
        }

        $rol->syncPermissions([]);
        $rol->delete();

        return response()->json(['message' => 'Rol silindi.']);
    }

    /** @return array<string, mixed> */
    private function goster(Role $rol): array
    {
        return [
            'id' => $rol->id,
            'name' => $rol->name,
            'is_system' => $rol->is_system,
            'permissions' => $rol->permissions(),
        ];
    }
}
