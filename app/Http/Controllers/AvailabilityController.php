<?php

namespace App\Http\Controllers;

use App\Models\Availability;
use App\Models\FreelancerProfile;
use App\Models\User;
use App\Services\ActivityLoggerService;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="Availabilities",
 *     description="Endpoints para la gestión de la disponibilidad horaria de freelancers"
 * )
 */
class AvailabilityController extends Controller
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

    private function formatFreelancerProfileResponse(?FreelancerProfile $profile): ?array
    {
        if (! $profile) {
            return null;
        }

        $profile->loadMissing('user.roles');

        return [
            'id' => $profile->id,
            'user_id' => $profile->user_id,
            'user' => $profile->user ? $this->formatUserResponse($profile->user) : null,
            'description' => $profile->description,
            'specialty' => $profile->specialty,
            'hourly_rate' => $profile->hourly_rate,
            'location' => $profile->location,
            'available' => $profile->available,
            'average_rate' => $profile->average_rate,
        ];
    }

    private function formatAvailabilityResponse(Availability $availability): array
    {
        $availability->loadMissing('freelancerProfile.user.roles');

        return [
            'id' => $availability->id,
            'freelancer_id' => $availability->freelancer_id,
            'freelancer_profile' => $this->formatFreelancerProfileResponse($availability->freelancerProfile),
            'start_date' => $availability->start_date,
            'end_date' => $availability->end_date,
            'status' => $availability->status,
            'created_at' => $availability->created_at,
            'updated_at' => $availability->updated_at,
        ];
    }

    private function canManageAvailability(Availability $availability): bool
    {
        $user = auth('api')->user();

        if (! $user) {
            return false;
        }

        $availability->loadMissing('freelancerProfile');

        return $user->hasRole('admin')
            || ($availability->freelancerProfile && $availability->freelancerProfile->user_id === $user->id);
    }

    /**
     * @OA\Get(
     *     path="/api/availabilities",
     *     operationId="listAvailabilities",
     *     tags={"Availabilities"},
     *     summary="Listar disponibilidades",
     *     description="Retorna todas las disponibilidades con perfil freelancer, usuario y rol.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Disponibilidades obtenidas exitosamente"),
     *     @OA\Response(response=401, description="No autorizado. Token requerido o inválido")
     * )
     */
    public function index()
    {
        $availabilities = Availability::with('freelancerProfile.user.roles')
            ->latest('created_at')
            ->get()
            ->map(fn ($availability) => $this->formatAvailabilityResponse($availability))
            ->values();

        return response()->json([
            'success' => true,
            'message' => 'Disponibilidades obtenidas exitosamente',
            'data' => [
                'availabilities' => $availabilities,
            ],
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/availabilities",
     *     operationId="createAvailability",
     *     tags={"Availabilities"},
     *     summary="Crear disponibilidad",
     *     description="Crea un rango de disponibilidad para un perfil freelancer. Un freelancer crea disponibilidad para su propio perfil; un admin puede indicar freelancer_id.",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"start_date","end_date"},
     *             @OA\Property(property="freelancer_id", type="integer", example=1, description="Solo requerido si el usuario autenticado es admin"),
     *             @OA\Property(property="start_date", type="string", format="date", example="2026-06-15"),
     *             @OA\Property(property="end_date", type="string", format="date", example="2026-06-30"),
     *             @OA\Property(property="status", type="string", enum={"available","busy","vacation"}, example="available")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Disponibilidad creada exitosamente"),
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
                'message' => 'Solo freelancers o administradores pueden crear disponibilidades.',
            ], 403);
        }

        $validated = $request->validate([
            'freelancer_id' => 'nullable|integer|exists:freelancer_profiles,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'status' => 'nullable|string|in:available,busy,vacation',
        ]);

        if ($authUser->hasRole('admin')) {
            if (empty($validated['freelancer_id'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'El administrador debe indicar freelancer_id.',
                ], 422);
            }

            $profile = FreelancerProfile::with('user.roles')->find($validated['freelancer_id']);
        } else {
            $profile = FreelancerProfile::with('user.roles')
                ->where('user_id', $authUser->id)
                ->first();
        }

        if (! $profile) {
            return response()->json([
                'success' => false,
                'message' => 'No se encontró un perfil freelancer válido.',
            ], 422);
        }

        if (! $profile->user || ! $profile->user->hasRole('freelancer')) {
            return response()->json([
                'success' => false,
                'message' => 'El perfil seleccionado debe pertenecer a un usuario con rol freelancer.',
            ], 422);
        }

        $availability = Availability::create([
            'freelancer_id' => $profile->id,
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'status' => $validated['status'] ?? 'available',
        ]);

        ActivityLoggerService::logCreate(
            module: 'AVAILABILITIES',
            entity: 'availabilities',
            entityId: $availability->id,
            description: "Availability created for freelancer profile ID {$profile->id}"
        );

        $availability->load('freelancerProfile.user.roles');

        return response()->json([
            'success' => true,
            'message' => 'Disponibilidad creada exitosamente',
            'data' => [
                'availability' => $this->formatAvailabilityResponse($availability),
            ],
        ], 201);
    }

    /**
     * @OA\Get(
     *     path="/api/availabilities/{id}",
     *     operationId="showAvailability",
     *     tags={"Availabilities"},
     *     summary="Obtener disponibilidad por ID",
     *     description="Retorna los detalles de una disponibilidad.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID de la disponibilidad",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(response=200, description="Disponibilidad obtenida exitosamente"),
     *     @OA\Response(response=401, description="No autorizado. Token requerido o inválido"),
     *     @OA\Response(response=404, description="Disponibilidad no encontrada")
     * )
     */
    public function show(int $id)
    {
        $availability = Availability::with('freelancerProfile.user.roles')->find($id);

        if (! $availability) {
            return response()->json([
                'success' => false,
                'message' => 'Disponibilidad no encontrada',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Disponibilidad obtenida exitosamente',
            'data' => [
                'availability' => $this->formatAvailabilityResponse($availability),
            ],
        ]);
    }

    /**
     * @OA\Put(
     *     path="/api/availabilities/{id}",
     *     operationId="updateAvailability",
     *     tags={"Availabilities"},
     *     summary="Actualizar disponibilidad",
     *     description="Actualiza las fechas o el estado de una disponibilidad existente.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID de la disponibilidad",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="start_date", type="string", format="date", example="2026-06-15"),
     *             @OA\Property(property="end_date", type="string", format="date", example="2026-07-10"),
     *             @OA\Property(property="status", type="string", enum={"available","busy","vacation"}, example="busy")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Disponibilidad actualizada correctamente"),
     *     @OA\Response(response=401, description="No autorizado. Token requerido o inválido"),
     *     @OA\Response(response=403, description="Sin permisos"),
     *     @OA\Response(response=404, description="Disponibilidad no encontrada"),
     *     @OA\Response(response=422, description="Error de validación")
     * )
     */
    public function update(Request $request, int $id)
    {
        $availability = Availability::with('freelancerProfile')->find($id);

        if (! $availability) {
            return response()->json([
                'success' => false,
                'message' => 'Disponibilidad no encontrada',
            ], 404);
        }

        if (! $this->canManageAvailability($availability)) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permisos para actualizar esta disponibilidad.',
            ], 403);
        }

        $validated = $request->validate([
            'start_date' => 'sometimes|required|date',
            'end_date' => 'sometimes|required|date',
            'status' => 'sometimes|required|string|in:available,busy,vacation',
        ]);

        $startDate = $validated['start_date'] ?? $availability->start_date;
        $endDate = $validated['end_date'] ?? $availability->end_date;

        if ($endDate < $startDate) {
            return response()->json([
                'success' => false,
                'message' => 'La fecha final debe ser igual o posterior a la fecha inicial.',
            ], 422);
        }

        $availability->update($validated);

        ActivityLoggerService::logUpdate(
            module: 'AVAILABILITIES',
            entity: 'availabilities',
            entityId: $availability->id,
            description: "Availability ID {$availability->id} updated"
        );

        $availability->refresh();
        $availability->load('freelancerProfile.user.roles');

        return response()->json([
            'success' => true,
            'message' => 'Disponibilidad actualizada correctamente',
            'data' => [
                'availability' => $this->formatAvailabilityResponse($availability),
            ],
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/availabilities/{id}",
     *     operationId="deleteAvailability",
     *     tags={"Availabilities"},
     *     summary="Eliminar disponibilidad",
     *     description="Elimina una disponibilidad existente.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID de la disponibilidad",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(response=200, description="Disponibilidad eliminada correctamente"),
     *     @OA\Response(response=401, description="No autorizado. Token requerido o inválido"),
     *     @OA\Response(response=403, description="Sin permisos"),
     *     @OA\Response(response=404, description="Disponibilidad no encontrada")
     * )
     */
    public function destroy(int $id)
    {
        $availability = Availability::with('freelancerProfile')->find($id);

        if (! $availability) {
            return response()->json([
                'success' => false,
                'message' => 'Disponibilidad no encontrada',
            ], 404);
        }

        if (! $this->canManageAvailability($availability)) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permisos para eliminar esta disponibilidad.',
            ], 403);
        }

        ActivityLoggerService::logDelete(
            module: 'AVAILABILITIES',
            entity: 'availabilities',
            entityId: $availability->id,
            description: "Availability ID {$availability->id} deleted"
        );

        $availability->delete();

        return response()->json([
            'success' => true,
            'message' => 'Disponibilidad eliminada correctamente',
        ]);
    }
}