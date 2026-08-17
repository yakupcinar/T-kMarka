<?php

namespace App\Platform\Subscription;

use RuntimeException;

/**
 * Abonelik imza anahtarı yapılandırılmamış. (3E)
 *
 * ⚠️ `SubscriptionProviderException`'dan AYRI bir sınıf çünkü sorumlu
 * taraf farklı: o "sağlayıcı hata döndürdü", bu "bizim yapılandırmamız
 * eksik". Aynı sınıf olsaydı webhook 400 döner ve "senin gönderdiğin
 * bozuk" derdi — oysa gönderen haklı.
 *
 * ⚠️ Boş anahtarla imzalamak YASAK: 1E.7'de ölçüldü, `hash_hmac(..., '')`
 * geçerli GÖRÜNEN bir imza üretiyor ve doğrulama hiçbir şeyi korumuyor.
 */
class MissingSubscriptionSecretException extends RuntimeException {}
