<?php

namespace App\Http\Controllers;

use App\Models\Contract;
use App\Models\ContractRequest;
use App\Models\FreelancerProfile;
use App\Models\Service;
use App\Models\User;
use App\Services\ActivityLoggerService;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="Contracts",
 *     description="Endpoints para la gestión de contrataciones formalizadas"
 * )
 */
class ContractController extends Controller
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

    private function formatContractRequestResponse(?ContractRequest $contractRequest): ?array
    {
        if (! $contractRequest) {
            return null;
        }

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

    private function formatContractResponse(Contract $contract): array
    {
        $contract->loadMissing([
            'contractRequest.client.roles',
            'contractRequest.freelancer.user.roles',
            'contractRequest.service',
        ]);

        return [
            'id' => $contract->id,
            'request_id' => $contract->request_id,
            'contract_request' => $this->formatContractRequestResponse($contract->contractRequest),
            'start_date' => $contract->start_date,
            'end_date' => $contract->end_date,
            'total_amount' => $contract->total_amount,
            'status' => $contract->status,
            'created_at' => $contract->created_at,
            'updated_at' => $contract->updated_at,
        ];
    }

    private function canViewContract(Contract $contract): bool
    {
        $user = auth('api')->user();

        if (! $user) {
            return false;
        }

        $contract->loadMissing('contractRequest.freelancer');

        $contractRequest = $contract->contractRequest;

        if (! $contractRequest) {
            return false;
        }

        return $user->hasRole('admin')
            || $contractRequest->client_id === $user->id
            || ($contractRequest->freelancer && $contractRequest->freelancer->user_id === $user->id);
    }

    private function canCreateContract(ContractRequest $contractRequest): bool
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

    private function canUpdateContract(Contract $contract): bool
    {
        return $this->canViewContract($contract);
    }

    private function canDeleteContract(Contract $contract): bool
    {
        $user = auth('api')->user();

        return $user && $user->hasRole('admin');
    }

    /**
     * @OA\Get(
     *     path="/api/contracts",
     *     operationId="listContracts",
     *     tags={"Contracts"},
     *     summary="Listar contratos",
     *     description="Retorna los contratos según el usuario autenticado y su rol.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Contratos obtenidos exitosamente"),
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

        $query = Contract::with([
            'contractRequest.client.roles',
            'contractRequest.freelancer.user.roles',
            'contractRequest.service',
        ]);

        if (! $authUser->hasRole('admin')) {
            if ($authUser->hasRole('freelancer')) {
                $query->whereHas('contractRequest.freelancer', function ($q) use ($authUser) {
                    $q->where('user_id', $authUser->id);
                });
            } else {
                $query->whereHas('contractRequest', function ($q) use ($authUser) {
                    $q->where('client_id', $authUser->id);
                });
            }
        }

        $contracts = $query->latest('created_at')
            ->get()
            ->map(fn ($contract) => $this->formatContractResponse($contract))
            ->values();

        return response()->json([
            'success' => true,
            'message' => 'Contratos obtenidos exitosamente',
            'data' => [
                'contracts' => $contracts,
            ],
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/contracts",
     *     operationId="createContract",
     *     tags={"Contracts"},
     *     summary="Crear contrato",
     *     description="Formaliza una solicitud de contratación aceptada en un contrato.",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"request_id","start_date","total_amount"},
     *             @OA\Property(property="request_id", type="integer", example=1),
     *             @OA\Property(property="start_date", type="string", format="date", example="2026-06-18"),
     *             @OA\Property(property="end_date", type="string", format="date", example="2026-07-18"),
     *             @OA\Property(property="total_amount", type="number", format="float", example=1200.50),
     *             @OA\Property(property="status", type="string", enum={"in_process","completed","canceled"}, example="in_process")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Contrato creado exitosamente"),
     *     @OA\Response(response=401, description="No autorizado. Token requerido o inválido"),
     *     @OA\Response(response=403, description="Sin permisos"),
     *     @OA\Response(response=422, description="Error de validación")
     * )
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'request_id' => 'required|integer|exists:contract_requests,id',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'total_amount' => 'required|numeric|min:0',
            'status' => 'nullable|string|in:in_process,completed,canceled',
        ]);

        $contractRequest = ContractRequest::with([
            'client.roles',
            'freelancer.user.roles',
            'service',
        ])->find($validated['request_id']);

        if (! $contractRequest) {
            return response()->json([
                'success' => false,
                'message' => 'Solicitud de contratación no encontrada.',
            ], 404);
        }

        if (! $this->canCreateContract($contractRequest)) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permisos para formalizar esta contratación.',
            ], 403);
        }

        if ($contractRequest->status !== 'accepted') {
            return response()->json([
                'success' => false,
                'message' => 'Solo se puede crear un contrato desde una solicitud aceptada.',
            ], 422);
        }

        if (Contract::where('request_id', $contractRequest->id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Esta solicitud ya tiene un contrato formalizado.',
            ], 422);
        }

        $contract = Contract::create([
            'request_id' => $contractRequest->id,
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'] ?? null,
            'total_amount' => $validated['total_amount'],
            'status' => $validated['status'] ?? 'in_process',
        ]);

        ActivityLoggerService::logCreate(
            module: 'CONTRACTS',
            entity: 'contracts',
            entityId: $contract->id,
            description: "Contract created from contract request ID {$contractRequest->id}"
        );

        $contract->load([
            'contractRequest.client.roles',
            'contractRequest.freelancer.user.roles',
            'contractRequest.service',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Contrato formalizado exitosamente',
            'data' => [
                'contract' => $this->formatContractResponse($contract),
            ],
        ], 201);
    }

    /**
     * @OA\Get(
     *     path="/api/contracts/{id}",
     *     operationId="showContract",
     *     tags={"Contracts"},
     *     summary="Obtener contrato por ID",
     *     description="Retorna los detalles de un contrato.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID del contrato",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(response=200, description="Contrato obtenido exitosamente"),
     *     @OA\Response(response=401, description="No autorizado. Token requerido o inválido"),
     *     @OA\Response(response=403, description="Sin permisos"),
     *     @OA\Response(response=404, description="Contrato no encontrado")
     * )
     */
    public function show(int $id)
    {
        $contract = Contract::with([
            'contractRequest.client.roles',
            'contractRequest.freelancer.user.roles',
            'contractRequest.service',
        ])->find($id);

        if (! $contract) {
            return response()->json([
                'success' => false,
                'message' => 'Contrato no encontrado',
            ], 404);
        }

        if (! $this->canViewContract($contract)) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permisos para ver este contrato.',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'message' => 'Contrato obtenido exitosamente',
            'data' => [
                'contract' => $this->formatContractResponse($contract),
            ],
        ]);
    }

    /**
     * @OA\Put(
     *     path="/api/contracts/{id}",
     *     operationId="updateContract",
     *     tags={"Contracts"},
     *     summary="Actualizar contrato",
     *     description="Actualiza fechas, monto o estado de un contrato.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID del contrato",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="start_date", type="string", format="date", example="2026-06-18"),
     *             @OA\Property(property="end_date", type="string", format="date", example="2026-07-18"),
     *             @OA\Property(property="total_amount", type="number", format="float", example=1200.50),
     *             @OA\Property(property="status", type="string", enum={"in_process","completed","canceled"}, example="completed")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Contrato actualizado correctamente"),
     *     @OA\Response(response=401, description="No autorizado. Token requerido o inválido"),
     *     @OA\Response(response=403, description="Sin permisos"),
     *     @OA\Response(response=404, description="Contrato no encontrado"),
     *     @OA\Response(response=422, description="Error de validación")
     * )
     */
    public function update(Request $request, int $id)
    {
        $contract = Contract::with([
            'contractRequest.client.roles',
            'contractRequest.freelancer.user.roles',
            'contractRequest.service',
        ])->find($id);

        if (! $contract) {
            return response()->json([
                'success' => false,
                'message' => 'Contrato no encontrado',
            ], 404);
        }

        if (! $this->canUpdateContract($contract)) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permisos para actualizar este contrato.',
            ], 403);
        }

        $validated = $request->validate([
            'start_date' => 'sometimes|required|date',
            'end_date' => 'nullable|date',
            'total_amount' => 'sometimes|required|numeric|min:0',
            'status' => 'sometimes|required|string|in:in_process,completed,canceled',
        ]);

        $startDate = $validated['start_date'] ?? $contract->start_date;
        $endDate = $validated['end_date'] ?? $contract->end_date;

        if ($endDate && $endDate < $startDate) {
            return response()->json([
                'success' => false,
                'message' => 'La fecha final debe ser igual o posterior a la fecha inicial.',
            ], 422);
        }

        $contract->update($validated);

        ActivityLoggerService::logUpdate(
            module: 'CONTRACTS',
            entity: 'contracts',
            entityId: $contract->id,
            description: "Contract ID {$contract->id} updated"
        );

        $contract->refresh();
        $contract->load([
            'contractRequest.client.roles',
            'contractRequest.freelancer.user.roles',
            'contractRequest.service',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Contrato actualizado correctamente',
            'data' => [
                'contract' => $this->formatContractResponse($contract),
            ],
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/contracts/{id}",
     *     operationId="deleteContract",
     *     tags={"Contracts"},
     *     summary="Eliminar contrato",
     *     description="Elimina un contrato. Solo administradores.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID del contrato",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(response=200, description="Contrato eliminado correctamente"),
     *     @OA\Response(response=401, description="No autorizado. Token requerido o inválido"),
     *     @OA\Response(response=403, description="Sin permisos"),
     *     @OA\Response(response=404, description="Contrato no encontrado")
     * )
     */
    public function destroy(int $id)
    {
        $contract = Contract::with('contractRequest.freelancer')->find($id);

        if (! $contract) {
            return response()->json([
                'success' => false,
                'message' => 'Contrato no encontrado',
            ], 404);
        }

        if (! $this->canDeleteContract($contract)) {
            return response()->json([
                'success' => false,
                'message' => 'Solo administradores pueden eliminar contratos.',
            ], 403);
        }

        ActivityLoggerService::logDelete(
            module: 'CONTRACTS',
            entity: 'contracts',
            entityId: $contract->id,
            description: "Contract ID {$contract->id} deleted"
        );

        $contract->delete();

        return response()->json([
            'success' => true,
            'message' => 'Contrato eliminado correctamente',
        ]);
    }
}