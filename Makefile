# TıkMarka — günlük işler tek yerden.
#
# ⚠️ Her şey KONTEYNER İÇİNDE koşuyor; yerel makinede PHP/Composer yok.
# Bu dosya o uzun `docker compose exec -T app ...` satırlarını kısaltıyor.
#
# Yardım için:  make

DC   = docker compose
APP  = $(DC) exec -T app

.DEFAULT_GOAL := yardim
.PHONY: yardim ayaga kaldir indir yeniden derle durum tunel tunel-kapat kontrol lint analiz test \
        migrate marka kabuk log temiz

yardim:
	@echo ""
	@echo "  GÜNLÜK"
	@echo "    make ayaga        her şeyi başlat (TÜNEL HARİÇ)"
	@echo "    make kaldir       her şeyi başlat + NGROK TÜNELİ"
	@echo "    make indir        her şeyi durdur (tünel dâhil)"
	@echo "    make yeniden      kodu yenile: worker + scheduler + caddy"
	@echo "    make durum        ne çalışıyor, tünel adresi ne"
	@echo ""
	@echo "  TÜNEL  (yalnızca ödeme sağlayıcısı testi için)"
	@echo "    make tunel        tüneli aç"
	@echo "    make tunel-kapat  tüneli kapat"
	@echo ""
	@echo "  DENETİM"
	@echo "    make derle        panel arayüzünü derle (Vue değişince ŞART)"
	@echo "    make kontrol      lint + analiz + test (commit öncesi ZORUNLU)"
	@echo "    make lint / analiz / test"
	@echo ""
	@echo "  VERİTABANI"
	@echo "    make migrate      merkez + tüm markalar"
	@echo "    make marka ad=\"C Markası\" alan=marka-c.localhost"
	@echo ""
	@echo "  DİĞER"
	@echo "    make kabuk        konteyner içinde kabuk"
	@echo "    make log s=app    servis günlüğü"
	@echo ""

# ── Günlük ─────────────────────────────────────────────────────────

ayaga:
	$(DC) up -d
	@echo "→ https://marka-a.localhost  ·  https://marka-b.localhost"

kaldir: ayaga tunel

# ⚠️ `--profile tunel` olmadan `down` tünel konteynerini GÖRMEZ ve
# arkada açık kalır — makine internete açık kalmış olur.
indir:
	$(DC) --profile tunel down

# ⚠️ Kod değişince ŞART: işçi kodu belleğe alıyor, bayat kodla koşmaya
# devam eder (CLAUDE.md). Caddy de bağlı yapılandırmayı yeniden okumaz.
yeniden:
	$(DC) restart worker scheduler caddy

# ⚠️ PANEL DEĞİŞİNCE ŞART (4C). Vue/Inertia dosyaları derlenmeden
# tarayıcıya ulaşmaz — sunucu doğru HTML döner ama sayfa BOŞ açılır.
# Vitrin etkilenmez: o sunucuda render edilen Blade (4-K1).
derle:
	$(DC) exec -T app npm run build

durum:
	@$(DC) ps --format "  {{.Service}}\t{{.State}}"
	@echo ""
	@curl -s http://localhost:4040/api/tunnels 2>/dev/null \
	  | python3 -c "import sys,json;[print(\"  tünel:\",t[\"public_url\"]) for t in json.load(sys.stdin)[\"tunnels\"]]" \
	  2>/dev/null || echo "  tünel: KAPALI"

# ── Tünel ──────────────────────────────────────────────────────────
#
# ⚠️ Adres SABİT ve hesabında ayrılmış — tünel kapalıyken de senin.
# Kapalıyken sadece cevap vermiyor. iyzico paneline bir kez yazıldı,
# tekrar girmeye gerek yok.
#
# ⚠️ Tünel açıkken bu makine internete açık ve arkasında PANEL de var.
# İşin bitince kapat.

tunel:
	@grep -q "^NGROK_AUTHTOKEN=." .env || { echo "✗ .env icinde NGROK_AUTHTOKEN yok"; exit 1; }
	@grep -q "^NGROK_DOMAIN=." .env || { echo "✗ .env icinde NGROK_DOMAIN yok"; exit 1; }
	$(DC) --profile tunel up -d ngrok
	@sleep 3
	@echo "→ tünel arayüzü: http://localhost:4040"

tunel-kapat:
	$(DC) stop ngrok

# ── Denetim ────────────────────────────────────────────────────────

kontrol: lint analiz test

lint:
	$(APP) composer lint

analiz:
	$(APP) composer analyse

test:
	$(APP) composer test

# ── Veritabanı ─────────────────────────────────────────────────────

migrate:
	$(APP) composer migrate:landlord
	$(APP) php artisan tenants:migrate

# ⚠️ Yeni marka HTTPS'e çıkmaz: docker/Caddyfile'da alan adları elle
# sayılı. Ekleyip `make yeniden` (CLAUDE.md).
marka:
	$(APP) php artisan tenant:create "$(ad)" $(alan)

# ── Diğer ──────────────────────────────────────────────────────────

kabuk:
	$(DC) exec app bash

log:
	$(DC) logs -f --tail=100 $(or $(s),app)
