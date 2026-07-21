<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use App\Services\ActivityLoggerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
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
     * Formatea la respuesta del usuario con su rol principal.
     */
    private function formatUserResponse(User $user): array
    {
        $user->loadMissing('roles');

        $role = $user->roles->first();

        return [
            'id' => $user->id,
            'name' => $user->name,
            'last_name' => $user->last_name,
            'maternal_last_name' => $user->maternal_last_name,
            'email' => $user->email,
            'phone' => $user->phone,

            // Ruta interna almacenada en la base de datos.
            'profile_photo' => $user->profile_photo,

            // URL completa lista para utilizarse en el frontend.
            'profile_photo_url' => $user->profile_photo
                ? asset(Storage::url($user->profile_photo))
                : null,

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

    /*
    |--------------------------------------------------------------------------
    | Current authenticated user
    |--------------------------------------------------------------------------
    */

    /**
     * @OA\Patch(
     *     path="/api/users/me",
     *     operationId="updateMyUserAccount",
     *     summary="Actualizar mi cuenta",
     *     description="Permite al usuario autenticado actualizar sus propios datos personales. La fotografía se modifica desde /api/users/me/profile-photo.",
     *     tags={"Users"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="name",
     *                 type="string",
     *                 example="Adrian"
     *             ),
     *             @OA\Property(
     *                 property="last_name",
     *                 type="string",
     *                 example="Vite"
     *             ),
     *             @OA\Property(
     *                 property="maternal_last_name",
     *                 type="string",
     *                 nullable=true,
     *                 example="Espinosa"
     *             ),
     *             @OA\Property(
     *                 property="email",
     *                 type="string",
     *                 format="email",
     *                 example="adrian@example.com"
     *             ),
     *             @OA\Property(
     *                 property="phone",
     *                 type="string",
     *                 nullable=true,
     *                 example="7711234567"
     *             ),
     *             @OA\Property(
     *                 property="current_password",
     *                 type="string",
     *                 nullable=true,
     *                 example="password123",
     *                 description="Obligatoria únicamente para cambiar la contraseña"
     *             ),
     *             @OA\Property(
     *                 property="password",
     *                 type="string",
     *                 nullable=true,
     *                 example="newPassword123"
     *             ),
     *             @OA\Property(
     *                 property="password_confirmation",
     *                 type="string",
     *                 nullable=true,
     *                 example="newPassword123"
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Cuenta actualizada exitosamente"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="No autorizado"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Datos inválidos"
     *     )
     * )
     */
    public function updateMe(Request $request)
    {
        $user = auth('api')->user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'No autorizado.',
            ], 401);
        }

        $validated = $request->validate([
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:100',
            ],

            'last_name' => [
                'sometimes',
                'required',
                'string',
                'max:100',
            ],

            'maternal_last_name' => [
                'sometimes',
                'nullable',
                'string',
                'max:100',
            ],

            'email' => [
                'sometimes',
                'required',
                'email',
                'max:150',
                Rule::unique('users', 'email')
                    ->whereNull('deleted_at')
                    ->ignore($user->id),
            ],

            'phone' => [
                'sometimes',
                'nullable',
                'string',
                'max:20',
            ],

            'current_password' => [
                'required_with:password',
                'nullable',
                'string',
            ],

            'password' => [
                'sometimes',
                'nullable',
                'string',
                'min:8',
                'confirmed',
            ],
        ]);

        if (! empty($validated['password'])) {
            if (
                empty($validated['current_password'])
                || ! Hash::check(
                    $validated['current_password'],
                    $user->password
                )
            ) {
                return response()->json([
                    'success' => false,
                    'message' => 'La contraseña actual es incorrecta.',
                    'errors' => [
                        'current_password' => [
                            'La contraseña actual no es correcta.',
                        ],
                    ],
                ], 422);
            }

            if (
                Hash::check(
                    $validated['password'],
                    $user->password
                )
            ) {
                return response()->json([
                    'success' => false,
                    'message' => 'La nueva contraseña debe ser diferente a la actual.',
                    'errors' => [
                        'password' => [
                            'La nueva contraseña debe ser diferente a la actual.',
                        ],
                    ],
                ], 422);
            }
        }

        DB::transaction(function () use ($user, $validated) {
            $data = [];

            foreach (
                [
                    'name',
                    'last_name',
                    'maternal_last_name',
                    'email',
                    'phone',
                ] as $field
            ) {
                if (array_key_exists($field, $validated)) {
                    $data[$field] = $validated[$field];
                }
            }

            if (! empty($validated['password'])) {
                $data['password'] = Hash::make(
                    $validated['password']
                );
            }

            if (! empty($data)) {
                $user->update($data);
            }

            ActivityLoggerService::logUpdate(
                module: 'USERS',
                entity: 'users',
                entityId: $user->id,
                description: "User {$user->name} {$user->last_name} updated their own account"
            );
        });

        $user->refresh();
        $user->load('roles');

        return response()->json([
            'success' => true,
            'message' => 'Tu cuenta fue actualizada exitosamente.',
            'data' => [
                'user' => $this->formatUserResponse($user),
            ],
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/users/me/profile-photo",
     *     operationId="updateMyProfilePhoto",
     *     summary="Actualizar mi foto de perfil",
     *     description="Sube o reemplaza la foto del usuario autenticado.",
     *     tags={"Users"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *
     *             @OA\Schema(
     *                 required={"profile_photo"},
     *
     *                 @OA\Property(
     *                     property="profile_photo",
     *                     type="string",
     *                     format="binary",
     *                     description="Imagen JPG, JPEG, PNG o WEBP. Tamaño máximo: 2 MB."
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Foto de perfil actualizada exitosamente"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="No autorizado"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Archivo inválido"
     *     )
     * )
     */
    public function updateMyProfilePhoto(Request $request)
    {
        $user = auth('api')->user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'No autorizado.',
            ], 401);
        }

        $request->validate([
            'profile_photo' => [
                'required',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:2048',
            ],
        ]);

        $oldPhotoPath = $user->profile_photo;

        $newPhotoPath = $request
            ->file('profile_photo')
            ->store('profile_photos', 'public');

        try {
            DB::transaction(function () use (
                $user,
                $newPhotoPath
            ) {
                $user->update([
                    'profile_photo' => $newPhotoPath,
                ]);

                ActivityLoggerService::logUpdate(
                    module: 'USERS',
                    entity: 'users',
                    entityId: $user->id,
                    description: "User {$user->name} {$user->last_name} updated their profile photo"
                );
            });
        } catch (\Throwable $exception) {
            Storage::disk('public')->delete($newPhotoPath);

            return response()->json([
                'success' => false,
                'message' => 'No se pudo actualizar la foto de perfil.',
                'error' => $exception->getMessage(),
            ], 500);
        }

        if (
            $oldPhotoPath
            && $oldPhotoPath !== $newPhotoPath
            && Storage::disk('public')->exists($oldPhotoPath)
        ) {
            Storage::disk('public')->delete($oldPhotoPath);
        }

        $user->refresh();
        $user->load('roles');

        return response()->json([
            'success' => true,
            'message' => 'Tu foto de perfil fue actualizada exitosamente.',
            'data' => [
                'user' => $this->formatUserResponse($user),
            ],
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/users/me/profile-photo",
     *     operationId="deleteMyProfilePhoto",
     *     summary="Eliminar mi foto de perfil",
     *     description="Elimina la foto de perfil del usuario autenticado.",
     *     tags={"Users"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Response(
     *         response=200,
     *         description="Foto de perfil eliminada exitosamente"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="No autorizado"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="El usuario no tiene una foto de perfil"
     *     )
     * )
     */
    public function destroyMyProfilePhoto()
    {
        $user = auth('api')->user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'No autorizado.',
            ], 401);
        }

        if (! $user->profile_photo) {
            return response()->json([
                'success' => false,
                'message' => 'Tu cuenta no tiene una foto de perfil.',
            ], 404);
        }

        $oldPhotoPath = $user->profile_photo;

        DB::transaction(function () use ($user) {
            $user->update([
                'profile_photo' => null,
            ]);

            ActivityLoggerService::logUpdate(
                module: 'USERS',
                entity: 'users',
                entityId: $user->id,
                description: "User {$user->name} {$user->last_name} removed their profile photo"
            );
        });

        if (Storage::disk('public')->exists($oldPhotoPath)) {
            Storage::disk('public')->delete($oldPhotoPath);
        }

        $user->refresh();
        $user->load('roles');

        return response()->json([
            'success' => true,
            'message' => 'Tu foto de perfil fue eliminada exitosamente.',
            'data' => [
                'user' => $this->formatUserResponse($user),
            ],
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/users/me",
     *     operationId="deleteMyUserAccount",
     *     summary="Eliminar mi cuenta",
     *     description="Permite al usuario autenticado eliminar su propia cuenta confirmando su contraseña.",
     *     tags={"Users"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"password"},
     *
     *             @OA\Property(
     *                 property="password",
     *                 type="string",
     *                 example="password123"
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Cuenta eliminada exitosamente"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="No autorizado"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Operación no permitida"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Datos inválidos"
     *     )
     * )
     */
    public function destroyMe(Request $request)
    {
        $user = auth('api')->user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'No autorizado.',
            ], 401);
        }

        $validated = $request->validate([
            'password' => [
                'required',
                'string',
            ],
        ]);

        if (! Hash::check($validated['password'], $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'La contraseña proporcionada es incorrecta.',
                'errors' => [
                    'password' => [
                        'La contraseña proporcionada no coincide con tu cuenta.',
                    ],
                ],
            ], 422);
        }

        if ($user->hasRole('admin')) {
            return response()->json([
                'success' => false,
                'message' => 'Una cuenta administrativa no puede eliminarse desde este endpoint.',
            ], 403);
        }

        DB::transaction(function () use ($user) {
            ActivityLoggerService::logDelete(
                module: 'USERS',
                entity: 'users',
                entityId: $user->id,
                description: "User {$user->name} {$user->last_name} deleted their own account"
            );

            $user->delete();
        });

        try {
            auth('api')->logout();
        } catch (\Throwable $exception) {
            // La cuenta ya está eliminada lógicamente.
        }

        return response()->json([
            'success' => true,
            'message' => 'Tu cuenta fue eliminada exitosamente.',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Administrative endpoints
    |--------------------------------------------------------------------------
    */

    /**
     * @OA\Post(
     *     path="/api/users",
     *     operationId="createUser",
     *     summary="Crear usuario",
     *     description="Crea un usuario, asigna su rol principal y permite subir una foto de perfil.",
     *     tags={"Users"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *
     *             @OA\Schema(
     *                 required={
     *                     "name",
     *                     "last_name",
     *                     "email",
     *                     "password",
     *                     "password_confirmation",
     *                     "role"
     *                 },
     *
     *                 @OA\Property(
     *                     property="name",
     *                     type="string",
     *                     example="Juan"
     *                 ),
     *                 @OA\Property(
     *                     property="last_name",
     *                     type="string",
     *                     example="Pérez"
     *                 ),
     *                 @OA\Property(
     *                     property="maternal_last_name",
     *                     type="string",
     *                     nullable=true,
     *                     example="López"
     *                 ),
     *                 @OA\Property(
     *                     property="email",
     *                     type="string",
     *                     format="email",
     *                     example="juan@example.com"
     *                 ),
     *                 @OA\Property(
     *                     property="phone",
     *                     type="string",
     *                     nullable=true,
     *                     example="7712233445"
     *                 ),
     *                 @OA\Property(
     *                     property="password",
     *                     type="string",
     *                     format="password",
     *                     example="password123"
     *                 ),
     *                 @OA\Property(
     *                     property="password_confirmation",
     *                     type="string",
     *                     format="password",
     *                     example="password123"
     *                 ),
     *                 @OA\Property(
     *                     property="role",
     *                     type="string",
     *                     enum={"cliente","freelancer","empresa"},
     *                     example="cliente"
     *                 ),
     *                 @OA\Property(
     *                     property="profile_photo",
     *                     type="string",
     *                     format="binary",
     *                     nullable=true,
     *                     description="Imagen JPG, JPEG, PNG o WEBP. Tamaño máximo: 2 MB."
     *                 ),
     *                 @OA\Property(
     *                     property="is_active",
     *                     type="boolean",
     *                     example=true
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="Usuario creado exitosamente"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Sin permisos"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Datos inválidos"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error interno del servidor"
     *     )
     * )
     */
    public function store(Request $request)
    {
        $photoPath = null;

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:100',
            ],

            'last_name' => [
                'required',
                'string',
                'max:100',
            ],

            'maternal_last_name' => [
                'nullable',
                'string',
                'max:100',
            ],

            'email' => [
                'required',
                'email',
                'max:150',
                Rule::unique('users', 'email')
                    ->whereNull('deleted_at'),
            ],

            'password' => [
                'required',
                'string',
                'min:8',
                'confirmed',
            ],

            'role' => [
                'required',
                'string',
                Rule::in([
                    'cliente',
                    'freelancer',
                    'empresa',
                ]),
            ],

            'phone' => [
                'nullable',
                'string',
                'max:20',
            ],

            'profile_photo' => [
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:2048',
            ],

            'is_active' => [
                'nullable',
                'boolean',
            ],
        ]);

        if ($request->hasFile('profile_photo')) {
            $photoPath = $request
                ->file('profile_photo')
                ->store('profile_photos', 'public');
        }

        try {
            $user = DB::transaction(function () use (
                $validated,
                $photoPath
            ) {
                $role = Role::where(
                    'name',
                    $validated['role']
                )->first();

                if (! $role) {
                    throw new \Exception(
                        'El rol seleccionado no existe.'
                    );
                }

                $user = User::create([
                    'name' => $validated['name'],
                    'last_name' => $validated['last_name'],
                    'maternal_last_name' =>
                    $validated['maternal_last_name'] ?? null,
                    'email' => $validated['email'],
                    'password' => Hash::make(
                        $validated['password']
                    ),
                    'phone' => $validated['phone'] ?? null,
                    'profile_photo' => $photoPath,
                    'is_active' =>
                    $validated['is_active'] ?? true,
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
        } catch (\Throwable $exception) {
            if (
                $photoPath
                && Storage::disk('public')->exists($photoPath)
            ) {
                Storage::disk('public')->delete($photoPath);
            }

            return response()->json([
                'success' => false,
                'message' => 'No se pudo crear el usuario.',
                'error' => $exception->getMessage(),
            ], 500);
        }

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
     *
     *     @OA\Response(
     *         response=200,
     *         description="Usuarios obtenidos exitosamente"
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
            ->map(
                fn(User $user) =>
                $this->formatUserResponse($user)
            )
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
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID del usuario",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Usuario obtenido exitosamente"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Usuario no encontrado"
     *     )
     * )
     */
    public function show(int $id)
    {
        $user = User::with('roles')->find($id);

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no encontrado.',
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
     * @OA\Patch(
     *     path="/api/users/{id}",
     *     operationId="updateUser",
     *     summary="Actualizar usuario como administrador",
     *     description="Actualiza los datos administrativos de un usuario. La fotografía se administra desde el endpoint personal de fotografía.",
     *     tags={"Users"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID del usuario",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="name",
     *                 type="string",
     *                 example="Juan"
     *             ),
     *             @OA\Property(
     *                 property="last_name",
     *                 type="string",
     *                 example="Pérez"
     *             ),
     *             @OA\Property(
     *                 property="maternal_last_name",
     *                 type="string",
     *                 nullable=true
     *             ),
     *             @OA\Property(
     *                 property="email",
     *                 type="string",
     *                 format="email"
     *             ),
     *             @OA\Property(
     *                 property="password",
     *                 type="string",
     *                 nullable=true
     *             ),
     *             @OA\Property(
     *                 property="password_confirmation",
     *                 type="string",
     *                 nullable=true
     *             ),
     *             @OA\Property(
     *                 property="role",
     *                 type="string",
     *                 enum={"cliente","freelancer","empresa"}
     *             ),
     *             @OA\Property(
     *                 property="phone",
     *                 type="string",
     *                 nullable=true
     *             ),
     *             @OA\Property(
     *                 property="is_active",
     *                 type="boolean"
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Usuario actualizado exitosamente"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Usuario no encontrado"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Datos inválidos"
     *     )
     * )
     */
    public function update(Request $request, int $id)
    {
        $user = User::with('roles')->find($id);

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no encontrado.',
            ], 404);
        }

        $validated = $request->validate([
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:100',
            ],

            'last_name' => [
                'sometimes',
                'required',
                'string',
                'max:100',
            ],

            'maternal_last_name' => [
                'sometimes',
                'nullable',
                'string',
                'max:100',
            ],

            'email' => [
                'sometimes',
                'required',
                'email',
                'max:150',
                Rule::unique('users', 'email')
                    ->whereNull('deleted_at')
                    ->ignore($user->id),
            ],

            'password' => [
                'sometimes',
                'nullable',
                'string',
                'min:8',
                'confirmed',
            ],

            'role' => [
                'sometimes',
                'nullable',
                'string',
                Rule::in([
                    'cliente',
                    'freelancer',
                    'empresa',
                ]),
            ],

            'phone' => [
                'sometimes',
                'nullable',
                'string',
                'max:20',
            ],

            'is_active' => [
                'sometimes',
                'boolean',
            ],
        ]);

        DB::transaction(function () use ($user, $validated) {
            $data = [];

            foreach (
                [
                    'name',
                    'last_name',
                    'maternal_last_name',
                    'email',
                    'phone',
                    'is_active',
                ] as $field
            ) {
                if (array_key_exists($field, $validated)) {
                    $data[$field] = $validated[$field];
                }
            }

            if (! empty($validated['password'])) {
                $data['password'] = Hash::make(
                    $validated['password']
                );
            }

            if (! empty($data)) {
                $user->update($data);
            }

            if (! empty($validated['role'])) {
                $role = Role::where(
                    'name',
                    $validated['role']
                )->firstOrFail();

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
                description: "User {$user->name} {$user->last_name} updated by administrator"
            );
        });

        $user->refresh();
        $user->load('roles');

        return response()->json([
            'success' => true,
            'message' => 'Usuario actualizado exitosamente.',
            'data' => [
                'user' => $this->formatUserResponse($user),
            ],
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/users/{id}",
     *     operationId="deleteUser",
     *     summary="Eliminar usuario como administrador",
     *     tags={"Users"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID del usuario",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Usuario eliminado exitosamente"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Usuario no encontrado"
     *     ),
     *     @OA\Response(
     *         response=410,
     *         description="Usuario ya eliminado"
     *     )
     * )
     */
    public function destroy(int $id)
    {
        $user = User::withTrashed()->find($id);

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no encontrado.',
            ], 404);
        }

        if ($user->trashed()) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario ya eliminado.',
            ], 410);
        }

        ActivityLoggerService::logDelete(
            module: 'USERS',
            entity: 'users',
            entityId: $user->id,
            description: "User {$user->name} {$user->last_name} deleted by administrator"
        );

        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'Usuario eliminado exitosamente.',
        ]);
    }
}
