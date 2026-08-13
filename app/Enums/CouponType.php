<?php

namespace App\Enums;

/** Kupon türü. (2A) */
enum CouponType: string
{
    /** Yüzde indirim — `value` yüzde (20 = %20). */
    case Percentage = 'percentage';

    /** Sabit tutar indirimi — `value` TL. */
    case Fixed = 'fixed';

    /**
     * Ücretsiz kargo.
     *
     * ⚠️ Ürün tutarına DOKUNMUYOR: yalnızca kargo bedelini sıfırlıyor.
     * İndirim gibi işlenseydi vergi hesabı bozulurdu — kargonun vergisi
     * ürün vergisinden ayrı hesaplanıyor (§8.2).
     */
    case FreeShipping = 'free_shipping';
}
