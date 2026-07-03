<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request;

class ActivityLoggerService
{
    /**
     * Register an activity in the audit log.
     */
    public static function log(
        string $action,
        string $module,
        string $entity,
        ?int $entityId = null,
        ?string $description = null,
        ?int $userId = null
    ): ActivityLog {
        try {
            if ($userId === null) {
                $user = Auth::guard('api')->user();
                $userId = $user?->id;
            }

            if ($userId === null) {
                return new ActivityLog();
            }

            return ActivityLog::create([
                'user_id' => $userId,
                'action' => $action,
                'module' => $module,
                'entity' => $entity,
                'entity_id' => $entityId,
                'description' => $description,
                'ip_address' => Request::ip(),
                'user_agent' => Request::userAgent(),
            ]);
        } catch (\Exception $e) {
            Log::error('Error registering activity log:', [
                'error' => $e->getMessage(),
                'action' => $action,
                'module' => $module,
                'entity' => $entity,
                'entity_id' => $entityId,
                'user_id' => $userId,
            ]);

            return new ActivityLog();
        }
    }

    /**
     * Register LOGIN action.
     */
    public static function logLogin(?int $userId = null): ActivityLog
    {
        return self::log(
            action: 'LOGIN',
            module: 'AUTHENTICATION',
            entity: 'users',
            entityId: $userId,
            description: 'User logged in',
            userId: $userId
        );
    }

    /**
     * Register LOGOUT action.
     */
    public static function logLogout(?int $userId = null): ActivityLog
    {
        return self::log(
            action: 'LOGOUT',
            module: 'AUTHENTICATION',
            entity: 'users',
            entityId: $userId,
            description: 'User logged out',
            userId: $userId
        );
    }

    /**
     * Register user registration action.
     */
    public static function logRegister(int $userId, string $email): ActivityLog
    {
        return self::log(
            action: 'REGISTER',
            module: 'AUTHENTICATION',
            entity: 'users',
            entityId: $userId,
            description: "New user registered: {$email}",
            userId: $userId
        );
    }

    /**
     * Register CREATE action.
     */
    public static function logCreate(
        string $module,
        string $entity,
        int $entityId,
        string $description = ''
    ): ActivityLog {
        return self::log(
            action: 'CREATE',
            module: $module,
            entity: $entity,
            entityId: $entityId,
            description: $description ?: "A new record was created in {$entity}"
        );
    }

    /**
     * Register UPDATE action.
     */
    public static function logUpdate(
        string $module,
        string $entity,
        int $entityId,
        string $description = ''
    ): ActivityLog {
        return self::log(
            action: 'UPDATE',
            module: $module,
            entity: $entity,
            entityId: $entityId,
            description: $description ?: "A record was updated in {$entity}"
        );
    }

    /**
     * Register DELETE action.
     */
    public static function logDelete(
        string $module,
        string $entity,
        int $entityId,
        string $description = ''
    ): ActivityLog {
        return self::log(
            action: 'DELETE',
            module: $module,
            entity: $entity,
            entityId: $entityId,
            description: $description ?: "A record was deleted in {$entity}"
        );
    }

    /**
     * Get activity logs with optional filters.
     */
    public static function getLogs(
        ?int $userId = null,
        ?string $action = null,
        ?string $module = null,
        int $limit = 20
    ) {
        $query = ActivityLog::query();

        if ($userId) {
            $query->where('user_id', $userId);
        }

        if ($action) {
            $query->where('action', $action);
        }

        if ($module) {
            $query->where('module', $module);
        }

        return $query->with('user')
            ->latest('created_at')
            ->paginate($limit);
    }
}