<?php

namespace App\Http\Controllers;

use App\Models\FreelancerProfile;
use App\Models\Service;
use App\Models\User;
use App\Services\ActivityLoggerService;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="Services",
 *     description="Endpoints para la gestión de servicios de freelancers"
 * )
 */
class ServiceController extends Controller
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

    private function formatServiceResponse(Service $service): array
    {
        $service->loadMissing('freelancerProfile.user.roles');

        return [
            'id' => $service->id,
            'freelancer_id' => $service->freelancer_id,
            'freelancer_profile' => $this->formatFreelancerProfileResponse($service->freelancerProfile),
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

    private function canManageService(Service $service): bool
    {
        $user = auth('api')->user();

        if (! $user) {
            return false;
        }

        $service->loadMissing('freelancerProfile');

        return $user->hasRole('admin')
            || ($service->freelancerProfile && $service->freelancerProfile->user_id === $user->id);
    }

    /**
     * @OA\Get(
     *     path="/api/services",
     *     operationId="listServices",
     *     tags={"Services"},
     *     summary="Listar servicios",
     *     description="Retorna todos los servicios con su perfil de freelancer, usuario y rol.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Servicios obtenidos exitosamente"),
     *     @OA\Response(response=401, description="No autorizado. Token requerido o inválido")
     * )
     */
    public function index()
    {
        $services = Service::with('freelancerProfile.user.roles')
            ->latest('created_at')
            ->get()
            ->map(fn ($service) => $this->formatServiceResponse($service))
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
     *     description="Crea un servicio para un perfil freelancer. Un freelancer crea servicios para su propio perfil; un admin puede indicar freelancer_id.",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"title","description","category"},
     *             @OA\Property(property="freelancer_id", type="integer", example=1, description="Solo requerido si el usuario autenticado es admin"),
     *             @OA\Property(property="title", type="string", example="Desarrollo Web en Laravel"),
     *             @OA\Property(property="description", type="string", example="Creación de API RESTful con Laravel"),
     *             @OA\Property(property="price", type="number", format="float", example=50.00),
     *             @OA\Property(property="category", type="string", example="Programación"),
     *             @OA\Property(property="location", type="string", example="Remoto"),
     *             @OA\Property(property="is_active", type="boolean", example=true)
     *         )
     *     ),
     *     @OA\Response(response=201, description="Servicio creado exitosamente"),
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
                'message' => 'Solo freelancers o administradores pueden crear servicios.',
            ], 403);
        }

        $validated = $request->validate([
            'freelancer_id' => 'nullable|integer|exists:freelancer_profiles,id',
            'title' => 'required|string|max:150',
            'description' => 'required|string',
            'price' => 'nullable|numeric|min:0',
            'category' => 'required|string|max:100',
            'location' => 'nullable|string|max:150',
            'is_active' => 'nullable|boolean',
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

        $service->load('freelancerProfile.user.roles');

        return response()->json([
            'success' => true,
            'message' => 'Servicio creado exitosamente',
            'data' => [
                'service' => $this->formatServiceResponse($service),
            ],
        ], 201);
    }

    /**
     * @OA\Get(
     *     path="/api/services/{id}",
     *     operationId="showService",
     *     tags={"Services"},
     *     summary="Obtener servicio por ID",
     *     description="Retorna los detalles de un servicio.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID del servicio",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(response=200, description="Servicio obtenido exitosamente"),
     *     @OA\Response(response=401, description="No autorizado. Token requerido o inválido"),
     *     @OA\Response(response=404, description="Servicio no encontrado")
     * )
     */
    public function show(int $id)
    {
        $service = Service::with('freelancerProfile.user.roles')->find($id);

        if (! $service) {
            return response()->json([
                'success' => false,
                'message' => 'Servicio no encontrado',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Servicio obtenido exitosamente',
            'data' => [
                'service' => $this->formatServiceResponse($service),
            ],
        ]);
    }

    /**
     * @OA\Put(
     *     path="/api/services/{id}",
     *     operationId="updateService",
     *     tags={"Services"},
     *     summary="Actualizar servicio",
     *     description="Actualiza la información de un servicio existente.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID del servicio",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="title", type="string", example="Desarrollo Web Actualizado"),
     *             @OA\Property(property="description", type="string", example="Nueva descripción del servicio"),
     *             @OA\Property(property="price", type="number", format="float", example=60.00),
     *             @OA\Property(property="category", type="string", example="Programación"),
     *             @OA\Property(property="location", type="string", example="Remoto"),
     *             @OA\Property(property="is_active", type="boolean", example=true)
     *         )
     *     ),
     *     @OA\Response(response=200, description="Servicio actualizado correctamente"),
     *     @OA\Response(response=401, description="No autorizado. Token requerido o inválido"),
     *     @OA\Response(response=403, description="Sin permisos"),
     *     @OA\Response(response=404, description="Servicio no encontrado"),
     *     @OA\Response(response=422, description="Error de validación")
     * )
     */
    public function update(Request $request, int $id)
    {
        $service = Service::with('freelancerProfile')->find($id);

        if (! $service) {
            return response()->json([
                'success' => false,
                'message' => 'Servicio no encontrado',
            ], 404);
        }

        if (! $this->canManageService($service)) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permisos para actualizar este servicio.',
            ], 403);
        }

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:150',
            'description' => 'sometimes|required|string',
            'price' => 'nullable|numeric|min:0',
            'category' => 'sometimes|required|string|max:100',
            'location' => 'nullable|string|max:150',
            'is_active' => 'nullable|boolean',
        ]);

        $service->update($validated);

        ActivityLoggerService::logUpdate(
            module: 'SERVICES',
            entity: 'services',
            entityId: $service->id,
            description: "Service {$service->title} updated"
        );

        $service->refresh();
        $service->load('freelancerProfile.user.roles');

        return response()->json([
            'success' => true,
            'message' => 'Servicio actualizado correctamente',
            'data' => [
                'service' => $this->formatServiceResponse($service),
            ],
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/services/{id}",
     *     operationId="deleteService",
     *     tags={"Services"},
     *     summary="Eliminar servicio",
     *     description="Elimina un servicio existente.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID del servicio",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(response=200, description="Servicio eliminado correctamente"),
     *     @OA\Response(response=401, description="No autorizado. Token requerido o inválido"),
     *     @OA\Response(response=403, description="Sin permisos"),
     *     @OA\Response(response=404, description="Servicio no encontrado")
     * )
     */
    public function destroy(int $id)
    {
        $service = Service::with('freelancerProfile')->find($id);

        if (! $service) {
            return response()->json([
                'success' => false,
                'message' => 'Servicio no encontrado',
            ], 404);
        }

        if (! $this->canManageService($service)) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permisos para eliminar este servicio.',
            ], 403);
        }

        ActivityLoggerService::logDelete(
            module: 'SERVICES',
            entity: 'services',
            entityId: $service->id,
            description: "Service {$service->title} deleted"
        );

        $service->delete();

        return response()->json([
            'success' => true,
            'message' => 'Servicio eliminado correctamente',
        ]);
    }
}