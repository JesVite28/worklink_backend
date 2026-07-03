<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Role;
use App\Services\ActivityLoggerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

/**
 * @OA\Tag(
 *     name="Users",
 *     description="Gestión de usuarios"
 * )
 */
class UserController extends Controller
{
    /**
     * Formatea la respuesta del usuario con un solo rol principal.
     */
    private function formatUserResponse(User $user): array
    {
        $role = $user->roles->first();

        return [
            'id' => $user->id,
            'name' => $user->name,
            'last_name' => $user->last_name,
            'maternal_last_name' => $user->maternal_last_name,
            'email' => $user->email,
            'phone' => $user->phone,
            'profile_photo' => $user->profile_photo,
            'is_active' => $user->is_active,
            'role' => $role ? [
                'id' => $role->id,
                'name' => $role->name,
                'description' => $role->description,
            ] : null,
            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,
        ];
    }

    /**
     * @OA\Post(
     *     path="/api/users",
     *     operationId="createUser",
     *     summary="Crear usuario",
     *     description="Crea un usuario y le asigna un solo rol principal. El rol admin no se asigna desde este endpoint.",
     *     tags={"Users"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name","last_name","email","password","password_confirmation","role"},
     *             @OA\Property(property="name", type="string", example="Juan"),
     *             @OA\Property(property="last_name", type="string", example="Pérez"),
     *             @OA\Property(property="maternal_last_name", type="string", nullable=true, example="López"),
     *             @OA\Property(property="email", type="string", format="email", example="juan@example.com"),
     *             @OA\Property(property="password", type="string", example="password123"),
     *             @OA\Property(property="password_confirmation", type="string", example="password123"),
     *             @OA\Property(property="role", type="string", enum={"cliente","freelancer","empresa"}, example="cliente"),
     *             @OA\Property(property="phone", type="string", example="7712233445"),
     *             @OA\Property(property="profile_photo", type="string", nullable=true, example="https://example.com/photo.jpg"),
     *             @OA\Property(property="is_active", type="boolean", example=true)
     *         )
     *     ),
     *     @OA\Response(response=201, description="Usuario creado exitosamente"),
     *     @OA\Response(response=401, description="No autorizado. Token requerido o inválido"),
     *     @OA\Response(response=403, description="Sin permisos"),
     *     @OA\Response(response=422, description="Datos inválidos")
     * )
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'maternal_last_name' => 'nullable|string|max:100',
            'email' => 'required|email|max:150|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'role' => 'required|string|in:cliente,freelancer,empresa',
            'phone' => 'nullable|string|max:20',
            'profile_photo' => 'nullable|string|max:255',
            'is_active' => 'nullable|boolean',
        ]);

        $user = DB::transaction(function () use ($validated) {
            $role = Role::where('name', $validated['role'])->firstOrFail();

            $user = User::create([
                'name' => $validated['name'],
                'last_name' => $validated['last_name'],
                'maternal_last_name' => $validated['maternal_last_name'] ?? null,
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'phone' => $validated['phone'] ?? null,
                'profile_photo' => $validated['profile_photo'] ?? null,
                'is_active' => $validated['is_active'] ?? true,
            ]);

            $user->roles()->sync([
                $role->id => [
                    'assigned_at' => now(),
                ],
            ]);

            ActivityLoggerService::logCreate(
                module: 'USERS',
                entity: 'users',
                entityId: $user->id,
                description: "User {$user->name} {$user->last_name} created with role {$role->name}"
            );

            $user->load('roles');

            return $user;
        });

        return response()->json([
            'success' => true,
            'message' => 'Usuario creado exitosamente',
            'data' => [
                'user' => $this->formatUserResponse($user),
            ],
        ], 201);
    }

    /**
     * @OA\Get(
     *     path="/api/users",
     *     operationId="listUsers",
     *     summary="Listar todos los usuarios",
     *     description="Obtiene todos los usuarios registrados con su rol principal.",
     *     tags={"Users"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Usuarios obtenidos exitosamente"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="No autorizado. Token requerido o inválido"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Sin permisos"
     *     )
     * )
     */
    public function index()
    {
        $users = User::with('roles')
            ->latest('created_at')
            ->get()
            ->map(function ($user) {
                return $this->formatUserResponse($user);
            })
            ->values();

        return response()->json([
            'success' => true,
            'message' => 'Usuarios obtenidos exitosamente',
            'data' => [
                'users' => $users,
            ],
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/users/{id}",
     *     operationId="showUser",
     *     summary="Obtener usuario por ID",
     *     tags={"Users"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID del usuario",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(response=200, description="Usuario obtenido exitosamente"),
     *     @OA\Response(response=401, description="No autorizado. Token requerido o inválido"),
     *     @OA\Response(response=404, description="Usuario no encontrado")
     * )
     */
    public function show(int $id)
    {
        $user = User::with('roles')->find($id);

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no encontrado',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Usuario obtenido exitosamente',
            'data' => [
                'user' => $this->formatUserResponse($user),
            ],
        ]);
    }

    /**
     * @OA\Put(
     *     path="/api/users/{id}",
     *     operationId="updateUser",
     *     summary="Actualizar usuario",
     *     tags={"Users"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID del usuario",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", example="Juan"),
     *             @OA\Property(property="last_name", type="string", example="Pérez"),
     *             @OA\Property(property="maternal_last_name", type="string", nullable=true, example="López"),
     *             @OA\Property(property="email", type="string", format="email", example="juan@example.com"),
     *             @OA\Property(property="password", type="string", example="password123"),
     *             @OA\Property(property="password_confirmation", type="string", example="password123"),
     *             @OA\Property(property="role", type="string", enum={"cliente","freelancer","empresa"}, example="empresa"),
     *             @OA\Property(property="phone", type="string", example="7712233445"),
     *             @OA\Property(property="profile_photo", type="string", nullable=true, example="https://example.com/photo.jpg"),
     *             @OA\Property(property="is_active", type="boolean", example=true)
     *         )
     *     ),
     *     @OA\Response(response=200, description="Usuario actualizado exitosamente"),
     *     @OA\Response(response=401, description="No autorizado. Token requerido o inválido"),
     *     @OA\Response(response=404, description="Usuario no encontrado"),
     *     @OA\Response(response=422, description="Datos inválidos")
     * )
     */
    public function update(Request $request, int $id)
    {
        $user = User::with('roles')->find($id);

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no encontrado',
            ], 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:100',
            'last_name' => 'sometimes|required|string|max:100',
            'maternal_last_name' => 'nullable|string|max:100',
            'email' => [
                'sometimes',
                'required',
                'email',
                'max:150',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'password' => 'nullable|string|min:8|confirmed',
            'role' => 'nullable|string|in:cliente,freelancer,empresa',
            'phone' => 'nullable|string|max:20',
            'profile_photo' => 'nullable|string|max:255',
            'is_active' => 'nullable|boolean',
        ]);

