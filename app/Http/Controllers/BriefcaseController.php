<?php

namespace App\Http\Controllers;

use App\Models\Briefcase;
use App\Models\FreelancerProfile;
use App\Models\User;
use App\Services\ActivityLoggerService;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="Briefcases",
 *     description="Endpoints para la gestión del portafolio de freelancers"
 * )
 */
class BriefcaseController extends Controller
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

    private function formatBriefcaseResponse(Briefcase $briefcase): array
    {
        $briefcase->loadMissing('freelancerProfile.user.roles');

        return [
            'id' => $briefcase->id,
            'freelancer_id' => $briefcase->freelancer_id,
            'freelancer_profile' => $this->formatFreelancerProfileResponse($briefcase->freelancerProfile),
            'title' => $briefcase->title,
            'description' => $briefcase->description,
            'image_url' => $briefcase->image_url,
            'project_url' => $briefcase->project_url,
            'created_at' => $briefcase->created_at,
            'updated_at' => $briefcase->updated_at,
        ];
    }

    private function canManageBriefcase(Briefcase $briefcase): bool
    {
        $user = auth('api')->user();

        if (! $user) {
            return false;
        }

        $briefcase->loadMissing('freelancerProfile');

        return $user->hasRole('admin')
            || ($briefcase->freelancerProfile && $briefcase->freelancerProfile->user_id === $user->id);
    }

    /**
     * @OA\Get(
     *     path="/api/briefcases",
     *     operationId="listBriefcases",
     *     tags={"Briefcases"},
     *     summary="Listar portafolios",
     *     description="Retorna todos los proyectos de portafolio con su perfil freelancer, usuario y rol.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Portafolios obtenidos exitosamente"),
     *     @OA\Response(response=401, description="No autorizado. Token requerido o inválido")
     * )
     */
    public function index()
    {
        $briefcases = Briefcase::with('freelancerProfile.user.roles')
            ->latest('created_at')
            ->get()
            ->map(fn ($briefcase) => $this->formatBriefcaseResponse($briefcase))
            ->values();

        return response()->json([
            'success' => true,
            'message' => 'Portafolios obtenidos exitosamente',
            'data' => [
                'briefcases' => $briefcases,
            ],
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/briefcases",
     *     operationId="createBriefcase",
     *     tags={"Briefcases"},
     *     summary="Crear proyecto de portafolio",
     *     description="Crea un proyecto en el portafolio de un freelancer. Un freelancer crea proyectos para su propio perfil; un admin puede indicar freelancer_id.",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"title"},
     *             @OA\Property(property="freelancer_id", type="integer", example=1, description="Solo requerido si el usuario autenticado es admin"),
     *             @OA\Property(property="title", type="string", example="E-commerce en Laravel"),
     *             @OA\Property(property="description", type="string", example="Desarrollo completo de una tienda online"),
     *             @OA\Property(property="image_url", type="string", example="https://misitio.com/imagen.jpg"),
     *             @OA\Property(property="project_url", type="string", example="https://github.com/usuario/repo")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Proyecto creado exitosamente"),
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
                'message' => 'Solo freelancers o administradores pueden crear proyectos de portafolio.',
            ], 403);
        }

        $validated = $request->validate([
            'freelancer_id' => 'nullable|integer|exists:freelancer_profiles,id',
            'title' => 'required|string|max:150',
            'description' => 'nullable|string',
            'image_url' => 'nullable|string|max:255',
            'project_url' => 'nullable|string|max:255',
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

        $briefcase = Briefcase::create([
            'freelancer_id' => $profile->id,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'image_url' => $validated['image_url'] ?? null,
            'project_url' => $validated['project_url'] ?? null,
        ]);

        ActivityLoggerService::logCreate(
            module: 'BRIEFCASES',
            entity: 'briefcases',
            entityId: $briefcase->id,
            description: "Briefcase project {$briefcase->title} created"
        );

        $briefcase->load('freelancerProfile.user.roles');

        return response()->json([
            'success' => true,
            'message' => 'Proyecto añadido al portafolio exitosamente',
            'data' => [
                'briefcase' => $this->formatBriefcaseResponse($briefcase),
            ],
        ], 201);
    }

    /**
     * @OA\Get(
     *     path="/api/briefcases/{id}",
     *     operationId="showBriefcase",
     *     tags={"Briefcases"},
     *     summary="Obtener proyecto de portafolio por ID",
     *     description="Retorna los detalles de un proyecto de portafolio.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID del proyecto",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(response=200, description="Proyecto obtenido exitosamente"),
     *     @OA\Response(response=401, description="No autorizado. Token requerido o inválido"),
     *     @OA\Response(response=404, description="Proyecto no encontrado")
     * )
     */
    public function show(int $id)
    {
        $briefcase = Briefcase::with('freelancerProfile.user.roles')->find($id);

        if (! $briefcase) {
            return response()->json([
                'success' => false,
                'message' => 'Proyecto no encontrado',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Proyecto obtenido exitosamente',
            'data' => [
                'briefcase' => $this->formatBriefcaseResponse($briefcase),
            ],
        ]);
    }

    /**
     * @OA\Put(
     *     path="/api/briefcases/{id}",
     *     operationId="updateBriefcase",
     *     tags={"Briefcases"},
     *     summary="Actualizar proyecto de portafolio",
     *     description="Actualiza la información de un proyecto existente.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID del proyecto",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="title", type="string", example="E-commerce en Laravel actualizado"),
     *             @OA\Property(property="description", type="string", example="Nueva descripción del proyecto"),
     *             @OA\Property(property="image_url", type="string", example="https://misitio.com/nueva_imagen.jpg"),
     *             @OA\Property(property="project_url", type="string", example="https://github.com/usuario/repo_actualizado")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Proyecto actualizado correctamente"),
     *     @OA\Response(response=401, description="No autorizado. Token requerido o inválido"),
     *     @OA\Response(response=403, description="Sin permisos"),
     *     @OA\Response(response=404, description="Proyecto no encontrado"),
     *     @OA\Response(response=422, description="Error de validación")
     * )
     */
    public function update(Request $request, int $id)
    {
        $briefcase = Briefcase::with('freelancerProfile')->find($id);

        if (! $briefcase) {
            return response()->json([
                'success' => false,
                'message' => 'Proyecto no encontrado',
            ], 404);
        }

        if (! $this->canManageBriefcase($briefcase)) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permisos para actualizar este proyecto.',
            ], 403);
        }

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:150',
            'description' => 'nullable|string',
            'image_url' => 'nullable|string|max:255',
            'project_url' => 'nullable|string|max:255',
        ]);

        $briefcase->update($validated);

        ActivityLoggerService::logUpdate(
            module: 'BRIEFCASES',
            entity: 'briefcases',
            entityId: $briefcase->id,
            description: "Briefcase project {$briefcase->title} updated"
        );

        $briefcase->refresh();
        $briefcase->load('freelancerProfile.user.roles');

        return response()->json([
            'success' => true,
            'message' => 'Proyecto actualizado correctamente',
            'data' => [
                'briefcase' => $this->formatBriefcaseResponse($briefcase),
            ],
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/briefcases/{id}",
     *     operationId="deleteBriefcase",
     *     tags={"Briefcases"},
     *     summary="Eliminar proyecto de portafolio",
     *     description="Elimina un proyecto del portafolio.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID del proyecto",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(response=200, description="Proyecto eliminado correctamente"),
     *     @OA\Response(response=401, description="No autorizado. Token requerido o inválido"),
     *     @OA\Response(response=403, description="Sin permisos"),
     *     @OA\Response(response=404, description="Proyecto no encontrado")
     * )
     */
    public function destroy(int $id)
    {
        $briefcase = Briefcase::with('freelancerProfile')->find($id);

        if (! $briefcase) {
            return response()->json([
                'success' => false,
                'message' => 'Proyecto no encontrado',
            ], 404);
        }

        if (! $this->canManageBriefcase($briefcase)) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permisos para eliminar este proyecto.',
            ], 403);
        }

        ActivityLoggerService::logDelete(
            module: 'BRIEFCASES',
            entity: 'briefcases',
            entityId: $briefcase->id,
            description: "Briefcase project {$briefcase->title} deleted"
        );

        $briefcase->delete();

        return response()->json([
            'success' => true,
            'message' => 'Proyecto eliminado del portafolio correctamente',
        ]);
    }
}