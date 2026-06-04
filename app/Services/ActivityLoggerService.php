<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class ActivityLoggerService
{
    /**
     * Registra una actividad en el log de auditoría.
     *
     * @param string $accion       Acción realizada (LOGIN, LOGOUT, CREATE, UPDATE, DELETE, etc.)
     * @param string $modulo       Módulo del sistema (USUARIOS, ROLES, PEDIDOS, etc.)
     * @param string $entidad      Nombre de la tabla/entidad (users, roles, etc.)
     * @param int|null $entidadId  ID de la entidad afectada
     * @param string|null $descripcion Descripción detallada de la acción
     * @param int|null $usuarioId  ID del usuario (si es null, usa el usuario autenticado)
     * 
     * @return ActivityLog Modelo de log creado
     */
    public static function log(
        string $accion,
        string $modulo,
        string $entidad,
        ?int $entidadId = null,
        ?string $descripcion = null,
        ?int $usuarioId = null
    ): ActivityLog {
        try {
            // Usar usuario autenticado si no se especifica uno
            if ($usuarioId === null) {
                $user = Auth::guard('api')->user();
                $usuarioId = $user?->id;
            }

            // Si no hay usuario autenticado, no registrar (opcional: cambiar según necesidad)
            if ($usuarioId === null) {
                return new ActivityLog();
            }

            return ActivityLog::create([
                'usuario_id' => $usuarioId,
                'accion' => $accion,
                'modulo' => $modulo,
                'entidad' => $entidad,
                'entidad_id' => $entidadId,
                'descripcion' => $descripcion,
                'ip_address' => Request::ip(),
                'user_agent' => Request::userAgent(),
                'creado_en' => now(),
            ]);
        } catch (\Exception $e) {
            // Log interno en caso de error, pero no interrumpir la aplicación
            \Illuminate\Support\Facades\Log::error('Error al registrar actividad:', [
                'error' => $e->getMessage(),
                'accion' => $accion,
                'modulo' => $modulo,
            ]);

            return new ActivityLog();
        }
    }

    /**
     * Registra una acción de LOGIN.
     */
    public static function logLogin(?int $usuarioId = null): ActivityLog
    {
        return self::log(
            accion: 'LOGIN',
            modulo: 'AUTENTICACION',
            entidad: 'users',
            entidadId: $usuarioId,
            descripcion: 'Usuario inició sesión',
            usuarioId: $usuarioId
        );
    }

    /**
     * Registra una acción de LOGOUT.
     */
    public static function logLogout(?int $usuarioId = null): ActivityLog
    {
        return self::log(
            accion: 'LOGOUT',
            modulo: 'AUTENTICACION',
            entidad: 'users',
            entidadId: $usuarioId,
            descripcion: 'Usuario cerró sesión',
            usuarioId: $usuarioId
        );
    }

    /**
     * Registra una acción de REGISTRO.
     */
    public static function logRegister(int $usuarioId, string $email): ActivityLog
    {
        return self::log(
            accion: 'REGISTER',
            modulo: 'AUTENTICACION',
            entidad: 'users',
            entidadId: $usuarioId,
            descripcion: "Nuevo usuario registrado: {$email}",
            usuarioId: $usuarioId
        );
    }

    /**
     * Registra una acción de CREACIÓN.
     */
    public static function logCreate(
        string $modulo,
        string $entidad,
        int $entidadId,
        string $descripcion = ''
    ): ActivityLog {
        return self::log(
            accion: 'CREATE',
            modulo: $modulo,
            entidad: $entidad,
            entidadId: $entidadId,
            descripcion: $descripcion ?: "Se creó un nuevo registro en {$entidad}"
        );
    }

    /**
     * Registra una acción de ACTUALIZACIÓN.
     */
    public static function logUpdate(
        string $modulo,
        string $entidad,
        int $entidadId,
        string $descripcion = ''
    ): ActivityLog {
        return self::log(
            accion: 'UPDATE',
            modulo: $modulo,
            entidad: $entidad,
            entidadId: $entidadId,
            descripcion: $descripcion ?: "Se actualizó un registro en {$entidad}"
        );
    }

    /**
     * Registra una acción de ELIMINACIÓN.
     */
    public static function logDelete(
        string $modulo,
        string $entidad,
        int $entidadId,
        string $descripcion = ''
    ): ActivityLog {
        return self::log(
            accion: 'DELETE',
            modulo: $modulo,
            entidad: $entidad,
            entidadId: $entidadId,
            descripcion: $descripcion ?: "Se eliminó un registro en {$entidad}"
        );
    }

    /**
     * Obtiene los logs de actividad con filtros opcionales.
     */
    public static function getLogs(
        ?int $usuarioId = null,
        ?string $accion = null,
        ?string $modulo = null,
        int $limit = 20
    ) {
        $query = ActivityLog::query();

        if ($usuarioId) {
            $query->where('usuario_id', $usuarioId);
        }

        if ($accion) {
            $query->where('accion', $accion);
        }

        if ($modulo) {
            $query->where('modulo', $modulo);
        }

        return $query->with('user')
            ->latest('creado_en')
            ->paginate($limit);
    }
}
