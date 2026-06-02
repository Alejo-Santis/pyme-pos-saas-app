<?php

declare(strict_types=1);

use App\Modules\Accounting\Controllers\AccountingConceptController;
use App\Modules\Accounting\Controllers\AccountingController;
use App\Modules\Accounting\Controllers\FinancialReportController;
use App\Modules\Accounting\Controllers\FiscalPeriodController;
use App\Modules\Auth\Controllers\ImpersonationController;
use App\Modules\Auth\Controllers\LoginController;
use App\Modules\Auth\Controllers\PasswordResetController;
use App\Modules\Auth\Controllers\ProfileController;
use App\Modules\Auth\Controllers\UserController;
use App\Modules\Cash\Controllers\BankController;
use App\Modules\Cash\Controllers\CashBoxController;
use App\Modules\Cash\Controllers\ReceiptController;
use App\Modules\Core\Controllers\CompanyController;
use App\Modules\Core\Controllers\DashboardController;
use App\Modules\Core\Controllers\EstablishmentController;
use App\Modules\Core\Controllers\OnboardingController;
use App\Modules\Core\Controllers\ResolutionController;
use App\Modules\Core\Controllers\SetupController;
use App\Modules\Core\Controllers\ThirdPartyController;
use App\Modules\Core\Controllers\WarehouseController;
use App\Modules\Inventory\Controllers\ItemCategoryController;
use App\Modules\Inventory\Controllers\ItemController;
use App\Modules\Inventory\Controllers\TransferController;
use App\Modules\Invoice\Controllers\InvoiceController;
use App\Modules\Payroll\Controllers\EmployeeController;
use App\Modules\Payroll\Controllers\PayrollController;
use App\Modules\Payroll\Controllers\SocialBenefitController;
use App\Modules\POS\Controllers\PosController;
use App\Modules\Purchases\Controllers\PurchaseController;
use App\Modules\Audit\Controllers\AuditController;
use App\Modules\Audit\Controllers\NotificationController;
use App\Modules\Tenant\Controllers\SubscriptionController;
use App\Modules\Reports\Controllers\ReportController;
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

/*
|--------------------------------------------------------------------------
| Rutas del Tenant (por empresa)
|--------------------------------------------------------------------------
| Estas rutas corren dentro del schema del tenant (empresa cliente).
| Son accesibles desde el subdominio: {empresa}.pymepossaas-app.test
|
| InitializeTenancyByDomain identifica el tenant por dominio completo
| (ej: santinet.pymepossaas-app.test) y cambia la conexión al schema.
*/

