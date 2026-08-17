<?php

namespace App\Domain\Quota;

/**
 * Plan sınırlarının kapısı. (3F)
 *
 * ★ ARAYÜZ BURADA, UYGULAMA `app/Platform/`'da — bilinçli.
 *
 * Kota markanın PLANINA bakıyor, plan ise MERKEZ kayıtta. Ama `app/Domain/`
 * kiracıdan habersiz olmak zorunda (M-2.7) ve bu ÖLÇÜLÜYOR — kiracılık
 * yardımcılarının bu klasörde geçiş sayısı SIFIR.
 *
 * ⚠️ Yardımcıların adları bu dosyada bilerek YAZILMIYOR: ölçüm basit bir
 * metin taraması ve yorumları da sayıyor. Kendi belgemiz ölçümü kirletirse
 * bir sonraki koşu yanlış alarm verir.
 *
 * Bağımlılık ters çevriliyor: iş mantığı "kotam var mı" diye soruyor,
 * hangi markada olduğunu ve planın nereden geldiğini bilmiyor.
 *
 * ⚠️ Plan doğrudan kiracı üzerinden okunsaydı bu dosya M-2.7'yi ihlal eder
 * ve `app/Domain/` bir daha kiracılıktan bağımsız test edilemezdi.
 */
interface QuotaGuard
{
    /**
     * Yeni ürün eklenebilir mi?
     *
     * @throws QuotaExceededException
     */
    public function urunEklenebilirMi(int $mevcutAdet): void;

    /**
     * Yeni personel eklenebilir mi?
     *
     * @throws QuotaExceededException
     */
    public function personelEklenebilirMi(int $mevcutAdet): void;

    /**
     * Bu özellik markanın planında açık mı?
     *
     * ⚠️ Özellik KAPALIYSA var olan veri SİLİNMİYOR — yalnızca yeni işlem
     * engelleniyor. Plan düşüren marka verisini kaybetmemeli.
     */
    public function ozellikAcikMi(string $ozellik): bool;

    /**
     * @throws QuotaExceededException
     */
    public function ozelligiDogrula(string $ozellik): void;
}
