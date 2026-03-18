<?php

namespace App\Modules\Cash\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Cash\Models\Bank;
use App\Modules\Cash\Models\BankAccount;
use App\Modules\Cash\Models\BankAccountMovement;
use App\Modules\Core\Models\ThirdParty;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class BankController extends Controller
{
    /**
     * Listado de bancos con saldos.
     */
    public function index(): Response
    {
        $banks = Bank::with(['bankAccounts', 'defaultBankAccount'])
            ->where('state', true)
            ->orderBy('name')
            ->get()
            ->map(function ($bank) {
                $bank->total_balance = $bank->bankAccounts->sum(fn ($a) => $a->getCurrentBalance());
                return $bank;
            });

        return Inertia::render('Cash/Banks', [
            'banks'    => $banks,
            'suppliers' => ThirdParty::active()->select('id', 'name')->get(),
        ]);
    }

    /**
     * Crear banco.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name'           => 'required|string|max:200',
            'third_party_id' => 'nullable|uuid',
        ]);

        $bank = Bank::create($data);

        return back()->with('success', "Banco '{$bank->name}' creado correctamente.");
    }

    /**
     * Actualizar banco.
     */
    public function update(Request $request, Bank $bank)
    {
        $data = $request->validate([
            'name'           => 'required|string|max:200',
            'third_party_id' => 'nullable|uuid',
        ]);

        $bank->update($data);

        return back()->with('success', 'Banco actualizado.');
    }

    /**
     * Eliminar banco.
     */
    public function destroy(Bank $bank)
    {
        if ($bank->bankAccounts()->count() > 0) {
            return back()->withErrors(['error' => 'No se puede eliminar un banco con cuentas asociadas.']);
        }

        $bank->delete();

        return back()->with('success', 'Banco eliminado.');
    }

    /**
     * Crear cuenta bancaria.
     */
    public function storeAccount(Request $request, Bank $bank)
    {
        $data = $request->validate([
            'name'                => 'required|string|max:150',
            'type'                => 'required|in:Ahorro,Corriente',
            'account_bank_number' => 'nullable|string|max:50|unique:bank_accounts',
            'has_gmf'             => 'boolean',
            'initial_balance'     => 'nullable|numeric|min:0',
        ]);

        $account = BankAccount::create([
            'bank_id'             => $bank->id,
            'name'                => $data['name'],
            'type'                => $data['type'],
            'account_bank_number' => $data['account_bank_number'] ?? null,
            'has_gmf'             => $data['has_gmf'] ?? false,
        ]);

        // Saldo inicial como movimiento de débito
        if (!empty($data['initial_balance']) && $data['initial_balance'] > 0) {
            BankAccountMovement::create([
                'bank_account_id' => $account->id,
                'user_id'         => Auth::id(),
                'debit'           => $data['initial_balance'],
                'credit'          => 0,
                'description'     => 'Saldo inicial',
                'issue_date'      => now()->toDateString(),
                'state'           => true,
            ]);
        }

        // Si es la primera cuenta, asignarla como predeterminada
        if ($bank->bankAccounts()->count() === 1) {
            $bank->update(['default_bank_account_id' => $account->id]);
        }

        return back()->with('success', "Cuenta '{$account->name}' creada.");
    }

    /**
     * Actualizar cuenta bancaria.
     */
    public function updateAccount(Request $request, BankAccount $account)
    {
        $data = $request->validate([
            'name'                => 'required|string|max:150',
            'type'                => 'required|in:Ahorro,Corriente',
            'account_bank_number' => 'nullable|string|max:50|unique:bank_accounts,account_bank_number,' . $account->id,
            'has_gmf'             => 'boolean',
        ]);

        $account->update($data);

        return back()->with('success', 'Cuenta actualizada.');
    }

    /**
     * Eliminar cuenta bancaria.
     */
    public function destroyAccount(BankAccount $account)
    {
        if ($account->movements()->count() > 0) {
            return back()->withErrors(['error' => 'No se puede eliminar una cuenta con movimientos.']);
        }

        $account->delete();

        return back()->with('success', 'Cuenta eliminada.');
    }

    /**
     * Registrar movimiento bancario manual.
     */
    public function storeMovement(Request $request, BankAccount $account)
    {
        $data = $request->validate([
            'type'        => 'required|in:debit,credit',
            'amount'      => 'required|numeric|min:0.01',
            'description' => 'required|string|max:500',
            'reference'   => 'nullable|string|max:100',
            'issue_date'  => 'required|date',
        ]);

        BankAccountMovement::create([
            'bank_account_id' => $account->id,
            'user_id'         => Auth::id(),
            'debit'           => $data['type'] === 'debit'  ? $data['amount'] : 0,
            'credit'          => $data['type'] === 'credit' ? $data['amount'] : 0,
            'description'     => $data['description'],
            'reference'       => $data['reference'] ?? null,
            'issue_date'      => $data['issue_date'],
            'state'           => true,
        ]);

        return back()->with('success', 'Movimiento bancario registrado.');
    }
}
