<?php

namespace App\Http\Controllers;

use App\Models\Briefcase;
use App\Models\FreelancerProfile;
use App\Models\User;
use App\Services\ActivityLoggerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * @OA\Tag(
 *     name="Briefcases",
 *     description="Endpoints para la gestión del portafolio de freelancers"
 * )
 */
class BriefcaseController extends Controller
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

        return $user->roles->firstWhere('name', 'freelancer')
            ?? $user->roles->first();
    }

    /**
     * Convierte una ruta interna del disco public en una URL pública.
     * También conserva URLs externas antiguas si ya existieran.
     */
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

    /**
     * Información privada para endpoints protegidos.
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
     * Información segura para endpoints públicos.
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

            // Ruta interna guardada en la base de datos.
            'image_path' => $briefcase->image_url,

            // URL completa lista para utilizarse en el frontend.
            'image_url' => $this->buildPublicStorageUrl(
                $briefcase->image_url
            ),

            'project_url' => $briefcase->project_url,
            'created_at' => $briefcase->created_at,
            'updated_at' => $briefcase->updated_at,
        ];
    }

    /**
     * Consulta base para portafolios públicos.
     */
    private function publicBriefcaseQuery()
    {
        return Briefcase::query()
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
            );
    }

    /**
     * Obtiene un perfil freelancer visible públicamente.
     */
    private function findPublicFreelancerProfile(
        int $freelancerId
    ): ?FreelancerProfile {
        return FreelancerProfile::query()
            ->with('user.roles')
            ->whereKey($freelancerId)
            ->whereHas(
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
            )
            ->first();
    }

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

    private function canViewPrivateFreelancer(
        FreelancerProfile $profile
    ): bool {
        $user = auth('api')->user();

        if (! $user) {
            return false;
        }

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
     *     path="/api/public/briefcases",
     *     operationId="publicListBriefcases",
     *     tags={"Briefcases"},
     *     summary="Listar todos los proyectos públicos",
     *     @OA\Response(
     *         response=200,
     *         description="Portafolios públicos obtenidos exitosamente"
     *     )
     * )
     */
    public function publicIndex()
    {
        $briefcases = $this->publicBriefcaseQuery()
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
     *     path="/api/public/briefcases/freelancer/{freelancerId}",
     *     operationId="publicBriefcasesByFreelancer",
     *     tags={"Briefcases"},
     *     summary="Obtener portafolio público por ID de freelancer",
     *
     *     @OA\Parameter(
     *         name="freelancerId",
     *         in="path",
     *         required=true,
     *         description="ID del perfil freelancer",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Portafolio obtenido exitosamente"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Perfil freelancer no encontrado"
     *     )
     * )
     */
    public function publicByFreelancer(int $freelancerId)
    {
        $profile = $this->findPublicFreelancerProfile(
            $freelancerId
        );

        if (! $profile) {
            return response()->json([
                'success' => false,
                'message' => 'Perfil freelancer público no encontrado.',
            ], 404);
        }

        $briefcases = Briefcase::query()
            ->with('freelancerProfile.user.roles')
            ->where('freelancer_id', $profile->id)
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
            'message' => 'Portafolio público del freelancer obtenido exitosamente',
            'data' => [
                'freelancer_profile' =>
                    $this->formatFreelancerProfileResponse(
                        $profile,
                        true
                    ),
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
     *         description="ID del proyecto",
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
        $briefcase = $this->publicBriefcaseQuery()
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
                'briefcase' =>
                    $this->formatBriefcaseResponse(
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
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Response(
     *         response=200,
     *         description="Portafolios obtenidos exitosamente"
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
     * @OA\Get(
     *     path="/api/briefcases/me",
     *     operationId="listMyBriefcases",
     *     tags={"Briefcases"},
     *     summary="Obtener mi portafolio",
     *     description="Obtiene los proyectos del perfil freelancer asociado al usuario autenticado.",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Response(
     *         response=200,
     *         description="Portafolio obtenido exitosamente"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="El usuario no tiene rol freelancer"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Perfil freelancer no encontrado"
     *     )
     * )
     */
    public function myBriefcases()
    {
        $authUser = auth('api')->user();

        if (! $authUser) {
            return response()->json([
                'success' => false,
                'message' => 'No autorizado.',
            ], 401);
        }

        if (! $authUser->hasRole('freelancer')) {
            return response()->json([
                'success' => false,
                'message' => 'Solo los usuarios freelancer tienen portafolio.',
            ], 403);
        }

        $profile = FreelancerProfile::with('user.roles')
            ->where('user_id', $authUser->id)
            ->first();

        if (! $profile) {
            return response()->json([
                'success' => false,
                'message' => 'No se encontró un perfil freelancer activo para tu cuenta.',
            ], 404);
        }

        $briefcases = Briefcase::query()
            ->with('freelancerProfile.user.roles')
            ->where('freelancer_id', $profile->id)
            ->latest('created_at')
            ->get()
            ->map(
                fn (Briefcase $briefcase) =>
                    $this->formatBriefcaseResponse($briefcase)
            )
            ->values();

        return response()->json([
            'success' => true,
            'message' => 'Tu portafolio fue obtenido exitosamente',
            'data' => [
                'freelancer_profile' =>
                    $this->formatFreelancerProfileResponse(
                        $profile
                    ),
                'briefcases' => $briefcases,
            ],
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/briefcases/freelancer/{freelancerId}",
     *     operationId="privateBriefcasesByFreelancer",
     *     tags={"Briefcases"},
     *     summary="Obtener portafolio privado por ID de freelancer",
     *     description="Solo puede consultarlo el propietario del perfil o un administrador.",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="freelancerId",
     *         in="path",
     *         required=true,
     *         description="ID del perfil freelancer",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Portafolio obtenido exitosamente"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Sin permisos"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Perfil freelancer no encontrado"
     *     )
     * )
     */
    public function byFreelancer(int $freelancerId)
    {
        $profile = FreelancerProfile::with(
            'user.roles'
        )->find($freelancerId);

        if (! $profile) {
            return response()->json([
                'success' => false,
                'message' => 'Perfil freelancer no encontrado.',
            ], 404);
        }

        if (! $this->canViewPrivateFreelancer($profile)) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permisos para consultar este portafolio privado.',
            ], 403);
        }

        $briefcases = Briefcase::query()
            ->with('freelancerProfile.user.roles')
            ->where('freelancer_id', $profile->id)
            ->latest('created_at')
            ->get()
            ->map(
                fn (Briefcase $briefcase) =>
                    $this->formatBriefcaseResponse($briefcase)
            )
            ->values();

        return response()->json([
            'success' => true,
            'message' => 'Portafolio del freelancer obtenido exitosamente',
            'data' => [
                'freelancer_profile' =>
                    $this->formatFreelancerProfileResponse(
                        $profile
                    ),
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
     *     description="Crea un proyecto y permite subir una imagen mediante multipart/form-data.",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *
     *             @OA\Schema(
     *                 required={"title"},
     *
     *                 @OA\Property(
     *                     property="freelancer_id",
     *                     type="integer",
     *                     nullable=true,
     *                     example=1,
     *                     description="Solo debe enviarlo un administrador"
     *                 ),
     *
     *                 @OA\Property(
     *                     property="title",
     *                     type="string",
     *                     example="E-commerce en Laravel"
     *                 ),
     *
     *                 @OA\Property(
     *                     property="description",
     *                     type="string",
     *                     nullable=true,
     *                     example="Desarrollo completo de una tienda en línea"
     *                 ),
     *
     *                 @OA\Property(
     *                     property="image_url",
     *                     type="string",
     *                     format="binary",
     *                     nullable=true,
     *                     description="Imagen opcional del proyecto. Formatos: JPG, JPEG, PNG o WEBP. Máximo 2 MB."
     *                 ),
     *
     *                 @OA\Property(
     *                     property="project_url",
     *                     type="string",
     *                     nullable=true,
     *                     example="https://github.com/usuario/repositorio"
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="Proyecto creado exitosamente"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Sin permisos"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error de validación"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error interno del servidor"
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
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:2048',
            ],

            'project_url' => [
                'nullable',
                'url',
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

        $imagePath = null;

        if ($request->hasFile('image_url')) {
            $imagePath = $request
                ->file('image_url')
                ->store('briefcase_images', 'public');
        }

        try {
            $briefcase = DB::transaction(function () use (
                $validated,
                $profile,
                $imagePath
            ) {
                $briefcase = Briefcase::create([
                    'freelancer_id' => $profile->id,
                    'title' => $validated['title'],
                    'description' =>
                        $validated['description'] ?? null,
                    'image_url' => $imagePath,
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

                return $briefcase;
            });
        } catch (\Throwable $exception) {
            if (
                $imagePath
                && Storage::disk('public')->exists($imagePath)
            ) {
                Storage::disk('public')->delete($imagePath);
            }

            return response()->json([
                'success' => false,
                'message' => 'No se pudo crear el proyecto de portafolio.',
                'error' => $exception->getMessage(),
            ], 500);
        }

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
    }

    /**
     * @OA\Get(
     *     path="/api/briefcases/{id}",
     *     operationId="showBriefcase",
     *     tags={"Briefcases"},
     *     summary="Obtener proyecto por ID",
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
                'briefcase' =>
                    $this->formatBriefcaseResponse(
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
    *     description="Actualiza datos del proyecto (título, descripción y enlace).",
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
     *                 @OA\Property(
     *                     property="title",
     *                     type="string",
     *                     example="E-commerce actualizado"
     *                 ),
     *
     *                 @OA\Property(
     *                     property="description",
     *                     type="string",
     *                     nullable=true,
     *                     example="Descripción actualizada del proyecto"
     *                 ),
     *
     *                 @OA\Property(
     *                     property="project_url",
     *                     type="string",
     *                     nullable=true,
     *                     example="https://github.com/usuario/repositorio-actualizado"
     *                 ),
     *
     *                 @OA\Property(
     *                     property="project_link",
     *                     type="string",
     *                     nullable=true,
     *                     example="https://github.com/usuario/repositorio-actualizado"
     *                 )
    *             )
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
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error interno del servidor"
     *     )
     * )
     */
    public function update(
        Request $request,
        int $id
    ) {
        if (
            ! $request->has('project_url')
            && $request->filled('project_link')
        ) {
            $request->merge([
                'project_url' => $request->input('project_link'),
            ]);
        }

        if (
            ! $request->has('project_url')
            && $request->filled('url_proyecto')
        ) {
            $request->merge([
                'project_url' => $request->input('url_proyecto'),
            ]);
        }

        if (
            $request->has('project_url')
            && $request->input('project_url') === ''
        ) {
            $request->merge([
                'project_url' => null,
            ]);
        }

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

            'project_url' => [
                'sometimes',
                'nullable',
                'url',
                'max:255',
            ],

            'project_link' => [
                'sometimes',
                'nullable',
                'url',
                'max:255',
            ],

            'url_proyecto' => [
                'sometimes',
                'nullable',
                'url',
                'max:255',
            ],
        ]);

        $data = [];

        foreach ([
            'title',
            'description',
            'project_url',
        ] as $field) {
            if (array_key_exists($field, $validated)) {
                $data[$field] = $validated[$field];
            }
        }

        if (
            ! array_key_exists('project_url', $data)
            && array_key_exists('project_link', $validated)
        ) {
            $data['project_url'] = $validated['project_link'];
        }

        if (
            ! array_key_exists('project_url', $data)
            && array_key_exists('url_proyecto', $validated)
        ) {
            $data['project_url'] = $validated['url_proyecto'];
        }

        if (empty($data)) {
            return response()->json([
                'success' => false,
                'message' => 'No se enviaron cambios para actualizar.',
            ], 422);
        }

        try {
            DB::transaction(function () use (
                $briefcase,
                $data
            ) {
                if (! empty($data)) {
                    $briefcase->update($data);
                }

                ActivityLoggerService::logUpdate(
                    module: 'BRIEFCASES',
                    entity: 'briefcases',
                    entityId: $briefcase->id,
                    description: "Briefcase project {$briefcase->title} updated"
                );
            });
        } catch (\Throwable $exception) {
            return response()->json([
                'success' => false,
                'message' => 'No se pudo actualizar el proyecto de portafolio.',
                'error' => $exception->getMessage(),
            ], 500);
        }

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
    }

    /**
     * @OA\Post(
     *     path="/api/briefcases/{id}/image",
     *     operationId="updateBriefcaseImage",
     *     tags={"Briefcases"},
     *     summary="Actualizar imagen de proyecto de portafolio",
     *     description="Actualiza solo la imagen del proyecto mediante multipart/form-data.",
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
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *
     *             @OA\Schema(
     *                 required={"image_url"},
     *
     *                 @OA\Property(
     *                     property="image_url",
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
     *         description="Imagen actualizada correctamente"
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
     *         description="Archivo inválido"
     *     )
     * )
     */
    public function updateImage(
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

        $request->validate([
            'image_url' => [
                'required',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:2048',
            ],
        ]);

        $oldImagePath = $briefcase->image_url;

        $newImagePath = $request
            ->file('image_url')
            ->store('briefcase_images', 'public');

        try {
            DB::transaction(function () use (
                $briefcase,
                $newImagePath
            ) {
                $briefcase->update([
                    'image_url' => $newImagePath,
                ]);

                ActivityLoggerService::logUpdate(
                    module: 'BRIEFCASES',
                    entity: 'briefcases',
                    entityId: $briefcase->id,
                    description: "Briefcase project {$briefcase->title} image updated"
                );
            });
        } catch (\Throwable $exception) {
            if (Storage::disk('public')->exists($newImagePath)) {
                Storage::disk('public')->delete($newImagePath);
            }

            return response()->json([
                'success' => false,
                'message' => 'No se pudo actualizar la imagen del proyecto.',
                'error' => $exception->getMessage(),
            ], 500);
        }

        if (
            $oldImagePath
            && $oldImagePath !== $newImagePath
            && ! filter_var(
                $oldImagePath,
                FILTER_VALIDATE_URL
            )
            && Storage::disk('public')->exists($oldImagePath)
        ) {
            Storage::disk('public')->delete($oldImagePath);
        }

        $briefcase->refresh();
        $briefcase->load('freelancerProfile.user.roles');

        return response()->json([
            'success' => true,
            'message' => 'Imagen del proyecto actualizada correctamente.',
            'data' => [
                'briefcase' =>
                    $this->formatBriefcaseResponse(
                        $briefcase
                    ),
            ],
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/briefcases/{id}/image",
     *     operationId="deleteBriefcaseImage",
     *     tags={"Briefcases"},
     *     summary="Eliminar imagen de proyecto de portafolio",
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
     *         description="Imagen eliminada correctamente"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Sin permisos"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Proyecto no encontrado o sin imagen"
     *     )
     * )
     */
    public function destroyImage(int $id)
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
                'message' => 'No tienes permisos para actualizar este proyecto.',
            ], 403);
        }

        if (! $briefcase->image_url) {
            return response()->json([
                'success' => false,
                'message' => 'Este proyecto no tiene imagen para eliminar.',
            ], 404);
        }

        $oldImagePath = $briefcase->image_url;

        try {
            DB::transaction(function () use ($briefcase) {
                $briefcase->update([
                    'image_url' => null,
                ]);

                ActivityLoggerService::logUpdate(
                    module: 'BRIEFCASES',
                    entity: 'briefcases',
                    entityId: $briefcase->id,
                    description: "Briefcase project {$briefcase->title} image removed"
                );
            });
        } catch (\Throwable $exception) {
            return response()->json([
                'success' => false,
                'message' => 'No se pudo eliminar la imagen del proyecto.',
                'error' => $exception->getMessage(),
            ], 500);
        }

        if (
            ! filter_var(
                $oldImagePath,
                FILTER_VALIDATE_URL
            )
            && Storage::disk('public')->exists($oldImagePath)
        ) {
            Storage::disk('public')->delete($oldImagePath);
        }

        $briefcase->refresh();
        $briefcase->load('freelancerProfile.user.roles');

        return response()->json([
            'success' => true,
            'message' => 'Imagen del proyecto eliminada correctamente.',
            'data' => [
                'briefcase' =>
                    $this->formatBriefcaseResponse(
                        $briefcase
                    ),
            ],
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/briefcases/{id}",
     *     operationId="deleteBriefcase",
     *     tags={"Briefcases"},
     *     summary="Eliminar proyecto de portafolio",
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

            $briefcase->delete();
        });

        return response()->json([
            'success' => true,
            'message' => 'Proyecto eliminado del portafolio correctamente.',
        ]);
    }
}