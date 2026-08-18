<?php

namespace App\Http\Panel;

use App\Domain\Returns\RefundService;
use App\Domain\Returns\ReturnService;
use App\Http\Controllers\Controller;
use App\Models\OrderReturn;
use App\Models\Refund;
use App\Models\ReturnItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * İade ekranları. (4E)
 *
 * ★ YETKİ AYRIMI: talebi GÖRMEK `order.view`, PARA İADESİ `order.refund`.
 *
 * ⚠️ Onay/ret/teslim alma da `order.refund` arkasında: bunlar para
 * iadesinin önündeki adımlar ve zinciri başlatan kişi ile parayı gönderen
 * kişinin yetkisi ayrılırsa "onayladım ama iade edemiyorum" durumu doğar.
 * Faz 1-2'deki uç ayrımıyla HİZALI — arayüz onu bozmuyor.
 */
class ReturnPageController extends Controller
{
    public const SAYFA = 25;

    public function __construct(
        private readonly ReturnService $iadeler,
        private readonly RefundService $paraIadesi,
    ) {}

    public function index(): Response
    {
        $talepler = OrderReturn::query()
            ->with(['order', 'items'])
            ->orderByDesc('id')
            ->paginate(self::SAYFA);

        return Inertia::render('Iadeler/Liste', [
            'talepler' => $talepler->through(fn (OrderReturn $t) => $this->satir($t)),
        ]);
    }

    public function show(OrderReturn $iade): Response
    {
        $iade->load(['order', 'items.orderItem']);

        return Inertia::render('Iadeler/Ayrinti', [
            'talep' => $this->ayrinti($iade),
        ]);
    }

    public function onayla(OrderReturn $iade): RedirectResponse
    {
        $this->iadeler->onayla($iade);

        return back()->with('mesaj', 'İade talebi onaylandı.');
    }

    public function reddet(Request $istek, OrderReturn $iade): RedirectResponse
    {
        $veri = $istek->validate(['note' => ['nullable', 'string', 'max:255']]);

        $this->iadeler->reddet($iade, isset($veri['note']) ? (string) $veri['note'] : null);

        return back()->with('mesaj', 'İade talebi reddedildi.');
    }

    public function teslimAl(Request $istek, OrderReturn $iade): RedirectResponse
    {
        $veri = $istek->validate(['restock' => ['nullable', 'boolean']]);

        /*
        | ⚠️ STOĞA GERİ KOYMA VARSAYILAN OLARAK KAPALI (2B). İade edilen
        | ürün hasarlı olabilir; otomatik stoğa girseydi marka satamayacağı
        | bir ürünü satışa açmış olurdu. Karar personelin.
        */
        $this->iadeler->teslimAlindi($iade, (bool) ($veri['restock'] ?? false));

        return back()->with('mesaj', 'İade teslim alındı.');
    }

    public function paraIadesi(Request $istek, OrderReturn $iade): RedirectResponse
    {
        $veri = $istek->validate(['reason' => ['nullable', 'string', 'max:255']]);

        $this->paraIadesi->iadeEt($iade, isset($veri['reason']) ? (string) $veri['reason'] : null);

        return back()->with('mesaj', 'Para iadesi yapıldı.');
    }

    /** @return array<string, mixed> */
    private function satir(OrderReturn $talep): array
    {
        return [
            'uuid' => $talep->uuid,
            'status' => $talep->status->value,
            'order_number' => $talep->order?->order_number,
            'order_uuid' => $talep->order?->uuid,

            // ⚠️ Cayma mı ayıplı mı: kargo bedelinin geri verilip
            // verilmeyeceğini bu belirliyor (2B-K1).
            'is_withdrawal' => (bool) $talep->is_withdrawal,

            'item_count' => $talep->items->sum('quantity'),
            'created_at' => $talep->created_at?->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    private function ayrinti(OrderReturn $talep): array
    {
        return $this->satir($talep) + [
            'reason' => $talep->reason,
            'decision_note' => $talep->decision_note,
            'items' => $talep->items->map(fn (ReturnItem $s) => [
                'title' => $s->orderItem?->product_title,
                'sku' => $s->orderItem?->sku,
                'quantity' => $s->quantity,
            ])->values()->all(),

            /*
            | Yapılmış para iadeleri — tutarıyla. Marka "bu talebe ne kadar
            | ödendi" sorusunu ekranda cevaplayabilmeli; yoksa aynı talebe
            | ikinci kez iade denemesi yapılırdı.
            */
            'refunds' => Refund::query()
                ->where('return_id', $talep->id)
                ->orderBy('id')
                ->get()
                ->map(fn (Refund $i) => [
                    'uuid' => $i->uuid,
                    'status' => $i->status->value,
                    'amount' => $i->amount,
                ])
                ->values()
                ->all(),
        ];
    }
}
