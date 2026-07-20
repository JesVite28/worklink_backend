<?php

namespace App\Http\Controllers;

use App\Models\Briefcase;
use App\Models\FreelancerProfile;
use App\Models\User;
use App\Services\ActivityLoggerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * @OA\Tag(
 *     name="Briefcases",
 *     description="Endpoints para la gestión del portafolio de freelancers"
 * )
 */
class BriefcaseController extends Controller
{
    /**
     * Formatea la información de un rol.
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
     * Formatea la información privada del usuario.
     *
     * Se utiliza únicamente en endpoints protegidos.
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
     * Formatea la información pública del usuario.
     *
     * No expone correo electrónico, teléfono ni datos privados.
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
     * Formatea el perfil freelancer relacionado con el portafolio.
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
     * Formatea la información del proyecto de portafolio.
     */
    private function formatBriefcaseResponse(
        Briefcase $briefcase,
        bool $public = false
    ): array {
        $briefcase->loadMissing(
            'freelancerProfile.user.roles'
        );

        return [
            'id' => $briefcase->id,
            'freelancer_id' => $briefcase->freelancer_id,

            'freelancer_profile' =>
                $this->formatFreelancerProfileResponse(
                    $briefcase->freelancerProfile,
                    $public
                ),

            'title' => $briefcase->title,
            'description' => $briefcase->description,
            'image_url' => $briefcase->image_url,
            'project_url' => $briefcase->project_url,
            'created_at' => $briefcase->created_at,
            'updated_at' => $briefcase->updated_at,
        ];
    }

    /**
     * Verifica si el usuario autenticado puede administrar el proyecto.
     */
    private function canManageBriefcase(
        Briefcase $briefcase
    ): bool {
        $user = auth('api')->user();

        if (! $user) {
            return false;
        }

        $briefcase->loadMissing('freelancerProfile');

        return $user->hasRole('admin')
            || (
                $briefcase->freelancerProfile
                && $briefcase->freelancerProfile->user_id === $user->id
            );
    }

    /*
    |--------------------------------------------------------------------------
    | Public Endpoints
    |--------------------------------------------------------------------------
    */