Route::middleware([
    'web',
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class,
])->group(function () {

    // ─── Auth (públicas dentro del tenant) ────────────────────────────────
    Route::get('/impersonate/{token}', [ImpersonationController::class, 'consume'])
        ->name('impersonate.consume');

    Route::middleware('guest')->group(function () {
        Route::get('/login', [LoginController::class, 'show'])->name('login');
        Route::post('/login', [LoginController::class, 'store'])->name('login.store')->middleware('throttle:tenant-login');

        // Recuperación de contraseña
        Route::get('/forgot-password',         [PasswordResetController::class, 'showForgotForm'])->name('password.request');
        Route::post('/forgot-password',        [PasswordResetController::class, 'sendResetLink'])->name('password.email')->middleware('throttle:5,1');
        Route::get('/reset-password/{token}',  [PasswordResetController::class, 'showResetForm'])->name('password.reset');
        Route::post('/reset-password',         [PasswordResetController::class, 'resetPassword'])->name('password.update');
    });

    Route::post('/logout', [LoginController::class, 'destroy'])
        ->middleware('auth')
        ->name('logout');

    // ─── Rutas protegidas ─────────────────────────────────────────────────
    Route::middleware(['auth'])->group(function () {

        // ─── Onboarding wizard (configuración inicial) ────────────────────
        Route::prefix('onboarding')->name('onboarding.')->group(function () {
            Route::get('/', [OnboardingController::class, 'show'])->name('show');
            Route::post('/company', [OnboardingController::class, 'saveCompany'])->name('company');
            Route::post('/resolution', [OnboardingController::class, 'saveResolution'])->name('resolution');
            Route::post('/complete', [OnboardingController::class, 'complete'])->name('complete');
            Route::get('/municipalities/{departmentId}', [OnboardingController::class, 'municipalities'])->name('municipalities');
        });

        // ─── Rutas que requieren onboarding completado ────────────────────
        Route::middleware(['onboarding'])->group(function () {

            Route::get('/dashboard', DashboardController::class)->name('dashboard');
            Route::get('/setup', [SetupController::class, 'index'])->name('setup');

            // ─── Suscripción ──────────────────────────────────────────────
            Route::get('/subscription', [SubscriptionController::class, 'show'])->name('subscription');

            // ─── Perfil del usuario ───────────────────────────────────────
            Route::get('/profile', [ProfileController::class, 'show'])->name('profile');
            Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
            Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');

            Route::middleware(['tenant.operational'])->group(function () {

            // ─── Usuarios del tenant ─────────────────────────────────────
            Route::prefix('users')->name('users.')->middleware('permission:users.view')->group(function () {
                Route::get('/',              [UserController::class, 'index'])->name('index');
                Route::get('/create',        [UserController::class, 'create'])->name('create');
                Route::post('/',             [UserController::class, 'store'])->name('store');
                Route::get('/{user}/edit',   [UserController::class, 'edit'])->name('edit');
                Route::put('/{user}',        [UserController::class, 'update'])->name('update');
                Route::delete('/{user}',     [UserController::class, 'destroy'])->name('destroy');
                Route::patch('/{user}/toggle', [UserController::class, 'toggleStatus'])->name('toggle');
            });

            // ─── Auditoría ───────────────────────────────────────────────
            Route::prefix('audit')->name('audit.')->group(function () {
                Route::get('/activity', [AuditController::class, 'activity'])->name('activity');
                Route::get('/api-logs', [AuditController::class, 'apiLogs'])->name('api-logs');
            });

            // ─── Notificaciones (API JSON para la campana del AppLayout) ──
            Route::prefix('notifications')->name('notifications.')->group(function () {
                Route::get('/', [NotificationController::class, 'index'])->name('index');
                Route::patch('/{notification}/read', [NotificationController::class, 'markRead'])->name('read');
                Route::patch('/mark-all-read', [NotificationController::class, 'markAllRead'])->name('read-all');
            });

            // ─── Configuración de empresa (Fase 5) ───────────────────────
            Route::prefix('config')->name('config.')->middleware('permission:config.view')->group(function () {
                // Establecimientos
                Route::get('/establishments', [EstablishmentController::class, 'index'])->name('establishments');
                Route::post('/establishments', [EstablishmentController::class, 'store'])->name('establishments.store');
                Route::put('/establishments/{establishment}', [EstablishmentController::class, 'update'])->name('establishments.update');
                Route::delete('/establishments/{establishment}', [EstablishmentController::class, 'destroy'])->name('establishments.destroy');

                // Bodegas
                Route::get('/warehouses', [WarehouseController::class, 'index'])->name('warehouses');
                Route::post('/warehouses', [WarehouseController::class, 'store'])->name('warehouses.store');
                Route::put('/warehouses/{warehouse}', [WarehouseController::class, 'update'])->name('warehouses.update');
                Route::delete('/warehouses/{warehouse}', [WarehouseController::class, 'destroy'])->name('warehouses.destroy');

                // Configuración de empresa
                Route::get('/company', [CompanyController::class, 'show'])->name('company');
                Route::put('/company', [CompanyController::class, 'update'])->name('company.update');
                Route::post('/company/test-nextpyme', [CompanyController::class, 'testNextpyme'])->name('company.test-nextpyme');

                // Resoluciones DIAN
                Route::get('/resolutions', [ResolutionController::class, 'index'])->name('resolutions');
                Route::post('/resolutions', [ResolutionController::class, 'store'])->name('resolutions.store');
                Route::put('/resolutions/{resolution}', [ResolutionController::class, 'update'])->name('resolutions.update');
                Route::delete('/resolutions/{resolution}', [ResolutionController::class, 'destroy'])->name('resolutions.destroy');
                Route::patch('/resolutions/{resolution}/toggle', [ResolutionController::class, 'toggle'])->name('resolutions.toggle');
            });

            // ─── Terceros (Fase 6) ────────────────────────────────────────
            Route::prefix('third-parties')->name('third-parties.')->group(function () {
                Route::get('/', [ThirdPartyController::class, 'index'])->name('index');
                Route::get('/create', [ThirdPartyController::class, 'create'])->name('create');
                Route::post('/', [ThirdPartyController::class, 'store'])->name('store');
                Route::get('/import/template', [ThirdPartyController::class, 'downloadTemplate'])->name('import.template');
                Route::post('/import', [ThirdPartyController::class, 'import'])->name('import');
                Route::get('/{thirdParty}/edit', [ThirdPartyController::class, 'edit'])->name('edit');
                Route::put('/{thirdParty}', [ThirdPartyController::class, 'update'])->name('update');
                Route::delete('/{thirdParty}', [ThirdPartyController::class, 'destroy'])->name('destroy');
                Route::patch('/{thirdParty}/toggle', [ThirdPartyController::class, 'toggleStatus'])->name('toggle');
                Route::get('/{thirdParty}/retentions', [ThirdPartyController::class, 'retentions'])->name('retentions');
                Route::post('/{thirdParty}/retentions', [ThirdPartyController::class, 'storeRetentions'])->name('retentions.store');
            });

            // ─── Inventario (Fase 7) ──────────────────────────────────────
            Route::prefix('inventory')->name('inventory.')->middleware('permission:inventory.view')->group(function () {

                // Ítems / productos
                Route::get('/', [ItemController::class, 'index'])->name('index');
                Route::get('/items', [ItemController::class, 'index'])->name('items.index');
                Route::post('/items', [ItemController::class, 'store'])->name('items.store');
                Route::get('/create', [ItemController::class, 'create'])->name('create');
                Route::post('/', [ItemController::class, 'store'])->name('store');
                Route::get('/import/template', [ItemController::class, 'downloadTemplate'])->name('import.template');
                Route::post('/import', [ItemController::class, 'import'])->name('import');
                Route::get('/{item}/edit', [ItemController::class, 'edit'])->name('edit')->whereUuid('item');
                Route::put('/{item}', [ItemController::class, 'update'])->name('update')->whereUuid('item');
                Route::put('/items/{item}', [ItemController::class, 'update'])->name('items.update')->whereUuid('item');
                Route::delete('/{item}', [ItemController::class, 'destroy'])->name('destroy')->whereUuid('item');
                Route::delete('/items/{item}', [ItemController::class, 'destroy'])->name('items.destroy')->whereUuid('item');
                Route::patch('/{item}/toggle', [ItemController::class, 'toggleStatus'])->name('toggle')->whereUuid('item');

                // Categorías de ítems
                Route::prefix('categories')->name('categories.')->group(function () {
                    Route::get('/', [ItemCategoryController::class, 'index'])->name('index');
                    Route::post('/', [ItemCategoryController::class, 'store'])->name('store');
                    Route::put('/{itemCategory}', [ItemCategoryController::class, 'update'])->name('update');
                    Route::delete('/{itemCategory}', [ItemCategoryController::class, 'destroy'])->name('destroy');
                });

                // Traslados entre bodegas
                Route::prefix('transfers')->name('transfers.')->group(function () {
                    Route::get('/', [TransferController::class, 'index'])->name('index');
                    Route::get('/create', [TransferController::class, 'create'])->name('create');
                    Route::post('/', [TransferController::class, 'store'])->name('store');
                    Route::get('/{transfer}', [TransferController::class, 'show'])->name('show');
                    Route::post('/{transfer}/dispatch', [TransferController::class, 'dispatch'])->name('dispatch');
                    Route::post('/{transfer}/receive', [TransferController::class, 'receive'])->name('receive');
                    Route::post('/{transfer}/cancel', [TransferController::class, 'cancel'])->name('cancel');
                });
            });

            // ─── Facturación electrónica (Fase 8) ────────────────────────
            Route::prefix('invoices')->name('invoices.')->middleware('permission:invoices.view')->group(function () {
                Route::get('/', [InvoiceController::class, 'index'])->name('index');
                Route::get('/create', [InvoiceController::class, 'create'])->name('create');
                Route::post('/', [InvoiceController::class, 'store'])->name('store');
                Route::get('/{document}', [InvoiceController::class, 'show'])->name('show');
                Route::get('/{document}/edit', [InvoiceController::class, 'edit'])->name('edit');
                Route::put('/{document}', [InvoiceController::class, 'update'])->name('update');
                Route::delete('/{document}', [InvoiceController::class, 'destroy'])->name('destroy');
                Route::post('/{document}/credit-note', [InvoiceController::class, 'storeCreditNote'])->name('credit-note');
                Route::post('/{document}/debit-note',  [InvoiceController::class, 'storeDebitNote'])->name('debit-note');
                Route::post('/{document}/retry-dian',    [InvoiceController::class, 'retryDian'])->name('retry-dian')->middleware('throttle:dian-retry');
                Route::post('/{document}/radian-event', [InvoiceController::class, 'sendRadianEvent'])->name('radian-event');
                Route::get('/{document}/pdf',            [InvoiceController::class, 'downloadPdf'])->name('pdf');
                Route::post('/{document}/send-email',    [InvoiceController::class, 'sendEmail'])->name('send-email');
            });

            // ─── Caja y bancos ────────────────────────────────────────────
            Route::prefix('cash')->name('cash.')->group(function () {
                // Cajas de efectivo
                Route::get('/', [CashBoxController::class, 'index'])->name('index');
                Route::post('/boxes', [CashBoxController::class, 'store'])->name('boxes.store');
                Route::put('/boxes/{cashBox}', [CashBoxController::class, 'update'])->name('boxes.update');
                Route::delete('/boxes/{cashBox}', [CashBoxController::class, 'destroy'])->name('boxes.destroy');
                Route::get('/boxes/{cashBox}', [CashBoxController::class, 'show'])->name('boxes.show');
                Route::post('/boxes/{cashBox}/movements', [CashBoxController::class, 'storeMovement'])->name('boxes.movements.store');
                Route::post('/boxes/transfer', [CashBoxController::class, 'transfer'])->name('boxes.transfer');
                Route::get('/receipts', [ReceiptController::class, 'index'])->name('receipts.index');
                Route::post('/receipts', [ReceiptController::class, 'storeCashReceipt'])->name('receipts.store');
                Route::post('/payment-receipts', [ReceiptController::class, 'storePaymentReceipt'])->name('payment-receipts.store');

                // Bancos y cuentas bancarias
                Route::get('/banks', [BankController::class, 'index'])->name('banks.index');
                Route::post('/banks', [BankController::class, 'store'])->name('banks.store');
                Route::put('/banks/{bank}', [BankController::class, 'update'])->name('banks.update');
                Route::delete('/banks/{bank}', [BankController::class, 'destroy'])->name('banks.destroy');
                Route::post('/banks/{bank}/accounts', [BankController::class, 'storeAccount'])->name('banks.accounts.store');
                Route::put('/banks/accounts/{account}', [BankController::class, 'updateAccount'])->name('banks.accounts.update');
                Route::delete('/banks/accounts/{account}', [BankController::class, 'destroyAccount'])->name('banks.accounts.destroy');
                Route::post('/banks/accounts/{account}/movements', [BankController::class, 'storeMovement'])->name('banks.accounts.movements.store');
            });

                // Conciliación bancaria
                Route::prefix('reconciliations')->name('reconciliations.')->group(function () {
                    Route::get('/', [\App\Modules\Cash\Controllers\BankReconciliationController::class, 'index'])->name('index');
                    Route::post('/', [\App\Modules\Cash\Controllers\BankReconciliationController::class, 'store'])->name('store');
                    Route::get('/{reconciliation}', [\App\Modules\Cash\Controllers\BankReconciliationController::class, 'show'])->name('show');
                    Route::post('/{reconciliation}/statement-line', [\App\Modules\Cash\Controllers\BankReconciliationController::class, 'addStatementLine'])->name('statement-line');
                    Route::patch('/lines/{line}/toggle', [\App\Modules\Cash\Controllers\BankReconciliationController::class, 'toggleMatch'])->name('lines.toggle');
                    Route::post('/{reconciliation}/reconcile', [\App\Modules\Cash\Controllers\BankReconciliationController::class, 'reconcile'])->name('reconcile');
                });
            });

            // ─── POS ──────────────────────────────────────────────────────
            Route::prefix('pos')->name('pos.')->middleware('permission:pos.view')->group(function () {
                // Selección de terminales
                Route::get('/', [PosController::class, 'index'])->name('index');

                // Gestión de terminales (admin)
                Route::post('/terminals', [PosController::class, 'storeTerminal'])->name('terminals.store');
                Route::put('/terminals/{terminal}', [PosController::class, 'updateTerminal'])->name('terminals.update');
                Route::delete('/terminals/{terminal}', [PosController::class, 'destroyTerminal'])->name('terminals.destroy');

                // Turnos de caja
                Route::post('/{terminal}/open', [PosController::class, 'openShift'])->name('open');
                Route::get('/{terminal}/shift-summary', [PosController::class, 'shiftSummary'])->name('shift-summary');
                Route::post('/{terminal}/close', [PosController::class, 'closeShift'])->name('close');

                // Pantalla de venta
                Route::get('/{terminal}', [PosController::class, 'terminal'])->name('terminal');
                Route::post('/{terminal}/sale', [PosController::class, 'store'])->name('sale');
            });

            // ─── Contabilidad ─────────────────────────────────────────────
            Route::prefix('accounting')->name('accounting.')->middleware('role:admin|contador')->group(function () {
                Route::get('/audit', [AccountingController::class,        'audit'])->name('audit');
                Route::post('/audit/regenerate', [AccountingController::class,        'regenerate'])->name('audit.regenerate');
                Route::get('/auxiliary', [AccountingController::class,        'auxiliary'])->name('auxiliary');
                Route::get('/differences', [AccountingController::class,        'differences'])->name('differences');
                Route::post('/differences/adjust', [AccountingController::class,        'storeAdjustment'])->name('differences.adjust');
                Route::get('/adjustments', [AccountingController::class,        'adjustments'])->name('adjustments');
                Route::get('/adjustments/export', [AccountingController::class,        'exportAdjustments'])->name('adjustments.export');
                Route::post('/adjustments/{voucher}/reverse', [AccountingController::class,        'reverseAdjustment'])->name('adjustments.reverse');
                Route::get('/journal', [AccountingController::class,        'journal'])->name('journal');
                Route::get('/journal/export', [AccountingController::class,        'exportJournal'])->name('journal.export');
                Route::get('/ledger', [AccountingController::class,        'ledger'])->name('ledger');
                Route::get('/trial-balance', [AccountingController::class,        'trialBalance'])->name('trial-balance');
                Route::get('/trial-balance/export', [AccountingController::class,        'exportTrialBalance'])->name('trial-balance.export');
                Route::get('/income-statement', [FinancialReportController::class,   'incomeStatement'])->name('income-statement');
                Route::get('/income-statement/export', [FinancialReportController::class,   'exportIncomeStatement'])->name('income-statement.export');
                Route::get('/balance-sheet', [FinancialReportController::class,   'balanceSheet'])->name('balance-sheet');
                Route::get('/balance-sheet/export', [FinancialReportController::class,   'exportBalanceSheet'])->name('balance-sheet.export');
                // Presupuesto vs Real
                Route::prefix('budget')->name('budget.')->group(function () {
                    Route::get('/', [\App\Modules\Accounting\Controllers\BudgetController::class, 'index'])->name('index');
                    Route::get('/create', [\App\Modules\Accounting\Controllers\BudgetController::class, 'create'])->name('create');
                    Route::post('/', [\App\Modules\Accounting\Controllers\BudgetController::class, 'store'])->name('store');
                    Route::post('/{budget}/approve', [\App\Modules\Accounting\Controllers\BudgetController::class, 'approve'])->name('approve');
                    Route::get('/{budget}/compare', [\App\Modules\Accounting\Controllers\BudgetController::class, 'compare'])->name('compare');
                    Route::get('/{budget}/export', [\App\Modules\Accounting\Controllers\BudgetController::class, 'export'])->name('export');
                });
                // Períodos fiscales (cierre contable)
                Route::prefix('fiscal-periods')->name('fiscal-periods.')->group(function () {
                    Route::get('/', [FiscalPeriodController::class, 'index'])->name('index');
                    Route::post('/{fiscalPeriod}/close', [FiscalPeriodController::class, 'close'])->name('close');
                    Route::post('/{fiscalPeriod}/reopen', [FiscalPeriodController::class, 'reopen'])->name('reopen');
                    Route::post('/close-year', [FiscalPeriodController::class, 'closeYear'])->name('close-year');
                });
                // Configuración de conceptos contables
                Route::prefix('concepts')->name('concepts.')->group(function () {
                    Route::get('/', [AccountingConceptController::class, 'index'])->name('index');
                    Route::get('/create', [AccountingConceptController::class, 'create'])->name('create');
                    Route::post('/', [AccountingConceptController::class, 'store'])->name('store');
                    Route::get('/{concept}/edit', [AccountingConceptController::class, 'edit'])->name('edit');
                    Route::put('/{concept}', [AccountingConceptController::class, 'update'])->name('update');
                    Route::delete('/{concept}', [AccountingConceptController::class, 'destroy'])->name('destroy');
                });
            });

            // ─── Compras ──────────────────────────────────────────────────
            Route::prefix('purchases')->name('purchases.')->middleware('permission:purchases.view')->group(function () {
                Route::get('/', [PurchaseController::class, 'index'])->name('index');
                Route::get('/create', [PurchaseController::class, 'create'])->name('create');
                Route::post('/', [PurchaseController::class, 'store'])->name('store');
                Route::get('/{purchase}', [PurchaseController::class, 'show'])->name('show');
                Route::post('/{purchase}/approve', [PurchaseController::class, 'approve'])->name('approve');
                Route::post('/{purchase}/receive', [PurchaseController::class, 'receive'])->name('receive');
                Route::post('/{purchase}/annul', [PurchaseController::class, 'annul'])->name('annul');
                Route::post('/{purchase}/support-document', [PurchaseController::class, 'storeSupportDocument'])->name('support-document');
            });

            // ─── Nómina ───────────────────────────────────────────────────
            Route::prefix('payroll')->name('payroll.')->middleware('role:admin')->group(function () {
                // Empleados
                Route::prefix('employees')->name('employees.')->group(function () {
                    Route::get('/', [EmployeeController::class, 'index'])->name('index');
                    Route::get('/create', [EmployeeController::class, 'create'])->name('create');
                    Route::post('/', [EmployeeController::class, 'store'])->name('store');
                    Route::get('/import/template', [EmployeeController::class, 'downloadTemplate'])->name('import.template');
                    Route::post('/import', [EmployeeController::class, 'import'])->name('import');
                    Route::get('/{employee}', [EmployeeController::class, 'show'])->name('show');
                    Route::get('/{employee}/edit', [EmployeeController::class, 'edit'])->name('edit');
                    Route::put('/{employee}', [EmployeeController::class, 'update'])->name('update');
                    Route::delete('/{employee}', [EmployeeController::class, 'destroy'])->name('destroy');
                });
                // Liquidaciones
                Route::prefix('runs')->name('runs.')->group(function () {
                    Route::get('/', [PayrollController::class, 'index'])->name('index');
                    Route::get('/create', [PayrollController::class, 'create'])->name('create');
                    Route::post('/', [PayrollController::class, 'store'])->name('store');
                    Route::get('/{run}', [PayrollController::class, 'show'])->name('show');
                    Route::get('/{run}/export', [PayrollController::class, 'export'])->name('export');
                    Route::post('/{run}/approve', [PayrollController::class, 'approve'])->name('approve');
                    Route::post('/{run}/mark-paid', [PayrollController::class, 'markPaid'])->name('mark-paid');
                    Route::post('/{run}/cancel', [PayrollController::class, 'cancel'])->name('cancel');
                    Route::post('/{run}/send-nes', [PayrollController::class, 'sendNES'])->name('send-nes');
                });
                // Novedades
                Route::prefix('novelties')->name('novelties.')->group(function () {
                    Route::get('/', [PayrollController::class, 'noveltyIndex'])->name('index');
                    Route::post('/', [PayrollController::class, 'noveltyStore'])->name('store');
                    Route::delete('/{novelty}', [PayrollController::class, 'noveltyDestroy'])->name('destroy');
                });
                // Prestaciones sociales
                Route::prefix('benefits')->name('benefits.')->group(function () {
                    Route::get('/', [SocialBenefitController::class, 'index'])->name('index');
                    Route::get('/calculate', [SocialBenefitController::class, 'calculate'])->name('calculate');
                    Route::post('/', [SocialBenefitController::class, 'store'])->name('store');
                    Route::post('/{benefit}/pay', [SocialBenefitController::class, 'pay'])->name('pay');
                    Route::delete('/{benefit}', [SocialBenefitController::class, 'destroy'])->name('destroy');
                });
            });

            // ─── Reportes ─────────────────────────────────────────────────
            Route::prefix('reports')->name('reports.')->middleware('permission:reports.view')->group(function () {
                Route::get('/sales', [ReportController::class, 'sales'])->name('sales');
                Route::get('/sales/export', [ReportController::class, 'exportSales'])->name('sales.export');
                Route::get('/receivables', [ReportController::class, 'receivables'])->name('receivables');
                Route::get('/payables', [ReportController::class, 'payables'])->name('payables');
                Route::get('/cash', [ReportController::class, 'cash'])->name('cash');
                Route::get('/cash/export', [ReportController::class, 'exportCash'])->name('cash.export');
                Route::get('/inventory', [ReportController::class, 'inventory'])->name('inventory');
                Route::get('/inventory/export', [ReportController::class, 'exportInventory'])->name('inventory.export');
                Route::get('/payroll', [ReportController::class, 'payroll'])->name('payroll');
                Route::get('/payroll/export', [ReportController::class, 'exportPayroll'])->name('payroll.export');
                Route::get('/kardex', [ReportController::class, 'kardex'])->name('kardex');
                Route::get('/kardex/export', [ReportController::class, 'exportKardex'])->name('kardex.export');
            });

            }); // fin middleware tenant.operational

        }); // fin middleware onboarding

    });

