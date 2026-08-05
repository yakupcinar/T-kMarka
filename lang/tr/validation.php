<?php

/*
| Doğrulama mesajlarının Türkçesi.
|
| ⚠️ .env'de hem APP_LOCALE hem APP_FALLBACK_LOCALE 'tr'. Bu dosya olmasaydı
| kullanıcı ham anahtar görürdü: "validation.required".
|
| Laravel'in TÜM kurallarını çevirmiyoruz — yalnızca kullandıklarımızı.
| Yeni bir kural kullanıldığında buraya eklenir; unutulursa anahtar görünür
| ve hemen fark edilir.
*/

return [
    'required' => ':attribute alanı zorunludur.',
    'email' => ':attribute geçerli bir e-posta adresi olmalıdır.',
    'unique' => 'Bu :attribute zaten kullanılıyor.',
    'string' => ':attribute metin olmalıdır.',
    'boolean' => ':attribute doğru veya yanlış olmalıdır.',
    'confirmed' => ':attribute tekrarı eşleşmiyor.',

    'min' => [
        'string' => ':attribute en az :min karakter olmalıdır.',
        'numeric' => ':attribute en az :min olmalıdır.',
    ],
    'max' => [
        'string' => ':attribute en fazla :max karakter olabilir.',
        'numeric' => ':attribute en fazla :max olabilir.',
    ],

    /*
    | Alan adlarının kullanıcıya görünen karşılığı. Olmasaydı mesajda
    | "accepts_marketing alanı zorunludur" yazardı.
    */
    'attributes' => [
        'name' => 'ad',
        'email' => 'e-posta adresi',
        'password' => 'parola',
        'phone' => 'telefon',
        'accepts_marketing' => 'pazarlama izni',
        'title' => 'başlık',
        'full_name' => 'ad soyad',
        'city' => 'il',
        'district' => 'ilçe',
        'line1' => 'adres',
    ],
];
