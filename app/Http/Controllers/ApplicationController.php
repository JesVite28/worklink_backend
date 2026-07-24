<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\CompanyProfile;
use App\Models\FreelancerProfile;
use App\Models\User;
use App\Models\Vacancy;
use App\Services\ActivityLoggerService;
use App\Services\NotificationService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * @OA\Tag(
 *     name="Applications",
 *     description="Endpoints para la gestión de postulaciones a vacantes"
 * )
 */
class ApplicationController extends Controller
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

    private function getPreferredRole(
        User $user,
        ?string $preferredRole = null
    ) {
        $user->loadMissing('roles');

        if ($preferredRole) {
            return $user->roles->firstWhere(
                'name',
                $preferredRole
            ) ?? $user->roles->first();
        }

        return $user->roles->first();
    }

    private function buildPublicStorageUrl(
        ?string $path
    ): ?string {
        if (! $path) {
            return null;
        }

        if (filter_var($path, FILTER_VALIDATE_URL)) {
            return $path;
        }

        return asset(Storage::url($path));
    }

    private function formatUserResponse(
        ?User $user,
        ?string $preferredRole = null
    ): ?array {
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
            'profile_photo_url' =>
                $this->buildPublicStorageUrl(
                    $user->profile_photo
                ),
            'is_active' => $user->is_active,
            'role' => $this->formatRoleResponse(
                $this->getPreferredRole(
                    $user,
                    $preferredRole
                )
            ),
        ];
    }

    private function formatCompanyProfileResponse(
        ?CompanyProfile $profile
    ): ?array {
        if (! $profile) {
            return null;
        }

        $profile->loadMissing('user.roles');

        return [
            'id' => $profile->id,
            'user_id' => $profile->user_id,
            'user' => $this->formatUserResponse(
                $profile->user,
                'empresa'
            ),
            'company_name' => $profile->company_name,
            'description' => $profile->description,
            'industry' => $profile->industry,
            'location' => $profile->location,
            'average_rate' => $profile->average_rate,
        ];
    }

    private function formatVacancyResponse(
        ?Vacancy $vacancy
    ): ?array {
        if (! $vacancy) {
            return null;
        }

        $vacancy->loadMissing(
            'companyProfile.user.roles'
        );

        return [
            'id' => $vacancy->id,
            'company_id' => $vacancy->company_id,
            'company_profile' =>
                $this->formatCompanyProfileResponse(
                    $vacancy->companyProfile
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

    private function formatFreelancerProfileResponse(
        ?FreelancerProfile $profile
    ): ?array {
        if (! $profile) {
            return null;
        }

        $profile->loadMissing('user.roles');

        return [
            'id' => $profile->id,
            'user_id' => $profile->user_id,
            'user' => $this->formatUserResponse(
                $profile->user,
                'freelancer'
            ),
            'description' => $profile->description,
            'specialty' => $profile->specialty,
            'location' => $profile->location,
            'service_area' => $profile->service_area,
            'work_mode' => $profile->work_mode,
            'experience' => $profile->experience,
            'rate_type' => $profile->rate_type,
            'rate' => $profile->rate,
            'languages' => $profile->languages ?? [],
            'professional_links' => [
                'website' => $profile->website,
                'facebook' => $profile->facebook,
                'instagram' => $profile->instagram,
                'linkedin' => $profile->linkedin,
                'github' => $profile->github,
                'portfolio_url' => $profile->portfolio_url,
            ],
            'available' => $profile->available,
            'average_rate' => $profile->average_rate,
        ];
    }

    private function formatApplicationResponse(
        Application $application
    ): array {
        $application->loadMissing([
            'vacancy.companyProfile.user.roles',
            'freelancerProfile.user.roles',
        ]);

        return [
            'id' => $application->id,
            'vacancy_id' => $application->vacancy_id,
            'vacancy' => $this->formatVacancyResponse(
                $application->vacancy
            ),
            'freelancer_id' =>
                $application->freelancer_id,
            'freelancer_profile' =>
                $this->formatFreelancerProfileResponse(
                    $application->freelancerProfile
                ),
            'message' => $application->message,
            'status' => $application->status,
            'created_at' => $application->created_at,
            'updated_at' => $application->updated_at,
        ];
    }

    private function isFreelancerOwner(
        Application $application,
        User $user
    ): bool {
        $application->loadMissing('freelancerProfile');

        return $application->freelancerProfile
            && $application->freelancerProfile->user_id
                === $user->id;
    }

    private function isCompanyOwner(
        Application $application,
        User $user
    ): bool {
        $application->loadMissing(
            'vacancy.companyProfile'
        );

        return $application->vacancy
            && $application->vacancy->companyProfile
            && $application->vacancy
                ->companyProfile
                ->user_id === $user->id;
    }

    private function canViewApplication(
        Application $application,
        User $user
    ): bool {
        return $user->hasRole('admin')
            || $this->isFreelancerOwner(
                $application,
                $user
            )
            || $this->isCompanyOwner(
                $application,
                $user
            );
    }

    private function canManageVacancyApplications(
        Vacancy $vacancy,
        User $user
    ): bool {
        $vacancy->loadMissing('companyProfile');

        return $user->hasRole('admin')
            || (
                $vacancy->companyProfile
                && $vacancy->companyProfile->user_id
                    === $user->id
            );
    }

    private function applyFilters(
        $query,
        Request $request
    ) {
        if ($request->filled('status')) {
            $query->where(
                'status',
                $request->input('status')
            );
        }

        if ($request->filled('vacancy_id')) {
            $query->where(
                'vacancy_id',
                $request->input('vacancy_id')
            );
        }

        if ($request->filled('freelancer_id')) {
            $query->where(
                'freelancer_id',
                $request->input('freelancer_id')
            );
        }

        if ($request->filled('search')) {
            $search = trim(
                (string) $request->input('search')
            );

            $query->where(
                function ($searchQuery) use ($search) {
                    $searchQuery
                        ->where(
                            'message',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhereHas(
                            'vacancy',
                            function ($vacancyQuery) use (
                                $search
                            ) {
                                $vacancyQuery
                                    ->where(
                                        'title',
                                        'like',
                                        "%{$search}%"
                                    )
                                    ->orWhere(
                                        'category',
                                        'like',
                                        "%{$search}%"
                                    );
                            }
                        )
                        ->orWhereHas(
                            'freelancerProfile.user',
                            function ($userQuery) use (
                                $search
                            ) {
                                $userQuery
                                    ->where(
                                        'name',
                                        'like',
                                        "%{$search}%"
                                    )
                                    ->orWhere(
                                        'last_name',
                                        'like',
                                        "%{$search}%"
                                    )
                                    ->orWhere(
                                        'maternal_last_name',
                                        'like',
                                        "%{$search}%"
                                    );
                            }
                        );
                }
            );
        }

        return $query;
    }

    private function paginationData(
        $paginator
    ): array {
        return [
            'applications' => collect(
                $paginator->items()
            )
                ->map(
                    fn (Application $application) =>
                        $this->formatApplicationResponse(
                            $application
                        )
                )
                ->values(),
            'pagination' => [
                'current_page' =>
                    $paginator->currentPage(),
                'last_page' =>
                    $paginator->lastPage(),
                'per_page' =>
                    $paginator->perPage(),
                'total' =>
                    $paginator->total(),
                'from' =>
                    $paginator->firstItem(),
                'to' =>
                    $paginator->lastItem(),
            ],
        ];
    }

    private function validateIndexFilters(
        Request $request
    ): void {
        $request->validate([
            'status' => [
                'nullable',
                'string',
                Rule::in(Application::STATUSES),
            ],
            'vacancy_id' => [
                'nullable',
                'integer',
                Rule::exists('vacancies', 'id')
                    ->whereNull('deleted_at'),
            ],
            'freelancer_id' => [
                'nullable',
                'integer',
                Rule::exists(
                    'freelancer_profiles',
                    'id'
                )->whereNull('deleted_at'),
            ],
            'search' => [
                'nullable',
                'string',
                'max:150',
            ],
            'per_page' => [
                'nullable',
                'integer',
                'min:1',
                'max:100',
            ],
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/applications",
     *     operationId="listApplications",
     *     tags={"Applications"},
     *     summary="Listar postulaciones",
     *     description="El administrador consulta todas; el freelancer consulta las propias; la empresa consulta las recibidas en sus vacantes.",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         required=false,
     *         @OA\Schema(
     *             type="string",
     *             enum={"pending","accepted","rejected"}
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="vacancy_id",
     *         in="query",
     *         required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="freelancer_id",
     *         in="query",
     *         required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         required=false,
     *         @OA\Schema(type="string", example="Laravel")
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         required=false,
     *         @OA\Schema(
     *             type="integer",
     *             minimum=1,
     *             maximum=100,
     *             example=15
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Postulaciones obtenidas exitosamente"
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

        $isAdmin = $authUser->hasRole('admin');
        $isFreelancer =
            $authUser->hasRole('freelancer');
        $isCompany = $authUser->hasRole('empresa');

        if (
            ! $isAdmin
            && ! $isFreelancer
            && ! $isCompany
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Tu tipo de cuenta no tiene acceso a las postulaciones.',
            ], 403);
        }

        $this->validateIndexFilters($request);

        $query = Application::query()->with([
            'vacancy.companyProfile.user.roles',
            'freelancerProfile.user.roles',
        ]);

        if (! $isAdmin) {
            $query->where(
                function ($roleQuery) use (
                    $authUser,
                    $isFreelancer,
                    $isCompany
                ) {
                    if ($isFreelancer) {
                        $roleQuery->whereHas(
                            'freelancerProfile',
                            function ($profileQuery) use (
                                $authUser
                            ) {
                                $profileQuery->where(
                                    'user_id',
                                    $authUser->id
                                );
                            }
                        );
                    }

                    if ($isCompany) {
                        $method = $isFreelancer
                            ? 'orWhereHas'
                            : 'whereHas';

                        $roleQuery->{$method}(
                            'vacancy.companyProfile',
                            function ($companyQuery) use (
                                $authUser
                            ) {
                                $companyQuery->where(
                                    'user_id',
                                    $authUser->id
                                );
                            }
                        );
                    }
                }
            );
        }

        $this->applyFilters($query, $request);

        $paginator = $query
            ->latest('created_at')
            ->paginate(
                (int) $request->input(
                    'per_page',
                    15
                )
            );

        return response()->json([
            'success' => true,
            'message' => 'Postulaciones obtenidas exitosamente',
            'data' => $this->paginationData(
                $paginator
            ),
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/applications/me",
     *     operationId="listMyApplications",
     *     tags={"Applications"},
     *     summary="Obtener mis postulaciones",
     *     description="Obtiene las postulaciones realizadas por el freelancer autenticado.",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Response(
     *         response=200,
     *         description="Postulaciones obtenidas exitosamente"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="El usuario no tiene rol freelancer"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Perfil freelancer no encontrado"
     *     )
     * )
     */
    public function myApplications(
        Request $request
    ) {
        $authUser = auth('api')->user();

        if (! $authUser) {
            return response()->json([
                'success' => false,
                'message' => 'No autorizado.',
            ], 401);
        }

        if (! $authUser->hasRole('freelancer')) {
            return response()->json([
                'success' => false,
                'message' => 'Solo los freelancers tienen postulaciones propias.',
            ], 403);
        }

        $this->validateIndexFilters($request);

        $profile = FreelancerProfile::query()
            ->with('user.roles')
            ->where('user_id', $authUser->id)
            ->first();

        if (! $profile) {
            return response()->json([
                'success' => false,
                'message' => 'No se encontró un perfil freelancer para tu cuenta.',
            ], 404);
        }

        $query = Application::query()
            ->with([
                'vacancy.companyProfile.user.roles',
                'freelancerProfile.user.roles',
            ])
            ->where(
                'freelancer_id',
                $profile->id
            );

        $this->applyFilters($query, $request);

        $paginator = $query
            ->latest('created_at')
            ->paginate(
                (int) $request->input(
                    'per_page',
                    15
                )
            );

        return response()->json([
            'success' => true,
            'message' => 'Tus postulaciones fueron obtenidas exitosamente',
            'data' => $this->paginationData(
                $paginator
            ),
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/applications/vacancy/{vacancyId}",
     *     operationId="applicationsByVacancy",
     *     tags={"Applications"},
     *     summary="Obtener postulaciones de una vacante",
     *     description="Solo puede consultarlas la empresa propietaria de la vacante o un administrador.",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="vacancyId",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         required=false,
     *         @OA\Schema(
     *             type="string",
     *             enum={"pending","accepted","rejected"}
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Postulaciones de la vacante obtenidas exitosamente"
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
    public function byVacancy(
        Request $request,
        int $vacancyId
    ) {
        $authUser = auth('api')->user();

        if (! $authUser) {
            return response()->json([
                'success' => false,
                'message' => 'No autorizado.',
            ], 401);
        }

        $request->validate([
            'status' => [
                'nullable',
                'string',
                Rule::in(Application::STATUSES),
            ],
            'search' => [
                'nullable',
                'string',
                'max:150',
            ],
            'per_page' => [
                'nullable',
                'integer',
                'min:1',
                'max:100',
            ],
        ]);

        $vacancy = Vacancy::with(
            'companyProfile.user.roles'
        )->find($vacancyId);

        if (! $vacancy) {
            return response()->json([
                'success' => false,
                'message' => 'Vacante no encontrada.',
            ], 404);
        }

        if (
            ! $this->canManageVacancyApplications(
                $vacancy,
                $authUser
            )
        ) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permisos para consultar las postulaciones de esta vacante.',
            ], 403);
        }

        $query = Application::query()
            ->with([
                'vacancy.companyProfile.user.roles',
                'freelancerProfile.user.roles',
            ])
            ->where(
                'vacancy_id',
                $vacancy->id
            );

        $this->applyFilters($query, $request);

        $paginator = $query
            ->latest('created_at')
            ->paginate(
                (int) $request->input(
                    'per_page',
                    15
                )
            );

        return response()->json([
            'success' => true,
            'message' => 'Postulaciones de la vacante obtenidas exitosamente',
            'data' => [
                'vacancy' =>
                    $this->formatVacancyResponse(
                        $vacancy
                    ),
                ...$this->paginationData(
                    $paginator
                ),
            ],
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/applications",
     *     operationId="createApplication",
     *     tags={"Applications"},
     *     summary="Postularse a una vacante",
     *     description="El freelancer autenticado se postula a una vacante abierta. freelancer_id y status son determinados por el backend.",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"vacancy_id"},
     *
     *             @OA\Property(
     *                 property="vacancy_id",
     *                 type="integer",
     *                 example=1
     *             ),
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 nullable=true,
     *                 example="Me interesa la vacante. Tengo experiencia desarrollando APIs REST con Laravel."
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="Postulación creada exitosamente"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Solo los freelancers pueden postularse"
     *     ),
     *     @OA\Response(
     *         response=409,
     *         description="El freelancer ya se postuló a la vacante"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Vacante no disponible o datos inválidos"
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

        if (
            ! $authUser->is_active
            || ! $authUser->hasRole('freelancer')
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Solo los freelancers activos pueden postularse a vacantes.',
            ], 403);
        }

        $validated = $request->validate([
            'vacancy_id' => [
                'required',
                'integer',
                Rule::exists('vacancies', 'id')
                    ->whereNull('deleted_at'),
            ],
            'message' => [
                'nullable',
                'string',
                'max:5000',
            ],
        ]);

        $profile = FreelancerProfile::query()
            ->with('user.roles')
            ->where('user_id', $authUser->id)
            ->first();

        if (
            ! $profile
            || ! $profile->user
            || ! $profile->user->is_active
            || ! $profile->user->hasRole('freelancer')
        ) {
            return response()->json([
                'success' => false,
                'message' => 'No se encontró un perfil freelancer activo y válido.',
            ], 422);
        }

        try {
            $application = DB::transaction(
                function () use (
                    $validated,
                    $profile,
                    $authUser
                ) {
                    $vacancy = Vacancy::query()
                        ->with(
                            'companyProfile.user.roles'
                        )
                        ->lockForUpdate()
                        ->find(
                            $validated['vacancy_id']
                        );

                    if (
                        ! $vacancy
                        || $vacancy->status
                            !== Vacancy::STATUS_OPEN
                    ) {
                        throw new \RuntimeException(
                            'VACANCY_NOT_OPEN'
                        );
                    }

                    $companyProfile =
                        $vacancy->companyProfile;

                    if (
                        ! $companyProfile
                        || ! $companyProfile->user
                        || ! $companyProfile->user
                            ->is_active
                        || ! $companyProfile->user
                            ->hasRole('empresa')
                    ) {
                        throw new \RuntimeException(
                            'COMPANY_NOT_AVAILABLE'
                        );
                    }

                    if (
                        $companyProfile->user_id
                            === $authUser->id
                    ) {
                        throw new \RuntimeException(
                            'OWN_COMPANY_VACANCY'
                        );
                    }

                    $alreadyApplied =
                        Application::withTrashed()
                            ->where(
                                'vacancy_id',
                                $vacancy->id
                            )
                            ->where(
                                'freelancer_id',
                                $profile->id
                            )
                            ->exists();

                    if ($alreadyApplied) {
                        throw new \RuntimeException(
                            'APPLICATION_ALREADY_EXISTS'
                        );
                    }

                    $application =
                        Application::create([
                            'vacancy_id' =>
                                $vacancy->id,
                            'freelancer_id' =>
                                $profile->id,
                            'message' =>
                                $validated['message']
                                ?? null,
                            'status' =>
                                Application::STATUS_PENDING,
                        ]);

                    ActivityLoggerService::logCreate(
                        module: 'APPLICATIONS',
                        entity: 'applications',
                        entityId: $application->id,
                        description: "Application created for vacancy ID {$vacancy->id}"
                    );

                    $freelancerName = trim(
                        $profile->user->name
                        . ' '
                        . $profile->user->last_name
                    );

                    NotificationService::applicationReceived(
                        $companyProfile->user_id,
                        $freelancerName,
                        $vacancy->title
                    );

                    return $application;
                }
            );
        } catch (\RuntimeException $exception) {
            return match ($exception->getMessage()) {
                'VACANCY_NOT_OPEN' => response()->json([
                    'success' => false,
                    'message' => 'La vacante no está abierta para recibir postulaciones.',
                ], 422),

                'COMPANY_NOT_AVAILABLE' => response()->json([
                    'success' => false,
                    'message' => 'La empresa propietaria de la vacante no está disponible.',
                ], 422),

                'OWN_COMPANY_VACANCY' => response()->json([
                    'success' => false,
                    'message' => 'No puedes postularte a una vacante de tu propia empresa.',
                ], 422),

                'APPLICATION_ALREADY_EXISTS' => response()->json([
                    'success' => false,
                    'message' => 'Ya existe una postulación para esta vacante.',
                ], 409),

                default => throw $exception,
            };
        } catch (QueryException $exception) {
            if (
                (string) $exception->getCode()
                    === '23000'
            ) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ya existe una postulación para esta vacante.',
                ], 409);
            }

            throw $exception;
        }

        $application->load([
            'vacancy.companyProfile.user.roles',
            'freelancerProfile.user.roles',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Postulación creada exitosamente',
            'data' => [
                'application' =>
                    $this->formatApplicationResponse(
                        $application
                    ),
            ],
        ], 201);
    }

    /**
     * @OA\Get(
     *     path="/api/applications/{id}",
     *     operationId="showApplication",
     *     tags={"Applications"},
     *     summary="Obtener postulación por ID",
     *     description="Puede consultarla el freelancer propietario, la empresa de la vacante o un administrador.",
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
     *         description="Postulación obtenida exitosamente"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Sin permisos"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Postulación no encontrada"
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

        $application = Application::with([
            'vacancy.companyProfile.user.roles',
            'freelancerProfile.user.roles',
        ])->find($id);

        if (! $application) {
            return response()->json([
                'success' => false,
                'message' => 'Postulación no encontrada.',
            ], 404);
        }

        if (
            ! $this->canViewApplication(
                $application,
                $authUser
            )
        ) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permisos para consultar esta postulación.',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'message' => 'Postulación obtenida exitosamente',
            'data' => [
                'application' =>
                    $this->formatApplicationResponse(
                        $application
                    ),
            ],
        ]);
    }

    /**
     * @OA\Patch(
     *     path="/api/applications/{id}",
     *     operationId="updateApplication",
     *     tags={"Applications"},
     *     summary="Actualizar postulación",
     *     description="Mientras esté pendiente, el freelancer puede editar su mensaje; la empresa puede aceptar o rechazar; el administrador puede realizar ambas acciones.",
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
     *                 property="message",
     *                 type="string",
     *                 nullable=true,
     *                 example="Mensaje de postulación actualizado."
     *             ),
     *             @OA\Property(
     *                 property="status",
     *                 type="string",
     *                 enum={"accepted","rejected"},
     *                 example="accepted"
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Postulación actualizada correctamente"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Sin permisos para realizar la acción"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Postulación no encontrada"
     *     ),
     *     @OA\Response(
     *         response=409,
     *         description="La postulación ya fue finalizada"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error de validación"
     *     )
     * )
     */
    public function update(
        Request $request,
        int $id
    ) {
        $authUser = auth('api')->user();

        if (! $authUser) {
            return response()->json([
                'success' => false,
                'message' => 'No autorizado.',
            ], 401);
        }

        $application = Application::with([
            'vacancy.companyProfile.user.roles',
            'freelancerProfile.user.roles',
        ])->find($id);

        if (! $application) {
            return response()->json([
                'success' => false,
                'message' => 'Postulación no encontrada.',
            ], 404);
        }

        if (
            ! $this->canViewApplication(
                $application,
                $authUser
            )
        ) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permisos para actualizar esta postulación.',
            ], 403);
        }

        if (
            $application->status
                !== Application::STATUS_PENDING
        ) {
            return response()->json([
                'success' => false,
                'message' => 'La postulación ya fue aceptada o rechazada y no puede modificarse.',
                'data' => [
                    'current_status' =>
                        $application->status,
                ],
            ], 409);
        }

        $validated = $request->validate([
            'message' => [
                'sometimes',
                'nullable',
                'string',
                'max:5000',
            ],
            'status' => [
                'sometimes',
                'required',
                'string',
                Rule::in([
                    Application::STATUS_ACCEPTED,
                    Application::STATUS_REJECTED,
                ]),
            ],
        ]);

        if (empty($validated)) {
            return response()->json([
                'success' => false,
                'message' => 'Debes enviar al menos un campo para actualizar.',
            ], 422);
        }

        $isAdmin = $authUser->hasRole('admin');
        $isFreelancerOwner =
            $this->isFreelancerOwner(
                $application,
                $authUser
            );
        $isCompanyOwner =
            $this->isCompanyOwner(
                $application,
                $authUser
            );

        if (! $isAdmin && $isFreelancerOwner) {
            if (array_key_exists('status', $validated)) {
                return response()->json([
                    'success' => false,
                    'message' => 'El freelancer no puede aceptar ni rechazar su propia postulación.',
                ], 403);
            }
        } elseif (! $isAdmin && $isCompanyOwner) {
            if (array_key_exists('message', $validated)) {
                return response()->json([
                    'success' => false,
                    'message' => 'La empresa no puede modificar el mensaje del freelancer.',
                ], 403);
            }

            if (! array_key_exists('status', $validated)) {
                return response()->json([
                    'success' => false,
                    'message' => 'La empresa debe indicar si acepta o rechaza la postulación.',
                ], 422);
            }
        } elseif (! $isAdmin) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permisos para actualizar esta postulación.',
            ], 403);
        }

        DB::transaction(function () use (
            $application,
            $validated
        ) {
            $application->update($validated);

            ActivityLoggerService::logUpdate(
                module: 'APPLICATIONS',
                entity: 'applications',
                entityId: $application->id,
                description: "Application ID {$application->id} updated"
            );

            if (
                isset($validated['status'])
                && in_array(
                    $validated['status'],
                    [
                        Application::STATUS_ACCEPTED,
                        Application::STATUS_REJECTED,
                    ],
                    true
                )
            ) {
                $application->loadMissing([
                    'vacancy',
                    'freelancerProfile.user',
                ]);

                NotificationService::applicationStatus(
                    $application->freelancerProfile->user_id,
                    $application->vacancy->title,
                    $validated['status']
                );
            }
        });

        $application->refresh();
        $application->load([
            'vacancy.companyProfile.user.roles',
            'freelancerProfile.user.roles',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Postulación actualizada correctamente',
            'data' => [
                'application' =>
                    $this->formatApplicationResponse(
                        $application
                    ),
            ],
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/applications/{id}",
     *     operationId="deleteApplication",
     *     tags={"Applications"},
     *     summary="Eliminar postulación",
     *     description="El freelancer propietario puede retirar una postulación pendiente. El administrador puede eliminar cualquier postulación.",
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
     *         description="Postulación eliminada correctamente"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Sin permisos"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Postulación no encontrada"
     *     ),
     *     @OA\Response(
     *         response=409,
     *         description="La postulación no puede retirarse en su estado actual"
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

        $application = Application::with([
            'vacancy.companyProfile',
            'freelancerProfile',
        ])->find($id);

        if (! $application) {
            return response()->json([
                'success' => false,
                'message' => 'Postulación no encontrada.',
            ], 404);
        }

        $isAdmin = $authUser->hasRole('admin');
        $isFreelancerOwner =
            $this->isFreelancerOwner(
                $application,
                $authUser
            );

        if (! $isAdmin && ! $isFreelancerOwner) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permisos para eliminar esta postulación.',
            ], 403);
        }

        if (
            ! $isAdmin
            && $application->status
                !== Application::STATUS_PENDING
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Solo puedes retirar una postulación pendiente.',
                'data' => [
                    'current_status' =>
                        $application->status,
                ],
            ], 409);
        }

        DB::transaction(function () use (
            $application
        ) {
            ActivityLoggerService::logDelete(
                module: 'APPLICATIONS',
                entity: 'applications',
                entityId: $application->id,
                description: "Application ID {$application->id} deleted"
            );

            $application->delete();
        });

        return response()->json([
            'success' => true,
            'message' => 'Postulación eliminada correctamente.',
        ]);
    }
}