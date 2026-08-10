#!/usr/bin/env bash
#
# CI adımı çalıştırıcı — hatayı ANOTASYONA da yazar.
#
# ⚠️ Neden gerekli: GitHub Actions günlüklerini indirmek depo YÖNETİCİSİ
# yetkisi istiyor (API 403 dönüyor). Anotasyonlar ise herkese açık ve
# commit üzerinden okunabiliyor. Kontrol kırıldığında çıktının son
# satırlarını anotasyona basıyoruz; böylece "CI neden kırmızı" sorusu
# yetki olmadan da cevaplanabiliyor.
#
# Kullanımı:  bash .github/ci-kontrol.sh "pint" composer lint:check

set -uo pipefail

AD="$1"
shift

CIKTI="$("$@" 2>&1)"
KOD=$?

printf '%s\n' "$CIKTI"

if [ "$KOD" -ne 0 ]; then
    printf '%s\n' "$CIKTI" | tail -40 | while IFS= read -r satir; do
        [ -n "$satir" ] && echo "::error::${AD}: ${satir}"
    done
fi

exit "$KOD"
