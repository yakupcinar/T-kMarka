#!/usr/bin/env bash
#
# CI adımı çalıştırıcı — hatayı ANOTASYONA da yazar.
#
# ⚠️ Neden gerekli: GitHub Actions günlüklerini indirmek depo YÖNETİCİSİ
# yetkisi istiyor (API 403 dönüyor). Anotasyonlar ise herkese açık ve
# commit üzerinden okunabiliyor. Kontrol kırıldığında çıktının ÖNEMLİ
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

if [ "$KOD" -eq 0 ]; then
    exit 0
fi

# ⚠️ ÖNCE `tail -40` YAZIYORDU VE İŞE YARAMADI (4A'da ölçüldü).
#
# Pest bir istisna fırlattığında yığın izi 40 satırı tek başına dolduruyor;
# asıl mesaj ("Failed asserting that 404 is identical to 200") izin ÜSTÜNDE
# kaldığı için anotasyona hiç girmiyordu. Üstüne GitHub bir adımda yalnızca
# ~10 anotasyon gösteriyor — yani ekranda yalnızca yığın izinin ortası
# görünüyordu ve hata teşhis EDİLEMİYORDU.
#
# Artık satırlar ÖNEME göre seçiliyor, konuma göre değil.
ONEMLI="$(printf '%s\n' "$CIKTI" | grep -E \
    'FAILED|Failed asserting|SQLSTATE|Tests:|Exception|Error:|⨯|expects|Expected|Undefined|not found' \
    | grep -vE '^\s*#[0-9]+ |vendor/' \
    | head -8)"

# Hiçbir kalıp tutmadıysa son çare: son 8 satır.
if [ -z "$ONEMLI" ]; then
    ONEMLI="$(printf '%s\n' "$CIKTI" | tail -8)"
fi

printf '%s\n' "$ONEMLI" | while IFS= read -r satir; do
    [ -n "$satir" ] && echo "::error::${AD}: ${satir}"
done

exit "$KOD"
