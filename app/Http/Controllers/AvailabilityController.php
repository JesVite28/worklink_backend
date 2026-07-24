<?php

namespace App\Http\Controllers;

use App\Models\Availability;
use App\Models\FreelancerProfile;
use App\Models\User;
use App\Services\ActivityLoggerService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;



/**
 * @OA\Tag(
 *     name="Availabilities",
 *     description="Endpoints para la gestión de la disponibilidad de freelancers"
 * )
 */
class AvailabilityController extends Controller
{
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

    private function formatUserResponse(?User $user): ?array
    {
        if (! $user) {
            return null;
        }

        $user->loadMissing('roles');

        return [
            'id' => $user->id,
            'name' => $user->name,
            'last_name' => $user->last_name,
            'maternal_last_name' => $user->maternal_last_name,
            'email' => $user->email,
            'phone' => $user->phone,
            'profile_photo' => $user->profile_photo,
            'profile_photo_url' => $this->buildPublicStorageUrl($user->profile_photo),
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
            'user' => $this->formatUserResponse($profile->user),
            'description' => $profile->description,
            'specialty' => $profile->specialty,
            'location' => $profile->location,
            'service_area' => $profile->service_area,
            'work_mode' => $profile->work_mode,
            'experience' => $profile->experience,
            'rate_type' => $profile->rate_type,
            'rate' => $profile->rate,
            'languages' => $profile->languages ?? [],
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
            'freelancer_profile' => $this->formatFreelancerProfileResponse(
                $availability->freelancerProfile
            ),
            'start_date' => $availability->start_date?->format('Y-m-d'),
            'end_date' => $availability->end_date?->format('Y-m-d'),
            'status' => $availability->status,
            'created_at' => $availability->created_at,
            'updated_at' => $availability->updated_at,
        ];
    }

    private function canManageAvailability(Availability $availability, User $user): bool
    {
        $availability->loadMissing('freelancerProfile');

        return $user->hasRole('admin')
            || (
                $availability->freelancerProfile
                && $availability->freelancerProfile->user_id === $user->id
            );
    }

    private function hasOverlappingRange(
        int $freelancerId,
        string $startDate,
        string $endDate,
        ?int $ignoreAvailabilityId = null
    ): bool {
        return Availability::query()
            ->where('freelancer_id', $freelancerId)
            ->when(
                $ignoreAvailabilityId,
                fn($query) => $query->where('id', '!=', $ignoreAvailabilityId)
            )
            ->where(function ($query) use ($startDate, $endDate) {
                $query
                    ->whereDate('start_date', '<=', $endDate)
                    ->whereDate('end_date', '>=', $startDate);
            })
            ->exists();
    }

