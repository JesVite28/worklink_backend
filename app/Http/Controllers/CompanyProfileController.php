<?php

namespace App\Http\Controllers;

use App\Models\CompanyProfile;
use App\Models\User;
use App\Services\ActivityLoggerService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * @OA\Tag(
 *     name="Company Profiles",
 *     description="Endpoints para la gestión de perfiles empresariales"
 * )
 */
class CompanyProfileController extends Controller
{
    private function formatRoleResponse($role): ?array
    {
        if (! $role) {
            return null;
        }

        return [
            'id' => $role->id,
            'name' => $role->name,
            'description' => $role->description,
        ];
    }

    private function getPrimaryRole(User $user)
    {
        $user->loadMissing('roles');

        return $user->roles->firstWhere('name', 'empresa')
            ?? $user->roles->first();
    }

    private function buildPublicStorageUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        if (filter_var($path, FILTER_VALIDATE_URL)) {
            return $path;
        }

        return asset(Storage::url($path));
    }

    private function formatPrivateUserResponse(?User $user): ?array
    {
        if (! $user) {
            return null;
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'last_name' => $user->last_name,
            'maternal_last_name' => $user->maternal_last_name,
            'email' => $user->email,
            'phone' => $user->phone,
            'profile_photo' => $user->profile_photo,
            'profile_photo_url' => $this->buildPublicStorageUrl(
                $user->profile_photo
            ),
            'is_active' => $user->is_active,
            'role' => $this->formatRoleResponse(
                $this->getPrimaryRole($user)
            ),
        ];
    }

    private function formatPublicUserResponse(?User $user): ?array
    {
        if (! $user) {
            return null;
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'last_name' => $user->last_name,
            'maternal_last_name' => $user->maternal_last_name,
            'profile_photo' => $user->profile_photo,
            'profile_photo_url' => $this->buildPublicStorageUrl(
                $user->profile_photo
            ),
            'role' => $this->formatRoleResponse(
                $this->getPrimaryRole($user)
            ),
        ];
    }

    private function formatCompanyProfileResponse(
        CompanyProfile $profile,
        bool $public = false
    ): array {
        $profile->loadMissing('user.roles');

        return [
            'id' => $profile->id,
            'user_id' => $profile->user_id,
            'user' => $public
                ? $this->formatPublicUserResponse($profile->user)
                : $this->formatPrivateUserResponse($profile->user),
            'company_name' => $profile->company_name,
            'description' => $profile->description,
            'industry' => $profile->industry,
            'location' => $profile->location,
            'average_rate' => $profile->average_rate,
            'created_at' => $profile->created_at,
            'updated_at' => $profile->updated_at,
        ];
    }

    private function publicCompanyProfileQuery()
    {
        return CompanyProfile::query()
            ->with('user.roles')
            ->fromActiveCompanies();
    }

    private function canManageCompanyProfile(
        CompanyProfile $profile,
        User $user
    ): bool {
        return $user->hasRole('admin')
            || $profile->user_id === $user->id;
    }

    /*
    |--------------------------------------------------------------------------
    | Public Endpoints
    |--------------------------------------------------------------------------
    */

    /**
     * @OA\Get(
     *     path="/api/public/company-profiles",
     *     operationId="publicListCompanyProfiles",
     *     tags={"Company Profiles"},
     *     summary="Listar perfiles empresariales públicos",
     *     description="Retorna únicamente perfiles pertenecientes a usuarios empresa activos.",
     *
     *     @OA\Response(
     *         response=200,
     *         description="Perfiles empresariales públicos obtenidos exitosamente"
     *     )
     * )
     */
    public function publicIndex()
    {
        $profiles = $this->publicCompanyProfileQuery()
            ->latest('created_at')
            ->get()
            ->map(
                fn (CompanyProfile $profile) =>
                    $this->formatCompanyProfileResponse($profile, true)
            )
            ->values();

        return response()->json([
            'success' => true,
            'message' => 'Perfiles empresariales públicos obtenidos exitosamente',
            'data' => [
                'company_profiles' => $profiles,
            ],
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/public/company-profiles/{id}",
     *     operationId="publicShowCompanyProfile",
     *     tags={"Company Profiles"},
     *     summary="Obtener perfil empresarial público por ID",
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID del perfil empresarial",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Perfil empresarial público obtenido exitosamente"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Perfil empresarial público no encontrado"
     *     )
     * )
     */
    public function publicShow(int $id)
    {
        $profile = $this->publicCompanyProfileQuery()
            ->whereKey($id)
            ->first();

        if (! $profile) {
            return response()->json([
                'success' => false,
                'message' => 'Perfil empresarial público no encontrado.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Perfil empresarial público obtenido exitosamente',
            'data' => [
                'company_profile' =>
                    $this->formatCompanyProfileResponse($profile, true),
            ],
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Protected Endpoints
    |--------------------------------------------------------------------------
    */

    /**
     * @OA\Get(
     *     path="/api/company-profiles",
     *     operationId="listCompanyProfiles",
     *     tags={"Company Profiles"},
     *     summary="Listar perfiles empresariales",
     *     description="El administrador consulta todos los perfiles. Una empresa consulta únicamente su propio perfil.",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Response(
     *         response=200,
     *         description="Perfiles empresariales obtenidos exitosamente"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="No autorizado"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Rol sin acceso al módulo"
     *     )
     * )
     */
    public function index()
    {
        $authUser = auth('api')->user();

        if (! $authUser) {
            return response()->json([
                'success' => false,
                'message' => 'No autorizado.',
            ], 401);
        }

        if (! $authUser->hasAnyRole(['empresa', 'admin'])) {
            return response()->json([
                'success' => false,
                'message' => 'Tu tipo de cuenta no tiene acceso a los perfiles empresariales privados.',
            ], 403);
        }

        $query = CompanyProfile::with('user.roles');

        if (! $authUser->hasRole('admin')) {
            $query->where('user_id', $authUser->id);
        }

        $profiles = $query
            ->latest('created_at')
            ->get()
            ->map(
                fn (CompanyProfile $profile) =>
                    $this->formatCompanyProfileResponse($profile)
            )
            ->values();

        return response()->json([
            'success' => true,
            'message' => 'Perfiles empresariales obtenidos exitosamente',
            'data' => [
                'company_profiles' => $profiles,
            ],
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/company-profiles/me",
     *     operationId="showMyCompanyProfile",
     *     tags={"Company Profiles"},
     *     summary="Obtener mi perfil empresarial",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Response(
     *         response=200,
     *         description="Perfil empresarial obtenido exitosamente"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="El usuario no tiene rol empresa"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Perfil empresarial no encontrado"
     *     )
     * )
     */
    public function myProfile()
    {
        $authUser = auth('api')->user();

        if (! $authUser) {
            return response()->json([
                'success' => false,
                'message' => 'No autorizado.',
            ], 401);
        }

        if (! $authUser->hasRole('empresa')) {
            return response()->json([
                'success' => false,
                'message' => 'Solo los usuarios empresa tienen un perfil empresarial.',
            ], 403);
        }

        $profile = CompanyProfile::with('user.roles')
            ->where('user_id', $authUser->id)
            ->first();

        if (! $profile) {
            return response()->json([
                'success' => false,
                'message' => 'No se encontró un perfil empresarial para tu cuenta.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Tu perfil empresarial fue obtenido exitosamente',
            'data' => [
                'company_profile' =>
                    $this->formatCompanyProfileResponse($profile),
            ],
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/company-profiles",
     *     operationId="createCompanyProfile",
     *     tags={"Company Profiles"},
     *     summary="Crear perfil empresarial",
     *     description="Una empresa crea su propio perfil. El administrador puede indicar user_id.",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"company_name"},
     *
     *             @OA\Property(
     *                 property="user_id",
     *                 type="integer",
     *                 nullable=true,
     *                 example=3,
     *                 description="Solo debe enviarlo un administrador"
     *             ),
     *             @OA\Property(
     *                 property="company_name",
     *                 type="string",
     *                 example="Tecnologías WorkLink"
     *             ),
     *             @OA\Property(
     *                 property="description",
     *                 type="string",
     *                 nullable=true,
     *                 example="Empresa dedicada al desarrollo de soluciones digitales."
     *             ),
     *             @OA\Property(
     *                 property="industry",
     *                 type="string",
     *                 nullable=true,
     *                 example="Tecnologías de la información"
     *             ),
     *             @OA\Property(
     *                 property="location",
     *                 type="string",
     *                 nullable=true,
     *                 example="Pachuca, Hidalgo"
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="Perfil empresarial creado exitosamente"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Sin permisos"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error de validación"
     *     )
     * )
     */
    public function store(Request $request)
    {
        $authUser = auth('api')->user();

        if (! $authUser) {
            return response()->json([
                'success' => false,
                'message' => 'No autorizado.',
            ], 401);
        }

        if (! $authUser->hasAnyRole(['empresa', 'admin'])) {
            return response()->json([
                'success' => false,
                'message' => 'Solo las empresas o administradores pueden crear perfiles empresariales.',
            ], 403);
        }

        $validated = $request->validate([
            'user_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->whereNull('deleted_at'),
            ],
            'company_name' => [
                'required',
                'string',
                'max:150',
            ],
            'description' => [
                'nullable',
                'string',
                'max:5000',
            ],
            'industry' => [
                'nullable',
                'string',
                'max:100',
            ],
            'location' => [
                'nullable',
                'string',
                'max:150',
            ],
        ]);

        if ($authUser->hasRole('admin')) {
            if (empty($validated['user_id'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'El administrador debe indicar user_id.',
                    'errors' => [
                        'user_id' => [
                            'El campo user_id es obligatorio para administradores.',
                        ],
                    ],
                ], 422);
            }

            $userId = (int) $validated['user_id'];
        } else {
            $userId = $authUser->id;
        }

        try {
            $result = DB::transaction(function () use (
                $validated,
                $userId
            ) {
                $companyUser = User::with('roles')
                    ->lockForUpdate()
                    ->find($userId);

                if (
                    ! $companyUser
                    || ! $companyUser->is_active
                    || ! $companyUser->hasRole('empresa')
                ) {
                    throw new \RuntimeException(
                        'INVALID_COMPANY_USER'
                    );
                }

                $profile = CompanyProfile::withTrashed()
                    ->where('user_id', $companyUser->id)
                    ->lockForUpdate()
                    ->first();

                if ($profile && ! $profile->trashed()) {
                    throw new \RuntimeException(
                        'ACTIVE_PROFILE_EXISTS'
                    );
                }

                $restored = false;

                if ($profile && $profile->trashed()) {
                    $profile->restore();

                    $profile->update([
                        'company_name' => $validated['company_name'],
                        'description' => $validated['description'] ?? null,
                        'industry' => $validated['industry'] ?? null,
                        'location' => $validated['location'] ?? null,
                    ]);

                    $restored = true;

                    ActivityLoggerService::logUpdate(
                        module: 'COMPANY_PROFILES',
                        entity: 'company_profiles',
                        entityId: $profile->id,
                        description: "Company profile {$profile->company_name} restored"
                    );
                } else {
                    $profile = CompanyProfile::create([
                        'user_id' => $companyUser->id,
                        'company_name' => $validated['company_name'],
                        'description' => $validated['description'] ?? null,
                        'industry' => $validated['industry'] ?? null,
                        'location' => $validated['location'] ?? null,
                    ]);

                    ActivityLoggerService::logCreate(
                        module: 'COMPANY_PROFILES',
                        entity: 'company_profiles',
                        entityId: $profile->id,
                        description: "Company profile {$profile->company_name} created"
                    );
                }

                return [
                    'profile' => $profile,
                    'restored' => $restored,
                ];
            });

            $profile = $result['profile'];
            $restored = $result['restored'];
        } catch (\RuntimeException $exception) {
            if ($exception->getMessage() === 'INVALID_COMPANY_USER') {
                return response()->json([
                    'success' => false,
                    'message' => 'El perfil debe pertenecer a un usuario empresa activo.',
                ], 422);
            }

            if ($exception->getMessage() === 'ACTIVE_PROFILE_EXISTS') {
                return response()->json([
                    'success' => false,
                    'message' => 'El usuario ya tiene un perfil empresarial activo.',
                ], 422);
            }

            throw $exception;
        } catch (QueryException $exception) {
            if ((string) $exception->getCode() === '23000') {
                return response()->json([
                    'success' => false,
                    'message' => 'El usuario ya tiene un perfil empresarial activo.',
                ], 422);
            }

            throw $exception;
        }

        $profile->load('user.roles');

        return response()->json([
            'success' => true,
            'message' => $restored
                ? 'Perfil empresarial restaurado y actualizado exitosamente'
                : 'Perfil empresarial creado exitosamente',
            'data' => [
                'company_profile' =>
                    $this->formatCompanyProfileResponse($profile),
            ],
        ], $restored ? 200 : 201);
    }

    /**
     * @OA\Get(
     *     path="/api/company-profiles/{id}",
     *     operationId="showCompanyProfile",
     *     tags={"Company Profiles"},
     *     summary="Obtener perfil empresarial privado por ID",
     *     description="Solo puede consultarlo el propietario o un administrador.",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Perfil empresarial obtenido exitosamente"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Sin permisos"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Perfil empresarial no encontrado"
     *     )
     * )
     */
    public function show(int $id)
    {
        $authUser = auth('api')->user();

        if (! $authUser) {
            return response()->json([
                'success' => false,
                'message' => 'No autorizado.',
            ], 401);
        }

        $profile = CompanyProfile::with('user.roles')->find($id);

        if (! $profile) {
            return response()->json([
                'success' => false,
                'message' => 'Perfil empresarial no encontrado.',
            ], 404);
        }

        if (! $this->canManageCompanyProfile($profile, $authUser)) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permisos para consultar este perfil empresarial privado.',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'message' => 'Perfil empresarial obtenido exitosamente',
            'data' => [
                'company_profile' =>
                    $this->formatCompanyProfileResponse($profile),
            ],
        ]);
    }

    /**
     * @OA\Patch(
     *     path="/api/company-profiles/{id}",
     *     operationId="updateCompanyProfile",
     *     tags={"Company Profiles"},
     *     summary="Actualizar perfil empresarial",
     *     description="El propietario o un administrador pueden actualizar los datos editables. user_id y average_rate no pueden modificarse.",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="company_name",
     *                 type="string",
     *                 example="Tecnologías WorkLink S.A."
     *             ),
     *             @OA\Property(
     *                 property="description",
     *                 type="string",
     *                 nullable=true,
     *                 example="Descripción actualizada de la empresa."
     *             ),
     *             @OA\Property(
     *                 property="industry",
     *                 type="string",
     *                 nullable=true,
     *                 example="Desarrollo de software"
     *             ),
     *             @OA\Property(
     *                 property="location",
     *                 type="string",
     *                 nullable=true,
     *                 example="Ciudad de México"
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Perfil empresarial actualizado correctamente"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Sin permisos"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Perfil empresarial no encontrado"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error de validación"
     *     )
     * )
     */
    public function update(Request $request, int $id)
    {
        $authUser = auth('api')->user();

        if (! $authUser) {
            return response()->json([
                'success' => false,
                'message' => 'No autorizado.',
            ], 401);
        }

        $profile = CompanyProfile::with('user.roles')->find($id);

        if (! $profile) {
            return response()->json([
                'success' => false,
                'message' => 'Perfil empresarial no encontrado.',
            ], 404);
        }

        if (! $this->canManageCompanyProfile($profile, $authUser)) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permisos para actualizar este perfil empresarial.',
            ], 403);
        }

        $validated = $request->validate([
            'company_name' => [
                'sometimes',
                'required',
                'string',
                'max:150',
            ],
            'description' => [
                'sometimes',
                'nullable',
                'string',
                'max:5000',
            ],
            'industry' => [
                'sometimes',
                'nullable',
                'string',
                'max:100',
            ],
            'location' => [
                'sometimes',
                'nullable',
                'string',
                'max:150',
            ],
        ]);

        if (empty($validated)) {
            return response()->json([
                'success' => false,
                'message' => 'Debes enviar al menos un campo para actualizar.',
            ], 422);
        }

        DB::transaction(function () use ($profile, $validated) {
            $profile->update($validated);

            ActivityLoggerService::logUpdate(
                module: 'COMPANY_PROFILES',
                entity: 'company_profiles',
                entityId: $profile->id,
                description: "Company profile {$profile->company_name} updated"
            );
        });

        $profile->refresh();
        $profile->load('user.roles');

        return response()->json([
            'success' => true,
            'message' => 'Perfil empresarial actualizado correctamente',
            'data' => [
                'company_profile' =>
                    $this->formatCompanyProfileResponse($profile),
            ],
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/company-profiles/{id}",
     *     operationId="deleteCompanyProfile",
     *     tags={"Company Profiles"},
     *     summary="Eliminar perfil empresarial",
     *     description="El propietario o un administrador pueden eliminar lógicamente el perfil.",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Perfil empresarial eliminado correctamente"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Sin permisos"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Perfil empresarial no encontrado"
     *     )
     * )
     */
    public function destroy(int $id)
    {
        $authUser = auth('api')->user();

        if (! $authUser) {
            return response()->json([
                'success' => false,
                'message' => 'No autorizado.',
            ], 401);
        }

        $profile = CompanyProfile::find($id);

        if (! $profile) {
            return response()->json([
                'success' => false,
                'message' => 'Perfil empresarial no encontrado.',
            ], 404);
        }

        if (! $this->canManageCompanyProfile($profile, $authUser)) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permisos para eliminar este perfil empresarial.',
            ], 403);
        }

        DB::transaction(function () use ($profile) {
            ActivityLoggerService::logDelete(
                module: 'COMPANY_PROFILES',
                entity: 'company_profiles',
                entityId: $profile->id,
                description: "Company profile {$profile->company_name} deleted"
            );

            $profile->delete();
        });

        return response()->json([
            'success' => true,
            'message' => 'Perfil empresarial eliminado correctamente.',
        ]);
    }
}