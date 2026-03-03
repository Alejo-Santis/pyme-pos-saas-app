<?php

namespace App\Modules\Tenant;

use Stancl\Tenancy\TenantDatabaseManagers\PostgreSQLSchemaManager as BaseManager;

/**
 * Manager personalizado para schema-based tenancy en PostgreSQL.
 *
 * Extiende el manager base con dos correcciones:
 * 1. Envuelve el schema del tenant en comillas dobles (soporta UUIDs con dashes)
 * 2. Incluye "public" en el search_path para que los catálogos globales
 *    (countries, municipalities, taxes, etc.) sean accesibles transparentemente
 *    desde el contexto del tenant sin prefijo de schema.
 *
 * Ejemplo: SET search_path = "tenant_uuid", public
 */
class PostgreSQLSchemaManager extends BaseManager
{
    /**
     * Configura la conexión de tenant con el search_path correcto.
     *
     * El tenant_schema va primero → sus tablas tienen prioridad.
     * "public" va segundo → los catálogos globales son accesibles como fallback.
     * Las FK constraints a tablas de public funcionan sin prefijo de schema.
     */
    public function makeConnectionConfig(array $baseConfig, string $databaseName): array
    {
        $baseConfig['search_path'] = '"' . $databaseName . '", public';

        return $baseConfig;
    }
}