    /**
     * @OA\Get(
     *     path="/api/availabilities",
     *     operationId="listAvailabilities",
     *     tags={"Availabilities"},
     *     summary="Listar disponibilidades",
     *     description="Retorna todas las disponibilidades registradas con los datos del perfil freelancer.",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Response(response=200, description="Disponibilidades obtenidas exitosamente"),
     *     @OA\Response(response=401, description="No autorizado")
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

        $availabilities = Availability::with('freelancerProfile.user.roles')
            ->orderBy('start_date')
            ->orderBy('end_date')
            ->get()
            ->map(
                fn(Availability $availability) =>
                $this->formatAvailabilityResponse($availability)
            )
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
     * @OA\Get(
     *     path="/api/availabilities/me",
     *     operationId="listMyAvailabilities",
     *     tags={"Availabilities"},
     *     summary="Obtener mis disponibilidades",
     *     description="Obtiene todos los periodos de disponibilidad registrados para el perfil freelancer asociado al usuario autenticado.",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Response(
     *         response=200,
     *         description="Disponibilidades del freelancer obtenidas exitosamente",
     *
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="success",
     *                 type="boolean",
     *                 example=true
     *             ),
     *
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="Tus disponibilidades fueron obtenidas exitosamente."
     *             ),
     *
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *
     *                 @OA\Property(
     *                     property="freelancer_profile",
     *                     type="object",
     *                     nullable=true,
     *
     *                     @OA\Property(
     *                         property="id",
     *                         type="integer",
     *                         example=1
     *                     ),
     *
     *                     @OA\Property(
     *                         property="user_id",
     *                         type="integer",
     *                         example=5
     *                     ),
     *
     *                     @OA\Property(
     *                         property="description",
     *                         type="string",
     *                         nullable=true,
     *                         example="Desarrollador web con experiencia en Laravel y React."
     *                     ),
     *
     *                     @OA\Property(
     *                         property="specialty",
     *                         type="string",
     *                         nullable=true,
     *                         example="Desarrollo web"
     *                     ),
     *
     *                     @OA\Property(
     *                         property="location",
     *                         type="string",
     *                         nullable=true,
     *                         example="Pachuca, Hidalgo"
     *                     ),
     *
     *                     @OA\Property(
     *                         property="service_area",
     *                         type="string",
     *                         nullable=true,
     *                         example="México y trabajo remoto"
     *                     ),
     *
     *                     @OA\Property(
     *                         property="work_mode",
     *                         type="string",
     *                         nullable=true,
     *                         example="remote"
     *                     ),
     *
     *                     @OA\Property(
     *                         property="experience",
     *                         type="string",
     *                         nullable=true,
     *                         example="3 años de experiencia"
     *                     ),
     *
     *                     @OA\Property(
     *                         property="rate_type",
     *                         type="string",
     *                         nullable=true,
     *                         example="hourly"
     *                     ),
     *
     *                     @OA\Property(
     *                         property="rate",
     *                         type="number",
     *                         format="float",
     *                         nullable=true,
     *                         example=350
     *                     ),
     *
     *                     @OA\Property(
     *                         property="languages",
     *                         type="array",
     *
     *                         @OA\Items(
     *                             type="string",
     *                             example="Español"
     *                         )
     *                     ),
     *
     *                     @OA\Property(
     *                         property="available",
     *                         type="boolean",
     *                         example=true
     *                     ),
     *
     *                     @OA\Property(
     *                         property="average_rate",
     *                         type="number",
     *                         format="float",
     *                         nullable=true,
     *                         example=4.8
     *                     )
     *                 ),
     *
     *                 @OA\Property(
     *                     property="availabilities",
     *                     type="array",
     *
     *                     @OA\Items(
     *                         type="object",
     *
     *                         @OA\Property(
     *                             property="id",
     *                             type="integer",
     *                             example=1
     *                         ),
     *
     *                         @OA\Property(
     *                             property="freelancer_id",
     *                             type="integer",
     *                             example=1
     *                         ),
     *
     *                         @OA\Property(
     *                             property="start_date",
     *                             type="string",
     *                             format="date",
     *                             example="2026-08-01"
     *                         ),
     *
     *                         @OA\Property(
     *                             property="end_date",
     *                             type="string",
     *                             format="date",
     *                             example="2026-08-15"
     *                         ),
     *
     *                         @OA\Property(
     *                             property="status",
     *                             type="string",
     *                             enum={"available","busy","vacation"},
     *                             example="available"
     *                         ),
     *
     *                         @OA\Property(
     *                             property="created_at",
     *                             type="string",
     *                             format="date-time",
     *                             example="2026-07-24T20:30:00.000000Z"
     *                         ),
     *
     *                         @OA\Property(
     *                             property="updated_at",
     *                             type="string",
     *                             format="date-time",
     *                             example="2026-07-24T20:30:00.000000Z"
     *                         )
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="Usuario no autenticado"
     *     ),
     *
     *     @OA\Response(
     *         response=403,
     *         description="El usuario autenticado no tiene el rol freelancer"
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Perfil freelancer no encontrado"
     *     )
     * )
     */
    public function myAvailabilities()
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
                'message' => 'Solo los usuarios freelancer tienen disponibilidad.',
            ], 403);
        }

        $profile = FreelancerProfile::with('user.roles')
            ->where('user_id', $authUser->id)
            ->first();

        if (! $profile) {
            return response()->json([
                'success' => false,
                'message' => 'No se encontró un perfil freelancer para tu cuenta.',
            ], 404);
        }

        $availabilities = Availability::query()
            ->with('freelancerProfile.user.roles')
            ->where('freelancer_id', $profile->id)
            ->orderBy('start_date')
            ->orderBy('end_date')
            ->get()
            ->map(
                fn(Availability $availability) =>
                $this->formatAvailabilityResponse($availability)
            )
            ->values();

        return response()->json([
            'success' => true,
            'message' => 'Tus disponibilidades fueron obtenidas exitosamente.',
            'data' => [
                'freelancer_profile' =>
                $this->formatFreelancerProfileResponse($profile),

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
     *     description="El freelancer crea disponibilidad para su propio perfil. Un administrador puede indicar freelancer_id. No se permiten rangos superpuestos.",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"start_date","end_date"},
     *             @OA\Property(
     *                 property="freelancer_id",
     *                 type="integer",
     *                 nullable=true,
     *                 example=1,
     *                 description="Solo debe enviarlo un administrador"
     *             ),
     *             @OA\Property(property="start_date", type="string", format="date", example="2026-08-01"),
     *             @OA\Property(property="end_date", type="string", format="date", example="2026-08-15"),
     *             @OA\Property(
     *                 property="status",
     *                 type="string",
     *                 enum={"available","busy","vacation"},
     *                 example="available"
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(response=201, description="Disponibilidad creada exitosamente"),
     *     @OA\Response(response=401, description="No autorizado"),
     *     @OA\Response(response=403, description="Sin permisos"),
     *     @OA\Response(response=422, description="Datos inválidos o rango superpuesto")
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
            'freelancer_id' => [
                'nullable',
                'integer',
                Rule::exists('freelancer_profiles', 'id')->whereNull('deleted_at'),
            ],
            'start_date' => [
                'required',
                'date',
                'after_or_equal:today',
            ],
            'end_date' => [
                'required',
                'date',
                'after_or_equal:start_date',
            ],
            'status' => [
                'nullable',
                'string',
                Rule::in(['available', 'busy', 'vacation']),
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
                'message' => 'No se encontró un perfil freelancer válido.',
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

        $startDate = Carbon::parse($validated['start_date'])->toDateString();
        $endDate = Carbon::parse($validated['end_date'])->toDateString();

        $availability = DB::transaction(function () use (
            $profile,
            $startDate,
            $endDate,
            $validated
        ) {
            FreelancerProfile::query()
                ->whereKey($profile->id)
                ->lockForUpdate()
                ->first();

            if ($this->hasOverlappingRange($profile->id, $startDate, $endDate)) {
                throw ValidationException::withMessages([
                    'start_date' => [
                        'El rango indicado se superpone con otra disponibilidad del freelancer.',
                    ],
                ]);
            }

            $availability = Availability::create([
                'freelancer_id' => $profile->id,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'status' => $validated['status'] ?? 'available',
            ]);

            ActivityLoggerService::logCreate(
                module: 'AVAILABILITIES',
                entity: 'availabilities',
                entityId: $availability->id,
                description: "Availability created for freelancer profile ID {$profile->id}"
            );

            return $availability;
        });

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
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID de la disponibilidad",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *
     *     @OA\Response(response=200, description="Disponibilidad obtenida exitosamente"),
     *     @OA\Response(response=401, description="No autorizado"),
     *     @OA\Response(response=404, description="Disponibilidad no encontrada")
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

        $availability = Availability::with('freelancerProfile.user.roles')->find($id);

        if (! $availability) {
            return response()->json([
                'success' => false,
                'message' => 'Disponibilidad no encontrada.',
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
     * @OA\Patch(
     *     path="/api/availabilities/{id}",
     *     operationId="updateAvailability",
     *     tags={"Availabilities"},
     *     summary="Actualizar disponibilidad",
     *     description="El freelancer propietario o un administrador pueden actualizar fechas o estado. No se permiten rangos superpuestos.",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID de la disponibilidad",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="start_date", type="string", format="date", example="2026-08-01"),
     *             @OA\Property(property="end_date", type="string", format="date", example="2026-08-20"),
     *             @OA\Property(
     *                 property="status",
     *                 type="string",
     *                 enum={"available","busy","vacation"},
     *                 example="busy"
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(response=200, description="Disponibilidad actualizada correctamente"),
     *     @OA\Response(response=401, description="No autorizado"),
     *     @OA\Response(response=403, description="Sin permisos"),
     *     @OA\Response(response=404, description="Disponibilidad no encontrada"),
     *     @OA\Response(response=422, description="Datos inválidos o rango superpuesto")
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

        $availability = Availability::with('freelancerProfile.user.roles')->find($id);

        if (! $availability) {
            return response()->json([
                'success' => false,
                'message' => 'Disponibilidad no encontrada.',
            ], 404);
        }

        if (! $this->canManageAvailability($availability, $authUser)) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permisos para actualizar esta disponibilidad.',
            ], 403);
        }

        if (
            ! $availability->freelancerProfile
            || ! $availability->freelancerProfile->user
            || ! $availability->freelancerProfile->user->is_active
        ) {
            return response()->json([
                'success' => false,
                'message' => 'El perfil freelancer asociado no está activo.',
            ], 422);
        }

        $validated = $request->validate([
            'start_date' => [
                'sometimes',
                'required',
                'date',
                'after_or_equal:today',
            ],
            'end_date' => [
                'sometimes',
                'required',
                'date',
            ],
            'status' => [
                'sometimes',
                'required',
                'string',
                Rule::in(['available', 'busy', 'vacation']),
            ],
        ]);

        if (empty($validated)) {
            return response()->json([
                'success' => false,
                'message' => 'Debes enviar al menos un campo para actualizar.',
            ], 422);
        }

        $startDate = Carbon::parse(
            $validated['start_date'] ?? $availability->start_date
        )->toDateString();

        $endDate = Carbon::parse(
            $validated['end_date'] ?? $availability->end_date
        )->toDateString();

        if ($endDate < $startDate) {
            return response()->json([
                'success' => false,
                'message' => 'La fecha final debe ser igual o posterior a la fecha inicial.',
                'errors' => [
                    'end_date' => [
                        'La fecha final debe ser igual o posterior a la fecha inicial.',
                    ],
                ],
            ], 422);
        }

        DB::transaction(function () use (
            $availability,
            $validated,
            $startDate,
            $endDate
        ) {
            FreelancerProfile::query()
                ->whereKey($availability->freelancer_id)
                ->lockForUpdate()
                ->first();

            $datesChanged = array_key_exists('start_date', $validated)
                || array_key_exists('end_date', $validated);

            if (
                $datesChanged
                && $this->hasOverlappingRange(
                    $availability->freelancer_id,
                    $startDate,
                    $endDate,
                    $availability->id
                )
            ) {
                throw ValidationException::withMessages([
                    'start_date' => [
                        'El rango indicado se superpone con otra disponibilidad del freelancer.',
                    ],
                ]);
            }

            $updateData = $validated;

            if (array_key_exists('start_date', $validated)) {
                $updateData['start_date'] = $startDate;
            }

            if (array_key_exists('end_date', $validated)) {
                $updateData['end_date'] = $endDate;
            }

            $availability->update($updateData);

            ActivityLoggerService::logUpdate(
                module: 'AVAILABILITIES',
                entity: 'availabilities',
                entityId: $availability->id,
                description: "Availability ID {$availability->id} updated"
            );
        });

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
     *     description="El freelancer propietario o un administrador pueden eliminar una disponibilidad.",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID de la disponibilidad",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *
     *     @OA\Response(response=200, description="Disponibilidad eliminada correctamente"),
     *     @OA\Response(response=401, description="No autorizado"),
     *     @OA\Response(response=403, description="Sin permisos"),
     *     @OA\Response(response=404, description="Disponibilidad no encontrada")
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

        $availability = Availability::with('freelancerProfile')->find($id);

        if (! $availability) {
            return response()->json([
                'success' => false,
                'message' => 'Disponibilidad no encontrada.',
            ], 404);
        }

        if (! $this->canManageAvailability($availability, $authUser)) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permisos para eliminar esta disponibilidad.',
            ], 403);
        }

        DB::transaction(function () use ($availability) {
            ActivityLoggerService::logDelete(
                module: 'AVAILABILITIES',
                entity: 'availabilities',
                entityId: $availability->id,
                description: "Availability ID {$availability->id} deleted"
            );

            $availability->delete();
        });

        return response()->json([
            'success' => true,
            'message' => 'Disponibilidad eliminada correctamente',
        ]);
    }
}
