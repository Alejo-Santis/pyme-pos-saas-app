<?php

namespace App\Modules\Purchases\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Models\ThirdParty;
use App\Modules\Core\Models\Warehouse;
use App\Modules\Inventory\Models\Item;
use App\Modules\Purchases\Models\PurchaseOrder;
use App\Modules\Purchases\Services\PurchaseService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PurchaseController extends Controller
{
    public function __construct(private readonly PurchaseService $service) {}

    /**
     * Listado de órdenes de compra.
     */
    public function index(Request $request): Response
    {
        $orders = PurchaseOrder::with(['thirdParty'])
            ->when($request->search, fn ($q, $s) => $q->where('internal_code', 'like', "%{$s}%"))
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Purchases/Index', [
            'orders'  => $orders,
            'filters' => $request->only(['search', 'status']),
        ]);
    }

    /**
     * Formulario de creación.
     */
    public function create(): Response
    {
        return Inertia::render('Purchases/Create', [
            'suppliers'  => ThirdParty::active()->select('id', 'business_name', 'identification_number')->get(),
            'warehouses' => Warehouse::where('state', true)->select('id', 'name')->get(),
            'items'      => Item::where('state', true)
                ->select('id', 'name', 'code', 'purchase_price', 'average_cost')
                ->get(),
        ]);
    }

    /**
     * Guardar nueva orden de compra.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'third_party_id'            => 'nullable|uuid',
            'reference'                 => 'nullable|string|max:100',
            'issue_date'                => 'required|date',
            'notes'                     => 'nullable|string',
            'lines'                     => 'required|array|min:1',
            'lines.*.item_id'           => 'required|uuid',
            'lines.*.invoice_quantity'  => 'required|numeric|min:0.001',
            'lines.*.average_cost'      => 'required|numeric|min:0',
            'lines.*.line_extension_amount' => 'required|numeric|min:0',
        ]);

        $order = $this->service->create($data);

        return redirect()->route('purchases.index')
            ->with('success', "Orden de compra {$order->internal_code} creada correctamente.");
    }

    /**
     * Ver detalle de la OC.
     */
    public function show(PurchaseOrder $purchase): Response
    {
        $purchase->load(['thirdParty', 'items.item', 'histories.user']);

        return Inertia::render('Purchases/Show', [
            'order'      => $purchase,
            'warehouses' => Warehouse::where('state', true)->select('id', 'name')->get(),
        ]);
    }

    /**
     * Aprobar la orden.
     */
    public function approve(PurchaseOrder $purchase)
    {
        $this->service->approve($purchase);

        return back()->with('success', "Orden {$purchase->internal_code} aprobada.");
    }

    /**
     * Registrar recepción de mercancía.
     */
    public function receive(Request $request, PurchaseOrder $purchase)
    {
        $data = $request->validate([
            'warehouse_id'                    => 'required|uuid',
            'lines'                           => 'required|array|min:1',
            'lines.*.item_id'                 => 'required|uuid',
            'lines.*.received_quantity'       => 'required|numeric|min:0.001',
            'lines.*.average_cost'            => 'required|numeric|min:0',
        ]);

        $this->service->receive($purchase, $data);

        return back()->with('success', 'Mercancía recibida y stock actualizado correctamente.');
    }

    /**
     * Anular la orden.
     */
    public function annul(Request $request, PurchaseOrder $purchase)
    {
        $data = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $this->service->annul($purchase, $data['reason'] ?? '');

        return redirect()->route('purchases.index')
            ->with('success', "Orden {$purchase->internal_code} anulada.");
    }
}
