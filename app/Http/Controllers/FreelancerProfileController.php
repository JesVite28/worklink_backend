<?php

namespace App\Http\Controllers;

use App\Models\FreelancerProfile;
use App\Models\User;
use App\Services\ActivityLoggerService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * @OA\Tag(
 *     name="Freelancer Profiles",
 *     description="Endpoints para la gestión de los perfiles de freelancers"
 * )
 */
class FreelancerProfileController extends Controller
{
    private const WORK_MODES = [
        'remote',
        'on_site',
        'hybrid',
        'home_service',
    ];

    private const RATE_TYPES = [
        'hourly',
        'daily',
        'project',
        'negotiable',
    ];

    /**
     * Formatea la información del rol.
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
     * Formatea la información básica del usuario.
     */
    private function formatUserResponse(User $user): array
    {
        $user->loadMissing('roles');

        return [
            'id' => $user->id,
            'name' => $user->name,
            'last_name' => $user->last_name,
            'maternal_last_name' => $user->maternal_last_name,
            'email' => $user->email,
            'phone' => $user->phone,
            'profile_photo' => $user->profile_photo,
            'is_active' => $user->is_active,
            'role' => $this->formatRoleResponse($user->roles->first()),
        ];
    }

    /**
     * Formatea la información completa del perfil.
     */
    private function formatProfileResponse(FreelancerProfile $profile): array
    {
        $profile->loadMissing('user.roles');

        $data = [
            'id' => $profile->id,
            'user_id' => $profile->user_id,

            'user' => $profile->user
                ? $this->formatUserResponse($profile->user)
                : null,

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

            'created_at' => $profile->created_at,
            'updated_at' => $profile->updated_at,
        ];

        if ($profile->relationLoaded('services')) {
            $data['services'] = $profile->services;
        }

        if ($profile->relationLoaded('briefcases')) {
            $data['briefcases'] = $profile->briefcases;
        }

        if ($profile->relationLoaded('availabilities')) {
            $data['availabilities'] = $profile->availabilities;
        }

        return $data;
    }

    /**
     * Verifica si el usuario autenticado puede administrar el perfil.
     */
    private function canManageProfile(FreelancerProfile $profile): bool
    {
        $user = auth('api')->user();

        if (! $user) {
            return false;
        }

        return $user->hasRole('admin')
            || $profile->user_id === $user->id;
    }

