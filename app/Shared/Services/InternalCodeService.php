<?php

namespace App\Shared\Services;

use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Genera códigos internos únicos de forma transaccional y atómica.
 * Adaptado de xedoc-laravel-svelte/app/Services/InternalCodeService.php
 */
class InternalCodeService
{
    private const MAX_RETRIES = 5;

    /**
     * Genera internal_code para cualquier modelo/tabla de forma atómica.
     *
     * @param string $modelClass Clase del modelo (ej: App\Modules\Invoice\Models\Document)
     * @param string $prefix     Prefijo del código (ej: 'D1', 'ART', 'R')
     * @param array  $conditions Condiciones adicionales para filtrar
     */
    public function generateInternalCode(string $modelClass, string $prefix, array $conditions = []): string
    {
        $retries = 0;

        while ($retries < self::MAX_RETRIES) {
            try {
                return $this->generateCodeWithTransaction($modelClass, $prefix, $conditions);
            } catch (Exception $e) {
                $retries++;

                if ($retries >= self::MAX_RETRIES) {
                    Log::error('Error generando internal_code tras ' . self::MAX_RETRIES . ' intentos', [
                        'model'      => $modelClass,
                        'prefix'     => $prefix,
                        'conditions' => $conditions,
                        'error'      => $e->getMessage(),
                    ]);
                    throw new Exception('No se pudo generar el código interno: ' . $e->getMessage());
                }

                // Backoff exponencial con jitter
                $delay  = min(1_000_000, 50_000 * (2 ** ($retries - 1)));
                $jitter = random_int(0, (int) ($delay / 4));
                usleep($delay + $jitter);
            }
        }

        throw new Exception('Error inesperado generando código interno');
    }

    /**
     * Método específico para documentos por tipo de operación.
     * Genera prefijos como: D1-00000001 (venta), D4-00000001 (POS), etc.
     */
    public function reserveInternalCode(int $typeOperationId): string
    {
        return $this->generateInternalCode(
            \App\Modules\Invoice\Models\Document::class,
            'D' . $typeOperationId,
            ['type_document_operation_id' => $typeOperationId]
        );
    }

    // ── Privados ──────────────────────────────────────────────────────────

    private function generateCodeWithTransaction(string $modelClass, string $prefix, array $conditions): string
    {
        return DB::transaction(function () use ($modelClass, $prefix, $conditions) {
            $model = new $modelClass;
            $table = $model->getTable();

            $query = DB::table($table)
                ->where('internal_code', 'LIKE', $prefix . '%')
                ->whereNotNull('internal_code');

            foreach ($conditions as $field => $value) {
                $query->where($field, $value);
            }

            $lastRecord = $query
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();

            $nextNumber   = $this->getNextNumber($lastRecord, $prefix);
            $internalCode = $prefix . '-' . str_pad($nextNumber, 8, '0', STR_PAD_LEFT);

            // Verificación final de unicidad
            $existsQuery = DB::table($table)->where('internal_code', $internalCode);
            foreach ($conditions as $field => $value) {
                $existsQuery->where($field, $value);
            }

            if ($existsQuery->exists()) {
                throw new Exception("Código duplicado: {$internalCode}");
            }

            return $internalCode;
        }, 3);
    }

    private function getNextNumber($lastRecord, string $prefix): int
    {
        if (! $lastRecord || ! $lastRecord->internal_code) {
            return 1;
        }

        if (preg_match('/^' . preg_quote($prefix, '/') . '-(\d+)$/', $lastRecord->internal_code, $matches)) {
            return (int) $matches[1] + 1;
        }

        return 1;
    }
}
