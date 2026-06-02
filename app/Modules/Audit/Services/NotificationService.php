<?php

namespace App\Modules\Audit\Services;

use App\Modules\Audit\Models\SystemNotification;
use App\Modules\Invoice\Models\Document;
use App\Modules\Inventory\Models\ItemWarehouse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Servicio de notificaciones transaccionales.
 *
 * Se llama desde Jobs, Services y Observers cuando ocurre un evento relevante.
 * Las notificaciones aparecen en la campana del AppLayout.
 */
class NotificationService
{
    /**
     * Notifica que una factura fue rechazada por la DIAN.
     */
    public static function dianRejection(Document $document, string $reason): void
    {
        self::create(
            type:  SystemNotification::TYPE_DIAN_REJECTION,
            title: "Factura rechazada por DIAN: {$document->prefix}{$document->number}",
            body:  $reason,
            icon:  'mdi-alert-circle-outline',
            color: 'rose',
            data:  ['document_id' => $document->id, 'internal_code' => $document->internal_code],
        );
    }

    /**
     * Notifica que el stock de un producto bajó del mínimo.
     */
    public static function lowStock(string $itemName, string $itemId, float $stock, float $minStock): void
    {
        // Evitar notificaciones duplicadas (solo una por producto en las últimas 24 h)
        $exists = SystemNotification::where('type', SystemNotification::TYPE_LOW_STOCK)
            ->where('created_at', '>=', now()->subDay())
            ->whereJsonContains('data->item_id', $itemId)
            ->exists();

        if ($exists) return;

        self::create(
            type:  SystemNotification::TYPE_LOW_STOCK,
            title: "Stock bajo: {$itemName}",
            body:  "El stock actual ({$stock}) está por debajo del mínimo configurado ({$minStock}).",
            icon:  'mdi-package-variant-closed',
            color: 'amber',
            data:  ['item_id' => $itemId, 'stock' => $stock, 'min_stock' => $minStock],
        );
    }

    /**
     * Notifica facturas de clientes vencidas (CXC).
     * Llamar desde un comando programado diario.
     */
    public static function checkReceivablesDue(int $daysOverdue = 0): int
    {
        $overdue = Document::query()
            ->where('paid', false)
            ->where('annulled', false)
            ->whereIn('type_document_operation_id', [1, 2]) // FEV + POS
            ->where('balance', '>', 0)
            ->whereNotNull('payment_forms')
            ->get()
            ->filter(function ($doc) use ($daysOverdue) {
                $forms = is_array($doc->payment_forms) ? $doc->payment_forms : [];
                $dueDate = collect($forms)->pluck('due_date')->filter()->min();
                return $dueDate && now()->startOfDay()->gt($dueDate) &&
                       now()->diffInDays($dueDate) >= $daysOverdue;
            });

        $count = 0;
        foreach ($overdue as $doc) {
            $exists = SystemNotification::where('type', SystemNotification::TYPE_RECEIVABLE_DUE)
                ->where('created_at', '>=', now()->startOfDay())
                ->whereJsonContains('data->document_id', $doc->id)
                ->exists();

            if (! $exists) {
                self::create(
                    type:  SystemNotification::TYPE_RECEIVABLE_DUE,
                    title: "Cartera vencida: {$doc->prefix}{$doc->number}",
                    body:  "Factura de " . ($doc->thirdParty?->full_name ?? 'cliente') . " — Saldo: $ " . number_format((float) $doc->balance, 0, ',', '.'),
                    icon:  'mdi-cash-clock',
                    color: 'amber',
                    data:  ['document_id' => $doc->id, 'internal_code' => $doc->internal_code],
                );
                $count++;
            }
        }

        return $count;
    }

    /**
     * Notifica fallo definitivo de NES (nómina electrónica).
     */
    public static function nesFailed(string $runName, string $employeeName, string $error): void
    {
        self::create(
            type:  SystemNotification::TYPE_NES_FAILED,
            title: "Fallo NES: {$runName} — {$employeeName}",
            body:  $error,
            icon:  'mdi-account-alert-outline',
            color: 'rose',
            data:  ['run' => $runName, 'employee' => $employeeName],
        );
    }

    /**
     * Notifica que un período contable está próximo a cerrar (recordatorio).
     */
    public static function periodClosingReminder(int $year, int $month): void
    {
        $exists = SystemNotification::where('type', SystemNotification::TYPE_PERIOD_CLOSING)
            ->where('created_at', '>=', now()->subWeek())
            ->whereJsonContains('data->year', $year)
            ->whereJsonContains('data->month', $month)
            ->exists();

        if ($exists) return;

        $monthName = \Carbon\Carbon::create($year, $month, 1)->locale('es')->monthName;

        self::create(
            type:  SystemNotification::TYPE_PERIOD_CLOSING,
            title: "Recordatorio: cierre de {$monthName} {$year}",
            body:  "El período {$monthName} {$year} aún está abierto. Recuerde cerrar el período contable cuando finalice.",
            icon:  'mdi-calendar-lock-outline',
            color: 'blue',
            data:  ['year' => $year, 'month' => $month],
        );
    }

    // ── Interno ───────────────────────────────────────────────────────────

    private static function create(
        string  $type,
        string  $title,
        string  $body,
        string  $icon  = 'mdi-bell',
        string  $color = 'blue',
        array   $data  = [],
        ?string $userId = null,
    ): void {
        try {
            SystemNotification::create(compact('type', 'title', 'body', 'icon', 'color', 'data', 'userId'));
        } catch (\Throwable $e) {
            Log::warning("NotificationService: error al crear notificación [{$type}]: " . $e->getMessage());
        }
    }
}
