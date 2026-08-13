<?php

namespace App\Enums;

/** Müşterinin veri talebi türü. (2G) */
enum DataRequestType: string
{
    /** Kişisel alanların tanınmaz hale getirilmesi. GERİ ALINAMAZ. */
    case Anonymize = 'anonymize';

    /** Kendi verisinin makine okunur dökümü. */
    case Export = 'export';
}
