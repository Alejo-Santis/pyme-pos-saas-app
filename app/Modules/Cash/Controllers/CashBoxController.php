<?php

namespace App\Modules\Cash\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Cash\Models\CashBox;
use App\Modules\Cash\Models\CashMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class CashBoxController extends Controller
{
    /**
     * Resumen de todas las cajas.
     */
    public function index(): Response
    {
        $boxes = CashBox::withCount('movements')
            ->orderBy('is_main', 'desc')
            ->orderBy('name')
            ->get()
            ->map(fn ($b) => array_merge($b->toArray(), [
                'current_balance' => $b->getCurrentBalance(),
            ]));

        return Inertia::render('Cash/Index', [
            'cashBoxes'    => $boxes,
            'totalBalance' => $boxes->sum('current_balance'),
        ]);
    }

    /**
     * Detalle de una caja con movimientos.
     */
    public function show(Request $request, CashBox $cashBox): Response
    {
        $start = $request->start_date;
        $end   = $request->end_date;

        $movements = CashMovement::where('cash_box_id', $cashBox->id)
            ->where('cash_movements.state', true)
            ->when($start, fn ($q) => $q->where('issue_date', '>=', $start))
            ->when($end,   fn ($q) => $q->where('issue_date', '<=', $end))
            ->with(['thirdParty', 'document'])
            ->orderByDesc('issue_date')
            ->orderByDesc('created_at')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('Cash/CashBox', [
            'cashBox'        => $cashBox,
            'currentBalance' => $cashBox->getCurrentBalance($start, $end),
            'movements'      => $movements,
            'filters'        => $request->only(['start_date', 'end_date']),
        ]);
    }

    /**
     * Crear caja.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name'    => 'required|string|max:150',
            'is_main' => 'boolean',
        ]);

        // Solo puede haber una caja principal
        if (!empty($data['is_main']) && $data['is_main']) {
            CashBox::where('is_main', true)->update(['is_main' => false]);
        }

        $box = CashBox::create($data);

        return back()->with('success', "Caja '{$box->name}' creada correctamente.");
    }

    /**
     * Actualizar caja.
     */
    public function update(Request $request, CashBox $cashBox)
    {
        $data = $request->validate([
            'name'    => 'required|string|max:150',
            'is_main' => 'boolean',
        ]);

        if (!empty($data['is_main']) && $data['is_main']) {
            CashBox::where('is_main', true)->where('id', '!=', $cashBox->id)->update(['is_main' => false]);
        }

        $cashBox->update($data);

        return back()->with('success', 'Caja actualizada correctamente.');
    }

    /**
     * Eliminar caja (solo si no tiene movimientos).
     */
    public function destroy(CashBox $cashBox)
    {
        if ($cashBox->movements()->count() > 0) {
            return back()->withErrors(['error' => 'No se puede eliminar una caja con movimientos registrados.']);
        }

        $cashBox->delete();

        return redirect()->route('cash.index')->with('success', 'Caja eliminada.');
    }

    /**
     * Registrar un movimiento manual (ingreso o egreso).
     */
    public function storeMovement(Request $request, CashBox $cashBox)
    {
        $data = $request->validate([
            'type'        => 'required|in:debit,credit',
            'amount'      => 'required|numeric|min:0.01',
            'description' => 'required|string|max:500',
            'reference'   => 'nullable|string|max:100',
            'issue_date'  => 'required|date',
        ]);

        CashMovement::create([
            'cash_box_id' => $cashBox->id,
            'user_id'     => Auth::id(),
            'debit'       => $data['type'] === 'debit'  ? $data['amount'] : 0,
            'credit'      => $data['type'] === 'credit' ? $data['amount'] : 0,
            'description' => $data['description'],
            'reference'   => $data['reference'] ?? null,
            'issue_date'  => $data['issue_date'],
            'state'       => true,
        ]);

        return back()->with('success', 'Movimiento registrado correctamente.');
    }

    /**
     * Transferir entre cajas.
     */
    public function transfer(Request $request)
    {
        $data = $request->validate([
            'from_cash_box_id' => 'required|uuid',
            'to_cash_box_id'   => 'required|uuid|different:from_cash_box_id',
            'amount'           => 'required|numeric|min:0.01',
            'description'      => 'nullable|string|max:500',
            'issue_date'       => 'required|date',
        ]);

        $from = CashBox::findOrFail($data['from_cash_box_id']);
        $to   = CashBox::findOrFail($data['to_cash_box_id']);

        if ($from->getCurrentBalance() < $data['amount']) {
            return back()->withErrors(['amount' => 'Saldo insuficiente en la caja origen.']);
        }

        $desc = $data['description'] ?? "Traslado de {$from->name} a {$to->name}";

        CashMovement::create([
            'cash_box_id' => $from->id,
            'user_id'     => Auth::id(),
            'credit'      => $data['amount'],
            'debit'       => 0,
            'description' => $desc,
            'issue_date'  => $data['issue_date'],
            'state'       => true,
        ]);

        CashMovement::create([
            'cash_box_id' => $to->id,
            'user_id'     => Auth::id(),
            'debit'       => $data['amount'],
            'credit'      => 0,
            'description' => $desc,
            'issue_date'  => $data['issue_date'],
            'state'       => true,
        ]);

        return back()->with('success', 'Traslado entre cajas registrado.');
    }
}