        DB::transaction(function () use ($user, $validated) {
            $data = [];

            foreach ([
                'name',
                'last_name',
                'maternal_last_name',
                'email',
                'phone',
                'profile_photo',
                'is_active',
            ] as $field) {
                if (array_key_exists($field, $validated)) {
                    $data[$field] = $validated[$field];
                }
            }

            if (! empty($validated['password'])) {
                $data['password'] = Hash::make($validated['password']);
            }

            if (! empty($data)) {
                $user->update($data);
            }

            if (! empty($validated['role'])) {
                $role = Role::where('name', $validated['role'])->firstOrFail();

                $user->roles()->sync([
                    $role->id => [
                        'assigned_at' => now(),
                    ],
                ]);
            }

            ActivityLoggerService::logUpdate(
                module: 'USERS',
                entity: 'users',
                entityId: $user->id,
                description: "User {$user->name} {$user->last_name} updated"
            );
        });

        $user->refresh();
        $user->load('roles');

        return response()->json([
            'success' => true,
            'message' => 'Usuario actualizado exitosamente',
            'data' => [
                'user' => $this->formatUserResponse($user),
            ],
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/users/{id}",
     *     operationId="deleteUser",
     *     summary="Eliminar usuario",
     *     tags={"Users"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID del usuario",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(response=200, description="Usuario eliminado exitosamente"),
     *     @OA\Response(response=401, description="No autorizado. Token requerido o inválido"),
     *     @OA\Response(response=404, description="Usuario no encontrado"),
     *     @OA\Response(response=410, description="Usuario ya eliminado")
     * )
     */
    public function destroy(int $id)
    {
        $user = User::withTrashed()->find($id);

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no encontrado',
            ], 404);
        }

        if ($user->trashed()) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario ya eliminado',
            ], 410);
        }

        ActivityLoggerService::logDelete(
            module: 'USERS',
            entity: 'users',
            entityId: $user->id,
            description: "User {$user->name} {$user->last_name} deleted"
        );

        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'Usuario eliminado exitosamente',
        ]);
    }
}