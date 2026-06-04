<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Role;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Services\ActivityLoggerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * @OA\Tag(
 *     name="Users",
 *     description="Gestión de usuarios"
 * )
 */
class UserController extends Controller
{
    /**
     * Transforma los roles del usuario al formato deseado.
     */
    private function transformRoles(User $user): array
    {
        return $user->roles->map(function ($role) {
            return [
                'id' => $role->id,
                'nombre' => $role->nombre,
                'descripcion' => $role->descripcion,
            ];
        })->values()->all();
    }

    /**
     * Obtiene los roles según el tipo de cuenta.
     */
    private function getDefaultRoles(string $tipoCuenta): array
    {
        $roles = [Role::where('nombre', 'user')->first()?->id];

        if ($tipoCuenta === 'Freelancer') {
            $roles[] = Role::where('nombre', 'freelancer')->first()?->id;
        } elseif ($tipoCuenta === 'Empresa') {
            $roles[] = Role::where('nombre', 'empresa')->first()?->id;
        }

        return array_filter($roles);
    }

    /**
     * @OA\Post(
     *     path="/api/users",
     *     operationId="createUser",
     *     summary="Crear usuario",
     *     tags={"Users"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"nombre","apellido","email","password","tipo_cuenta"},
     *             @OA\Property(property="nombre", type="string", example="Juan"),
     *             @OA\Property(property="apellido", type="string", example="Pérez"),
     *             @OA\Property(property="email", type="string", format="email", example="juan@example.com"),
     *             @OA\Property(property="password", type="string", example="password123"),
     *             @OA\Property(property="password_confirmation", type="string", example="password123"),
     *             @OA\Property(property="tipo_cuenta", type="string", enum={"Cliente","Freelancer","Empresa"}),
     *             @OA\Property(property="telefono", type="string", example="123456789"),
     *             @OA\Property(property="foto_perfil", type="string", nullable=true)
     *         )
     *     ),
     *     @OA\Response(response=201, description="Usuario creado exitosamente"),
     *     @OA\Response(response=401, description="No autorizado. Token requerido o inválido"),
     *     @OA\Response(response=422, description="Datos inválidos")
     * )
     */
    public function store(StoreUserRequest $request)
    {
        $validated = $request->validated();

        $user = User::create([
            'nombre' => $validated['nombre'],
            'apellido' => $validated['apellido'],
            'email' => $validated['email'],
            'password_hash' => Hash::make($validated['password']),
            'tipo_cuenta' => $validated['tipo_cuenta'],
            'telefono' => $validated['telefono'] ?? null,
            'foto_perfil' => $validated['foto_perfil'] ?? null,
            'activo' => $validated['activo'] ?? true,
        ]);

        // Asignar roles por defecto según tipo de cuenta
        $defaultRoles = $this->getDefaultRoles($validated['tipo_cuenta']);
        if (!empty($defaultRoles)) {
            $user->roles()->attach($defaultRoles);
        }

        $user->load('roles');

        // Registrar actividad
        ActivityLoggerService::logCreate(
            modulo: 'USUARIOS',
            entidad: 'users',
            entidadId: $user->id,
            descripcion: "Usuario {$user->nombre} {$user->apellido} ({$validated['tipo_cuenta']}) creado"
        );

        return response()->json([
            'success' => true,
            'message' => 'Usuario creado exitosamente',
            'data' => [
                'id' => $user->id,
                'nombre' => $user->nombre,
                'apellido' => $user->apellido,
                'email' => $user->email,
                'tipo_cuenta' => $user->tipo_cuenta,
                'roles' => $this->transformRoles($user),
            ]
        ], 201);
    }

    /**
     * @OA\Get(
     *     path="/api/users",
     *     operationId="listUsers",
     *     summary="Listar usuarios",
     *     tags={"Users"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Usuarios obtenidos exitosamente"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="No autorizado. Token requerido o inválido"
     *     )
     * )
     */
    public function index(Request $request)
    {
        $query = User::with('roles');

        // Filtros opcionales
        if ($request->has('tipo_cuenta')) {
            $query->where('tipo_cuenta', $request->tipo_cuenta);
        }

        if ($request->has('activo')) {
            $query->where('activo', (bool) $request->activo);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nombre', 'like', "%{$search}%")
                  ->orWhere('apellido', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $users = $query->paginate($request->get('per_page', 15))->map(function ($user) {
            return [
                'id' => $user->id,
                'nombre' => $user->nombre,
                'apellido' => $user->apellido,
                'email' => $user->email,
                'tipo_cuenta' => $user->tipo_cuenta,
                'telefono' => $user->telefono,
                'foto_perfil' => $user->foto_perfil,
                'activo' => $user->activo,
                'creado_en' => $user->creado_en,
                'roles' => $this->transformRoles($user),
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Usuarios obtenidos',
            'data' => $users,
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/users/{id}",
     *     operationId="showUser",
     *     summary="Obtener usuario",
     *     tags={"Users"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Usuario obtenido exitosamente"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="No autorizado. Token requerido o inválido"
     *     )
     * )
     */
    public function show($id)
    {
        $user = User::with('roles', 'activityLogs')->find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no encontrado',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Usuario obtenido',
            'data' => [
                'id' => $user->id,
                'nombre' => $user->nombre,
                'apellido' => $user->apellido,
                'email' => $user->email,
                'tipo_cuenta' => $user->tipo_cuenta,
                'telefono' => $user->telefono,
                'foto_perfil' => $user->foto_perfil,
                'activo' => $user->activo,
                'creado_en' => $user->creado_en,
                'roles' => $this->transformRoles($user),
            ]
        ]);
    }

    /**
     * @OA\Put(
     *     path="/api/users/{id}",
     *     operationId="updateUser",
     *     summary="Actualizar usuario",
     *     tags={"Users"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Usuario actualizado exitosamente"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="No autorizado. Token requerido o inválido"
     *     )
     * )
     */
    public function update(UpdateUserRequest $request, $id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no encontrado',
            ], 404);
        }

        $validated = $request->validated();

        // Actualizar contraseña si se proporciona
        if (isset($validated['password'])) {
            $validated['password_hash'] = Hash::make($validated['password']);
            unset($validated['password']);
        }

        $user->update($validated);
        $user->load('roles');

        // Registrar actividad
        ActivityLoggerService::logUpdate(
            modulo: 'USUARIOS',
            entidad: 'users',
            entidadId: $user->id,
            descripcion: "Usuario {$user->nombre} {$user->apellido} actualizado"
        );

        return response()->json([
            'success' => true,
            'message' => 'Usuario actualizado exitosamente',
            'data' => [
                'id' => $user->id,
                'nombre' => $user->nombre,
                'apellido' => $user->apellido,
                'email' => $user->email,
                'tipo_cuenta' => $user->tipo_cuenta,
                'roles' => $this->transformRoles($user),
            ]
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/users/{id}",
     *     operationId="deleteUser",
     *     summary="Eliminar usuario",
     *     tags={"Users"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Usuario eliminado exitosamente"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="No autorizado. Token requerido o inválido"
     *     )
     * )
     */
    public function destroy($id)
    {
        $user = User::withTrashed()->find($id);

        if (!$user) {
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

        // Registrar actividad antes de eliminar
        ActivityLoggerService::logDelete(
            modulo: 'USUARIOS',
            entidad: 'users',
            entidadId: $user->id,
            descripcion: "Usuario {$user->nombre} {$user->apellido} eliminado"
        );

        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'Usuario eliminado exitosamente',
        ]);
    }
}
