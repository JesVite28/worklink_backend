<?php

namespace App\Http\Controllers;

use App\Models\Contract;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 * name="Contracts",
 * description="Endpoints para la gestión de Contrataciones formalizadas"
 * )
 */
class ContractController extends Controller
{
    /**
     * @OA\Get(
     * path="/api/contracts",
     * tags={"Contracts"},
     * summary="Obtener lista de contratos",
     * description="Retorna una lista de todas las contrataciones con su solicitud origen",
     * @OA\Response(response=200, description="Operación exitosa")
     * )
     */
    public function index()
    {
        // Trae los contratos cargando su solicitud, cliente, freelancer y servicio de forma anidada
        $contracts = Contract::with(['contractRequest.client', 'contractRequest.freelancer', 'contractRequest.service'])->get();
        return response()->json($contracts, 200);
    }

    /**
     * @OA\Post(
     * path="/api/contracts",
     * tags={"Contracts"},
     * summary="Crear un nuevo contrato",
     * description="Formaliza una solicitud de contratación en un contrato activo",
     * @OA\RequestBody(
     * required=true,
     * @OA\JsonContent(
     * required={"request_id","start_date","total_amount"},
     * @OA\Property(property="request_id", type="integer", example=1),
     * @OA\Property(property="start_date", type="string", format="date", example="2026-06-18"),
     * @OA\Property(property="end_date", type="string", format="date", example="2026-07-18"),
     * @OA\Property(property="total_amount", type="number", format="float", example=1200.50),
     * @OA\Property(property="status", type="string", enum={"in_process", "completed", "canceled"}, example="in_process")
     * )
     * ),
     * @OA\Response(response=201, description="Contrato creado exitosamente")
     * )
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'request_id'   => 'required|exists:contract_requests,id',
            'start_date'   => 'required|date',
            'end_date'     => 'nullable|date|after_or_equal:start_date',
            'total_amount' => 'required|numeric|min:0',
            'status'       => 'sometimes|in:in_process,completed,canceled',
        ]);

        $contract = Contract::create($validated);

        return response()->json([
            'message' => 'Contrato formalizado exitosamente.',
            'data'    => $contract
        ], 201);
    }

    /**
     * @OA\Get(
     * path="/api/contracts/{id}",
     * tags={"Contracts"},
     * summary="Obtener detalles de un contrato",
     * @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     * @OA\Response(response=200, description="Operación exitosa")
     * )
     */
    public function show($id)
    {
        $contract = Contract::with(['contractRequest.client', 'contractRequest.freelancer', 'contractRequest.service'])->findOrFail($id);
        return response()->json($contract, 200);
    }

    /**
     * @OA\Put(
     * path="/api/contracts/{id}",
     * tags={"Contracts"},
     * summary="Actualizar información o estado de un contrato",
     * @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     * @OA\RequestBody(
     * @OA\JsonContent(
     * @OA\Property(property="status", type="string", enum={"in_process", "completed", "canceled"}),
     * @OA\Property(property="end_date", type="string", format="date")
     * )
     * ),
     * @OA\Response(response=200, description="Contrato actualizado")
     * )
     */
    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'start_date'   => 'sometimes|required|date',
            'end_date'     => 'nullable|date|after_or_equal:start_date',
            'total_amount' => 'sometimes|required|numeric|min:0',
            'status'       => 'sometimes|required|in:in_process,completed,canceled',
        ]);

        $contract = Contract::findOrFail($id);
        $contract->update($validated);

        return response()->json([
            'message' => 'Contrato actualizado correctamente.',
            'data'    => $contract
        ], 200);
    }

    /**
     * @OA\Delete(
     * path="/api/contracts/{id}",
     * tags={"Contracts"},
     * summary="Eliminar un contrato (Soft Delete)",
     * @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     * @OA\Response(response=200, description="Contrato eliminado")
     * )
     */
    public function destroy($id)
    {
        $contract = Contract::findOrFail($id);
        $contract->delete();

        return response()->json(['message' => 'Contrato eliminado correctamente.'], 200);
    }
}