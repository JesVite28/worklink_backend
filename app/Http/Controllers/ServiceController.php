<?php

namespace App\Http\Controllers;

use App\Models\Service;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 * name="Services",
 * description="Endpoints para la gestión de Servicios de los Freelancers"
 * )
 */
class ServiceController extends Controller
{
    /**
     * @OA\Get(
     * path="/api/services",
     * tags={"Services"},
     * summary="Obtener lista de servicios",
     * description="Retorna una lista de todos los servicios activos con su perfil de freelancer",
     * @OA\Response(
     * response=200,
     * description="Operación exitosa"
     * )
     * )
     */
    public function index()
    {
        $services = Service::with('freelancerProfile')->get();
        return response()->json($services, 200);
    }

    /**
     * @OA\Post(
     * path="/api/services",
     * tags={"Services"},
     * summary="Crear un nuevo servicio",
     * description="Crea un registro de servicio en la base de datos",
     * @OA\RequestBody(
     * required=true,
     * @OA\JsonContent(
     * required={"freelancer_id","title","description","category"},
     * @OA\Property(property="freelancer_id", type="integer", example=1),
     * @OA\Property(property="title", type="string", example="Desarrollo Web en Laravel"),
     * @OA\Property(property="description", type="string", example="Creación de API RESTful..."),
     * @OA\Property(property="price", type="number", format="float", example=50.00),
     * @OA\Property(property="category", type="string", example="Programación"),
     * @OA\Property(property="location", type="string", example="Remoto"),
     * @OA\Property(property="is_active", type="boolean", example=true)
     * )
     * ),
     * @OA\Response(
     * response=201,
     * description="Servicio creado exitosamente"
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
            'title'         => 'required|string|max:150',
            'description'   => 'required|string',
            'price'         => 'nullable|numeric|min:0',
            'category'      => 'required|string|max:100',
            'location'      => 'nullable|string|max:150',
            'is_active'     => 'boolean',
        ]);

        $service = Service::create($validated);

        return response()->json([
            'message' => 'Servicio creado exitosamente.',
            'data' => $service
        ], 201);
    }

    /**
     * @OA\Get(
     * path="/api/services/{id}",
     * tags={"Services"},
     * summary="Obtener un servicio específico",
     * description="Retorna los detalles de un solo servicio buscado por su ID",
     * @OA\Parameter(
     * name="id",
     * in="path",
     * description="ID del servicio",
     * required=true,
     * @OA\Schema(type="integer")
     * ),
     * @OA\Response(
     * response=200,
     * description="Operación exitosa"
     * ),
     * @OA\Response(
     * response=404,
     * description="Servicio no encontrado"
     * )
     * )
     */
    public function show($id)
    {
        $service = Service::with('freelancerProfile')->findOrFail($id);
        return response()->json($service, 200);
    }

    /**
     * @OA\Put(
     * path="/api/services/{id}",
     * tags={"Services"},
     * summary="Actualizar un servicio",
     * description="Actualiza la información de un servicio existente",
     * @OA\Parameter(
     * name="id",
     * in="path",
     * description="ID del servicio a actualizar",
     * required=true,
     * @OA\Schema(type="integer")
     * ),
     * @OA\RequestBody(
     * required=true,
     * @OA\JsonContent(
     * required={"title","description","category"},
     * @OA\Property(property="title", type="string", example="Desarrollo Web (Actualizado)"),
     * @OA\Property(property="description", type="string", example="Nueva descripción..."),
     * @OA\Property(property="price", type="number", format="float", example=60.00),
     * @OA\Property(property="category", type="string", example="Programación"),
     * @OA\Property(property="location", type="string", example="Remoto"),
     * @OA\Property(property="is_active", type="boolean", example=true)
     * )
     * ),
     * @OA\Response(
     * response=200,
     * description="Servicio actualizado correctamente"
     * )
     * )
     */
    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'title'         => 'required|string|max:150',
            'description'   => 'required|string',
            'price'         => 'nullable|numeric|min:0',
            'category'      => 'required|string|max:100',
            'location'      => 'nullable|string|max:150',
            'is_active'     => 'boolean',
        ]);

        $service = Service::findOrFail($id);
        $service->update($validated);

        return response()->json([
            'message' => 'Servicio actualizado correctamente.',
            'data' => $service
        ], 200);
    }

    /**
     * @OA\Delete(
     * path="/api/services/{id}",
     * tags={"Services"},
     * summary="Eliminar un servicio",
     * description="Aplica un Soft Delete al servicio especificado",
     * @OA\Parameter(
     * name="id",
     * in="path",
     * description="ID del servicio a eliminar",
     * required=true,
     * @OA\Schema(type="integer")
     * ),
     * @OA\Response(
     * response=200,
     * description="Servicio eliminado"
     * ),
     * @OA\Response(
     * response=404,
     * description="Servicio no encontrado"
     * )
     * )
     */
    public function destroy($id)
    {
        $service = Service::findOrFail($id);
        $service->delete();

        return response()->json([
            'message' => 'Servicio eliminado correctamente.'
        ], 200);
    }
}