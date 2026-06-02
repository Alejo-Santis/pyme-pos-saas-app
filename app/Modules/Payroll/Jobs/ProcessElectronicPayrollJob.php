<?php

namespace App\Modules\Payroll\Jobs;

use App\Modules\Audit\Services\NotificationService;
use App\Modules\Core\Models\Company;
use App\Modules\Invoice\Services\ApiNextpymeService;
use App\Modules\Payroll\Builders\NESJsonBuilder;
use App\Modules\Payroll\Models\PayrollElectronicSending;
use App\Modules\Payroll\Models\PayrollRun;
use App\Modules\Payroll\Models\PayrollRunEmployee;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Envía la Nómina Electrónica (NES) a la DIAN vía Nextpyme.
 *
 * Un job por empleado por liquidación para facilitar reintentos granulares.
 * Endpoint: POST /ubl2.1/payroll
 *
 * Después de que todos los empleados de la liquidación son enviados,
 * el controlador o un comando actualiza el estado global del PayrollRun.
 */
class ProcessElectronicPayrollJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int   $timeout  = 120;
    public int   $tries    = 3;
    public array $backoff  = [0, 10, 30];

    public function __construct(
        private readonly string $payrollRunId,
        private readonly string $payrollRunEmployeeId,
        private readonly int    $attempt = 1
    ) {}

    public function handle(ApiNextpymeService $api): void
    {
        $run     = PayrollRun::findOrFail($this->payrollRunId);
        $detail  = PayrollRunEmployee::with(['employee', 'contract'])->findOrFail($this->payrollRunEmployeeId);
        $company = Company::first();

        if (! $company) {
            Log::error('ProcessElectronicPayrollJob: empresa no configurada.');
            return;
        }

        // Obtener o crear registro de seguimiento
        $sending = PayrollElectronicSending::firstOrCreate(
            [
                'payroll_run_id'          => $run->id,
                'payroll_run_employee_id' => $detail->id,
            ],
            ['status' => PayrollElectronicSending::STATUS_PENDING, 'attempts' => 0]
        );

        if ($sending->isSent()) {
            Log::debug("NES: empleado {$detail->id} ya enviado.");
            return;
        }

        Log::info("NES: enviando empleado {$detail->employee?->full_name} (intento {$this->attempt}/{$this->tries})", [
            'run_id'    => $run->id,
            'detail_id' => $detail->id,
        ]);

        // Construir JSON NES
        $payload = NESJsonBuilder::fromEmployee($run, $detail, $company);

        // Enviar a Nextpyme
        $response = $api->makeRequest(
            method:    'POST',
            endpoint:  '/ubl2.1/payroll',
            parameters: $payload,
            documentId: null,
            operation: 'send_nes',
            attempt:   $this->attempt,
        );

        $sending->increment('attempts');

        if ($response['statusCode'] !== 200) {
            $sending->update([
                'status'        => PayrollElectronicSending::STATUS_FAILED,
                'error_message' => ['http' => $response['statusCode'], 'msg' => $response['message'] ?? null],
                'response_api'  => $response['data'] ?? [],
            ]);
            throw new \Exception("NES HTTP {$response['statusCode']} para empleado {$detail->id}");
        }

        $data = $response['data'] ?? [];

        if ($data['success'] ?? false) {
            $sending->update([
                'status'      => PayrollElectronicSending::STATUS_SENT,
                'cune'        => $data['cune'] ?? null,
                'response_api'=> $data,
                'sent_at'     => now(),
                'error_message' => null,
            ]);

            Log::info("NES: empleado {$detail->id} enviado. CUNE: " . ($data['cune'] ?? 'N/A'));
        } else {
            $sending->update([
                'status'        => PayrollElectronicSending::STATUS_FAILED,
                'error_message' => ['message' => $data['message'] ?? 'Error desconocido', 'errors' => $data['errors'] ?? []],
                'response_api'  => $data,
            ]);
            Log::warning("NES: rechazo DIAN para empleado {$detail->id}: " . ($data['message'] ?? ''));
        }

        // Actualizar estado global del PayrollRun después de cada envío
        $this->updateRunNesStatus($run);
    }

    public function failed(\Throwable $exception): void
    {
        Log::critical("ProcessElectronicPayrollJob: fallo definitivo", [
            'run_id'    => $this->payrollRunId,
            'detail_id' => $this->payrollRunEmployeeId,
            'error'     => $exception->getMessage(),
        ]);

        PayrollElectronicSending::where('payroll_run_id', $this->payrollRunId)
            ->where('payroll_run_employee_id', $this->payrollRunEmployeeId)
            ->update([
                'status'        => PayrollElectronicSending::STATUS_FAILED,
                'error_message' => ['message' => 'Fallo definitivo: ' . $exception->getMessage()],
            ]);

        $run    = PayrollRun::find($this->payrollRunId);
        $detail = PayrollRunEmployee::with('employee')->find($this->payrollRunEmployeeId);
        NotificationService::nesFailed(
            $run?->name ?? 'Liquidación',
            $detail?->employee?->full_name ?? 'Empleado',
            substr($exception->getMessage(), 0, 200)
        );
    }

    private function updateRunNesStatus(PayrollRun $run): void
    {
        $total  = PayrollElectronicSending::where('payroll_run_id', $run->id)->count();
        $sent   = PayrollElectronicSending::where('payroll_run_id', $run->id)->where('status', 'sent')->count();
        $failed = PayrollElectronicSending::where('payroll_run_id', $run->id)->where('status', 'failed')->count();

        $nesStatus = match (true) {
            $sent === $total && $total > 0 => 'sent',
            $failed > 0 && $sent === 0     => 'failed',
            $sent > 0                      => 'partial',
            default                        => 'processing',
        };

        $run->update([
            'nes_status'  => $nesStatus,
            'nes_sent_at' => $nesStatus === 'sent' ? now() : $run->nes_sent_at,
        ]);
    }
}