    /**
     * @OA\Get(
     *     path="/api/profiles",
     *     operationId="listFreelancerProfiles",
     *     tags={"Freelancer Profiles"},
     *     summary="Listar perfiles de freelancers",
     *     description="Retorna todos los perfiles de freelancers con su usuario y rol principal.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Perfiles obtenidos exitosamente"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="No autorizado"
     *     )
     * )
     */
    public function index()
    {
        $profiles = FreelancerProfile::with('user.roles')
            ->latest('created_at')
            ->get()
            ->map(
                fn($profile) => $this->formatProfileResponse($profile)
            )
            ->values();

        return response()->json([
            'success' => true,
            'message' => 'Perfiles de freelancers obtenidos exitosamente',
            'data' => [
                'profiles' => $profiles,
            ],
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/profiles",
     *     operationId="createFreelancerProfile",
     *     tags={"Freelancer Profiles"},
     *     summary="Crear perfil de freelancer",
     *     description="Crea el perfil profesional de un freelancer.",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={
     *                 "description",
     *                 "specialty",
     *                 "location",
     *                 "service_area",
     *                 "work_mode",
     *                 "experience",
     *                 "rate_type"
     *             },
     *
     *             @OA\Property(
     *                 property="user_id",
     *                 type="integer",
     *                 example=3,
     *                 description="Solo debe enviarlo un administrador"
     *             ),
     *
     *             @OA\Property(
     *                 property="description",
     *                 type="string",
     *                 example="Desarrollador Full Stack especializado en aplicaciones web"
     *             ),
     *
     *             @OA\Property(
     *                 property="specialty",
     *                 type="string",
     *                 example="Desarrollo Web"
     *             ),
     *
     *             @OA\Property(
     *                 property="location",
     *                 type="string",
     *                 example="Pachuca, Hidalgo"
     *             ),
     *
     *             @OA\Property(
     *                 property="service_area",
     *                 type="string",
     *                 example="Desarrollo de sistemas web y aplicaciones empresariales"
     *             ),
     *
     *             @OA\Property(
     *                 property="work_mode",
     *                 type="string",
     *                 enum={"remote","on_site","hybrid","home_service"},
     *                 example="remote"
     *             ),
     *
     *             @OA\Property(
     *                 property="experience",
     *                 type="string",
     *                 example="Tres años desarrollando aplicaciones con Laravel y React"
     *             ),
     *
     *             @OA\Property(
     *                 property="rate_type",
     *                 type="string",
     *                 enum={"hourly","daily","project","negotiable"},
     *                 example="project"
     *             ),
     *
     *             @OA\Property(
     *                 property="rate",
     *                 type="number",
     *                 format="float",
     *                 nullable=true,
     *                 example=8500
     *             ),
     *
     *             @OA\Property(
     *                 property="languages",
     *                 type="array",
     *                 @OA\Items(type="string"),
     *                 example={"Español","Inglés"}
     *             ),
     *
     *             @OA\Property(
     *                 property="website",
     *                 type="string",
     *                 nullable=true,
     *                 example="https://ejemplo.com"
     *             ),
     *
     *             @OA\Property(
     *                 property="facebook",
     *                 type="string",
     *                 nullable=true,
     *                 example="https://facebook.com/ejemplo"
     *             ),
     *
     *             @OA\Property(
     *                 property="instagram",
     *                 type="string",
     *                 nullable=true,
     *                 example="https://instagram.com/ejemplo"
     *             ),
     *
     *             @OA\Property(
     *                 property="linkedin",
     *                 type="string",
     *                 nullable=true,
     *                 example="https://linkedin.com/in/ejemplo"
     *             ),
     *
     *             @OA\Property(
     *                 property="github",
     *                 type="string",
     *                 nullable=true,
     *                 example="https://github.com/ejemplo"
     *             ),
     *
     *             @OA\Property(
     *                 property="portfolio_url",
     *                 type="string",
     *                 nullable=true,
     *                 example="https://behance.net/ejemplo"
     *             ),
     *
     *             @OA\Property(
     *                 property="available",
     *                 type="boolean",
     *                 example=true
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="Perfil creado exitosamente"
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
                'message' => 'Solo freelancers o administradores pueden crear perfiles de freelancer.',
            ], 403);
        }

        $validated = $request->validate([
            'user_id' => [
                'nullable',
                'integer',
                'exists:users,id',
                'unique:freelancer_profiles,user_id',
            ],

            'description' => [
                'required',
                'string',
                'max:500',
            ],

            'specialty' => [
                'required',
                'string',
                'max:150',
            ],

            'location' => [
                'required',
                'string',
                'max:150',
            ],

            'service_area' => [
                'required',
                'string',
                'max:150',
            ],

            'work_mode' => [
                'required',
                Rule::in(self::WORK_MODES),
            ],

            'experience' => [
                'required',
                'string',
                'max:3000',
            ],

            'rate_type' => [
                'required',
                Rule::in(self::RATE_TYPES),
            ],

            'rate' => [
                'required_unless:rate_type,negotiable',
                'nullable',
                'numeric',
                'min:0',
            ],

            'languages' => [
                'nullable',
                'array',
                'max:20',
            ],

            'languages.*' => [
                'string',
                'max:80',
            ],

            'website' => [
                'nullable',
                'url',
                'max:255',
            ],

            'facebook' => [
                'nullable',
                'url',
                'max:255',
            ],

            'instagram' => [
                'nullable',
                'url',
                'max:255',
            ],

            'linkedin' => [
                'nullable',
                'url',
                'max:255',
            ],

            'github' => [
                'nullable',
                'url',
                'max:255',
            ],

            'portfolio_url' => [
                'nullable',
                'url',
                'max:255',
            ],

            'available' => [
                'nullable',
                'boolean',
            ],
        ]);

        $userId = $authUser->hasRole('admin')
            ? ($validated['user_id'] ?? $authUser->id)
            : $authUser->id;

        $user = User::with('roles')->find($userId);

        if (! $user || ! $user->hasRole('freelancer')) {
            return response()->json([
                'success' => false,
                'message' => 'El usuario seleccionado debe tener rol freelancer.',
            ], 422);
        }

        if (FreelancerProfile::where('user_id', $userId)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Este usuario ya tiene un perfil de freelancer.',
            ], 422);
        }

        if ($validated['rate_type'] === 'negotiable') {
            $validated['rate'] = null;
        }

        $validated['user_id'] = $userId;
        $validated['available'] = $validated['available'] ?? true;

        /*
         * average_rate no se recibe desde el frontend.
         * Se calculará posteriormente con base en las calificaciones.
         */
        $profile = FreelancerProfile::create($validated);

        ActivityLoggerService::logCreate(
            module: 'FREELANCER_PROFILES',
            entity: 'freelancer_profiles',
            entityId: $profile->id,
            description: "Freelancer profile created for user ID {$userId}"
        );

        $profile->load([
            'user.roles',
            'briefcases',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Perfil de freelancer creado exitosamente',
            'data' => [
                'profile' => $this->formatProfileResponse($profile),
            ],
        ], 201);
    }

    /**
     * @OA\Get(
     *     path="/api/profiles/{id}",
     *     operationId="showFreelancerProfile",
     *     tags={"Freelancer Profiles"},
     *     summary="Obtener perfil de freelancer por ID de perfil",
     *     description="Retorna el perfil, usuario, servicios, portafolio y disponibilidad.",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID del perfil del freelancer",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Perfil obtenido exitosamente"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="No autorizado"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Perfil no encontrado"
     *     )
     * )
     */
    public function show(int $id)
    {
        $profile = FreelancerProfile::with([
            'user.roles',
            'services',
            'briefcases',
            'availabilities',
        ])->find($id);

        if (! $profile) {
            return response()->json([
                'success' => false,
                'message' => 'Perfil no encontrado.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Perfil obtenido exitosamente',
            'data' => [
                'profile' => $this->formatProfileResponse($profile),
            ],
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/profiles/user/{userId}",
     *     operationId="showFreelancerProfileByUserId",
     *     tags={"Freelancer Profiles"},
     *     summary="Obtener perfil y portafolio por ID de usuario",
     *     description="Retorna el perfil profesional y portafolio interno del freelancer utilizando el ID del usuario.",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="userId",
     *         in="path",
     *         required=true,
     *         description="ID del usuario asociado al perfil freelancer",
     *         @OA\Schema(type="integer", example=3)
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Perfil y portafolio obtenidos exitosamente"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="No autorizado"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Perfil de freelancer no encontrado"
     *     )
     * )
     */
    public function showByUserId(int $userId)
    {
        $profile = FreelancerProfile::with([
            'user.roles',
            'briefcases',
        ])
            ->where('user_id', $userId)
            ->first();

        if (! $profile) {
            return response()->json([
                'success' => false,
                'message' => 'No se encontró un perfil de freelancer para este usuario.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Perfil y portafolio obtenidos exitosamente',
            'data' => [
                'profile' => $this->formatProfileResponse($profile),
            ],
        ]);
    }

    /**
     * @OA\Put(
     *     path="/api/profiles/{id}",
     *     operationId="updateFreelancerProfile",
     *     tags={"Freelancer Profiles"},
     *     summary="Actualizar perfil de freelancer",
     *     description="Actualiza la información profesional del perfil.",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID del perfil a actualizar",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="description",
     *                 type="string",
     *                 example="Desarrollador Full Stack actualizado"
     *             ),
     *
     *             @OA\Property(
     *                 property="specialty",
     *                 type="string",
     *                 example="Arquitectura de software"
     *             ),
     *
     *             @OA\Property(
     *                 property="location",
     *                 type="string",
     *                 example="Monterrey, Nuevo León"
     *             ),
     *
     *             @OA\Property(
     *                 property="service_area",
     *                 type="string",
     *                 example="Desarrollo de sistemas empresariales"
     *             ),
     *
     *             @OA\Property(
     *                 property="work_mode",
     *                 type="string",
     *                 enum={"remote","on_site","hybrid","home_service"},
     *                 example="hybrid"
     *             ),
     *
     *             @OA\Property(
     *                 property="experience",
     *                 type="string",
     *                 example="Experiencia en Laravel, React, MySQL y AWS"
     *             ),
     *
     *             @OA\Property(
     *                 property="rate_type",
     *                 type="string",
     *                 enum={"hourly","daily","project","negotiable"},
     *                 example="hourly"
     *             ),
     *
     *             @OA\Property(
     *                 property="rate",
     *                 type="number",
     *                 format="float",
     *                 nullable=true,
     *                 example=250
     *             ),
     *
     *             @OA\Property(
     *                 property="languages",
     *                 type="array",
     *                 @OA\Items(type="string"),
     *                 example={"Español","Inglés"}
     *             ),
     *
     *             @OA\Property(
     *                 property="website",
     *                 type="string",
     *                 nullable=true
     *             ),
     *
     *             @OA\Property(
     *                 property="facebook",
     *                 type="string",
     *                 nullable=true
     *             ),
     *
     *             @OA\Property(
     *                 property="instagram",
     *                 type="string",
     *                 nullable=true
     *             ),
     *
     *             @OA\Property(
     *                 property="linkedin",
     *                 type="string",
     *                 nullable=true
     *             ),
     *
     *             @OA\Property(
     *                 property="github",
     *                 type="string",
     *                 nullable=true
     *             ),
     *
     *             @OA\Property(
     *                 property="portfolio_url",
     *                 type="string",
     *                 nullable=true
     *             ),
     *
     *             @OA\Property(
     *                 property="available",
     *                 type="boolean",
     *                 example=true
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Perfil actualizado correctamente"
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
     *         response=404,
     *         description="Perfil no encontrado"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error de validación"
     *     )
     * )
     */
    public function update(Request $request, int $id)
    {
        $profile = FreelancerProfile::with('user.roles')->find($id);

        if (! $profile) {
            return response()->json([
                'success' => false,
                'message' => 'Perfil no encontrado.',
            ], 404);
        }

        if (! $this->canManageProfile($profile)) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permisos para actualizar este perfil.',
            ], 403);
        }

        $validated = $request->validate([
            'description' => [
                'sometimes',
                'required',
                'string',
                'max:500',
            ],

            'specialty' => [
                'sometimes',
                'required',
                'string',
                'max:150',
            ],

            'location' => [
                'sometimes',
                'required',
                'string',
                'max:150',
            ],

            'service_area' => [
                'sometimes',
                'required',
                'string',
                'max:150',
            ],

            'work_mode' => [
                'sometimes',
                'required',
                Rule::in(self::WORK_MODES),
            ],

            'experience' => [
                'sometimes',
                'required',
                'string',
                'max:3000',
            ],

            'rate_type' => [
                'sometimes',
                'required',
                Rule::in(self::RATE_TYPES),
            ],

            'rate' => [
                'sometimes',
                'nullable',
                'numeric',
                'min:0',
            ],

            'languages' => [
                'sometimes',
                'nullable',
                'array',
                'max:20',
            ],

            'languages.*' => [
                'string',
                'max:80',
            ],

            'website' => [
                'sometimes',
                'nullable',
                'url',
                'max:255',
            ],

            'facebook' => [
                'sometimes',
                'nullable',
                'url',
                'max:255',
            ],

            'instagram' => [
                'sometimes',
                'nullable',
                'url',
                'max:255',
            ],

            'linkedin' => [
                'sometimes',
                'nullable',
                'url',
                'max:255',
            ],

            'github' => [
                'sometimes',
                'nullable',
                'url',
                'max:255',
            ],

            'portfolio_url' => [
                'sometimes',
                'nullable',
                'url',
                'max:255',
            ],

            'available' => [
                'sometimes',
                'boolean',
            ],
        ]);

        /*
         * Solo valida la combinación de tarifa cuando alguno
         * de esos dos campos está siendo actualizado.
         */
        if (
            array_key_exists('rate_type', $validated)
            || array_key_exists('rate', $validated)
        ) {
            $rateType = $validated['rate_type']
                ?? $profile->rate_type;

            $rate = array_key_exists('rate', $validated)
                ? $validated['rate']
                : $profile->rate;

            if (! $rateType) {
                return response()->json([
                    'success' => false,
                    'message' => 'Debes indicar el tipo de tarifa.',
                    'errors' => [
                        'rate_type' => [
                            'El campo tipo de tarifa es obligatorio.',
                        ],
                    ],
                ], 422);
            }

            if ($rateType === 'negotiable') {
                $validated['rate'] = null;
            } elseif ($rate === null) {
                return response()->json([
                    'success' => false,
                    'message' => 'Debes indicar una tarifa cuando el tipo no es negociable.',
                    'errors' => [
                        'rate' => [
                            'El campo tarifa es obligatorio.',
                        ],
                    ],
                ], 422);
            }
        }

        $profile->update($validated);

        ActivityLoggerService::logUpdate(
            module: 'FREELANCER_PROFILES',
            entity: 'freelancer_profiles',
            entityId: $profile->id,
            description: "Freelancer profile ID {$profile->id} updated"
        );

        $profile->refresh();

        $profile->load([
            'user.roles',
            'briefcases',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Perfil actualizado correctamente',
            'data' => [
                'profile' => $this->formatProfileResponse($profile),
            ],
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/profiles/{id}",
     *     operationId="deleteFreelancerProfile",
     *     tags={"Freelancer Profiles"},
     *     summary="Eliminar perfil de freelancer",
     *     description="Elimina el perfil especificado.",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID del perfil a eliminar",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Perfil eliminado correctamente"
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
     *         response=404,
     *         description="Perfil no encontrado"
     *     )
     * )
     */
    public function destroy(int $id)
    {
        $profile = FreelancerProfile::find($id);

        if (! $profile) {
            return response()->json([
                'success' => false,
                'message' => 'Perfil no encontrado.',
            ], 404);
        }

        if (! $this->canManageProfile($profile)) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permisos para eliminar este perfil.',
            ], 403);
        }

        ActivityLoggerService::logDelete(
            module: 'FREELANCER_PROFILES',
            entity: 'freelancer_profiles',
            entityId: $profile->id,
            description: "Freelancer profile ID {$profile->id} deleted"
        );

        $profile->delete();

        return response()->json([
            'success' => true,
            'message' => 'Perfil eliminado correctamente',
        ]);
    }
}
