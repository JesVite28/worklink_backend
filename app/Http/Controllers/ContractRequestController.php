<?php

namespace App\Http\Controllers;

use App\Models\ContractRequest;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\FreelancerProfile;
use App\Models\Service;

/**
 * @OA\Tag(
 * name="Contract Requests",
 * description="Endpoints para la gestión de solicitudes de contratación"
 * )
 */
class ContractRequestController extends Controller
{
    /**
     * @OA\Get(
     * path="/api/contract-requests",
     * tags={"Contract Requests"},
     * summary="Obtener lista de solicitudes",
     * description="Retorna todas las solicitudes con su cliente, freelancer y servicio relacionado",
     * @OA\Response(response=200, description="Operación exitosa")
     * )
     */
    public function index()
    {
        $requests = ContractRequest::with(['client', 'freelancer', 'service'])->get();
        return response()->json($requests, 200);
    }

    /**
     * @OA\Post(
     * path="/api/contract-requests",
     * tags={"Contract Requests"},
     * summary="Crear una solicitud",
     * @OA\RequestBody(
     * required=true,
     * @OA\JsonContent(
     * required={"client_id","freelancer_id","service_id","description"},
     * @OA\Property(property="client_id", type="integer", example=1),
     * @OA\Property(property="freelancer_id", type="integer", example=1),
     * @OA\Property(property="service_id", type="integer", example=1),
     * @OA\Property(property="description", type="string", example="Detalles del proyecto..."),
     * @OA\Property(property="budget", type="number", format="float", example=500.00),
     * @OA\Property(property="status", type="string", enum={"pending", "accepted", "rejected", "canceled"}, example="pending")
     * )
     * ),
     * @OA\Response(response=201, description="Solicitud creada")
     * )
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'client_id'     => 'required|exists:users,id',
            'freelancer_id' => 'required|exists:freelancer_profiles,id',
            'service_id'    => 'required|exists:services,id',
            'description'   => 'required|string',
            'budget'        => 'required|numeric|min:0',
            'status'        => 'sometimes|in:pending,accepted,rejected,canceled',
        ]);

        $contractRequest = ContractRequest::create($validated);

        return response()->json([
            'message' => 'Solicitud de contratación creada exitosamente.',
            'data'    => $contractRequest
        ], 201);
    }

    /**
     * @OA\Get(
     * path="/api/contract-requests/{id}",
     * tags={"Contract Requests"},
     * summary="Obtener una solicitud específica",
     * @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     * @OA\Response(response=200, description="Éxito")
     * )
     */
    public function show($id)
    {
        $contractRequest = ContractRequest::with(['client', 'freelancer', 'service'])->findOrFail($id);
        return response()->json($contractRequest, 200);
    }

    /**
     * @OA\Put(
     * path="/api/contract-requests/{id}",
     * tags={"Contract Requests"},
     * summary="Actualizar solicitud",
     * @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     * @OA\RequestBody(
     * @OA\JsonContent(
     * @OA\Property(property="status", type="string", enum={"pending", "accepted", "rejected", "canceled"}),
     * @OA\Property(property="budget", type="number", format="float")
     * )
     * ),
     * @OA\Response(response=200, description="Actualizado")
     * )
     */
    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'description' => 'sometimes|string',
            'budget'      => 'sometimes|numeric|min:0',
            'status'      => 'sometimes|in:pending,accepted,rejected,canceled',
        ]);

        $contractRequest = ContractRequest::findOrFail($id);
        $contractRequest->update($validated);

        return response()->json([
            'message' => 'Solicitud actualizada correctamente.',
            'data'    => $contractRequest
        ], 200);
    }

    /**
     * @OA\Delete(
     * path="/api/contract-requests/{id}",
     * tags={"Contract Requests"},
     * summary="Eliminar solicitud",
     * @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     * @OA\Response(response=200, description="Eliminado")
     * )
     */
    public function destroy($id)
    {
        $contractRequest = ContractRequest::findOrFail($id);
        $contractRequest->delete();

        return response()->json(['message' => 'Solicitud eliminada correctamente.'], 200);
    }
}