    /**
     * @OA\Get(
     *     path="/api/public/briefcases",
     *     operationId="publicListBriefcases",
     *     tags={"Briefcases"},
     *     summary="Listar proyectos públicos de portafolio",
     *     description="Retorna los proyectos pertenecientes a freelancers activos sin exponer información privada.",
     *
     *     @OA\Response(
     *         response=200,
     *         description="Portafolios públicos obtenidos exitosamente"
     *     )
     * )
     */
    public function publicIndex()
    {
        $briefcases = Briefcase::query()
            ->with('freelancerProfile.user.roles')
            ->whereHas(
                'freelancerProfile',
                function ($profileQuery) {
                    $profileQuery->whereHas(
                        'user',
                        function ($userQuery) {
                            $userQuery
                                ->where('is_active', true)
                                ->whereHas(
                                    'roles',
                                    function ($roleQuery) {
                                        $roleQuery->where(
                                            'name',
                                            'freelancer'
                                        );
                                    }
                                );
                        }
                    );
                }
            )
            ->latest('created_at')
            ->get()
            ->map(
                fn (Briefcase $briefcase) =>
                    $this->formatBriefcaseResponse(
                        $briefcase,
                        true
                    )
            )
            ->values();

        return response()->json([
            'success' => true,
            'message' => 'Portafolios públicos obtenidos exitosamente',
            'data' => [
                'briefcases' => $briefcases,
            ],
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/public/briefcases/{id}",
     *     operationId="publicShowBriefcase",
     *     tags={"Briefcases"},
     *     summary="Obtener públicamente un proyecto de portafolio",
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID del proyecto de portafolio",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Proyecto público obtenido exitosamente"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Proyecto público no encontrado"
     *     )
     * )
     */
    public function publicShow(int $id)
    {
        $briefcase = Briefcase::query()
            ->with('freelancerProfile.user.roles')
            ->whereHas(
                'freelancerProfile',
                function ($profileQuery) {
                    $profileQuery->whereHas(
                        'user',
                        function ($userQuery) {
                            $userQuery
                                ->where('is_active', true)
                                ->whereHas(
                                    'roles',
                                    function ($roleQuery) {
                                        $roleQuery->where(
                                            'name',
                                            'freelancer'
                                        );
                                    }
                                );
                        }
                    );
                }
            )
            ->whereKey($id)
            ->first();

        if (! $briefcase) {
            return response()->json([
                'success' => false,
                'message' => 'Proyecto público no encontrado.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Proyecto público obtenido exitosamente',
            'data' => [
                'briefcase' => $this->formatBriefcaseResponse(
                    $briefcase,
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
     *     path="/api/briefcases",
     *     operationId="listBriefcases",
     *     tags={"Briefcases"},
     *     summary="Listar portafolios",
     *     description="Retorna todos los proyectos de portafolio para usuarios autenticados.",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Response(
     *         response=200,
     *         description="Portafolios obtenidos exitosamente"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="No autorizado"
     *     )
     * )
     */
    public function index()
    {
        $briefcases = Briefcase::with(
            'freelancerProfile.user.roles'
        )
            ->latest('created_at')
            ->get()
            ->map(
                fn (Briefcase $briefcase) =>
                    $this->formatBriefcaseResponse($briefcase)
            )
            ->values();

        return response()->json([
            'success' => true,
            'message' => 'Portafolios obtenidos exitosamente',
            'data' => [
                'briefcases' => $briefcases,
            ],
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/briefcases",
     *     operationId="createBriefcase",
     *     tags={"Briefcases"},
     *     summary="Crear proyecto de portafolio",
     *     description="Crea un proyecto para el portafolio de un freelancer.",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"title"},
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
     *                 example="E-commerce en Laravel"
     *             ),
     *
     *             @OA\Property(
     *                 property="description",
     *                 type="string",
     *                 nullable=true,
     *                 example="Desarrollo completo de una tienda en línea"
     *             ),
     *
     *             @OA\Property(
     *                 property="image_url",
     *                 type="string",
     *                 nullable=true,
     *                 example="https://misitio.com/imagen.jpg"
     *             ),
     *
     *             @OA\Property(
     *                 property="project_url",
     *                 type="string",
     *                 nullable=true,
     *                 example="https://github.com/usuario/repositorio"
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="Proyecto creado exitosamente"
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
                'message' => 'Solo freelancers o administradores pueden crear proyectos de portafolio.',
            ], 403);
        }

        $validated = $request->validate([
            'freelancer_id' => [
                'nullable',
                'integer',
                Rule::exists(
                    'freelancer_profiles',
                    'id'
                )->whereNull('deleted_at'),
            ],

            'title' => [
                'required',
                'string',
                'max:150',
            ],

            'description' => [
                'nullable',
                'string',
                'max:3000',
            ],

            'image_url' => [
                'nullable',
                'string',
                'max:255',
            ],

            'project_url' => [
                'nullable',
                'string',
                'max:255',
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

            $profile = FreelancerProfile::with(
                'user.roles'
            )->find($validated['freelancer_id']);
        } else {
            $profile = FreelancerProfile::with(
                'user.roles'
            )
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
            $briefcase = Briefcase::create([
                'freelancer_id' => $profile->id,
                'title' => $validated['title'],
                'description' =>
                    $validated['description'] ?? null,
                'image_url' =>
                    $validated['image_url'] ?? null,
                'project_url' =>
                    $validated['project_url'] ?? null,
            ]);

            ActivityLoggerService::logCreate(
                module: 'BRIEFCASES',
                entity: 'briefcases',
                entityId: $briefcase->id,
                description: "Briefcase project {$briefcase->title} created"
            );

            $briefcase->load(
                'freelancerProfile.user.roles'
            );

            return response()->json([
                'success' => true,
                'message' => 'Proyecto añadido al portafolio exitosamente',
                'data' => [
                    'briefcase' =>
                        $this->formatBriefcaseResponse(
                            $briefcase
                        ),
                ],
            ], 201);
        });
    }

    /**
     * @OA\Get(
     *     path="/api/briefcases/{id}",
     *     operationId="showBriefcase",
     *     tags={"Briefcases"},
     *     summary="Obtener proyecto de portafolio por ID",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID del proyecto",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Proyecto obtenido exitosamente"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Proyecto no encontrado"
     *     )
     * )
     */
    public function show(int $id)
    {
        $briefcase = Briefcase::with(
            'freelancerProfile.user.roles'
        )->find($id);

        if (! $briefcase) {
            return response()->json([
                'success' => false,
                'message' => 'Proyecto no encontrado.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Proyecto obtenido exitosamente',
            'data' => [
                'briefcase' => $this->formatBriefcaseResponse(
                    $briefcase
                ),
            ],
        ]);
    }

    /**
     * @OA\Put(
     *     path="/api/briefcases/{id}",
     *     operationId="updateBriefcase",
     *     tags={"Briefcases"},
     *     summary="Actualizar proyecto de portafolio",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID del proyecto",
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
     *                 example="E-commerce actualizado"
     *             ),
     *
     *             @OA\Property(
     *                 property="description",
     *                 type="string",
     *                 nullable=true,
     *                 example="Nueva descripción del proyecto"
     *             ),
     *
     *             @OA\Property(
     *                 property="image_url",
     *                 type="string",
     *                 nullable=true,
     *                 example="https://misitio.com/nueva-imagen.jpg"
     *             ),
     *
     *             @OA\Property(
     *                 property="project_url",
     *                 type="string",
     *                 nullable=true,
     *                 example="https://github.com/usuario/nuevo-repositorio"
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Proyecto actualizado correctamente"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Sin permisos"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Proyecto no encontrado"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error de validación"
     *     )
     * )
     */
    public function update(
        Request $request,
        int $id
    ) {
        $briefcase = Briefcase::with(
            'freelancerProfile'
        )->find($id);

        if (! $briefcase) {
            return response()->json([
                'success' => false,
                'message' => 'Proyecto no encontrado.',
            ], 404);
        }

        if (! $this->canManageBriefcase($briefcase)) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permisos para actualizar este proyecto.',
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
                'nullable',
                'string',
                'max:3000',
            ],

            'image_url' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],

            'project_url' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],
        ]);

        return DB::transaction(function () use (
            $briefcase,
            $validated
        ) {
            $briefcase->update($validated);

            ActivityLoggerService::logUpdate(
                module: 'BRIEFCASES',
                entity: 'briefcases',
                entityId: $briefcase->id,
                description: "Briefcase project {$briefcase->title} updated"
            );

            $briefcase->refresh();

            $briefcase->load(
                'freelancerProfile.user.roles'
            );

            return response()->json([
                'success' => true,
                'message' => 'Proyecto actualizado correctamente',
                'data' => [
                    'briefcase' =>
                        $this->formatBriefcaseResponse(
                            $briefcase
                        ),
                ],
            ]);
        });
    }

    /**
     * @OA\Delete(
     *     path="/api/briefcases/{id}",
     *     operationId="deleteBriefcase",
     *     tags={"Briefcases"},
     *     summary="Eliminar proyecto de portafolio",
     *     description="Elimina lógicamente un proyecto de portafolio.",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID del proyecto",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Proyecto eliminado correctamente"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Sin permisos"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Proyecto no encontrado"
     *     )
     * )
     */
    public function destroy(int $id)
    {
        $briefcase = Briefcase::with(
            'freelancerProfile'
        )->find($id);

        if (! $briefcase) {
            return response()->json([
                'success' => false,
                'message' => 'Proyecto no encontrado.',
            ], 404);
        }

        if (! $this->canManageBriefcase($briefcase)) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permisos para eliminar este proyecto.',
            ], 403);
        }

        DB::transaction(function () use ($briefcase) {
            $briefcaseId = $briefcase->id;
            $briefcaseTitle = $briefcase->title;

            ActivityLoggerService::logDelete(
                module: 'BRIEFCASES',
                entity: 'briefcases',
                entityId: $briefcaseId,
                description: "Briefcase project {$briefcaseTitle} deleted"
            );

            /*
             * Si el modelo Briefcase usa SoftDeletes,
             * el registro permanece como historial.
             */
            $briefcase->delete();
        });

        return response()->json([
            'success' => true,
            'message' => 'Proyecto eliminado del portafolio correctamente.',
        ]);
    }
}