<?php

namespace App\Http\Controllers;

use App\Models\FreelancerProfile;
use App\Models\Service;
use App\Models\User;
use App\Services\ActivityLoggerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * @OA\Tag(
 *     name="Services",
 *     description="Endpoints para la gestión de servicios de freelancers"
 * )
 */
class ServiceController extends Controller
{
    /**
     * Formatea el rol principal del usuario.
     */
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

    /**
     * Obtiene preferentemente el rol freelancer.
     */
    private function getPrimaryRole(User $user)
    {
        $user->loadMissing('roles');

        return $user->roles->firstWhere('name', 'freelancer')
            ?? $user->roles->first();
    }

    /**
     * Información privada del usuario.
     *
     * Solo se utiliza en endpoints protegidos.
     */
    private function formatPrivateUserResponse(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'last_name' => $user->last_name,
            'maternal_last_name' => $user->maternal_last_name,
            'email' => $user->email,
            'phone' => $user->phone,
            'profile_photo' => $user->profile_photo,
            'is_active' => $user->is_active,
            'role' => $this->formatRoleResponse(
                $this->getPrimaryRole($user)
            ),
        ];
    }

    /**
     * Información pública del usuario.
     *
     * No expone correo, teléfono ni otros datos privados.
     */
    private function formatPublicUserResponse(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'last_name' => $user->last_name,
            'maternal_last_name' => $user->maternal_last_name,
            'profile_photo' => $user->profile_photo,
            'role' => $this->formatRoleResponse(
                $this->getPrimaryRole($user)
            ),
        ];
    }

    /**
     * Formatea el perfil asociado al servicio.
     */
    private function formatFreelancerProfileResponse(
        ?FreelancerProfile $profile,
        bool $public = false
    ): ?array {
        if (! $profile) {
            return null;
        }

        $profile->loadMissing('user.roles');

        $user = null;

        if ($profile->user) {
            $user = $public
                ? $this->formatPublicUserResponse($profile->user)
                : $this->formatPrivateUserResponse($profile->user);
        }

        return [
            'id' => $profile->id,
            'user_id' => $profile->user_id,
            'user' => $user,

            'description' => $profile->description,
            'specialty' => $profile->specialty,
            'location' => $profile->location,
            'service_area' => $profile->service_area,
            'work_mode' => $profile->work_mode,
            'experience' => $profile->experience,

            'rate_type' => $profile->rate_type,
            'rate' => $profile->rate,

            'languages' => $profile->languages ?? [],

            'professional_links' => [
                'website' => $profile->website,
                'facebook' => $profile->facebook,
                'instagram' => $profile->instagram,
                'linkedin' => $profile->linkedin,
                'github' => $profile->github,
                'portfolio_url' => $profile->portfolio_url,
            ],

            'available' => $profile->available,
            'average_rate' => $profile->average_rate,
        ];
    }

    /**
     * Formatea la información del servicio.
     */
    private function formatServiceResponse(
        Service $service,
        bool $public = false
    ): array {
        $service->loadMissing('freelancerProfile.user.roles');

        return [
            'id' => $service->id,
            'freelancer_id' => $service->freelancer_id,

            'freelancer_profile' =>
                $this->formatFreelancerProfileResponse(
                    $service->freelancerProfile,
                    $public
                ),

            'title' => $service->title,
            'description' => $service->description,
            'price' => $service->price,
            'category' => $service->category,
            'location' => $service->location,
            'is_active' => $service->is_active,
            'created_at' => $service->created_at,
            'updated_at' => $service->updated_at,
        ];
    }

    /**
     * Verifica si el usuario autenticado puede administrar el servicio.
     */
    private function canManageService(Service $service): bool
    {
        $user = auth('api')->user();

        if (! $user) {
            return false;
        }

        $service->loadMissing('freelancerProfile');

        return $user->hasRole('admin')
            || (
                $service->freelancerProfile
                && $service->freelancerProfile->user_id === $user->id
            );
    }

    /*
    |--------------------------------------------------------------------------
    | Public Endpoints
    |--------------------------------------------------------------------------
    */

    /**
     * @OA\Get(
     *     path="/api/public/services",
     *     operationId="publicListServices",
     *     tags={"Services"},
     *     summary="Listar servicios públicos",
     *     description="Retorna servicios activos pertenecientes a freelancers activos.",
     *
     *     @OA\Response(
     *         response=200,
     *         description="Servicios públicos obtenidos exitosamente"
     *     )
     * )
     */
    public function publicIndex()
    {
        $services = Service::query()
            ->with('freelancerProfile.user.roles')
            ->where('is_active', true)
            ->whereHas('freelancerProfile', function ($profileQuery) {
                $profileQuery->whereHas(
                    'user',
                    function ($userQuery) {
                        $userQuery->where('is_active', true);
                    }
                );
            })
            ->latest('created_at')
            ->get()
            ->map(
                fn (Service $service) =>
                    $this->formatServiceResponse($service, true)
            )
            ->values();

        return response()->json([
            'success' => true,
            'message' => 'Servicios públicos obtenidos exitosamente',
            'data' => [
                'services' => $services,
            ],
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/public/services/{id}",
     *     operationId="publicShowService",
     *     tags={"Services"},
     *     summary="Obtener un servicio público por ID",
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID del servicio",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Servicio público obtenido exitosamente"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Servicio público no encontrado"
     *     )
     * )
     */
    public function publicShow(int $id)
    {
        $service = Service::query()
            ->with('freelancerProfile.user.roles')
            ->where('is_active', true)
            ->whereHas('freelancerProfile', function ($profileQuery) {
                $profileQuery->whereHas(
                    'user',
                    function ($userQuery) {
                        $userQuery->where('is_active', true);
                    }
                );
            })
            ->whereKey($id)
            ->first();

        if (! $service) {
            return response()->json([
                'success' => false,
                'message' => 'Servicio público no encontrado.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Servicio público obtenido exitosamente',
            'data' => [
                'service' => $this->formatServiceResponse(
                    $service,
                    true
                ),
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
     *     path="/api/services",
     *     operationId="listServices",
     *     tags={"Services"},
     *     summary="Listar servicios",
     *     description="Retorna todos los servicios para usuarios autenticados.",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Response(
     *         response=200,
     *         description="Servicios obtenidos exitosamente"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="No autorizado"
     *     )
     * )
     */
    public function index()
    {
        $services = Service::with(
            'freelancerProfile.user.roles'
        )
            ->latest('created_at')
            ->get()
            ->map(
                fn (Service $service) =>
                    $this->formatServiceResponse($service)
            )
            ->values();

        return response()->json([
            'success' => true,
            'message' => 'Servicios obtenidos exitosamente',
            'data' => [
                'services' => $services,
            ],
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/services",
     *     operationId="createService",
     *     tags={"Services"},
     *     summary="Crear servicio",
     *     description="Crea un servicio para un perfil freelancer.",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"title","description","category"},
     *
     *             @OA\Property(
     *                 property="freelancer_id",
     *                 type="integer",
     *                 nullable=true,
     *                 example=1,
     *                 description="Solo debe enviarlo un administrador"
     *             ),
     *
     *             @OA\Property(
     *                 property="title",
     *                 type="string",
     *                 example="Desarrollo Web en Laravel"
     *             ),
     *
     *             @OA\Property(
     *                 property="description",
     *                 type="string",
     *                 example="Creación de sistemas y API REST con Laravel"
     *             ),
     *
     *             @OA\Property(
     *                 property="price",
     *                 type="number",
     *                 format="float",
     *                 nullable=true,
     *                 example=8500
     *             ),
     *
     *             @OA\Property(
     *                 property="category",
     *                 type="string",
     *                 example="Programación"
     *             ),
     *
     *             @OA\Property(
     *                 property="location",
     *                 type="string",
     *                 nullable=true,
     *                 example="Remoto"
     *             ),
     *
     *             @OA\Property(
     *                 property="is_active",
     *                 type="boolean",
     *                 example=true
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="Servicio creado exitosamente"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="No autorizado"
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

        if (! $authUser->hasAnyRole(['freelancer', 'admin'])) {
            return response()->json([
                'success' => false,
                'message' => 'Solo freelancers o administradores pueden crear servicios.',
            ], 403);
        }

        $validated = $request->validate([
            'freelancer_id' => [
                'nullable',
                'integer',
                Rule::exists('freelancer_profiles', 'id')
                    ->whereNull('deleted_at'),
            ],

            'title' => [
                'required',
                'string',
                'max:150',
            ],

            'description' => [
                'required',
                'string',
                'max:3000',
            ],

            'price' => [
                'nullable',
                'numeric',
                'min:0',
            ],

            'category' => [
                'required',
                'string',
                'max:100',
            ],

            'location' => [
                'nullable',
                'string',
                'max:150',
            ],

            'is_active' => [
                'nullable',
                'boolean',
            ],
        ]);

        if ($authUser->hasRole('admin')) {
            if (empty($validated['freelancer_id'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'El administrador debe indicar freelancer_id.',
                    'errors' => [
                        'freelancer_id' => [
                            'El campo freelancer_id es obligatorio para administradores.',
                        ],
                    ],
                ], 422);
            }

            $profile = FreelancerProfile::with('user.roles')
                ->find($validated['freelancer_id']);
        } else {
            $profile = FreelancerProfile::with('user.roles')
                ->where('user_id', $authUser->id)
                ->first();
        }

        if (! $profile) {
            return response()->json([
                'success' => false,
                'message' => 'No se encontró un perfil freelancer activo.',
            ], 422);
        }

        if (
            ! $profile->user
            || ! $profile->user->is_active
            || ! $profile->user->hasRole('freelancer')
        ) {
            return response()->json([
                'success' => false,
                'message' => 'El perfil debe pertenecer a un usuario freelancer activo.',
            ], 422);
        }

        return DB::transaction(function () use (
            $validated,
            $profile
        ) {
            $service = Service::create([
                'freelancer_id' => $profile->id,
                'title' => $validated['title'],
                'description' => $validated['description'],
                'price' => $validated['price'] ?? null,
                'category' => $validated['category'],
                'location' => $validated['location'] ?? null,
                'is_active' => $validated['is_active'] ?? true,
            ]);

            ActivityLoggerService::logCreate(
                module: 'SERVICES',
                entity: 'services',
                entityId: $service->id,
                description: "Service {$service->title} created"
            );

            $service->load(
                'freelancerProfile.user.roles'
            );

            return response()->json([
                'success' => true,
                'message' => 'Servicio creado exitosamente',
                'data' => [
                    'service' => $this->formatServiceResponse(
                        $service
                    ),
                ],
            ], 201);
        });
    }

    /**
     * @OA\Get(
     *     path="/api/services/{id}",
     *     operationId="showService",
     *     tags={"Services"},
     *     summary="Obtener servicio por ID",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID del servicio",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Servicio obtenido exitosamente"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Servicio no encontrado"
     *     )
     * )
     */
    public function show(int $id)
    {
        $service = Service::with(
            'freelancerProfile.user.roles'
        )->find($id);

        if (! $service) {
            return response()->json([
                'success' => false,
                'message' => 'Servicio no encontrado.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Servicio obtenido exitosamente',
            'data' => [
                'service' => $this->formatServiceResponse(
                    $service
                ),
            ],
        ]);
    }

    /**
     * @OA\Put(
     *     path="/api/services/{id}",
     *     operationId="updateService",
     *     tags={"Services"},
     *     summary="Actualizar servicio",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID del servicio",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="title",
     *                 type="string",
     *                 example="Desarrollo Web Actualizado"
     *             ),
     *
     *             @OA\Property(
     *                 property="description",
     *                 type="string",
     *                 example="Nueva descripción del servicio"
     *             ),
     *
     *             @OA\Property(
     *                 property="price",
     *                 type="number",
     *                 format="float",
     *                 nullable=true,
     *                 example=9000
     *             ),
     *
     *             @OA\Property(
     *                 property="category",
     *                 type="string",
     *                 example="Programación"
     *             ),
     *
     *             @OA\Property(
     *                 property="location",
     *                 type="string",
     *                 nullable=true,
     *                 example="Remoto"
     *             ),
     *
     *             @OA\Property(
     *                 property="is_active",
     *                 type="boolean",
     *                 example=true
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Servicio actualizado correctamente"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Sin permisos"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Servicio no encontrado"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error de validación"
     *     )
     * )
     */
    public function update(Request $request, int $id)
    {
        $service = Service::with(
            'freelancerProfile'
        )->find($id);

        if (! $service) {
            return response()->json([
                'success' => false,
                'message' => 'Servicio no encontrado.',
            ], 404);
        }

        if (! $this->canManageService($service)) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permisos para actualizar este servicio.',
            ], 403);
        }

        $validated = $request->validate([
            'title' => [
                'sometimes',
                'required',
                'string',
                'max:150',
            ],

            'description' => [
                'sometimes',
                'required',
                'string',
                'max:3000',
            ],

            'price' => [
                'sometimes',
                'nullable',
                'numeric',
                'min:0',
            ],

            'category' => [
                'sometimes',
                'required',
                'string',
                'max:100',
            ],

            'location' => [
                'sometimes',
                'nullable',
                'string',
                'max:150',
            ],

            'is_active' => [
                'sometimes',
                'boolean',
            ],
        ]);

        return DB::transaction(function () use (
            $service,
            $validated
        ) {
            $service->update($validated);

            ActivityLoggerService::logUpdate(
                module: 'SERVICES',
                entity: 'services',
                entityId: $service->id,
                description: "Service {$service->title} updated"
            );

            $service->refresh();

            $service->load(
                'freelancerProfile.user.roles'
            );

            return response()->json([
                'success' => true,
                'message' => 'Servicio actualizado correctamente',
                'data' => [
                    'service' => $this->formatServiceResponse(
                        $service
                    ),
                ],
            ]);
        });
    }

    /**
     * @OA\Delete(
     *     path="/api/services/{id}",
     *     operationId="deleteService",
     *     tags={"Services"},
     *     summary="Eliminar servicio",
     *     description="Elimina lógicamente un servicio existente.",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID del servicio",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Servicio eliminado correctamente"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Sin permisos"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Servicio no encontrado"
     *     )
     * )
     */
    public function destroy(int $id)
    {
        $service = Service::with(
            'freelancerProfile'
        )->find($id);

        if (! $service) {
            return response()->json([
                'success' => false,
                'message' => 'Servicio no encontrado.',
            ], 404);
        }

        if (! $this->canManageService($service)) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permisos para eliminar este servicio.',
            ], 403);
        }

        DB::transaction(function () use ($service) {
            $serviceId = $service->id;
            $serviceTitle = $service->title;

            ActivityLoggerService::logDelete(
                module: 'SERVICES',
                entity: 'services',
                entityId: $serviceId,
                description: "Service {$serviceTitle} deleted"
            );

            /*
             * Si el modelo Service utiliza SoftDeletes,
             * el registro permanecerá como historial.
             */
            $service->delete();
        });

        return response()->json([
            'success' => true,
            'message' => 'Servicio eliminado correctamente.',
        ]);
    }
}