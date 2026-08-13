<?php

namespace App\Domain\Privacy;

use App\Domain\Identity\EmailNormalizer;
use App\Enums\DataRequestStatus;
use App\Enums\DataRequestType;
use App\Mail\PrivacyVerificationMail;
use App\Models\Customer;
use App\Models\DataRequest;
use App\Models\Order;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * Veri talebi akışı: iste → doğrula → uygula. (2G)
 *
 * ★ İKİ AŞAMA, TEK SEBEP: doğrulanmamış talep işlenmiyor.
 *
 * ⚠️ Tek aşamalı olsaydı, sipariş numarası tahmin eden biri başkasının
 * verisini sildirebilirdi — numaralar ardışık (1D-K4) ve silme GERİ
 * ALINAMAZ.
 */
class DataRequestService
{
    /** Doğrulama bağlantısının ömrü. */
    public const GECERLILIK_SAAT = 24;

    public function __construct(
        private readonly Anonymizer $anonimlestirici,
        private readonly DataExporter $dokumcu,
    ) {}

    /**
     * Talep açar ve doğrulama postası yollar.
     *
     * ⚠️ Kayıtlı müşteri varsa ona bağlanıyor; yoksa yalnızca e-posta
     * üzerinden ilerliyor (misafir siparişi — M-1).
     *
     * @throws UnknownDataSubjectException
     */
    public function talepAc(DataRequestType $tur, string $eposta, ?string $siparisNumarasi, string $donusAdresi): DataRequest
    {
        /*
        | ⚠️ E-posta NORMALLEŞTİRİLİYOR (1A.2'nin dersi): Türkçe `İ`
        | `mb_strtolower` ile birleşik noktalı bir harfe dönüşüyor ve
        | PostgreSQL'in `lower()`'ıyla tutmuyor. Aynı kişi iki farklı
        | kayıt gibi görünürdü.
        */
        $eposta = (string) EmailNormalizer::normallestir($eposta);

        $musteri = Customer::where('email', $eposta)->first();

        /*
        | ★ KİMLİK KANITI: ya kayıtlı müşteri, ya da e-posta + sipariş
        | numarası eşleşmesi.
        |
        | ⚠️ Yalnızca e-posta yeterli sayılsaydı, herhangi biri bir adres
        | yazıp o adrese doğrulama postası gönderttirebilirdi. Postanın
        | kendisi zaten koruma; ama boş talep üretmenin de anlamı yok.
        */
        if ($musteri === null) {
            $siparis = $siparisNumarasi === null
                ? null
                : Order::where('order_number', $siparisNumarasi)->where('email', $eposta)->first();

            if ($siparis === null) {
                throw new UnknownDataSubjectException;
            }
        }

        $talep = new DataRequest;
        $talep->type = $tur;
        $talep->status = DataRequestStatus::Pending;

        // ⚠️ GEÇİCİ: tamamlanınca temizleniyor (2G-K4).
        $talep->email = $eposta;

        /*
        | ⚠️ KALICI ama geri çevrilemez iz. "Bu adres için talep var mıydı"
        | sorusu cevaplanabilsin diye; adresin kendisi okunamıyor.
        */
        $talep->email_hash = hash('sha256', $eposta);

        $talep->customer()->associate($musteri);
        $talep->token = Str::random(64);
        $talep->expires_at = now()->addHours(self::GECERLILIK_SAAT);
        $talep->save();

        $this->postaGonder($talep, $eposta, rtrim($donusAdresi, '/').'/'.$talep->token);

        return $talep;
    }

    /**
     * Talebi doğrular ve UYGULAR.
     *
     * @return array<string, mixed>|null dışa aktarmada döküm, silmede null
     *
     * @throws InvalidDataRequestException
     */
    public function dogrulaVeUygula(string $token): ?array
    {
        $talep = DataRequest::where('token', $token)->first();

        /*
        | ⚠️ Tamamlanmış talep TEKRAR İŞLENMİYOR. İşlenseydi silme ikinci
        | kez koşar; ikinci koşum zararsız görünür ama dışa aktarmada
        | bağlantıyı ele geçiren biri veriyi tekrar tekrar indirebilirdi.
        */
        if ($talep === null || $talep->status !== DataRequestStatus::Pending) {
            throw new InvalidDataRequestException;
        }

        if ($talep->suresiDoldu()) {
            $talep->status = DataRequestStatus::Expired;
            $talep->email = null;
            $talep->save();

            throw new InvalidDataRequestException;
        }

        $talep->verified_at = now();

        $dokum = $talep->type === DataRequestType::Export
            ? $this->dokumUret($talep)
            : $this->anonimlestir($talep);

        $talep->status = DataRequestStatus::Completed;
        $talep->completed_at = now();

        /*
        | ★ 2G-K4 — E-POSTA BURADA SİLİNİYOR.
        |
        | ⚠️ Kalsaydı silme kaydı, silinen e-postanın kopyasını saklardı.
        | Denetim izi `email_hash` ile duruyor.
        |
        | ⚠️ `customer_id` de kopuyor: anonimleştirilen müşteriye bağlı
        | kalsaydı talep kaydı "bu kişi sildirdi" bilgisini taşırdı.
        */
        $talep->email = null;
        $talep->customer_id = null;
        $talep->save();

        return $dokum;
    }

    /** @return array<string, mixed> */
    private function dokumUret(DataRequest $talep): array
    {
        $musteri = $talep->customer;

        return $musteri === null
            ? ['siparisler' => [], 'not' => 'Kayıtlı hesap bulunamadı.']
            : $this->dokumcu->musteriDokumü($musteri);
    }

    private function anonimlestir(DataRequest $talep): null
    {
        $musteri = $talep->customer;

        if ($musteri !== null) {
            $this->anonimlestirici->musteriyiAnonimlestir($musteri);

            return null;
        }

        /*
        | Misafir: kayıtlı hesap yok, kişisel veri yalnızca siparişlerin
        | kendi kopyalarında.
        |
        | ⚠️ Sorgu `email_hash` ile değil E-POSTA ile yapılıyor — hash tek
        | yönlü, siparişte karşılığı yok. Bu yüzden e-posta talep
        | tamamlanana kadar saklanıyor.
        */
        $eposta = $talep->email;

        if ($eposta !== null) {
            Order::where('email', $eposta)->get()
                ->each(fn (Order $s) => $this->anonimlestirici->siparisiAnonimlestir($s));
        }

        return null;
    }

    /**
     * ⚠️ Posta düşerse talep AÇILMIŞ kalıyor ama doğrulanamıyor — yani
     * hiçbir şey silinmiyor. 2H-K2'nin aksine burada yutmak tehlikeli
     * değil: en kötü ihtimalle kullanıcı tekrar talep açar.
     */
    private function postaGonder(DataRequest $talep, string $eposta, string $adres): void
    {
        try {
            Mail::to($eposta)->queue(new PrivacyVerificationMail($talep, $adres));
        } catch (Throwable $e) {
            report($e);
        }
    }
}
