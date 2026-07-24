<?php

namespace App\Http\Controllers;

use App\Models\CompanyProfile;
use App\Models\User;
use App\Models\Vacancy;
use App\Services\ActivityLoggerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * @OA\Tag(
 *     name="Vacancies",
 *     description="Endpoints para la gestión de vacantes empresariales"
 * )
 */
class VacancyController extends Controller
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

    private function getPrimaryRole(User $user)
    {
        $user->loadMissing('roles');

        return $user->roles->firstWhere('name', 'empresa')
            ?? $user->roles->first();
    }

    private function buildPublicStorageUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        if (filter_var($path, FILTER_VALIDATE_URL)) {
            return $path;
        }

        return asset(Storage::url($path));
    }

    private function formatPublicUserResponse(?User $user): ?array
    {
        if (! $user) {
            return null;
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'last_name' => $user->last_name,
            'maternal_last_name' => $user->maternal_last_name,
            'profile_photo' => $user->profile_photo,
            'profile_photo_url' => $this->buildPublicStorageUrl(
                $user->profile_photo
            ),
            'role' => $this->formatRoleResponse(
                $this->getPrimaryRole($user)
            ),
        ];
    }

    private function formatPrivateUserResponse(?User $user): ?array
    {
        if (! $user) {
            return null;
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'last_name' => $user->last_name,
            'maternal_last_name' => $user->maternal_last_name,
            'email' => $user->email,
            'phone' => $user->phone,
            'profile_photo' => $user->profile_photo,
            'profile_photo_url' => $this->buildPublicStorageUrl(
                $user->profile_photo
            ),
            'is_active' => $user->is_active,
            'role' => $this->formatRoleResponse(
                $this->getPrimaryRole($user)
            ),
        ];
    }

    private function formatCompanyProfileResponse(
        ?CompanyProfile $profile,
        bool $public = false
    ): ?array {
        if (! $profile) {
            return null;
        }

        $profile->loadMissing('user.roles');

        return [
            'id' => $profile->id,
            'user_id' => $profile->user_id,
            'user' => $public
                ? $this->formatPublicUserResponse($profile->user)
                : $this->formatPrivateUserResponse($profile->user),
            'company_name' => $profile->company_name,
            'description' => $profile->description,
            'industry' => $profile->industry,
            'location' => $profile->location,
            'average_rate' => $profile->average_rate,
        ];
    }

    private function formatVacancyResponse(
        Vacancy $vacancy,
        bool $public = false
    ): array {
        $vacancy->loadMissing('companyProfile.user.roles');

        return [
            'id' => $vacancy->id,
            'company_id' => $vacancy->company_id,
            'company_profile' =>
                $this->formatCompanyProfileResponse(
                    $vacancy->companyProfile,
                    $public
                ),
            'title' => $vacancy->title,
            'description' => $vacancy->description,
            'category' => $vacancy->category,
            'location' => $vacancy->location,
            'salary' => $vacancy->salary,
            'status' => $vacancy->status,
            'accepts_applications' =>
                $vacancy->status === Vacancy::STATUS_OPEN,
            'created_at' => $vacancy->created_at,
            'updated_at' => $vacancy->updated_at,
        ];
    }

    private function canManageVacancy(
        Vacancy $vacancy,
        User $user
    ): bool {
        $vacancy->loadMissing('companyProfile');

        return $user->hasRole('admin')
            || (
                $vacancy->companyProfile
                && $vacancy->companyProfile->user_id === $user->id
            );
    }

    private function findValidCompanyProfile(
        int $companyId
    ): ?CompanyProfile {
        return CompanyProfile::query()
            ->with('user.roles')
            ->whereKey($companyId)
            ->fromActiveCompanies()
            ->first();
    }

    private function applyFilters($query, Request $request)
    {
        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));

            $query->where(function ($searchQuery) use ($search) {
                $searchQuery
                    ->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('category', 'like', "%{$search}%")
                    ->orWhereHas(
                        'companyProfile',
                        function ($companyQuery) use ($search) {
                            $companyQuery->where(
                                'company_name',
                                'like',
                                "%{$search}%"
                            );
                        }
                    );
            });
        }

        if ($request->filled('category')) {
            $query->where(
                'category',
                $request->input('category')
            );
        }

        if ($request->filled('location')) {
            $query->where(
                'location',
                'like',
                '%' . trim((string) $request->input('location')) . '%'
            );
        }

        if ($request->filled('status')) {
            $query->where(
                'status',
                $request->input('status')
            );
        }

        if ($request->filled('min_salary')) {
            $query->where(
                'salary',
                '>=',
                $request->input('min_salary')
            );
        }

        if ($request->filled('max_salary')) {
            $query->where(
                'salary',
                '<=',
                $request->input('max_salary')
            );
        }

        return $query;
    }

    private function paginationData($paginator, bool $public): array
    {
        return [
            'vacancies' => collect($paginator->items())
                ->map(
                    fn (Vacancy $vacancy) =>
                        $this->formatVacancyResponse(
                            $vacancy,
                            $public
                        )
                )
                ->values(),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Public Endpoints
    |--------------------------------------------------------------------------
    */

    /**
     * @OA\Get(
     *     path="/api/public/vacancies",
     *     operationId="publicListVacancies",
     *     tags={"Vacancies"},
     *     summary="Listar vacantes públicas",
     *     description="Retorna únicamente vacantes abiertas pertenecientes a empresas activas.",
     *
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         required=false,
     *         @OA\Schema(type="string", example="Laravel")
     *     ),
     *     @OA\Parameter(
     *         name="category",
     *         in="query",
     *         required=false,
     *         @OA\Schema(type="string", example="Desarrollo de software")
     *     ),
     *     @OA\Parameter(
     *         name="location",
     *         in="query",
     *         required=false,
     *         @OA\Schema(type="string", example="Pachuca")
     *     ),
     *     @OA\Parameter(
     *         name="min_salary",
     *         in="query",
     *         required=false,
     *         @OA\Schema(type="number", format="float", example=10000)
     *     ),
     *     @OA\Parameter(
     *         name="max_salary",
     *         in="query",
     *         required=false,
     *         @OA\Schema(type="number", format="float", example=30000)
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         required=false,
     *         @OA\Schema(type="integer", minimum=1, maximum=100, example=12)
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Vacantes públicas obtenidas exitosamente"
     *     )
     * )
     */
    public function publicIndex(Request $request)
    {
        $request->validate([
            'search' => ['nullable', 'string', 'max:150'],
            'category' => ['nullable', 'string', 'max:100'],
            'location' => ['nullable', 'string', 'max:150'],
            'min_salary' => [
                'nullable',
                'numeric',
                'min:0',
                'max:9999999999.99',
            ],
            'max_salary' => [
                'nullable',
                'numeric',
                'min:0',
                'max:9999999999.99',
                'gte:min_salary',
            ],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = Vacancy::query()
            ->with('companyProfile.user.roles')
            ->publiclyAvailable();

        $this->applyFilters($query, $request);

        $paginator = $query
            ->latest('created_at')
            ->paginate((int) $request->input('per_page', 12));

        return response()->json([
            'success' => true,
            'message' => 'Vacantes públicas obtenidas exitosamente',
            'data' => $this->paginationData(
                $paginator,
                true
            ),
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/public/vacancies/company/{companyId}",
     *     operationId="publicVacanciesByCompany",
     *     tags={"Vacancies"},
     *     summary="Obtener vacantes públicas de una empresa",
     *
     *     @OA\Parameter(
     *         name="companyId",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Vacantes públicas de la empresa obtenidas exitosamente"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Empresa no encontrada"
     *     )
     * )
     */
    public function publicByCompany(int $companyId)
    {
        $companyProfile = $this->findValidCompanyProfile(
            $companyId
        );

        if (! $companyProfile) {
            return response()->json([
                'success' => false,
                'message' => 'Perfil empresarial público no encontrado.',
            ], 404);
        }

        $vacancies = Vacancy::query()
            ->with('companyProfile.user.roles')
            ->where('company_id', $companyProfile->id)
            ->where('status', Vacancy::STATUS_OPEN)
            ->latest('created_at')
            ->get()
            ->map(
                fn (Vacancy $vacancy) =>
                    $this->formatVacancyResponse(
                        $vacancy,
                        true
                    )
            )
            ->values();

        return response()->json([
            'success' => true,
            'message' => 'Vacantes públicas de la empresa obtenidas exitosamente',
            'data' => [
                'company_profile' =>
                    $this->formatCompanyProfileResponse(
                        $companyProfile,
                        true
                    ),
                'vacancies' => $vacancies,
            ],
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/public/vacancies/{id}",
     *     operationId="publicShowVacancy",
     *     tags={"Vacancies"},
     *     summary="Obtener vacante pública por ID",
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Vacante pública obtenida exitosamente"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Vacante pública no encontrada"
     *     )
     * )
     */
    public function publicShow(int $id)
    {
        $vacancy = Vacancy::query()
            ->with('companyProfile.user.roles')
            ->publiclyAvailable()
            ->whereKey($id)
            ->first();

        if (! $vacancy) {
            return response()->json([
                'success' => false,
                'message' => 'Vacante pública no encontrada.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Vacante pública obtenida exitosamente',
            'data' => [
                'vacancy' => $this->formatVacancyResponse(
                    $vacancy,
                    true
                ),
            ],
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Protected Endpoints
    |--------------------------------------------------------------------------
    */

    /**
     * @OA\Get(
     *     path="/api/vacancies",
     *     operationId="listVacancies",
     *     tags={"Vacancies"},
     *     summary="Listar vacantes para administración",
     *     description="El administrador consulta todas las vacantes. Una empresa consulta únicamente las vacantes de su perfil.",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         required=false,
     *         @OA\Schema(type="string", example="Laravel")
     *     ),
     *     @OA\Parameter(
     *         name="category",
     *         in="query",
     *         required=false,
     *         @OA\Schema(type="string", example="Desarrollo de software")
     *     ),
     *     @OA\Parameter(
     *         name="location",
     *         in="query",
     *         required=false,
     *         @OA\Schema(type="string", example="Pachuca")
     *     ),
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         required=false,
     *         @OA\Schema(
     *             type="string",
     *             enum={"open","paused","closed"}
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         required=false,
     *         @OA\Schema(type="integer", minimum=1, maximum=100, example=15)
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Vacantes obtenidas exitosamente"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Rol sin acceso al módulo"
     *     )
     * )
     */
    public function index(Request $request)
    {
        $authUser = auth('api')->user();

        if (! $authUser) {
            return response()->json([
                'success' => false,
                'message' => 'No autorizado.',
            ], 401);
        }

        if (! $authUser->hasAnyRole(['empresa', 'admin'])) {
            return response()->json([
                'success' => false,
                'message' => 'Solo empresas o administradores pueden administrar vacantes.',
            ], 403);
        }

        $request->validate([
            'search' => ['nullable', 'string', 'max:150'],
            'category' => ['nullable', 'string', 'max:100'],
            'location' => ['nullable', 'string', 'max:150'],
            'status' => [
                'nullable',
                'string',
                Rule::in(Vacancy::STATUSES),
            ],
            'min_salary' => [
                'nullable',
                'numeric',
                'min:0',
                'max:9999999999.99',
            ],
            'max_salary' => [
                'nullable',
                'numeric',
                'min:0',
                'max:9999999999.99',
                'gte:min_salary',
            ],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = Vacancy::query()
            ->with('companyProfile.user.roles');

        if (! $authUser->hasRole('admin')) {
            $query->whereHas(
                'companyProfile',
                function ($companyQuery) use ($authUser) {
                    $companyQuery->where(
                        'user_id',
                        $authUser->id
                    );
                }
            );
        }

        $this->applyFilters($query, $request);

        $paginator = $query
            ->latest('created_at')
            ->paginate((int) $request->input('per_page', 15));

        return response()->json([
            'success' => true,
            'message' => 'Vacantes obtenidas exitosamente',
            'data' => $this->paginationData(
                $paginator,
                false
            ),
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/vacancies/me",
     *     operationId="listMyVacancies",
     *     tags={"Vacancies"},
     *     summary="Obtener mis vacantes",
     *     description="Retorna las vacantes del perfil empresarial asociado al usuario autenticado.",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Response(
     *         response=200,
     *         description="Vacantes de la empresa obtenidas exitosamente"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="El usuario no tiene rol empresa"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Perfil empresarial no encontrado"
     *     )
     * )
     */
    public function myVacancies()
    {
        $authUser = auth('api')->user();

        if (! $authUser) {
            return response()->json([
                'success' => false,
                'message' => 'No autorizado.',
            ], 401);
        }

        if (! $authUser->hasRole('empresa')) {
            return response()->json([
                'success' => false,
                'message' => 'Solo los usuarios empresa tienen vacantes propias.',
            ], 403);
        }

        $companyProfile = CompanyProfile::query()
            ->with('user.roles')
            ->where('user_id', $authUser->id)
            ->first();

        if (! $companyProfile) {
            return response()->json([
                'success' => false,
                'message' => 'No se encontró un perfil empresarial para tu cuenta.',
            ], 404);
        }

        $vacancies = Vacancy::query()
            ->with('companyProfile.user.roles')
            ->where('company_id', $companyProfile->id)
            ->latest('created_at')
            ->get()
            ->map(
                fn (Vacancy $vacancy) =>
                    $this->formatVacancyResponse($vacancy)
            )
            ->values();

        return response()->json([
            'success' => true,
            'message' => 'Tus vacantes fueron obtenidas exitosamente',
            'data' => [
                'company_profile' =>
                    $this->formatCompanyProfileResponse(
                        $companyProfile
                    ),
                'vacancies' => $vacancies,
            ],
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/vacancies",
     *     operationId="createVacancy",
     *     tags={"Vacancies"},
     *     summary="Crear vacante",
     *     description="Una empresa crea una vacante para su propio perfil. El administrador puede indicar company_id.",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={
     *                 "title",
     *                 "description",
     *                 "category",
     *                 "location"
     *             },
     *
     *             @OA\Property(
     *                 property="company_id",
     *                 type="integer",
     *                 nullable=true,
     *                 example=1,
     *                 description="Solo debe enviarlo un administrador"
     *             ),
     *             @OA\Property(
     *                 property="title",
     *                 type="string",
     *                 example="Desarrollador Laravel"
     *             ),
     *             @OA\Property(
     *                 property="description",
     *                 type="string",
     *                 example="Buscamos desarrollador con experiencia en APIs REST."
     *             ),
     *             @OA\Property(
     *                 property="category",
     *                 type="string",
     *                 example="Desarrollo de software"
     *             ),
     *             @OA\Property(
     *                 property="location",
     *                 type="string",
     *                 example="Pachuca, Hidalgo"
     *             ),
     *             @OA\Property(
     *                 property="salary",
     *                 type="number",
     *                 format="float",
     *                 nullable=true,
     *                 example=18000.00
     *             ),
     *             @OA\Property(
     *                 property="status",
     *                 type="string",
     *                 enum={"open","paused"},
     *                 example="open"
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="Vacante creada exitosamente"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Sin permisos"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Datos inválidos o empresa no disponible"
     *     )
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

        if (! $authUser->hasAnyRole(['empresa', 'admin'])) {
            return response()->json([
                'success' => false,
                'message' => 'Solo empresas o administradores pueden publicar vacantes.',
            ], 403);
        }

        $validated = $request->validate([
            'company_id' => [
                'nullable',
                'integer',
                Rule::exists('company_profiles', 'id')
                    ->whereNull('deleted_at'),
            ],
            'title' => [
                'required',
                'string',
                'max:150',
            ],
            'description' => [
                'required',
                'string',
                'max:10000',
            ],
            'category' => [
                'required',
                'string',
                'max:100',
            ],
            'location' => [
                'required',
                'string',
                'max:150',
            ],
            'salary' => [
                'nullable',
                'numeric',
                'min:0',
                'max:9999999999.99',
            ],
            'status' => [
                'nullable',
                'string',
                Rule::in([
                    Vacancy::STATUS_OPEN,
                    Vacancy::STATUS_PAUSED,
                ]),
            ],
        ]);

        if ($authUser->hasRole('admin')) {
            if (empty($validated['company_id'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'El administrador debe indicar company_id.',
                    'errors' => [
                        'company_id' => [
                            'El campo company_id es obligatorio para administradores.',
                        ],
                    ],
                ], 422);
            }

            $companyProfile = $this->findValidCompanyProfile(
                (int) $validated['company_id']
            );
        } else {
            $companyProfile = CompanyProfile::query()
                ->with('user.roles')
                ->where('user_id', $authUser->id)
                ->fromActiveCompanies()
                ->first();
        }

        if (! $companyProfile) {
            return response()->json([
                'success' => false,
                'message' => 'No se encontró un perfil empresarial activo y válido.',
            ], 422);
        }

        $vacancy = DB::transaction(function () use (
            $validated,
            $companyProfile
        ) {
            $vacancy = Vacancy::create([
                'company_id' => $companyProfile->id,
                'title' => $validated['title'],
                'description' => $validated['description'],
                'category' => $validated['category'],
                'location' => $validated['location'],
                'salary' => $validated['salary'] ?? null,
                'status' =>
                    $validated['status']
                    ?? Vacancy::STATUS_OPEN,
            ]);

            ActivityLoggerService::logCreate(
                module: 'VACANCIES',
                entity: 'vacancies',
                entityId: $vacancy->id,
                description: "Vacancy {$vacancy->title} created"
            );

            return $vacancy;
        });

        $vacancy->load('companyProfile.user.roles');

        return response()->json([
            'success' => true,
            'message' => 'Vacante creada exitosamente',
            'data' => [
                'vacancy' => $this->formatVacancyResponse(
                    $vacancy
                ),
            ],
        ], 201);
    }

    /**
     * @OA\Get(
     *     path="/api/vacancies/{id}",
     *     operationId="showVacancy",
     *     tags={"Vacancies"},
     *     summary="Obtener vacante privada por ID",
     *     description="Solo puede consultarla la empresa propietaria o un administrador.",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Vacante obtenida exitosamente"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Sin permisos"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Vacante no encontrada"
     *     )
     * )
     */
    public function show(int $id)
    {
        $authUser = auth('api')->user();

        if (! $authUser) {
            return response()->json([
                'success' => false,
                'message' => 'No autorizado.',
            ], 401);
        }

        $vacancy = Vacancy::with(
            'companyProfile.user.roles'
        )->find($id);

        if (! $vacancy) {
            return response()->json([
                'success' => false,
                'message' => 'Vacante no encontrada.',
            ], 404);
        }

        if (! $this->canManageVacancy($vacancy, $authUser)) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permisos para consultar esta vacante.',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'message' => 'Vacante obtenida exitosamente',
            'data' => [
                'vacancy' => $this->formatVacancyResponse(
                    $vacancy
                ),
            ],
        ]);
    }

    /**
     * @OA\Patch(
     *     path="/api/vacancies/{id}",
     *     operationId="updateVacancy",
     *     tags={"Vacancies"},
     *     summary="Actualizar vacante",
     *     description="La empresa propietaria o el administrador pueden modificar una vacante abierta o pausada. Una vacante cerrada ya no puede modificarse ni reabrirse.",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="title",
     *                 type="string",
     *                 example="Desarrollador Laravel Senior"
     *             ),
     *             @OA\Property(
     *                 property="description",
     *                 type="string",
     *                 example="Descripción actualizada de la vacante."
     *             ),
     *             @OA\Property(
     *                 property="category",
     *                 type="string",
     *                 example="Desarrollo de software"
     *             ),
     *             @OA\Property(
     *                 property="location",
     *                 type="string",
     *                 example="Modalidad remota"
     *             ),
     *             @OA\Property(
     *                 property="salary",
     *                 type="number",
     *                 format="float",
     *                 nullable=true,
     *                 example=22000.00
     *             ),
     *             @OA\Property(
     *                 property="status",
     *                 type="string",
     *                 enum={"open","paused","closed"},
     *                 example="paused"
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Vacante actualizada correctamente"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Sin permisos"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Vacante no encontrada"
     *     ),
     *     @OA\Response(
     *         response=409,
     *         description="La vacante ya está cerrada"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error de validación"
     *     )
     * )
     */
    public function update(Request $request, int $id)
    {
        $authUser = auth('api')->user();

        if (! $authUser) {
            return response()->json([
                'success' => false,
                'message' => 'No autorizado.',
            ], 401);
        }

        $vacancy = Vacancy::with(
            'companyProfile.user.roles'
        )->find($id);

        if (! $vacancy) {
            return response()->json([
                'success' => false,
                'message' => 'Vacante no encontrada.',
            ], 404);
        }

        if (! $this->canManageVacancy($vacancy, $authUser)) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permisos para actualizar esta vacante.',
            ], 403);
        }

        if ($vacancy->status === Vacancy::STATUS_CLOSED) {
            return response()->json([
                'success' => false,
                'message' => 'La vacante está cerrada y ya no puede modificarse ni reabrirse.',
            ], 409);
        }

        $validated = $request->validate([
            'title' => [
                'sometimes',
                'required',
                'string',
                'max:150',
            ],
            'description' => [
                'sometimes',
                'required',
                'string',
                'max:10000',
            ],
            'category' => [
                'sometimes',
                'required',
                'string',
                'max:100',
            ],
            'location' => [
                'sometimes',
                'required',
                'string',
                'max:150',
            ],
            'salary' => [
                'sometimes',
                'nullable',
                'numeric',
                'min:0',
                'max:9999999999.99',
            ],
            'status' => [
                'sometimes',
                'required',
                'string',
                Rule::in(Vacancy::STATUSES),
            ],
        ]);

        if (empty($validated)) {
            return response()->json([
                'success' => false,
                'message' => 'Debes enviar al menos un campo para actualizar.',
            ], 422);
        }

        DB::transaction(function () use (
            $vacancy,
            $validated
        ) {
            $vacancy->update($validated);

            ActivityLoggerService::logUpdate(
                module: 'VACANCIES',
                entity: 'vacancies',
                entityId: $vacancy->id,
                description: "Vacancy {$vacancy->title} updated"
            );
        });

        $vacancy->refresh();
        $vacancy->load('companyProfile.user.roles');

        return response()->json([
            'success' => true,
            'message' => 'Vacante actualizada correctamente',
            'data' => [
                'vacancy' => $this->formatVacancyResponse(
                    $vacancy
                ),
            ],
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/vacancies/{id}",
     *     operationId="deleteVacancy",
     *     tags={"Vacancies"},
     *     summary="Eliminar vacante",
     *     description="Realiza borrado lógico. Solo la empresa propietaria o un administrador pueden eliminarla.",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Vacante eliminada correctamente"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Sin permisos"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Vacante no encontrada"
     *     )
     * )
     */
    public function destroy(int $id)
    {
        $authUser = auth('api')->user();

        if (! $authUser) {
            return response()->json([
                'success' => false,
                'message' => 'No autorizado.',
            ], 401);
        }

        $vacancy = Vacancy::with(
            'companyProfile'
        )->find($id);

        if (! $vacancy) {
            return response()->json([
                'success' => false,
                'message' => 'Vacante no encontrada.',
            ], 404);
        }

        if (! $this->canManageVacancy($vacancy, $authUser)) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permisos para eliminar esta vacante.',
            ], 403);
        }

        DB::transaction(function () use ($vacancy) {
            ActivityLoggerService::logDelete(
                module: 'VACANCIES',
                entity: 'vacancies',
                entityId: $vacancy->id,
                description: "Vacancy {$vacancy->title} deleted"
            );

            $vacancy->delete();
        });

        return response()->json([
            'success' => true,
            'message' => 'Vacante eliminada correctamente.',
        ]);
    }
}