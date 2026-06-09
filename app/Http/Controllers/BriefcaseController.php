<?php

namespace App\Http\Controllers;

use App\Models\Briefcase;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 * name="Briefcases",
 * description="Endpoints para la gestión del Portafolio (Briefcases) de los Freelancers"
 * )
 */
class BriefcaseController extends Controller
{
    /**
     * @OA\Get(
     * path="/api/briefcases",
     * tags={"Briefcases"},
     * summary="Obtener lista de portafolios",
     * description="Retorna una lista de todos los proyectos de portafolio con su perfil de freelancer",
     * @OA\Response(
     * response=200,
     * description="Operación exitosa"
     * )
     * )
     */
    public function index()
    {
        $briefcases = Briefcase::with('freelancerProfile')->get();
        return response()->json($briefcases, 200);
    }

    /**
     * @OA\Post(
     * path="/api/briefcases",
     * tags={"Briefcases"},
     * summary="Crear un nuevo proyecto en el portafolio",
     * description="Crea un registro de portafolio en la base de datos",
     * @OA\RequestBody(
     * required=true,
     * @OA\JsonContent(
     * required={"freelancer_id","title"},
     * @OA\Property(property="freelancer_id", type="integer", example=1),
     * @OA\Property(property="title", type="string", example="E-commerce en Laravel"),
     * @OA\Property(property="description", type="string", example="Desarrollo completo de una tienda online..."),
     * @OA\Property(property="url_image", type="string", example="https://misitio.com/imagen.jpg"),
     * @OA\Property(property="url_proyecto", type="string", example="https://github.com/usuario/repo")
     * )
     * ),
     * @OA\Response(
     * response=201,
     * description="Proyecto creado exitosamente"
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
            'description'   => 'nullable|string',
            'url_image'     => 'nullable|string|max:255',
            'url_proyecto'  => 'nullable|string|max:255',
        ]);

        $briefcase = Briefcase::create($validated);

        return response()->json([
            'message' => 'Proyecto añadido al portafolio exitosamente.',
            'data' => $briefcase
        ], 201);
    }

    /**
     * @OA\Get(
     * path="/api/briefcases/{id}",
     * tags={"Briefcases"},
     * summary="Obtener un proyecto específico",
     * description="Retorna los detalles de un proyecto de portafolio buscado por su ID",
     * @OA\Parameter(
     * name="id",
     * in="path",
     * description="ID del proyecto",
     * required=true,
     * @OA\Schema(type="integer")
     * ),
     * @OA\Response(
     * response=200,
     * description="Operación exitosa"
     * ),
     * @OA\Response(
     * response=404,
     * description="Proyecto no encontrado"
     * )
     * )
     */
    public function show($id)
    {
        $briefcase = Briefcase::with('freelancerProfile')->findOrFail($id);
        return response()->json($briefcase, 200);
    }

    /**
     * @OA\Put(
     * path="/api/briefcases/{id}",
     * tags={"Briefcases"},
     * summary="Actualizar un proyecto del portafolio",
     * description="Actualiza la información de un proyecto existente",
     * @OA\Parameter(
     * name="id",
     * in="path",
     * description="ID del proyecto a actualizar",
     * required=true,
     * @OA\Schema(type="integer")
     * ),
     * @OA\RequestBody(
     * required=true,
     * @OA\JsonContent(
     * required={"title"},
     * @OA\Property(property="title", type="string", example="E-commerce en Laravel (Actualizado)"),
     * @OA\Property(property="description", type="string", example="Nueva descripción del proyecto..."),
     * @OA\Property(property="url_image", type="string", example="https://misitio.com/nueva_imagen.jpg"),
     * @OA\Property(property="url_proyecto", type="string", example="https://github.com/usuario/repo_actualizado")
     * )
     * ),
     * @OA\Response(
     * response=200,
     * description="Proyecto actualizado correctamente"
     * )
     * )
     */
    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'title'         => 'required|string|max:150',
            'description'   => 'nullable|string',
            'url_image'     => 'nullable|string|max:255',
            'url_proyecto'  => 'nullable|string|max:255',
        ]);

        $briefcase = Briefcase::findOrFail($id);
        $briefcase->update($validated);

        return response()->json([
            'message' => 'Proyecto actualizado correctamente.',
            'data' => $briefcase
        ], 200);
    }

    /**
     * @OA\Delete(
     * path="/api/briefcases/{id}",
     * tags={"Briefcases"},
     * summary="Eliminar un proyecto del portafolio",
     * description="Aplica un Soft Delete al proyecto especificado",
     * @OA\Parameter(
     * name="id",
     * in="path",
     * description="ID del proyecto a eliminar",
     * required=true,
     * @OA\Schema(type="integer")
     * ),
     * @OA\Response(
     * response=200,
     * description="Proyecto eliminado"
     * ),
     * @OA\Response(
     * response=404,
     * description="Proyecto no encontrado"
     * )
     * )
     */
    public function destroy($id)
    {
        $briefcase = Briefcase::findOrFail($id);
        $briefcase->delete();

        return response()->json([
            'message' => 'Proyecto eliminado del portafolio correctamente.'
        ], 200);
    }
}