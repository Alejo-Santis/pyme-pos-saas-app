<?php

namespace App\Modules\Reports\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Cash\Models\CashBox;
use App\Modules\Cash\Models\CashMovement;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\ThirdParty;
use App\Modules\Core\Models\Warehouse;
use App\Modules\Inventory\Models\Item;
use App\Modules\Invoice\Models\Document;
use App\Modules\Invoice\Models\DocumentLine;
use App\Modules\POS\Models\PosTerminal;
use App\Modules\POS\Models\PosTerminalUser;
use App\Shared\Exports\ArrayExport;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReportController extends Controller
{
    // ── Reporte de Ventas ─────────────────────────────────────────────────

    public function sales(Request $request): Response
    {
        $from      = $request->input('date_from', now()->startOfMonth()->toDateString());
        $to        = $request->input('date_to', now()->toDateString());
        $terminalId = $request->input('terminal_id');
        $groupBy   = $request->input('group_by', 'day'); // day | product | terminal | third

        // ── Totales generales ────────────────────────────────────────────
        $totalsQuery = Document::whereBetween('issue_date', [$from, $to])
            ->where('annulled', false)
            ->whereIn('type_document_operation_id', [4, 1]); // POS + Factura de venta

        if ($terminalId) {
            $totalsQuery->where('pos_terminal_id', $terminalId);
        }

        $totals = $totalsQuery->selectRaw('
            COUNT(*)            as total_docs,
            COALESCE(SUM(subtotal), 0) as total_subtotal,
            COALESCE(SUM(total_tax), 0) as total_tax,
            COALESCE(SUM(total), 0)    as total_amount,
            COALESCE(SUM(CASE WHEN paid = true THEN total ELSE 0 END), 0) as total_paid,
            COALESCE(SUM(CASE WHEN paid = false THEN total ELSE 0 END), 0) as total_pending
        ')->first();

        // ── Agrupación dinámica ───────────────────────────────────────────
        $rows = match ($groupBy) {
            'day'      => $this->salesByDay($from, $to, $terminalId),
            'product'  => $this->salesByProduct($from, $to, $terminalId),
            'terminal' => $this->salesByTerminal($from, $to),
            'third'    => $this->salesByThird($from, $to, $terminalId),
            default    => $this->salesByDay($from, $to, $terminalId),
        };

        // ── Top 10 productos más vendidos ────────────────────────────────
        $topProducts = $this->salesByProduct($from, $to, $terminalId, 10);

        return Inertia::render('Reports/Sales', [
            'totals'      => $totals,
            'rows'        => $rows,
            'topProducts' => $topProducts,
            'terminals'   => PosTerminal::where('state', true)->get(['id', 'name']),
            'filters'     => [
                'date_from'   => $from,
                'date_to'     => $to,
                'terminal_id' => $terminalId,
                'group_by'    => $groupBy,
            ],
        ]);
    }

    // ── Cartera de Clientes ───────────────────────────────────────────────

    public function receivables(Request $request): Response
    {
        $asOf = $request->input('as_of', now()->toDateString());
        $thirdPartyId = $request->input('third_party_id');
        $status = $request->input('status', 'all'); // all | current | due | overdue
        $search = trim((string) $request->input('search', ''));

        $baseQuery = Document::with('thirdParty:id,name,identification_number,credit_limit,payment_days')
            ->where('annulled', false)
            ->where('balance', '>', 0)
            ->whereDate('issue_date', '<=', $asOf)
            ->whereIn('type_document_operation_id', [1, 92])
            ->when($thirdPartyId, fn ($q) => $q->where('third_party_id', $thirdPartyId))
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('internal_code', 'ilike', "%{$search}%")
                        ->orWhere('prefix', 'ilike', "%{$search}%")
                        ->orWhereHas('thirdParty', function ($party) use ($search) {
                            $party->where('name', 'ilike', "%{$search}%")
                                ->orWhere('identification_number', 'ilike', "%{$search}%");
                        });
                });
            });

        $documentsForSummary = (clone $baseQuery)
            ->orderBy('issue_date')
            ->get(['id', 'internal_code', 'third_party_id', 'type_document_operation_id', 'issue_date', 'total', 'balance']);

        $allRows = $this->mapReceivableRows($documentsForSummary, $asOf);
        $filteredIds = $allRows
            ->filter(fn ($row) => $status === 'all' || $row['status'] === $status)
            ->pluck('id')
            ->values();

        $documents = (clone $baseQuery)
            ->when($status !== 'all', fn ($q) => $q->whereIn('id', $filteredIds))
            ->orderBy('issue_date')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Document $document) => $this->mapReceivableRow($document, $asOf));

        $summary = [
            'total_balance' => round($allRows->sum('balance'), 2),
            'total_documents' => $allRows->count(),
            'current' => round($allRows->where('status', 'current')->sum('balance'), 2),
            'due' => round($allRows->where('status', 'due')->sum('balance'), 2),
            'overdue' => round($allRows->where('status', 'overdue')->sum('balance'), 2),
            'bucket_0_30' => round($allRows->where('bucket', '0_30')->sum('balance'), 2),
            'bucket_31_60' => round($allRows->where('bucket', '31_60')->sum('balance'), 2),
            'bucket_61_90' => round($allRows->where('bucket', '61_90')->sum('balance'), 2),
            'bucket_over_90' => round($allRows->where('bucket', 'over_90')->sum('balance'), 2),
        ];

        $customers = $allRows
            ->groupBy('third_party_id')
            ->map(function ($rows) {
                $first = $rows->first();
                $balance = (float) $rows->sum('balance');
                $creditLimit = (float) ($first['credit_limit'] ?? 0);

                return [
                    'id' => $first['third_party_id'],
                    'name' => $first['third_party'],
                    'identification_number' => $first['identification_number'],
                    'documents' => $rows->count(),
                    'balance' => round($balance, 2),
                    'overdue' => round($rows->where('status', 'overdue')->sum('balance'), 2),
                    'credit_limit' => round($creditLimit, 2),
                    'credit_usage' => $creditLimit > 0 ? round(($balance / $creditLimit) * 100, 1) : null,
                    'max_days_overdue' => (int) $rows->max('days_overdue'),
                ];
            })
            ->sortByDesc('balance')
            ->values()
            ->take(15);

        return Inertia::render('Reports/Receivables', [
            'documents' => $documents,
            'summary' => $summary,
            'customers' => $customers,
            'thirdParties' => ThirdParty::active()
                ->whereHas('linkage', fn ($q) => $q->where('customer', true))
                ->orderBy('name')
                ->get(['id', 'name', 'identification_number']),
            'filters' => [
                'as_of' => $asOf,
                'third_party_id' => $thirdPartyId,
                'status' => $status,
                'search' => $search,
            ],
        ]);
    }

    // ── Cuentas por Pagar ─────────────────────────────────────────────────

    public function payables(Request $request): Response
    {
        $asOf = $request->input('as_of', now()->toDateString());
        $thirdPartyId = $request->input('third_party_id');
        $status = $request->input('status', 'all');
        $search = trim((string) $request->input('search', ''));

        $baseQuery = Document::with('thirdParty:id,name,identification_number,credit_limit,payment_days')
            ->where('annulled', false)
            ->where('balance', '>', 0)
            ->whereDate('issue_date', '<=', $asOf)
            ->whereIn('type_document_operation_id', [5, 14])
            ->when($thirdPartyId, fn ($q) => $q->where('third_party_id', $thirdPartyId))
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('internal_code', 'ilike', "%{$search}%")
                        ->orWhere('prefix', 'ilike', "%{$search}%")
                        ->orWhereHas('thirdParty', function ($party) use ($search) {
                            $party->where('name', 'ilike', "%{$search}%")
                                ->orWhere('identification_number', 'ilike', "%{$search}%");
                        });
                });
            });

        $documentsForSummary = (clone $baseQuery)
            ->orderBy('issue_date')
            ->get(['id', 'internal_code', 'third_party_id', 'type_document_operation_id', 'issue_date', 'total', 'balance']);

        $allRows = $this->mapPayableRows($documentsForSummary, $asOf);
        $filteredIds = $allRows
            ->filter(fn ($row) => $status === 'all' || $row['status'] === $status)
            ->pluck('id')
            ->values();

        $documents = (clone $baseQuery)
            ->when($status !== 'all', fn ($q) => $q->whereIn('id', $filteredIds))
            ->orderBy('issue_date')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Document $document) => $this->mapPayableRow($document, $asOf));

        $summary = [
            'total_balance' => round($allRows->sum('balance'), 2),
            'total_documents' => $allRows->count(),
            'current' => round($allRows->where('status', 'current')->sum('balance'), 2),
            'due' => round($allRows->where('status', 'due')->sum('balance'), 2),
            'overdue' => round($allRows->where('status', 'overdue')->sum('balance'), 2),
            'bucket_0_30' => round($allRows->where('bucket', '0_30')->sum('balance'), 2),
            'bucket_31_60' => round($allRows->where('bucket', '31_60')->sum('balance'), 2),
            'bucket_61_90' => round($allRows->where('bucket', '61_90')->sum('balance'), 2),
            'bucket_over_90' => round($allRows->where('bucket', 'over_90')->sum('balance'), 2),
        ];

        $providers = $allRows
            ->groupBy('third_party_id')
            ->map(function ($rows) {
                $first = $rows->first();

                return [
                    'id' => $first['third_party_id'],
                    'name' => $first['third_party'],
                    'identification_number' => $first['identification_number'],
                    'documents' => $rows->count(),
                    'balance' => round((float) $rows->sum('balance'), 2),
                    'overdue' => round($rows->where('status', 'overdue')->sum('balance'), 2),
                    'max_days_overdue' => (int) $rows->max('days_overdue'),
                ];
            })
            ->sortByDesc('balance')
            ->values()
            ->take(15);

        return Inertia::render('Reports/Payables', [
            'documents' => $documents,
            'summary' => $summary,
            'providers' => $providers,
            'thirdParties' => ThirdParty::active()
                ->whereHas('linkage', fn ($q) => $q->where('provider', true))
                ->orderBy('name')
                ->get(['id', 'name', 'identification_number']),
            'filters' => [
                'as_of' => $asOf,
                'third_party_id' => $thirdPartyId,
                'status' => $status,
                'search' => $search,
            ],
        ]);
    }

    // ── Reporte de Caja / Turnos ──────────────────────────────────────────

    public function cash(Request $request): Response
    {
        $from    = $request->input('date_from', now()->startOfMonth()->toDateString());
        $to      = $request->input('date_to', now()->toDateString());
        $cashBoxId = $request->input('cash_box_id');

        // Historial de turnos cerrados
        $shiftsQuery = PosTerminalUser::with(['user:id,name', 'terminal:id,name'])
            ->whereNotNull('shift_closed_at')
            ->whereDate('shift_closed_at', '>=', $from)
            ->whereDate('shift_closed_at', '<=', $to)
            ->orderByDesc('shift_closed_at');

        $shifts = $shiftsQuery->get();

        // Movimientos de caja por período
        $movementsQuery = CashMovement::with(['cashBox:id,name', 'document:id,internal_code'])
            ->whereBetween('issue_date', [$from, $to])
            ->where('cash_movements.state', true)
            ->orderByDesc('issue_date');

        if ($cashBoxId) {
            $movementsQuery->where('cash_box_id', $cashBoxId);
        }

        $movements = $movementsQuery->get();

        // Totales por caja
        $boxTotals = CashMovement::whereBetween('issue_date', [$from, $to])
            ->where('cash_movements.state', true)
            ->when($cashBoxId, fn ($q) => $q->where('cash_box_id', $cashBoxId))
            ->join('cash_boxes', 'cash_movements.cash_box_id', '=', 'cash_boxes.id')
            ->selectRaw('
                cash_boxes.name,
                COALESCE(SUM(cash_movements.debit), 0)  as total_debit,
                COALESCE(SUM(cash_movements.credit), 0) as total_credit,
                COALESCE(SUM(cash_movements.debit) - SUM(cash_movements.credit), 0) as balance
            ')
            ->groupBy('cash_boxes.id', 'cash_boxes.name')
            ->get();

        // Resumen de turnos
        $shiftSummary = [
            'total_shifts'   => $shifts->count(),
            'total_sales'    => $shifts->sum('total_sales'),
            'total_cash'     => $shifts->sum('total_cash'),
            'total_card'     => $shifts->sum('total_card'),
            'total_transfer' => $shifts->sum('total_transfer'),
        ];

        return Inertia::render('Reports/Cash', [
            'shifts'       => $shifts,
            'movements'    => $movements,
            'boxTotals'    => $boxTotals,
            'shiftSummary' => $shiftSummary,
            'cashBoxes'    => CashBox::where('state', true)->get(['id', 'name']),
            'filters'      => [
                'date_from'    => $from,
                'date_to'      => $to,
                'cash_box_id'  => $cashBoxId,
            ],
        ]);
    }

    // ── Reporte de Inventario ─────────────────────────────────────────────

    public function inventory(Request $request): Response
    {
        $warehouseId = $request->input('warehouse_id');
        $search      = $request->input('search');
        $filter      = $request->input('filter', 'all'); // all | low_stock | no_stock

        $query = Item::with(['itemWarehouses.warehouse', 'itemCategory'])
            ->where('is_active', true)
            ->orderBy('name');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                  ->orWhere('internal_code', 'ilike', "%{$search}%")
                  ->orWhere('barcode_one', 'ilike', "%{$search}%");
            });
        }

        if ($warehouseId) {
            $query->whereHas('itemWarehouses', fn ($q) => $q->where('warehouse_id', $warehouseId));
        }

        $items = $query->get()->map(function ($item) use ($warehouseId) {
            $warehouses = $warehouseId
                ? $item->itemWarehouses->where('warehouse_id', $warehouseId)
                : $item->itemWarehouses;

            $totalStock    = $warehouses->sum('stock');
            $avgCost       = $warehouses->avg('average_cost') ?? 0;
            $stockValue    = $warehouses->sum(fn ($w) => ($w->stock ?? 0) * ($w->average_cost ?? 0));
            $potentialSale = $totalStock * ($item->default_sale_price ?? 0);

            return [
                'id'            => $item->id,
                'internal_code' => $item->internal_code,
                'name'          => $item->name,
                'category'      => $item->itemCategory?->name,
                'min_stock'     => $item->minimum_existence ?? 0,
                'total_stock'   => round($totalStock, 2),
                'avg_cost'      => round($avgCost, 2),
                'sale_price'    => $item->default_sale_price ?? 0,
                'stock_value'   => round($stockValue, 2),
                'potential_sale'=> round($potentialSale, 2),
                'warehouses'    => $warehouses->map(fn ($w) => [
                    'name'  => $w->warehouse?->name,
                    'stock' => round($w->stock ?? 0, 2),
                    'cost'  => round($w->average_cost ?? 0, 2),
                ])->values(),
                'status'        => $this->stockStatus($totalStock, $item->minimum_existence ?? 0),
            ];
        });

        // Aplicar filtro de stock
        $items = match ($filter) {
            'low_stock'  => $items->filter(fn ($i) => $i['status'] === 'low'),
            'no_stock'   => $items->filter(fn ($i) => $i['status'] === 'empty'),
            default      => $items,
        };

        // Totales
        $totals = [
            'total_items'       => $items->count(),
            'total_stock_value' => $items->sum('stock_value'),
            'total_sale_value'  => $items->sum('potential_sale'),
            'low_stock_items'   => $items->where('status', 'low')->count(),
            'empty_stock_items' => $items->where('status', 'empty')->count(),
        ];

        return Inertia::render('Reports/Inventory', [
            'items'      => $items->values(),
            'totals'     => $totals,
            'warehouses' => Warehouse::where('is_active', true)->get(['id', 'name']),
            'filters'    => [
                'warehouse_id' => $warehouseId,
                'search'       => $search,
                'filter'       => $filter,
            ],
        ]);
    }

    // ─── Helpers privados ─────────────────────────────────────────────────

    private function salesByDay(string $from, string $to, ?string $terminalId): \Illuminate\Support\Collection
    {
        return Document::whereBetween('issue_date', [$from, $to])
            ->where('annulled', false)
            ->whereIn('type_document_operation_id', [4, 1])
            ->when($terminalId, fn ($q) => $q->where('pos_terminal_id', $terminalId))
            ->selectRaw("
                issue_date::date             as period,
                COUNT(*)                     as total_docs,
                COALESCE(SUM(total), 0)      as total_amount,
                COALESCE(SUM(total_tax), 0)  as total_tax
            ")
            ->groupBy(DB::raw('issue_date::date'))
            ->orderBy('period')
            ->get();
    }

    private function mapReceivableRows($documents, string $asOf): \Illuminate\Support\Collection
    {
        return $documents->map(fn (Document $document) => $this->mapReceivableRow($document, $asOf));
    }

    private function mapReceivableRow(Document $document, string $asOf): array
    {
        $issueDate = Carbon::parse($document->issue_date)->startOfDay();
        $paymentDays = (int) ($document->thirdParty?->payment_days ?? 0);
        $dueDate = $issueDate->copy()->addDays($paymentDays);
        $daysOverdue = max(0, $dueDate->diffInDays(Carbon::parse($asOf)->startOfDay(), false));
        $daysToDue = max(0, Carbon::parse($asOf)->startOfDay()->diffInDays($dueDate, false));

        $status = match (true) {
            $daysOverdue > 0 => 'overdue',
            $daysToDue <= 7 => 'due',
            default => 'current',
        };

        $bucket = match (true) {
            $daysOverdue <= 0 => 'current',
            $daysOverdue <= 30 => '0_30',
            $daysOverdue <= 60 => '31_60',
            $daysOverdue <= 90 => '61_90',
            default => 'over_90',
        };

        $total = (float) $document->total;
        $balance = (float) $document->balance;

        return [
            'id' => $document->id,
            'internal_code' => $document->internal_code,
            'type_document_operation_id' => $document->type_document_operation_id,
            'third_party_id' => $document->third_party_id,
            'third_party' => $document->thirdParty?->name ?? 'Sin tercero',
            'identification_number' => $document->thirdParty?->identification_number,
            'credit_limit' => $document->thirdParty?->credit_limit,
            'payment_days' => $paymentDays,
            'issue_date' => $issueDate->toDateString(),
            'due_date' => $dueDate->toDateString(),
            'days_overdue' => $daysOverdue,
            'days_to_due' => $daysToDue,
            'status' => $status,
            'bucket' => $bucket,
            'total' => round($total, 2),
            'paid_amount' => round(max(0, $total - $balance), 2),
            'balance' => round($balance, 2),
        ];
    }

    private function mapPayableRows($documents, string $asOf): \Illuminate\Support\Collection
    {
        return $documents->map(fn (Document $document) => $this->mapPayableRow($document, $asOf));
    }

    private function mapPayableRow(Document $document, string $asOf): array
    {
        $issueDate = Carbon::parse($document->issue_date)->startOfDay();
        $paymentDays = (int) ($document->thirdParty?->payment_days ?? 30);
        $dueDate = $issueDate->copy()->addDays($paymentDays);
        $daysOverdue = max(0, $dueDate->diffInDays(Carbon::parse($asOf)->startOfDay(), false));
        $daysToDue = max(0, Carbon::parse($asOf)->startOfDay()->diffInDays($dueDate, false));

        $status = match (true) {
            $daysOverdue > 0 => 'overdue',
            $daysToDue <= 7 => 'due',
            default => 'current',
        };

        $bucket = match (true) {
            $daysOverdue <= 0 => 'current',
            $daysOverdue <= 30 => '0_30',
            $daysOverdue <= 60 => '31_60',
            $daysOverdue <= 90 => '61_90',
            default => 'over_90',
        };

        $total = (float) $document->total;
        $balance = (float) $document->balance;

        return [
            'id' => $document->id,
            'internal_code' => $document->internal_code,
            'type_document_operation_id' => $document->type_document_operation_id,
            'third_party_id' => $document->third_party_id,
            'third_party' => $document->thirdParty?->name ?? 'Sin proveedor',
            'identification_number' => $document->thirdParty?->identification_number,
            'payment_days' => $paymentDays,
            'issue_date' => $issueDate->toDateString(),
            'due_date' => $dueDate->toDateString(),
            'days_overdue' => $daysOverdue,
            'days_to_due' => $daysToDue,
            'status' => $status,
            'bucket' => $bucket,
            'total' => round($total, 2),
            'paid_amount' => round(max(0, $total - $balance), 2),
            'balance' => round($balance, 2),
        ];
    }

    private function salesByProduct(string $from, string $to, ?string $terminalId, int $limit = 0): \Illuminate\Support\Collection
    {
        $q = DocumentLine::join('documents', 'documents_details.document_id', '=', 'documents.id')
            ->leftJoin('items', 'documents_details.item_id', '=', 'items.id')
            ->whereBetween('documents.issue_date', [$from, $to])
            ->where('documents.annulled', false)
            ->whereIn('documents.type_document_operation_id', [4, 1])
            ->when($terminalId, fn ($q) => $q->where('documents.pos_terminal_id', $terminalId))
            ->selectRaw("
                COALESCE(items.internal_code, '—')  as internal_code,
                COALESCE(items.name, documents_details.description, 'Sin nombre') as product_name,
                COALESCE(SUM(documents_details.amount), 0)          as total_qty,
                COALESCE(SUM(documents_details.taxable_amount), 0)  as total_amount,
                COUNT(DISTINCT documents.id)                         as total_docs
            ")
            ->groupBy('items.id', 'items.internal_code', 'items.name', 'documents_details.description')
            ->orderByDesc('total_amount');

        if ($limit > 0) $q->limit($limit);

        return $q->get();
    }

    private function salesByTerminal(string $from, string $to): \Illuminate\Support\Collection
    {
        return Document::whereBetween('issue_date', [$from, $to])
            ->where('annulled', false)
            ->whereIn('type_document_operation_id', [4, 1])
            ->leftJoin('pos_terminals', 'documents.pos_terminal_id', '=', 'pos_terminals.id')
            ->selectRaw("
                COALESCE(pos_terminals.name, 'Sin terminal')    as period,
                COUNT(*)                                          as total_docs,
                COALESCE(SUM(documents.total), 0)               as total_amount,
                COALESCE(SUM(documents.total_tax), 0)           as total_tax
            ")
            ->groupBy('pos_terminals.id', 'pos_terminals.name')
            ->orderByDesc('total_amount')
            ->get();
    }

    private function salesByThird(string $from, string $to, ?string $terminalId): \Illuminate\Support\Collection
    {
        return Document::whereBetween('issue_date', [$from, $to])
            ->where('annulled', false)
            ->whereIn('type_document_operation_id', [4, 1])
            ->when($terminalId, fn ($q) => $q->where('pos_terminal_id', $terminalId))
            ->leftJoin('third_parties', 'documents.third_party_id', '=', 'third_parties.id')
            ->selectRaw("
                COALESCE(third_parties.name, 'Consumidor final')  as period,
                COUNT(*)                                           as total_docs,
                COALESCE(SUM(documents.total), 0)                as total_amount,
                COALESCE(SUM(documents.total_tax), 0)            as total_tax
            ")
            ->groupBy('third_parties.id', 'third_parties.name')
            ->orderByDesc('total_amount')
            ->get();
    }

    // ──────────────────────────────────────────────────────────────────────
    // EXPORTACIONES (Excel y PDF)
    // ──────────────────────────────────────────────────────────────────────

    public function exportSales(Request $request): mixed
    {
        $from      = $request->input('date_from', now()->startOfMonth()->toDateString());
        $to        = $request->input('date_to', now()->toDateString());
        $terminalId = $request->input('terminal_id');
        $groupBy   = $request->input('group_by', 'day');
        $format    = $request->input('format', 'excel');

        $rows    = match ($groupBy) {
            'day'      => $this->salesByDay($from, $to, $terminalId),
            'product'  => $this->salesByProduct($from, $to, $terminalId),
            'terminal' => $this->salesByTerminal($from, $to),
            'third'    => $this->salesByThird($from, $to, $terminalId),
            default    => $this->salesByDay($from, $to, $terminalId),
        };

        $totals  = Document::whereBetween('issue_date', [$from, $to])
            ->where('annulled', false)
            ->whereIn('type_document_operation_id', [4, 1])
            ->when($terminalId, fn ($q) => $q->where('pos_terminal_id', $terminalId))
            ->selectRaw('COUNT(*) as total_docs, COALESCE(SUM(subtotal),0) as total_subtotal,
                         COALESCE(SUM(total_tax),0) as total_tax, COALESCE(SUM(total),0) as total_amount')
            ->first();

        $company = Company::first();
        $meta    = [
            $company?->name ?? 'Empresa',
            "Reporte de Ventas — {$groupBy}",
            "Período: {$from} al {$to}",
        ];

        if ($format === 'pdf') {
            $pdf = Pdf::loadView('exports.sales', compact('rows', 'totals', 'meta', 'from', 'to', 'groupBy', 'company'))
                ->setPaper('a4', 'landscape');
            return $pdf->download("ventas_{$from}_{$to}.pdf");
        }

        $groupLabels = ['day' => 'Fecha', 'product' => 'Producto', 'terminal' => 'Terminal', 'third' => 'Cliente'];
        $headers     = [$groupLabels[$groupBy] ?? 'Período', 'Documentos', 'Subtotal', 'IVA', 'Total'];

        $excelRows = $rows->map(fn ($r) => [
            $r->period ?? $r->product_name ?? $r->internal_code ?? '—',
            $r->total_docs,
            $r->total_amount ?? $r->total_amount ?? 0,
            $r->total_tax ?? 0,
            $r->total_amount ?? 0,
        ])->toArray();

        return Excel::download(
            new ArrayExport($excelRows, $headers, 'Ventas', $meta),
            "ventas_{$from}_{$to}.xlsx"
        );
    }

    public function exportCash(Request $request): mixed
    {
        $from      = $request->input('date_from', now()->startOfMonth()->toDateString());
        $to        = $request->input('date_to', now()->toDateString());
        $cashBoxId = $request->input('cash_box_id');
        $format    = $request->input('format', 'excel');

        $movements = CashMovement::with(['cashBox:id,name', 'document:id,internal_code'])
            ->whereBetween('issue_date', [$from, $to])
            ->where('state', true)
            ->when($cashBoxId, fn ($q) => $q->where('cash_box_id', $cashBoxId))
            ->orderByDesc('issue_date')
            ->get();

        $company = Company::first();
        $meta    = [$company?->name ?? 'Empresa', "Reporte de Caja — {$from} al {$to}"];

        if ($format === 'pdf') {
            $pdf = Pdf::loadView('exports.cash', compact('movements', 'meta', 'from', 'to', 'company'))
                ->setPaper('a4', 'landscape');
            return $pdf->download("caja_{$from}_{$to}.pdf");
        }

        $headers   = ['Fecha', 'Caja', 'Concepto', 'Documento', 'Ingreso', 'Egreso', 'Saldo'];
        $excelRows = $movements->map(fn ($m) => [
            $m->issue_date,
            $m->cashBox?->name ?? '—',
            $m->concept ?? '—',
            $m->document?->internal_code ?? '—',
            $m->debit ?? 0,
            $m->credit ?? 0,
            ($m->debit ?? 0) - ($m->credit ?? 0),
        ])->toArray();

        return Excel::download(
            new ArrayExport($excelRows, $headers, 'Caja', $meta),
            "caja_{$from}_{$to}.xlsx"
        );
    }

    public function exportInventory(Request $request): mixed
    {
        $warehouseId = $request->input('warehouse_id');
        $format      = $request->input('format', 'excel');

        $query = Item::with(['itemWarehouses.warehouse', 'itemCategory'])
            ->where('is_active', true)
            ->orderBy('name');

        if ($warehouseId) {
            $query->whereHas('itemWarehouses', fn ($q) => $q->where('warehouse_id', $warehouseId));
        }

        $items = $query->get()->map(function ($item) use ($warehouseId) {
            $warehouses  = $warehouseId ? $item->itemWarehouses->where('warehouse_id', $warehouseId) : $item->itemWarehouses;
            $totalStock  = $warehouses->sum('stock');
            $avgCost     = $warehouses->avg('average_cost') ?? 0;
            $stockValue  = $warehouses->sum(fn ($w) => ($w->stock ?? 0) * ($w->average_cost ?? 0));
            return [
                'internal_code' => $item->internal_code,
                'name'          => $item->name,
                'category'      => $item->itemCategory?->name ?? '—',
                'total_stock'   => round($totalStock, 2),
                'min_stock'     => $item->minimum_existence ?? 0,
                'avg_cost'      => round($avgCost, 2),
                'sale_price'    => $item->default_sale_price ?? 0,
                'stock_value'   => round($stockValue, 2),
                'status'        => $this->stockStatus($totalStock, $item->minimum_existence ?? 0),
            ];
        });

        $company = Company::first();
        $meta    = [$company?->name ?? 'Empresa', 'Reporte de Inventario — ' . now()->toDateString()];

        if ($format === 'pdf') {
            $pdf = Pdf::loadView('exports.inventory', compact('items', 'meta', 'company'))
                ->setPaper('a4', 'landscape');
            return $pdf->download('inventario_' . now()->toDateString() . '.pdf');
        }

        $headers   = ['Código', 'Nombre', 'Categoría', 'Stock Total', 'Stock Mínimo', 'Costo Prom.', 'Precio Venta', 'Valor Stock', 'Estado'];
        $excelRows = $items->map(fn ($i) => array_values($i))->toArray();

        return Excel::download(
            new ArrayExport($excelRows, $headers, 'Inventario', $meta),
            'inventario_' . now()->toDateString() . '.xlsx'
        );
    }

    private function stockStatus(float $stock, float $minStock): string
    {
        if ($stock <= 0) return 'empty';
        if ($minStock > 0 && $stock <= $minStock) return 'low';
        return 'ok';
    }
}
