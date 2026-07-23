<?php

namespace App\Http\Controllers;

use App\Models\ContractRequest;
use App\Models\FreelancerProfile;
use App\Models\Service;
use App\Models\User;
use App\Services\ActivityLoggerService;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="Contract Requests",
 *     description="Endpoints para la gestión de solicitudes de contratación"
 * )
 */
class ContractRequestController extends Controller
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
            'user' => $this->formatUserResponse($profile->user),
            'description' => $profile->description,
            'specialty' => $profile->specialty,
            'hourly_rate' => $profile->hourly_rate,
            'location' => $profile->location,
            'available' => $profile->available,
            'average_rate' => $profile->average_rate,
        ];
    }

    private function formatServiceResponse(?Service $service): ?array
    {
        if (! $service) {
            return null;
        }

        return [
            'id' => $service->id,
            'freelancer_id' => $service->freelancer_id,
            'title' => $service->title,
            'description' => $service->description,
            'price' => $service->price,
            'category' => $service->category,
            'location' => $service->location,
            'is_active' => $service->is_active,
        ];
    }

    private function formatContractRequestResponse(ContractRequest $contractRequest): array
    {
        $contractRequest->loadMissing([
            'client.roles',
            'freelancer.user.roles',
            'service',
        ]);

        return [
            'id' => $contractRequest->id,
            'client_id' => $contractRequest->client_id,
            'client' => $this->formatUserResponse($contractRequest->client),
            'freelancer_id' => $contractRequest->freelancer_id,
            'freelancer_profile' => $this->formatFreelancerProfileResponse($contractRequest->freelancer),
            'service_id' => $contractRequest->service_id,
            'service' => $this->formatServiceResponse($contractRequest->service),
            'description' => $contractRequest->description,
            'budget' => $contractRequest->budget,
            'status' => $contractRequest->status,
            'created_at' => $contractRequest->created_at,
            'updated_at' => $contractRequest->updated_at,
        ];
    }

    private function canViewContractRequest(ContractRequest $contractRequest): bool
    {
        $user = auth('api')->user();

        if (! $user) {
            return false;
        }

        $contractRequest->loadMissing('freelancer');

        return $user->hasRole('admin')
            || $contractRequest->client_id === $user->id
            || ($contractRequest->freelancer && $contractRequest->freelancer->user_id === $user->id);
    }

    private function canUpdateContractRequest(ContractRequest $contractRequest): bool
    {
        return $this->canViewContractRequest($contractRequest);
    }

    private function canDeleteContractRequest(ContractRequest $contractRequest): bool
    {
        $user = auth('api')->user();

        if (! $user) {
            return false;
        }

        return $user->hasRole('admin') || $contractRequest->client_id === $user->id;
    }

    private function canViewServiceContractRequests(Service $service): bool
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

    /**
     * @OA\Get(
     *     path="/api/contract-requests",
     *     operationId="listContractRequests",
     *     tags={"Contract Requests"},
     *     summary="Listar solicitudes de contratación",
     *     description="Retorna las solicitudes de contratación según el usuario autenticado y su rol.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Solicitudes obtenidas exitosamente"),
     *     @OA\Response(response=401, description="No autorizado. Token requerido o inválido")
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

        $query = ContractRequest::with([
            'client.roles',
            'freelancer.user.roles',
            'service',
        ]);

        if (! $authUser->hasRole('admin')) {
            if ($authUser->hasRole('freelancer')) {
                $query->whereHas('freelancer', function ($q) use ($authUser) {
                    $q->where('user_id', $authUser->id);
                });
            } else {
                $query->where('client_id', $authUser->id);
            }
        }

        $requests = $query->latest('created_at')
            ->get()
            ->map(fn ($contractRequest) => $this->formatContractRequestResponse($contractRequest))
            ->values();

        return response()->json([
            'success' => true,
            'message' => 'Solicitudes de contratación obtenidas exitosamente',
            'data' => [
                'contract_requests' => $requests,
            ],
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/contract-requests",
     *     operationId="createContractRequest",
     *     tags={"Contract Requests"},
     *     summary="Crear solicitud de contratación",
     *     description="Crea una solicitud para contratar un servicio. Clientes y empresas crean solicitudes; admin puede indicar client_id.",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"service_id","description"},
     *             @OA\Property(property="client_id", type="integer", example=1, description="Solo requerido si el usuario autenticado es admin"),
     *             @OA\Property(property="service_id", type="integer", example=1),
     *             @OA\Property(property="description", type="string", example="Necesito una API REST para mi plataforma."),
     *             @OA\Property(property="budget", type="number", format="float", example=500.00)
     *         )
     *     ),
     *     @OA\Response(response=201, description="Solicitud creada exitosamente"),
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

        if (! $authUser->hasAnyRole(['cliente', 'empresa', 'admin'])) {
            return response()->json([
                'success' => false,
                'message' => 'Solo clientes, empresas o administradores pueden crear solicitudes de contratación.',
            ], 403);
        }

        $validated = $request->validate([
            'client_id' => 'nullable|integer|exists:users,id',
            'service_id' => 'required|integer|exists:services,id',
            'description' => 'required|string',
            'budget' => 'nullable|numeric|min:0',
        ]);

        $clientId = $authUser->hasRole('admin')
            ? ($validated['client_id'] ?? $authUser->id)
            : $authUser->id;

        $client = User::with('roles')->find($clientId);

        if (! $client || ! $client->hasAnyRole(['cliente', 'empresa', 'admin'])) {
            return response()->json([
                'success' => false,
                'message' => 'El usuario cliente debe tener rol cliente o empresa.',
            ], 422);
        }

        $service = Service::with('freelancerProfile.user.roles')->find($validated['service_id']);

        if (! $service || ! $service->freelancerProfile) {
            return response()->json([
                'success' => false,
                'message' => 'El servicio seleccionado no tiene un perfil freelancer válido.',
            ], 422);
        }

        if (! $service->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'El servicio seleccionado no está activo.',
            ], 422);
        }

        if ($service->freelancerProfile->user_id === $clientId) {
            return response()->json([
                'success' => false,
                'message' => 'No puedes solicitar contratación sobre tu propio servicio.',
            ], 422);
        }

        $contractRequest = ContractRequest::create([
            'client_id' => $clientId,
            'freelancer_id' => $service->freelancer_id,
            'service_id' => $service->id,
            'description' => $validated['description'],
            'budget' => $validated['budget'] ?? null,
            'status' => 'pending',
        ]);

        ActivityLoggerService::logCreate(
            module: 'CONTRACT_REQUESTS',
            entity: 'contract_requests',
            entityId: $contractRequest->id,
            description: "Contract request created for service ID {$service->id}"
        );

        $contractRequest->load([
            'client.roles',
            'freelancer.user.roles',
            'service',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Solicitud de contratación creada exitosamente',
            'data' => [
                'contract_request' => $this->formatContractRequestResponse($contractRequest),
            ],
        ], 201);
    }

    /**
     * @OA\Get(
     *     path="/api/contract-requests/{id}",
     *     operationId="showContractRequest",
     *     tags={"Contract Requests"},
     *     summary="Obtener solicitud por ID",
     *     description="Retorna los detalles de una solicitud de contratación.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID de la solicitud",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(response=200, description="Solicitud obtenida exitosamente"),
     *     @OA\Response(response=401, description="No autorizado. Token requerido o inválido"),
     *     @OA\Response(response=403, description="Sin permisos"),
     *     @OA\Response(response=404, description="Solicitud no encontrada")
     * )
     */
    public function show(int $id)
    {
        $contractRequest = ContractRequest::with([
            'client.roles',
            'freelancer.user.roles',
            'service',
        ])->find($id);

        if (! $contractRequest) {
            return response()->json([
                'success' => false,
                'message' => 'Solicitud no encontrada',
            ], 404);
        }

        if (! $this->canViewContractRequest($contractRequest)) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permisos para ver esta solicitud.',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'message' => 'Solicitud obtenida exitosamente',
            'data' => [
                'contract_request' => $this->formatContractRequestResponse($contractRequest),
            ],
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/service/contractRequest/{id}",
     *     operationId="listContractRequestsByService",
     *     tags={"Contract Requests"},
     *     summary="Listar solicitudes por servicio",
     *     description="Retorna las solicitudes ligadas a un servicio específico.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID del servicio",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(response=200, description="Solicitudes obtenidas exitosamente"),
     *     @OA\Response(response=401, description="No autorizado. Token requerido o inválido"),
     *     @OA\Response(response=403, description="Sin permisos"),
     *     @OA\Response(response=404, description="Servicio no encontrado")
     * )
     */
    public function byService(int $id)
    {
        $service = Service::with('freelancerProfile')->find($id);

        if (! $service) {
            return response()->json([
                'success' => false,
                'message' => 'Servicio no encontrado.',
            ], 404);
        }

        if (! $this->canViewServiceContractRequests($service)) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permisos para ver las solicitudes de este servicio.',
            ], 403);
        }

        $requests = $service->contractRequests()
            ->with([
                'client.roles',
                'freelancer.user.roles',
                'service',
            ])
            ->latest('created_at')
            ->get()
            ->map(fn (ContractRequest $contractRequest) => $this->formatContractRequestResponse($contractRequest))
            ->values();

        return response()->json([
            'success' => true,
            'message' => 'Solicitudes del servicio obtenidas exitosamente',
            'data' => [
                'service_id' => $service->id,
                'contract_requests' => $requests,
            ],
        ]);
    }

    /**
     * @OA\Put(
     *     path="/api/contract-requests/{id}",
     *     operationId="updateContractRequest",
     *     tags={"Contract Requests"},
     *     summary="Actualizar solicitud de contratación",
     *     description="Actualiza la descripción, presupuesto o estado de una solicitud.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID de la solicitud",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="description", type="string", example="Descripción actualizada."),
     *             @OA\Property(property="budget", type="number", format="float", example=700.00),
     *             @OA\Property(property="status", type="string", enum={"pending","accepted","rejected","canceled"}, example="accepted")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Solicitud actualizada correctamente"),
     *     @OA\Response(response=401, description="No autorizado. Token requerido o inválido"),
     *     @OA\Response(response=403, description="Sin permisos"),
     *     @OA\Response(response=404, description="Solicitud no encontrada"),
     *     @OA\Response(response=422, description="Error de validación")
     * )
     */
    public function update(Request $request, int $id)
    {
        $contractRequest = ContractRequest::with([
            'client.roles',
            'freelancer.user.roles',
            'service',
        ])->find($id);

        if (! $contractRequest) {
            return response()->json([
                'success' => false,
                'message' => 'Solicitud no encontrada',
            ], 404);
        }

        if (! $this->canUpdateContractRequest($contractRequest)) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permisos para actualizar esta solicitud.',
            ], 403);
        }

        $validated = $request->validate([
            'description' => 'sometimes|required|string',
            'budget' => 'sometimes|nullable|numeric|min:0',
            'status' => 'sometimes|required|string|in:pending,accepted,rejected,canceled',
        ]);

        $authUser = auth('api')->user();

        if (isset($validated['status']) && ! $authUser->hasRole('admin')) {
            $isFreelancerOwner = $contractRequest->freelancer
                && $contractRequest->freelancer->user_id === $authUser->id;

            $isClientOwner = $contractRequest->client_id === $authUser->id;

            if ($isFreelancerOwner && ! in_array($validated['status'], ['accepted', 'rejected'], true)) {
                return response()->json([
                    'success' => false,
                    'message' => 'El freelancer solo puede aceptar o rechazar la solicitud.',
                ], 403);
            }

            if ($isClientOwner && $validated['status'] !== 'canceled') {
                return response()->json([
                    'success' => false,
                    'message' => 'El cliente solo puede cancelar la solicitud.',
                ], 403);
            }
        }

        $contractRequest->update($validated);

        ActivityLoggerService::logUpdate(
            module: 'CONTRACT_REQUESTS',
            entity: 'contract_requests',
            entityId: $contractRequest->id,
            description: "Contract request ID {$contractRequest->id} updated"
        );

        $contractRequest->refresh();
        $contractRequest->load([
            'client.roles',
            'freelancer.user.roles',
            'service',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Solicitud actualizada correctamente',
            'data' => [
                'contract_request' => $this->formatContractRequestResponse($contractRequest),
            ],
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/contract-requests/{id}",
     *     operationId="deleteContractRequest",
     *     tags={"Contract Requests"},
     *     summary="Eliminar solicitud de contratación",
     *     description="Elimina una solicitud de contratación.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID de la solicitud",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(response=200, description="Solicitud eliminada correctamente"),
     *     @OA\Response(response=401, description="No autorizado. Token requerido o inválido"),
     *     @OA\Response(response=403, description="Sin permisos"),
     *     @OA\Response(response=404, description="Solicitud no encontrada")
     * )
     */
    public function destroy(int $id)
    {
        $contractRequest = ContractRequest::with('freelancer')->find($id);

        if (! $contractRequest) {
            return response()->json([
                'success' => false,
                'message' => 'Solicitud no encontrada',
            ], 404);
        }

        if (! $this->canDeleteContractRequest($contractRequest)) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permisos para eliminar esta solicitud.',
            ], 403);
        }

        ActivityLoggerService::logDelete(
            module: 'CONTRACT_REQUESTS',
            entity: 'contract_requests',
            entityId: $contractRequest->id,
            description: "Contract request ID {$contractRequest->id} deleted"
        );

        $contractRequest->delete();

        return response()->json([
            'success' => true,
            'message' => 'Solicitud eliminada correctamente',
        ]);
    }
}