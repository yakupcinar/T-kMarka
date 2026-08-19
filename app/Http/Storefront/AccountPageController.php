<?php

namespace App\Http\Storefront;

use App\Domain\Cart\CartService;
use App\Domain\Identity\CustomerAuthService;
use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureSessionTenant;
use App\Http\Storefront\Requests\AddressRequest;
use App\Http\Storefront\Requests\RegisterRequest;
use App\Models\Customer;
use App\Models\Order;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Müşteri hesabı — giriş, kayıt, adres defteri, sipariş takibi. (4.5D)
 *
 * ★ Faz 4'ün en büyük vitrin boşluğuydu: uçları 1A/1C/2G'de vardı ama
 * müşterinin hiçbir ekranı yoktu — siparişini takip edemiyordu.
 *
 * ⚠️ Kimlik OTURUMLA (`customer-web`), token'la değil: vitrin sunucuda
 * render ediliyor ve formlar JavaScript'siz çalışıyor (4B-K1). `customer`
 * (sanctum) guard'ı DURUYOR — mobil ve entegrasyonlar onu kullanacak.
 */
class AccountPageController extends Controller
{
    public function __construct(
        private readonly CustomerAuthService $musteriler,
        private readonly CartService $sepetler,
    ) {}

    public function girisFormu(): View|RedirectResponse
    {
        return Auth::guard('customer-web')->check()
            ? redirect()->route('vitrin.hesap')
            : view('storefront.hesap-giris');
    }

    public function giris(Request $istek): RedirectResponse
    {
        $veri = $istek->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        try {
            $sonuc = $this->musteriler->girisYap((string) $veri['email'], (string) $veri['password']);
        } catch (AuthenticationException|ValidationException) {
            /*
            | ⚠️ TEK MESAJ: "e-posta yok" ile "parola yanlış" ayrılsaydı
            | saldırgan hangi adreslerin kayıtlı olduğunu öğrenirdi.
            */
            return back()->withInput($istek->only('email'))
                ->with('hata', 'E-posta veya parola hatalı.');
        }

        return $this->oturumAc($istek, $sonuc['customer']);
    }

    public function kayitFormu(): View|RedirectResponse
    {
        return Auth::guard('customer-web')->check()
            ? redirect()->route('vitrin.hesap')
            : view('storefront.hesap-kayit');
    }

    public function kayit(RegisterRequest $istek): RedirectResponse
    {
        $sonuc = $this->musteriler->kaydet($istek->validated());

        return $this->oturumAc($istek, $sonuc['customer']);
    }

    public function cikis(Request $istek): RedirectResponse
    {
        Auth::guard('customer-web')->logout();

        $istek->session()->invalidate();
        $istek->session()->regenerateToken();

        return redirect()->route('vitrin.anasayfa')->with('mesaj', 'Çıkış yapıldı.');
    }

    /** Hesap özeti — siparişler ve adresler buradan geziliyor. */
    public function hesap(Request $istek): View
    {
        $musteri = $this->musteri($istek);

        return view('storefront.hesap', [
            'musteri' => $musteri,
            'siparisler' => $this->siparisleri($musteri),
        ]);
    }

    public function siparis(Request $istek, Order $siparis): View
    {
        $musteri = $this->musteri($istek);

        /*
        | ⚠️ 1A.5 deseni: 404, 403 DEĞİL. "Böyle bir sipariş var ama senin
        | değil" bilgisi de sızıntıdır.
        */
        abort_unless($siparis->customer_id === $musteri->id, 404);

        $siparis->load(['items', 'fulfillments.items', 'legalVersion']);

        return view('storefront.hesap-siparis', ['siparis' => $siparis]);
    }

    public function adresler(Request $istek): View
    {
        $musteri = $this->musteri($istek);

        return view('storefront.hesap-adresler', [
            'adresler' => $musteri->addresses()->orderByDesc('id')->get(),
        ]);
    }

    public function adresEkle(AddressRequest $istek): RedirectResponse
    {
        $musteri = $this->musteri($istek);

        $musteri->addresses()->create($istek->validated());

        return back()->with('mesaj', 'Adres eklendi.');
    }

    public function adresSil(Request $istek, string $adres): RedirectResponse
    {
        $musteri = $this->musteri($istek);

        /*
        | ⚠️ Adres MÜŞTERİYE DARALTILMIŞ sorgudan çözülüyor (1A.5):
        | başkasının adresi sonuç kümesine hiç girmiyor → 404.
        */
        $kayit = $musteri->addresses()->where('uuid', $adres)->firstOrFail();

        $kayit->delete();

        return back()->with('mesaj', 'Adres silindi.');
    }

    /**
     * Girişten sonra oturumu açar.
     *
     * ⚠️ Üç şey BİRLİKTE yapılıyor ve sırası önemli.
     */
    private function oturumAc(Request $istek, Customer $musteri): RedirectResponse
    {
        /*
        | 1 — MİSAFİR SEPETİNİ TAŞI (1C-K5). Oturum açılmadan ÖNCE:
        | birleştirme misafir token'ını okuyor ve oturum açıldıktan sonra
        | istek artık müşteri sepetini çözerdi.
        */
        $misafirSepeti = $this->sepetler->misafirSepetiBul(CartToken::oku($istek));

        if ($misafirSepeti !== null) {
            $this->sepetler->birlestir($misafirSepeti, $musteri);
        }

        Auth::guard('customer-web')->login($musteri);

        /*
        | 2 — OTURUM KİMLİĞİ TAZELENİYOR: oturum sabitleme saldırısına
        | karşı (giriş öncesi çerezi bilen biri sonrasında da bilemesin).
        */
        $istek->session()->regenerate();

        /*
        | 3 — MARKA DAMGASI (4H): oturum yalnızca kullanıcı `id`'sini
        | tutuyor ve guard onu İSTEĞİN kiracısından çözüyor. Damgasız
        | oturum başka markanın vitrininde geçerli sayılırdı.
        */
        $istek->session()->put(EnsureSessionTenant::ANAHTAR, (string) tenant('id'));

        return redirect()->intended(route('vitrin.hesap'))->with('mesaj', 'Hoş geldiniz.');
    }

    /**
     * Siparişleri — YENİDEN ESKİYE.
     *
     * ⚠️ Müşteri sipariş listesi ucu HİÇ YOKTU (ölçüldü): API tarafında
     * yalnızca tek siparişin iadesi ve ödemesi vardı. Yani müşteri
     * siparişini hiçbir yerden göremiyordu.
     *
     * @return Collection<int, Order>
     */
    private function siparisleri(Customer $musteri)
    {
        return Order::query()
            ->where('customer_id', $musteri->id)
            ->with('items')
            ->orderByDesc('placed_at')
            ->limit(50)
            ->get();
    }

    private function musteri(Request $istek): Customer
    {
        $musteri = $istek->user('customer-web');

        abort_unless($musteri instanceof Customer, 403);

        return $musteri;
    }
}
