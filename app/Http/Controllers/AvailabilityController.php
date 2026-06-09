<?php

namespace App\Http\Controllers;

use App\Models\Availability;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 * name="Availabilities",
 * description="Endpoints para la gestión de la Disponibilidad Horaria de los Freelancers"
 * )
 */
class AvailabilityController extends Controller
{
    /**
     * @OA\Get(
     * path="/api/availabilities",
     * tags={"Availabilities"},
     * summary="Obtener lista de disponibilidades",
     * description="Retorna una lista de todos los rangos de disponibilidad con su perfil de freelancer",
     * @OA\Response(
     * response=200,
     * description="Operación exitosa"
     * )
     * )
     */
    public function index()
    {
        $availabilities = Availability::with('freelancerProfile')->get();
        return response()->json($availabilities, 200);
    }

    /**
     * @OA\Post(
     * path="/api/availabilities",
     * tags={"Availabilities"},
     * summary="Crear un nuevo rango de disponibilidad",
     * description="Crea un registro de disponibilidad en la base de datos",
     * @OA\RequestBody(
     * required=true,
     * @OA\JsonContent(
     * required={"freelancer_id","start_date","end_date"},
     * @OA\Property(property="freelancer_id", type="integer", example=1),
     * @OA\Property(property="start_date", type="string", format="date", example="2026-06-15"),
     * @OA\Property(property="end_date", type="string", format="date", example="2026-06-30"),
     * @OA\Property(property="status", type="string", enum={"available", "busy", "vacation"}, example="available")
     * )
     * ),
     * @OA\Response(
     * response=201,
     * description="Disponibilidad creada exitosamente"
     * ),
     * @OA\Response(
     * response=422,
     * description="Error de validación"
     * )
     * )
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'freelancer_id' => 'required|exists:freelancer_profiles,id',
            'start_date'    => 'required|date',
            'end_date'      => 'required|date|after_or_equal:start_date',
            'status'        => 'string|in:available,busy,vacation',
        ]);

        $availability = Availability::create($validated);

        return response()->json([
            'message' => 'Disponibilidad horaria guardada exitosamente.',
            'data' => $availability
        ], 201);
    }

    /**
     * @OA\Get(
     * path="/api/availabilities/{id}",
     * tags={"Availabilities"},
     * summary="Obtener una disponibilidad específica",
     * description="Retorna los detalles de un registro específico de disponibilidad buscado por su ID",
     * @OA\Parameter(
     * name="id",
     * in="path",
     * description="ID de la disponibilidad",
     * required=true,
     * @OA\Schema(type="integer")
     * ),
     * @OA\Response(
     * response=200,
     * description="Operación exitosa"
     * ),
     * @OA\Response(
     * response=404,
     * description="Disponibilidad no encontrada"
     * )
     * )
     */
    public function show($id)
    {
        $availability = Availability::with('freelancerProfile')->findOrFail($id);
        return response()->json($availability, 200);
    }

    /**
     * @OA\Put(
     * path="/api/availabilities/{id}",
     * tags={"Availabilities"},
     * summary="Actualizar una disponibilidad horaria",
     * description="Actualiza las fechas o el estado de una disponibilidad existente",
     * @OA\Parameter(
     * name="id",
     * in="path",
     * description="ID de la disponibilidad a actualizar",
     * required=true,
     * @OA\Schema(type="integer")
     * ),
     * @OA\RequestBody(
     * required=true,
     * @OA\JsonContent(
     * @OA\Property(property="start_date", type="string", format="date", example="2026-06-15"),
     * @OA\Property(property="end_date", type="string", format="date", example="2026-07-10"),
     * @OA\Property(property="status", type="string", enum={"available", "busy", "vacation"}, example="busy")
     * )
     * ),
     * @OA\Response(
     * response=200,
     * description="Disponibilidad actualizada correctamente"
     * )
     * )
     */
    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'start_date'    => 'sometimes|required|date',
            'end_date'      => 'sometimes|required|date|after_or_equal:start_date',
            'status'        => 'sometimes|required|string|in:available,busy,vacation',
        ]);

        $availability = Availability::findOrFail($id);
        $availability->update($validated);

        return response()->json([
            'message' => 'Disponibilidad actualizada correctamente.',
            'data' => $availability
        ], 200);
    }

    /**
     * @OA\Delete(
     * path="/api/availabilities/{id}",
     * tags={"Availabilities"},
     * summary="Eliminar una disponibilidad horaria",
     * description="Aplica un Soft Delete al registro de disponibilidad especificado",
     * @OA\Parameter(
     * name="id",
     * in="path",
     * description="ID de la disponibilidad a eliminar",
     * required=true,
     * @OA\Schema(type="integer")
     * ),
     * @OA\Response(
     * response=200,
     * description="Disponibilidad eliminada"
     * ),
     * @OA\Response(
     * response=404,
     * description="Disponibilidad no encontrado"
     * )
     * )
     */
    public function destroy($id)
    {
        $availability = Availability::findOrFail($id);
        $availability->delete();

        return response()->json([
            'message' => 'Horario de disponibilidad eliminado correctamente.'
        ], 200);
    }
}