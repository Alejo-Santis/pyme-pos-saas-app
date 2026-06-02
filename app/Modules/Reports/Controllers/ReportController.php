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
use App\Modules\Payroll\Models\Employee;
use App\Modules\Payroll\Models\PayrollRun;
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

    // ── Reporte de Nómina ─────────────────────────────────────────────────

    public function payroll(Request $request): Response
    {
        $year    = (int) $request->input('year', now()->year);
        $status  = $request->input('status');       // draft|approved|paid|cancelled
        $runId   = $request->input('run_id');

        $runsQuery = PayrollRun::with('details.employee')
            ->whereYear('period_start', $year)
            ->when($status, fn ($q) => $q->where('status', $status))
            ->orderByDesc('period_start');

        $runs = $runsQuery->get()->map(function (PayrollRun $run) {
            return [
                'id'                 => $run->id,
                'name'               => $run->name,
                'period_start'       => $run->period_start?->format('Y-m-d'),
                'period_end'         => $run->period_end?->format('Y-m-d'),
                'status'             => $run->status,
                'status_label'       => PayrollRun::statusLabel($run->status),
                'total_earned'       => (float) $run->total_earned,
                'total_deductions'   => (float) $run->total_deductions,
                'total_net'          => (float) $run->total_net,
                'total_employer_cost'=> (float) $run->total_employer_cost,
                'employee_count'     => $run->details->count(),
                'is_electronic'      => (bool) $run->is_electronic,
                'nes_status'         => $run->nes_status,
            ];
        });

        // Detalle por empleado de la liquidación seleccionada
        $detail = null;
        if ($runId) {
            $run = PayrollRun::with('details.employee')->find($runId);
            if ($run) {
                $detail = [
                    'run'      => [
                        'id'           => $run->id,
                        'name'         => $run->name,
                        'period_start' => $run->period_start?->format('Y-m-d'),
                        'period_end'   => $run->period_end?->format('Y-m-d'),
                        'status'       => $run->status,
                        'status_label' => PayrollRun::statusLabel($run->status),
                    ],
                    'employees' => $run->details->map(fn ($d) => [
                        'employee_name'      => $d->employee?->full_name ?? 'N/A',
                        'identification'     => $d->employee?->identification_number,
                        'worked_days'        => $d->worked_days,
                        'basic_salary'       => (float) $d->basic_salary,
                        'transport'          => (float) $d->transport_allowance,
                        'overtime'           => (float) $d->overtime_amount,
                        'total_earned'       => (float) $d->total_earned,
                        'health_employee'    => (float) $d->health_employee,
                        'pension_employee'   => (float) $d->pension_employee,
                        'income_tax'         => (float) $d->income_tax_withholding,
                        'total_deductions'   => (float) $d->total_deductions,
                        'net_pay'            => (float) $d->net_pay,
                        'health_employer'    => (float) $d->health_employer,
                        'pension_employer'   => (float) $d->pension_employer,
                        'arl_employer'       => (float) $d->arl_employer,
                        'ccf_employer'       => (float) $d->ccf_employer,
                        'sena_employer'      => (float) $d->sena_employer,
                        'icbf_employer'      => (float) $d->icbf_employer,
                        'total_employer_cost'=> (float) $d->total_employer_cost,
                    ])->values(),
                ];
            }
        }

        // Totales acumulados del año
        $yearTotals = PayrollRun::whereYear('period_start', $year)
            ->whereNotIn('status', [PayrollRun::STATUS_CANCELLED])
            ->selectRaw('
                COUNT(*) as total_runs,
                COALESCE(SUM(total_earned), 0) as total_earned,
                COALESCE(SUM(total_deductions), 0) as total_deductions,
                COALESCE(SUM(total_net), 0) as total_net,
                COALESCE(SUM(total_employer_cost), 0) as total_employer_cost
            ')->first();

        return Inertia::render('Reports/Payroll', [
            'runs'       => $runs,
            'detail'     => $detail,
            'yearTotals' => $yearTotals,
            'filters'    => ['year' => $year, 'status' => $status, 'run_id' => $runId],
            'years'      => range(now()->year, max(2024, now()->year - 3)),
        ]);
    }

    public function exportPayroll(Request $request): mixed
    {
        $runId = $request->input('run_id');

        if (! $runId) {
            return back()->withErrors(['run_id' => 'Seleccione una liquidación para exportar.']);
        }

        $run = PayrollRun::with('details.employee')->findOrFail($runId);
        $company = Company::first();

        $headers = [
            'Empleado', 'Identificación', 'Días', 'Salario Base', 'Subsidio Transporte',
            'Horas Extra', 'Total Devengado', 'Salud Empleado', 'Pensión Empleado',
            'Retención Fuente', 'Total Deducciones', 'Neto a Pagar',
            'Salud Empleador', 'Pensión Empleador', 'ARL', 'Caja Comp.', 'SENA', 'ICBF', 'Costo Total',
        ];

        $rows = $run->details->map(fn ($d) => [
            $d->employee?->full_name ?? 'N/A',
            $d->employee?->identification_number ?? '',
            $d->worked_days,
            (float) $d->basic_salary,
            (float) $d->transport_allowance,
            (float) $d->overtime_amount,
            (float) $d->total_earned,
            (float) $d->health_employee,
            (float) $d->pension_employee,
            (float) $d->income_tax_withholding,
            (float) $d->total_deductions,
            (float) $d->net_pay,
            (float) $d->health_employer,
            (float) $d->pension_employer,
            (float) $d->arl_employer,
            (float) $d->ccf_employer,
            (float) $d->sena_employer,
            (float) $d->icbf_employer,
            (float) $d->total_employer_cost,
        ])->toArray();

        $meta = [
            $company?->business_name ?? 'Empresa',
            "Nómina: {$run->name}",
            "Período: {$run->period_start?->format('d/m/Y')} – {$run->period_end?->format('d/m/Y')}",
            'Estado: ' . PayrollRun::statusLabel($run->status),
        ];

        return Excel::download(
            new ArrayExport($rows, $headers, 'Nómina', $meta),
            "nomina_{$run->period_start?->format('Y-m')}.xlsx"
        );
    }

    // ── Kardex de Inventario ──────────────────────────────────────────────

    public function kardex(Request $request): Response
    {
        $itemId      = $request->input('item_id');
        $warehouseId = $request->input('warehouse_id');
        $from        = $request->input('date_from', now()->startOfMonth()->toDateString());
        $to          = $request->input('date_to', now()->toDateString());

        $items      = Item::where('state', true)->orderBy('name')->get(['id', 'name', 'internal_code']);
        $warehouses = Warehouse::where('state', true)->orderBy('name')->get(['id', 'name']);

        $movements  = collect();
        $item       = null;
        $stockInfo  = null;

        if ($itemId) {
            $item = Item::find($itemId, ['id', 'name', 'internal_code', 'unit_measure_id']);

            $query = DB::table('item_stocktakings as k')
                ->join('documents as d', 'd.id', '=', 'k.document_id')
                ->leftJoin('warehouses as w', 'w.id', '=', 'k.warehouse_id')
                ->where('k.item_id', $itemId)
                ->whereBetween('d.issue_date', [$from, $to])
                ->when($warehouseId, fn ($q) => $q->where('k.warehouse_id', $warehouseId))
                ->orderBy('d.issue_date')
                ->orderBy('k.id')
                ->select([
                    'k.id',
                    'd.issue_date as date',
                    'd.internal_code as document',
                    'w.name as warehouse',
                    'k.input_quantity',
                    'k.output_quantity',
                    'k.purchase_price',
                    'k.new_average',
                ]);

            $movements = $query->get()->map(fn ($row) => [
                'date'            => $row->date,
                'document'        => $row->document,
                'warehouse'       => $row->warehouse ?? '—',
                'input_quantity'  => (float) $row->input_quantity,
                'output_quantity' => (float) $row->output_quantity,
                'purchase_price'  => (float) $row->purchase_price,
                'new_average'     => (float) $row->new_average,
                'type'            => $row->input_quantity > 0 ? 'in' : 'out',
            ]);

            // Saldo actual por bodega
            $stockInfo = DB::table('item_warehouses')
                ->join('warehouses', 'warehouses.id', '=', 'item_warehouses.warehouse_id')
                ->where('item_warehouses.item_id', $itemId)
                ->select('warehouses.name as warehouse', 'item_warehouses.stock', 'item_warehouses.average_cost')
                ->get();
        }

        return Inertia::render('Reports/Kardex', [
            'items'      => $items,
            'warehouses' => $warehouses,
            'movements'  => $movements,
            'item'       => $item,
            'stockInfo'  => $stockInfo,
            'filters'    => compact('itemId', 'warehouseId', 'from', 'to'),
        ]);
    }

    public function exportKardex(Request $request): mixed
    {
        $itemId      = $request->input('item_id');
        $warehouseId = $request->input('warehouse_id');
        $from        = $request->input('date_from', now()->startOfMonth()->toDateString());
        $to          = $request->input('date_to', now()->toDateString());

        $item = Item::findOrFail($itemId, ['name', 'internal_code']);

        $movements = DB::table('item_stocktakings as k')
            ->join('documents as d', 'd.id', '=', 'k.document_id')
            ->leftJoin('warehouses as w', 'w.id', '=', 'k.warehouse_id')
            ->where('k.item_id', $itemId)
            ->whereBetween('d.issue_date', [$from, $to])
            ->when($warehouseId, fn ($q) => $q->where('k.warehouse_id', $warehouseId))
            ->orderBy('d.issue_date')
            ->select(['d.issue_date as date', 'd.internal_code as document', 'w.name as warehouse',
                      'k.input_quantity', 'k.output_quantity', 'k.purchase_price', 'k.new_average'])
            ->get();

        $company = Company::first();
        $headers = ['Fecha', 'Documento', 'Bodega', 'Entrada', 'Salida', 'Costo Unit.', 'Costo Prom.'];
        $rows    = $movements->map(fn ($r) => [
            $r->date, $r->document, $r->warehouse ?? '—',
            $r->input_quantity, $r->output_quantity, $r->purchase_price, $r->new_average,
        ])->toArray();

        $meta = [
            $company?->business_name ?? 'Empresa',
            "Kardex: {$item->name} ({$item->internal_code})",
            "Período: {$from} al {$to}",
        ];

        return Excel::download(
            new ArrayExport($rows, $headers, 'Kardex', $meta),
            "kardex_{$item->internal_code}_{$from}_{$to}.xlsx"
        );
    }
}
