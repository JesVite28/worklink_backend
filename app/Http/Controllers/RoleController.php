<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Services\ActivityLoggerService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * @OA\Tag(
 *     name="Roles",
 *     description="Gestión de roles"
 * )
 */
class RoleController extends Controller
{
    private array $protectedRoles = [
        'cliente',
        'freelancer',
        'empresa',
        'admin',
    ];

    private function formatRoleResponse(Role $role): array
    {
        return [
            'id' => $role->id,
            'name' => $role->name,
            'description' => $role->description,
            'users_count' => $role->users_count ?? $role->users()->count(),
            'created_at' => $role->created_at,
            'updated_at' => $role->updated_at,
        ];
    }

    /**
     * @OA\Get(
     *     path="/api/roles",
     *     operationId="listRoles",
     *     summary="Listar todos los roles",
     *     description="Obtiene todos los roles registrados en el sistema.",
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
    public function index()
    {
        $roles = Role::withCount('users')
            ->orderBy('name')
            ->get()
            ->map(function ($role) {
                return $this->formatRoleResponse($role);
            })
            ->values();

        return response()->json([
            'success' => true,
            'message' => 'Roles obtenidos exitosamente',
            'data' => [
                'roles' => $roles,
            ],
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/roles",
     *     operationId="createRole",
     *     summary="Crear rol",
     *     description="Crea un nuevo rol. Endpoint protegido para administradores.",
     *     tags={"Roles"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name"},
     *             @OA\Property(property="name", type="string", example="moderador"),
     *             @OA\Property(property="description", type="string", example="Rol con permisos limitados de moderación")
     *         )
     *     ),
     *
     *     @OA\Response(response=201, description="Rol creado exitosamente"),
     *     @OA\Response(response=401, description="No autorizado. Token requerido o inválido"),
     *     @OA\Response(response=403, description="Sin permisos"),
     *     @OA\Response(response=422, description="Datos inválidos")
     * )
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:80',
                'regex:/^[a-z_]+$/',
                'unique:roles,name',
            ],
            'description' => 'nullable|string|max:500',
        ]);

        $role = Role::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
        ]);

        ActivityLoggerService::logCreate(
            module: 'ROLES',
            entity: 'roles',
            entityId: $role->id,
            description: "Role {$role->name} created"
        );

        return response()->json([
            'success' => true,
            'message' => 'Rol creado exitosamente',
            'data' => [
                'role' => $this->formatRoleResponse($role),
            ],
        ], 201);
    }

    /**
     * @OA\Get(
     *     path="/api/roles/{id}",
     *     operationId="showRole",
     *     summary="Obtener rol por ID",
     *     description="Obtiene la información de un rol específico.",
     *     tags={"Roles"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID del rol",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *
     *     @OA\Response(response=200, description="Rol obtenido exitosamente"),
     *     @OA\Response(response=401, description="No autorizado. Token requerido o inválido"),
     *     @OA\Response(response=403, description="Sin permisos"),
     *     @OA\Response(response=404, description="Rol no encontrado")
     * )
     */
    public function show(int $id)
    {
        $role = Role::withCount('users')->find($id);

        if (! $role) {
            return response()->json([
                'success' => false,
                'message' => 'Rol no encontrado',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Rol obtenido exitosamente',
            'data' => [
                'role' => $this->formatRoleResponse($role),
            ],
        ]);
    }

    /**
     * @OA\Put(
     *     path="/api/roles/{id}",
     *     operationId="updateRole",
     *     summary="Actualizar rol",
     *     description="Actualiza la información de un rol existente.",
     *     tags={"Roles"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID del rol",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", example="moderador"),
     *             @OA\Property(property="description", type="string", example="Rol actualizado")
     *         )
     *     ),
     *
     *     @OA\Response(response=200, description="Rol actualizado exitosamente"),
     *     @OA\Response(response=401, description="No autorizado. Token requerido o inválido"),
     *     @OA\Response(response=403, description="Sin permisos"),
     *     @OA\Response(response=404, description="Rol no encontrado"),
     *     @OA\Response(response=409, description="No se puede modificar un rol protegido"),
     *     @OA\Response(response=422, description="Datos inválidos")
     * )
     */
    public function update(Request $request, int $id)
    {
        $role = Role::find($id);

        if (! $role) {
            return response()->json([
                'success' => false,
                'message' => 'Rol no encontrado',
            ], 404);
        }

        $validated = $request->validate([
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:80',
                'regex:/^[a-z_]+$/',
                Rule::unique('roles', 'name')->ignore($role->id),
            ],
            'description' => 'sometimes|nullable|string|max:500',
        ]);

        if (
            in_array($role->name, $this->protectedRoles, true) &&
            isset($validated['name']) &&
            $validated['name'] !== $role->name
        ) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede cambiar el nombre de un rol protegido del sistema',
            ], 409);
        }

        $role->update($validated);

        ActivityLoggerService::logUpdate(
            module: 'ROLES',
            entity: 'roles',
            entityId: $role->id,
            description: "Role {$role->name} updated"
        );

        $role->loadCount('users');

        return response()->json([
            'success' => true,
            'message' => 'Rol actualizado exitosamente',
            'data' => [
                'role' => $this->formatRoleResponse($role),
            ],
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/roles/{id}",
     *     operationId="deleteRole",
     *     summary="Eliminar rol",
     *     description="Elimina un rol si no tiene usuarios asignados y no es un rol protegido del sistema.",
     *     tags={"Roles"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID del rol",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *
     *     @OA\Response(response=200, description="Rol eliminado exitosamente"),
     *     @OA\Response(response=401, description="No autorizado. Token requerido o inválido"),
     *     @OA\Response(response=403, description="Sin permisos"),
     *     @OA\Response(response=404, description="Rol no encontrado"),
     *     @OA\Response(response=409, description="No se puede eliminar el rol")
     * )
     */
    public function destroy(int $id)
    {
        $role = Role::find($id);

        if (! $role) {
            return response()->json([
                'success' => false,
                'message' => 'Rol no encontrado',
            ], 404);
        }

        if (in_array($role->name, $this->protectedRoles, true)) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede eliminar un rol protegido del sistema',
            ], 409);
        }

        if ($role->users()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede eliminar un rol que tiene usuarios asignados',
            ], 409);
        }

        ActivityLoggerService::logDelete(
            module: 'ROLES',
            entity: 'roles',
            entityId: $role->id,
            description: "Role {$role->name} deleted"
        );

        $role->delete();

        return response()->json([
            'success' => true,
            'message' => 'Rol eliminado exitosamente',
        ]);
    }
}
