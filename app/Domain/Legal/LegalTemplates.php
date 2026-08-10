<?php

namespace App\Domain\Legal;

use App\Enums\LegalDocumentType;

/**
 * Yeni marka açılırken taslaklara konan İSKELET metinler.
 *
 * ⚠️ BUNLAR HUKUKİ TAVSİYE DEĞİL. Amaç, metinde hangi bilgilerin geçmesi
 * gerektiğini markaya göstermek — çerçeve. Markanın kendi işine göre
 * düzenlemesi ve hukukçusuna okutması gerekiyor; yayınlama sorumluluğu
 * markada.
 *
 * ⚠️ Neden boş bırakmıyoruz: boş bir metin markaya hiçbir şey anlatmıyor
 * ve "ne yazacağım" sorusunda tıkanıyor. İskelet, atlanmaması gereken
 * başlıkları görünür kılıyor.
 *
 * ⚠️ Neden hazır tam metin de koymuyoruz: marka okumadan yayınlar ve
 * kendi işine uymayan bir taahhüdün altına girer. Yer tutucular bilinçli
 * bir dokunuş gerektiriyor — hiçbiri doldurulmadan metin yayınlanamıyor
 * (bkz. [LegalPlaceholders]).
 */
class LegalTemplates
{
    public static function iskelet(LegalDocumentType $tur): string
    {
        return match ($tur) {
            LegalDocumentType::DistanceSales => <<<'METIN'
                # Mesafeli Satış Sözleşmesi

                ## 1. Taraflar

                SATICI
                Unvan       : {{unvan}}
                Adres       : {{adres}}
                Vergi No    : {{vergi_no}} ({{vergi_dairesi}} V.D.)
                Telefon     : {{telefon}}
                E-posta     : {{eposta}}

                ALICI: Sipariş sırasında bildirilen ad, adres ve iletişim bilgileri.

                ## 2. Sözleşmenin Konusu

                ALICI'nın {{marka_adi}} üzerinden elektronik ortamda siparişini
                verdiği ürünlerin satışı ve teslimi.

                ## 3. Ürün ve Ödeme Bilgileri

                Ürün adı, adedi, satış bedeli, kargo ücreti ve ödeme şekli
                sipariş özetinde yer alır ve bu sözleşmenin ayrılmaz parçasıdır.

                ## 4. Teslimat

                (Teslim süresi, kargo firması ve teslim masraflarının kime ait
                olduğu buraya yazılmalıdır.)

                ## 5. Cayma Hakkı

                ALICI, teslim tarihinden itibaren 14 gün içinde gerekçe
                göstermeksizin cayma hakkına sahiptir.

                (Cayma hakkının kullanılamayacağı ürünler varsa buraya
                yazılmalıdır.)

                ## 6. Uyuşmazlık

                (Yetkili tüketici hakem heyeti ve mahkeme bilgisi.)
                METIN,

            LegalDocumentType::Privacy => <<<'METIN'
                # KVKK Aydınlatma Metni

                ## Veri Sorumlusu

                {{unvan}}
                Adres    : {{adres}}
                E-posta  : {{eposta}}

                ## İşlenen Kişisel Veriler

                Ad-soyad, iletişim bilgileri, teslimat ve fatura adresi,
                sipariş geçmişi.

                ## İşleme Amaçları

                Siparişin oluşturulması ve teslimi, ödeme işlemleri, müşteri
                desteği, yasal yükümlülüklerin yerine getirilmesi.

                (Pazarlama izni alınıyorsa ayrıca belirtilmelidir.)

                ## Aktarım

                (Kargo firması, ödeme kuruluşu gibi aktarım yapılan taraflar
                buraya yazılmalıdır.)

                ## Saklama Süresi

                (Yasal saklama süreleri buraya yazılmalıdır.)

                ## Haklarınız

                KVKK md. 11 kapsamındaki taleplerinizi {{eposta}} adresine
                iletebilirsiniz.
                METIN,

            LegalDocumentType::Returns => <<<'METIN'
                # İade ve Cayma Koşulları

                Satıcı: {{unvan}} · {{eposta}} · {{telefon}}

                ## Cayma Süresi

                Ürünü teslim aldığınız tarihten itibaren 14 gün içinde cayma
                hakkınızı kullanabilirsiniz.

                ## İade Şartları

                (Ürünün kullanılmamış, ambalajının zarar görmemiş olması gibi
                şartlar buraya yazılmalıdır.)

                ## İade Kargo Bedeli

                (Kargo bedelinin kime ait olduğu buraya yazılmalıdır.)

                ## Geri Ödeme

                İade onaylandıktan sonra bedel, ödemenin yapıldığı yöntemle
                iade edilir.

                ## Cayma Hakkının İstisnaları

                (Kişiye özel üretilen, hızlı bozulan veya ambalajı açıldığında
                iade edilemeyen ürünler varsa buraya yazılmalıdır.)

                ## İletişim

                İade talepleriniz için: {{eposta}}
                METIN,
        };
    }
}
