<?php
namespace App\Helpers;

use App\Services\ActivityLoggerService;

/**
 * Helper para compatibilidad hacia atrás.
 * Delegará al nuevo ActivityLoggerService.
 * 
 * DEPRECATED: Usa App\Services\ActivityLoggerService directamente en código nuevo.
 */
class ActivityLogHelper
{
    /**
     * Log simple - compatibilidad hacia atrás.
     * 
     * @deprecated Usa ActivityLoggerService::log() en su lugar
     */
    public static function log($module, $action, $description = null)
    {
        return ActivityLoggerService::log(
            accion: strtoupper($action),
            modulo: strtoupper($module),
            entidad: strtolower($module),
            descripcion: $description
        );
    }

    /**
     * Registra un login.
     * 
     * @deprecated Usa ActivityLoggerService::logLogin() en su lugar
     */
    public static function logLogin(?int $usuarioId = null)
    {
        return ActivityLoggerService::logLogin($usuarioId);
    }

    /**
     * Registra un logout.
     * 
     * @deprecated Usa ActivityLoggerService::logLogout() en su lugar
     */
    public static function logLogout(?int $usuarioId = null)
    {
        return ActivityLoggerService::logLogout($usuarioId);
    }
}