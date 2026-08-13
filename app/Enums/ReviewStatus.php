<?php

namespace App\Enums;

/** Yorum durumu. (2E-K2) */
enum ReviewStatus: string
{
    /**
     * Onay bekliyor — vitrinde YOK.
     *
     * ⚠️ Varsayılan bu. Otomatik yayınlansaydı hakaret veya kişisel veri
     * içeren yorum vitrinde anında görünürdü; sorumluluk markanın.
     */
    case Pending = 'pending';

    /** Onaylanmış — vitrinde var, ortalamaya DÂHİL. */
    case Approved = 'approved';

    /**
     * Reddedilmiş — vitrinde yok, ortalamaya dâhil DEĞİL.
     *
     * ⚠️ Silinmiyor: aynı müşteri yeniden yazıp kotayı tüketemesin ve
     * markanın kararı kayıtlı kalsın.
     */
    case Rejected = 'rejected';
}
