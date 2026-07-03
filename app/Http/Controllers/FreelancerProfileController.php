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

    private function formatUserResponse(User $user): array
    {
        $user->loadMissing('roles');

        return [
            'id' => $user->id,
            'name' => $user->name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'phone' => $user->phone,
            'profile_photo' => $user->profile_photo,
            'is_active' => $user->is_active,
            'role' => $this->formatRoleResponse($user->roles->first()),
        ];
    }

    private function formatProfileResponse(FreelancerProfile $profile): array
    {
        $profile->loadMissing('user.roles');

        $data = [
            'id' => $profile->id,
            'user_id' => $profile->user_id,
            'user' => $profile->user ? $this->formatUserResponse($profile->user) : null,
            'description' => $profile->description,
            'specialty' => $profile->specialty,
            'hourly_rate' => $profile->hourly_rate,
            'location' => $profile->location,
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

    private function canManageProfile(FreelancerProfile $profile): bool
    {
        $user = auth('api')->user();

        if (! $user) {
            return false;
        }

        return $user->hasRole('admin') || $profile->user_id === $user->id;
    }

    /**
     * @OA\Get(
     *     path="/api/profiles",
     *     operationId="listFreelancerProfiles",
     *     tags={"Freelancer Profiles"},
     *     summary="Listar perfiles de freelancers",
     *     description="Retorna todos los perfiles de freelancers con su usuario y rol principal.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Perfiles obtenidos exitosamente"),
     *     @OA\Response(response=401, description="No autorizado. Token requerido o inválido")
     * )
     */
    public function index()
    {
        $profiles = FreelancerProfile::with('user.roles')
            ->latest('created_at')
            ->get()
            ->map(fn ($profile) => $this->formatProfileResponse($profile))
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
     *     description="Crea un perfil de freelancer. Un freelancer crea su propio perfil; un admin puede crear el perfil indicando user_id.",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="user_id", type="integer", example=3, description="Solo requerido si el usuario autenticado es admin"),
     *             @OA\Property(property="description", type="string", example="Desarrollador Full Stack con experiencia en Laravel y React"),
     *             @OA\Property(property="specialty", type="string", example="Desarrollo Web"),
     *             @OA\Property(property="hourly_rate", type="number", format="float", example=25.50),
     *             @OA\Property(property="location", type="string", example="Pachuca, Hidalgo"),
     *             @OA\Property(property="available", type="boolean", example=true),
     *             @OA\Property(property="average_rate", type="number", format="float", example=5.00)
     *         )
     *     ),
     *     @OA\Response(response=201, description="Perfil creado exitosamente"),
     *     @OA\Response(response=401, description="No autorizado. Token requerido o inválido"),
     *     @OA\Response(response=403, description="Sin permisos"),
     *     @OA\Response(response=422, description="Error de validación")
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
            'description' => 'nullable|string',
            'specialty' => 'nullable|string|max:150',
            'hourly_rate' => 'nullable|numeric|min:0',
            'location' => 'nullable|string|max:150',
            'available' => 'nullable|boolean',
            'average_rate' => 'nullable|numeric|min:0|max:5',
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

        $profile = FreelancerProfile::create([
            'user_id' => $userId,
            'description' => $validated['description'] ?? null,
            'specialty' => $validated['specialty'] ?? null,
            'hourly_rate' => $validated['hourly_rate'] ?? null,
            'location' => $validated['location'] ?? null,
            'available' => $validated['available'] ?? true,
            'average_rate' => $validated['average_rate'] ?? null,
        ]);

        ActivityLoggerService::logCreate(
            module: 'FREELANCER_PROFILES',
            entity: 'freelancer_profiles',
            entityId: $profile->id,
            description: "Freelancer profile created for user ID {$userId}"
        );

        $profile->load('user.roles');

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
     *     summary="Obtener perfil de freelancer por ID",
     *     description="Retorna los detalles del perfil, incluyendo usuario, rol, servicios, portafolio y disponibilidad.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID del perfil del freelancer",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(response=200, description="Perfil obtenido exitosamente"),
     *     @OA\Response(response=401, description="No autorizado. Token requerido o inválido"),
     *     @OA\Response(response=404, description="Perfil no encontrado")
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
                'message' => 'Perfil no encontrado',
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
     * @OA\Put(
     *     path="/api/profiles/{id}",
     *     operationId="updateFreelancerProfile",
     *     tags={"Freelancer Profiles"},
     *     summary="Actualizar perfil de freelancer",
     *     description="Actualiza la información pública del perfil.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID del perfil a actualizar",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="description", type="string", example="Descripción actualizada"),
     *             @OA\Property(property="specialty", type="string", example="Arquitecto de Software"),
     *             @OA\Property(property="hourly_rate", type="number", format="float", example=30.00),
     *             @OA\Property(property="location", type="string", example="Monterrey"),
     *             @OA\Property(property="available", type="boolean", example=false),
     *             @OA\Property(property="average_rate", type="number", format="float", example=4.80)
     *         )
     *     ),
     *     @OA\Response(response=200, description="Perfil actualizado correctamente"),
     *     @OA\Response(response=401, description="No autorizado. Token requerido o inválido"),
     *     @OA\Response(response=403, description="Sin permisos"),
     *     @OA\Response(response=404, description="Perfil no encontrado"),
     *     @OA\Response(response=422, description="Error de validación")
     * )
     */
    public function update(Request $request, int $id)
    {
        $profile = FreelancerProfile::with('user.roles')->find($id);

        if (! $profile) {
            return response()->json([
                'success' => false,
                'message' => 'Perfil no encontrado',
            ], 404);
        }

        if (! $this->canManageProfile($profile)) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permisos para actualizar este perfil.',
            ], 403);
        }

        $validated = $request->validate([
            'description' => 'nullable|string',
            'specialty' => 'nullable|string|max:150',
            'hourly_rate' => 'nullable|numeric|min:0',
            'location' => 'nullable|string|max:150',
            'available' => 'nullable|boolean',
            'average_rate' => 'nullable|numeric|min:0|max:5',
        ]);

        $profile->update($validated);

        ActivityLoggerService::logUpdate(
            module: 'FREELANCER_PROFILES',
            entity: 'freelancer_profiles',
            entityId: $profile->id,
            description: "Freelancer profile ID {$profile->id} updated"
        );

        $profile->refresh();
        $profile->load('user.roles');

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
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID del perfil a eliminar",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(response=200, description="Perfil eliminado correctamente"),
     *     @OA\Response(response=401, description="No autorizado. Token requerido o inválido"),
     *     @OA\Response(response=403, description="Sin permisos"),
     *     @OA\Response(response=404, description="Perfil no encontrado")
     * )
     */
    public function destroy(int $id)
    {
        $profile = FreelancerProfile::find($id);

        if (! $profile) {
            return response()->json([
                'success' => false,
                'message' => 'Perfil no encontrado',
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