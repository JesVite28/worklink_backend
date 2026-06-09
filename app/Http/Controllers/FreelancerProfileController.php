<?php

namespace App\Http\Controllers;

use App\Models\FreelancerProfile;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 * name="Freelancer Profiles",
 * description="Endpoints para la gestión de los Perfiles de Freelancers"
 * )
 */
class FreelancerProfileController extends Controller
{
    /**
     * @OA\Get(
     * path="/api/profiles",
     * tags={"Freelancer Profiles"},
     * summary="Obtener lista de perfiles",
     * description="Retorna una lista de todos los perfiles de freelancers con su información de usuario",
     * @OA\Response(
     * response=200,
     * description="Operación exitosa"
     * )
     * )
     */
    public function index()
    {
        $profiles = FreelancerProfile::with('user')->get();
        return response()->json($profiles, 200);
    }

    /**
     * @OA\Post(
     * path="/api/profiles",
     * tags={"Freelancer Profiles"},
     * summary="Crear un nuevo perfil de freelancer",
     * description="Crea un registro de perfil vinculado a un usuario existente",
     * @OA\RequestBody(
     * required=true,
     * @OA\JsonContent(
     * required={"user_id"},
     * @OA\Property(property="user_id", type="integer", example=1),
     * @OA\Property(property="description", type="string", example="Desarrollador Full Stack con 5 años de experiencia..."),
     * @OA\Property(property="specialty", type="string", example="Desarrollo Web"),
     * @OA\Property(property="hourly_rate", type="number", format="float", example=25.50),
     * @OA\Property(property="location", type="string", example="Ciudad de México"),
     * @OA\Property(property="available", type="boolean", example=true),
     * @OA\Property(property="average_rate", type="number", format="float", example=5.00)
     * )
     * ),
     * @OA\Response(
     * response=201,
     * description="Perfil creado exitosamente"
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
            'user_id'      => 'required|exists:users,id|unique:freelancer_profiles,user_id',
            'description'  => 'nullable|string',
            'specialty'    => 'nullable|string|max:150',
            'hourly_rate'  => 'nullable|numeric|min:0',
            'location'     => 'nullable|string|max:150',
            'available'    => 'boolean',
            'average_rate' => 'nullable|numeric|min:0|max:5',
        ]);

        $profile = FreelancerProfile::create($validated);

        return response()->json([
            'message' => 'Perfil de freelancer creado exitosamente.',
            'data' => $profile
        ], 201);
    }

    /**
     * @OA\Get(
     * path="/api/profiles/{id}",
     * tags={"Freelancer Profiles"},
     * summary="Obtener el perfil completo de un freelancer",
     * description="Retorna los detalles del perfil, incluyendo sus servicios, portafolio y disponibilidad",
     * @OA\Parameter(
     * name="id",
     * in="path",
     * description="ID del perfil del freelancer",
     * required=true,
     * @OA\Schema(type="integer")
     * ),
     * @OA\Response(
     * response=200,
     * description="Operación exitosa"
     * ),
     * @OA\Response(
     * response=404,
     * description="Perfil no encontrado"
     * )
     * )
     */
    public function show($id)
    {
        $profile = FreelancerProfile::with(['user', 'services', 'briefcases', 'availabilities'])->findOrFail($id);
        
        return response()->json($profile, 200);
    }

    /**
     * @OA\Put(
     * path="/api/profiles/{id}",
     * tags={"Freelancer Profiles"},
     * summary="Actualizar un perfil de freelancer",
     * description="Actualiza la información pública del perfil (tarifa, descripción, especialidad, etc.)",
     * @OA\Parameter(
     * name="id",
     * in="path",
     * description="ID del perfil a actualizar",
     * required=true,
     * @OA\Schema(type="integer")
     * ),
     * @OA\RequestBody(
     * required=true,
     * @OA\JsonContent(
     * @OA\Property(property="description", type="string", example="Descripción actualizada..."),
     * @OA\Property(property="specialty", type="string", example="Arquitecto de Software"),
     * @OA\Property(property="hourly_rate", type="number", format="float", example=30.00),
     * @OA\Property(property="location", type="string", example="Monterrey"),
     * @OA\Property(property="available", type="boolean", example=false)
     * )
     * ),
     * @OA\Response(
     * response=200,
     * description="Perfil actualizado correctamente"
     * )
     * )
     */
    /**
     * @OA\Put(
     * path="/api/profiles/{id}",
     * tags={"Freelancer Profiles"},
     * summary="Actualizar un perfil de freelancer",
     * description="Actualiza la información pública del perfil (tarifa, descripción, especialidad, etc.)",
     * @OA\Parameter(
     * name="id",
     * in="path",
     * description="ID del perfil a actualizar",
     * required=true,
     * @OA\Schema(type="integer")
     * ),
     * @OA\RequestBody(
     * required=true,
     * @OA\JsonContent(
     * @OA\Property(property="description", type="string", example="Descripción actualizada..."),
     * @OA\Property(property="specialty", type="string", example="Arquitecto de Software"),
     * @OA\Property(property="hourly_rate", type="number", format="float", example=30.00),
     * @OA\Property(property="location", type="string", example="Monterrey"),
     * @OA\Property(property="available", type="boolean", example=false)
     * )
     * ),
     * @OA\Response(
     * response=200,
     * description="Perfil actualizado correctamente"
     * )
     * )
     */
    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'description'  => 'nullable|string',
            'specialty'    => 'nullable|string|max:150',
            'hourly_rate'  => 'nullable|numeric|min:0',
            'location'     => 'nullable|string|max:150',
            'available'    => 'boolean',
            'average_rate' => 'nullable|numeric|min:0|max:5',
        ]);

        // Buscamos el perfil por ID explícitamente
        $profile = FreelancerProfile::findOrFail($id);
        $profile->update($validated);

        return response()->json([
            'message' => 'Perfil actualizado correctamente.',
            'data' => $profile
        ], 200);
    }

    /**
     * @OA\Delete(
     * path="/api/profiles/{id}",
     * tags={"Freelancer Profiles"},
     * summary="Eliminar un perfil de freelancer",
     * description="Aplica un Soft Delete al perfil especificado",
     * @OA\Parameter(
     * name="id",
     * in="path",
     * description="ID del perfil a eliminar",
     * required=true,
     * @OA\Schema(type="integer")
     * ),
     * @OA\Response(
     * response=200,
     * description="Perfil eliminado"
     * ),
     * @OA\Response(
     * response=404,
     * description="Perfil no encontrado"
     * )
     * )
     */
    public function destroy($id)
    {
        // Buscamos el perfil por ID explícitamente
        $profile = FreelancerProfile::findOrFail($id);
        $profile->delete();

        return response()->json([
            'message' => 'Perfil eliminado correctamente.'
        ], 200);
    }
}