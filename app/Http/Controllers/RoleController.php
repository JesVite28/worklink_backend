<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Http\Requests\StoreRoleRequest;
use App\Services\ActivityLoggerService;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="Roles",
 *     description="Gestión de roles"
 * )
 */
class RoleController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/roles",
     *     operationId="listRoles",
     *     summary="Listar roles",
     *     tags={"Roles"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Roles obtenidos exitosamente"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="No autorizado. Token requerido o inválido"
     *     )
     * )
     */
    public function index(Request $request)
    {
        $query = Role::query();

        if ($request->has('search')) {
            $search = $request->search;
            $query->where('nombre', 'like', "%{$search}%")
                  ->orWhere('descripcion', 'like', "%{$search}%");
        }

        $roles = $query->orderBy('nombre')
            ->paginate($request->get('per_page', 15))
            ->through(function ($role) {
                return [
                    'id' => $role->id,
                    'nombre' => $role->nombre,
                    'descripcion' => $role->descripcion,
                    'usuarios_count' => $role->users()->count(),
                ];
            });

        return response()->json([
            'success' => true,
            'message' => 'Roles obtenidos',
            'data' => $roles,
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/roles",
     *     operationId="createRole",
     *     summary="Crear rol",
     *     tags={"Roles"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=201,
     *         description="Rol creado exitosamente"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="No autorizado. Token requerido o inválido"
     *     )
     * )
     */
    public function store(StoreRoleRequest $request)
    {
        $validated = $request->validated();

        $role = Role::create([
            'nombre' => $validated['nombre'],
            'descripcion' => $validated['descripcion'] ?? null,
        ]);

        // Registrar actividad
        ActivityLoggerService::logCreate(
            modulo: 'ROLES',
            entidad: 'roles',
            entidadId: $role->id,
            descripcion: "Rol {$role->nombre} creado"
        );

        return response()->json([
            'success' => true,
            'message' => 'Rol creado exitosamente',
            'data' => [
                'id' => $role->id,
                'nombre' => $role->nombre,
                'descripcion' => $role->descripcion,
            ]
        ], 201);
    }

    /**
     * @OA\Get(
     *     path="/api/roles/{id}",
     *     operationId="showRole",
     *     summary="Obtener rol",
     *     tags={"Roles"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Rol obtenido exitosamente"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="No autorizado. Token requerido o inválido"
     *     )
     * )
     */
    public function show($id)
    {
        $role = Role::with('users')->find($id);

        if (!$role) {
            return response()->json([
                'success' => false,
                'message' => 'Rol no encontrado',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Rol obtenido',
            'data' => [
                'id' => $role->id,
                'nombre' => $role->nombre,
                'descripcion' => $role->descripcion,
                'usuarios_count' => $role->users()->count(),
            ]
        ]);
    }

    /**
     * @OA\Put(
     *     path="/api/roles/{id}",
     *     operationId="updateRole",
     *     summary="Actualizar rol",
     *     tags={"Roles"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Rol actualizado exitosamente"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="No autorizado. Token requerido o inválido"
     *     )
     * )
     */
    public function update(Request $request, $id)
    {
        $role = Role::find($id);

        if (!$role) {
            return response()->json([
                'success' => false,
                'message' => 'Rol no encontrado',
            ], 404);
        }

        $validated = $request->validate([
            'nombre' => 'sometimes|string|max:80|unique:roles,nombre,' . $id,
            'descripcion' => 'sometimes|nullable|string|max:500',
        ]);

        $role->update($validated);

        // Registrar actividad
        ActivityLoggerService::logUpdate(
            modulo: 'ROLES',
            entidad: 'roles',
            entidadId: $role->id,
            descripcion: "Rol {$role->nombre} actualizado"
        );

        return response()->json([
            'success' => true,
            'message' => 'Rol actualizado exitosamente',
            'data' => [
                'id' => $role->id,
                'nombre' => $role->nombre,
                'descripcion' => $role->descripcion,
            ]
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/roles/{id}",
     *     operationId="deleteRole",
     *     summary="Eliminar rol",
     *     tags={"Roles"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Rol eliminado exitosamente"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="No autorizado. Token requerido o inválido"
     *     )
     * )
     */
    public function destroy($id)
    {
        $role = Role::find($id);

        if (!$role) {
            return response()->json([
                'success' => false,
                'message' => 'Rol no encontrado',
            ], 404);
        }

        // Verificar si el rol tiene usuarios
        if ($role->users()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede eliminar un rol que tiene usuarios asignados',
            ], 409);
        }

        // Registrar actividad antes de eliminar
        ActivityLoggerService::logDelete(
            modulo: 'ROLES',
            entidad: 'roles',
            entidadId: $role->id,
            descripcion: "Rol {$role->nombre} eliminado"
        );

        $role->delete();

        return response()->json([
            'success' => true,
            'message' => 'Rol eliminado exitosamente',
        ]);
    }
}
