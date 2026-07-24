<?php

namespace App\Http\Controllers;

use App\Models\ContractRequest;
use App\Models\FreelancerProfile;
use App\Models\Service;
use App\Models\User;
use App\Services\ActivityLoggerService;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * @OA\Tag(
 *     name="Contract Requests",
 *     description="Endpoints para la gestión de solicitudes de contratación"
 * )
 */
class ContractRequestController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Formateadores
    |--------------------------------------------------------------------------
    */

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

        $user->loadMissing('roles');

        $role = $preferredRole
            ? $user->roles->firstWhere(
                'name',
                $preferredRole
            )
            : null;

        $role ??= $user->roles->first();

        return [
            'id' => $user->id,
            'name' => $user->name,
            'last_name' => $user->last_name,
            'maternal_last_name' =>
            $user->maternal_last_name,
            'email' => $user->email,
            'phone' => $user->phone,
            'profile_photo' =>
            $user->profile_photo,
            'profile_photo_url' =>
            $this->buildPublicStorageUrl(
                $user->profile_photo
            ),
            'is_active' => $user->is_active,
            'role' =>
            $this->formatRoleResponse(
                $role
            ),
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

            'user' =>
            $this->formatUserResponse(
                $profile->user,
                'freelancer'
            ),

            'description' =>
            $profile->description,

            'specialty' =>
            $profile->specialty,

            'location' =>
            $profile->location,

            'service_area' =>
            $profile->service_area,

            'work_mode' =>
            $profile->work_mode,

            'experience' =>
            $profile->experience,

            'rate_type' =>
            $profile->rate_type,

            'rate' =>
            $profile->rate,

            'languages' =>
            $profile->languages ?? [],

            'professional_links' => [
                'website' =>
                $profile->website,

                'facebook' =>
                $profile->facebook,

                'instagram' =>
                $profile->instagram,

                'linkedin' =>
                $profile->linkedin,

                'github' =>
                $profile->github,

                'portfolio_url' =>
                $profile->portfolio_url,
            ],

            'available' =>
            $profile->available,

            'average_rate' =>
            $profile->average_rate,
        ];
    }

    private function formatServiceResponse(
        ?Service $service
    ): ?array {
        if (! $service) {
            return null;
        }

        return [
            'id' => $service->id,
            'freelancer_id' =>
            $service->freelancer_id,
            'title' => $service->title,
            'description' =>
            $service->description,
            'price' => $service->price,
            'category' => $service->category,
            'location' => $service->location,
            'is_active' =>
            $service->is_active,
            'created_at' =>
            $service->created_at,
            'updated_at' =>
            $service->updated_at,
        ];
    }

    private function formatContractRequestResponse(
        ContractRequest $contractRequest
    ): array {
        $contractRequest->loadMissing([
            'client.roles',
            'freelancer.user.roles',
            'service',
        ]);

        return [
            'id' => $contractRequest->id,

            'client_id' =>
            $contractRequest->client_id,

            'client' =>
            $this->formatUserResponse(
                $contractRequest->client
            ),

            'freelancer_id' =>
            $contractRequest->freelancer_id,

            'freelancer_profile' =>
            $this->formatFreelancerProfileResponse(
                $contractRequest->freelancer
            ),

            'service_id' =>
            $contractRequest->service_id,

            'service' =>
            $this->formatServiceResponse(
                $contractRequest->service
            ),

            'description' =>
            $contractRequest->description,

            'budget' =>
            $contractRequest->budget,

            'status' =>
            $contractRequest->status,

            'created_at' =>
            $contractRequest->created_at,

            'updated_at' =>
            $contractRequest->updated_at,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Permisos
    |--------------------------------------------------------------------------
    */

    private function isClientOwner(
        ContractRequest $contractRequest,
        User $user
    ): bool {
        return $contractRequest->client_id
            === $user->id;
    }

    private function isFreelancerOwner(
        ContractRequest $contractRequest,
        User $user
    ): bool {
        $contractRequest->loadMissing(
            'freelancer'
        );

        return $contractRequest->freelancer
            && $contractRequest
            ->freelancer
            ->user_id === $user->id;
    }

    private function canViewContractRequest(
        ContractRequest $contractRequest,
        User $user
    ): bool {
        return $user->hasRole('admin')
            || $this->isClientOwner(
                $contractRequest,
                $user
            )
            || $this->isFreelancerOwner(
                $contractRequest,
                $user
            );
    }

    private function canDeleteContractRequest(
        ContractRequest $contractRequest,
        User $user
    ): bool {
        return $user->hasRole('admin')
            || $this->isClientOwner(
                $contractRequest,
                $user
            );
    }

    /*
    |--------------------------------------------------------------------------
    | Filtros
    |--------------------------------------------------------------------------
    */

    private function validateIndexFilters(
        Request $request
    ): void {
        $request->validate([
            'status' => [
                'nullable',
                'string',
                Rule::in(
                    ContractRequest::STATUSES
                ),
            ],

            'service_id' => [
                'nullable',
                'integer',
                Rule::exists(
                    'services',
                    'id'
                )->whereNull('deleted_at'),
            ],

            'freelancer_id' => [
                'nullable',
                'integer',
                Rule::exists(
                    'freelancer_profiles',
                    'id'
                )->whereNull('deleted_at'),
            ],

            'client_id' => [
                'nullable',
                'integer',
                Rule::exists(
                    'users',
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

        if ($request->filled('service_id')) {
            $query->where(
                'service_id',
                $request->input('service_id')
            );
        }

        if (
            $request->filled(
                'freelancer_id'
            )
        ) {
            $query->where(
                'freelancer_id',
                $request->input(
                    'freelancer_id'
                )
            );
        }

        if ($request->filled('client_id')) {
            $query->where(
                'client_id',
                $request->input('client_id')
            );
        }

        if ($request->filled('search')) {
            $search = trim(
                (string) $request->input(
                    'search'
                )
            );

            $query->where(
                function ($searchQuery) use (
                    $search
                ) {
                    $searchQuery
                        ->where(
                            'description',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhereHas(
                            'service',
                            function (
                                $serviceQuery
                            ) use ($search) {
                                $serviceQuery
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
                            'client',
                            function (
                                $clientQuery
                            ) use ($search) {
                                $clientQuery
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
                        )
                        ->orWhereHas(
                            'freelancer.user',
                            function (
                                $userQuery
                            ) use ($search) {
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

    private function canViewServiceContractRequests(Service $service): bool
    {
        $user = auth('api')->user();

        if (! $user) {
            return false;
        }

        $service->loadMissing('freelancerProfile');

        return $user->hasRole('admin')
            || (
                $service->freelancerProfile
                && $service->freelancerProfile->user_id === $user->id
            );
    }

    /**
     * @OA\Get(
     *     path="/api/contract-requests",
     *     operationId="listContractRequests",
     *     tags={"Contract Requests"},
     *     summary="Listar solicitudes de contratación",
     *     description="Retorna las solicitudes según el rol del usuario autenticado.",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         required=false,
     *         @OA\Schema(
     *             type="string",
     *             enum={
     *                 "pending",
     *                 "accepted",
     *                 "rejected",
     *                 "canceled"
     *             }
     *         )
     *     ),
     *
     *     @OA\Parameter(
     *         name="service_id",
     *         in="query",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Parameter(
     *         name="freelancer_id",
     *         in="query",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Parameter(
     *         name="client_id",
     *         in="query",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         required=false,
     *         @OA\Schema(
     *             type="integer",
     *             minimum=1,
     *             maximum=100
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Solicitudes obtenidas exitosamente"
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

        if (
            ! $authUser->hasAnyRole([
                'cliente',
                'empresa',
                'freelancer',
                'admin',
            ])
        ) {
            return response()->json([
                'success' => false,
                'message' =>
                'Tu tipo de cuenta no tiene acceso a las solicitudes de contratación.',
            ], 403);
        }

        $this->validateIndexFilters(
            $request
        );

        $query = ContractRequest::query()
            ->with([
                'client.roles',
                'freelancer.user.roles',
                'service',
            ]);

        if (! $authUser->hasRole('admin')) {
            $isFreelancer =
                $authUser->hasRole(
                    'freelancer'
                );

            $isClient =
                $authUser->hasAnyRole([
                    'cliente',
                    'empresa',
                ]);

            $query->where(
                function ($roleQuery) use (
                    $authUser,
                    $isFreelancer,
                    $isClient
                ) {
                    if ($isFreelancer) {
                        $roleQuery->whereHas(
                            'freelancer',
                            function (
                                $profileQuery
                            ) use ($authUser) {
                                $profileQuery
                                    ->where(
                                        'user_id',
                                        $authUser->id
                                    );
                            }
                        );
                    }

                    if ($isClient) {
                        $method =
                            $isFreelancer
                            ? 'orWhere'
                            : 'where';

                        $roleQuery->{$method}(
                            'client_id',
                            $authUser->id
                        );
                    }
                }
            );
        }

        $this->applyFilters(
            $query,
            $request
        );

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
            'message' =>
            'Solicitudes de contratación obtenidas exitosamente',

            'data' =>
            $this->paginationData(
                $paginator
            ),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Crear
    |--------------------------------------------------------------------------
    */

    /**
     * @OA\Post(
     *     path="/api/contract-requests",
     *     operationId="createContractRequest",
     *     tags={"Contract Requests"},
     *     summary="Crear solicitud de contratación",
     *     description="Clientes y empresas crean solicitudes para contratar un servicio.",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={
     *                 "service_id",
     *                 "description"
     *             },
     *
     *             @OA\Property(
     *                 property="client_id",
     *                 type="integer",
     *                 nullable=true
     *             ),
     *
     *             @OA\Property(
     *                 property="service_id",
     *                 type="integer",
     *                 example=1
     *             ),
     *
     *             @OA\Property(
     *                 property="description",
     *                 type="string"
     *             ),
     *
     *             @OA\Property(
     *                 property="budget",
     *                 type="number",
     *                 format="float",
     *                 nullable=true
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="Solicitud creada exitosamente"
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
            ! $authUser->hasAnyRole([
                'cliente',
                'empresa',
                'admin',
            ])
        ) {
            return response()->json([
                'success' => false,
                'message' =>
                'Solo clientes, empresas o administradores pueden crear solicitudes de contratación.',
            ], 403);
        }

        $validated = $request->validate([
            'client_id' => [
                'nullable',
                'integer',
                Rule::exists(
                    'users',
                    'id'
                )->whereNull('deleted_at'),
            ],

            'service_id' => [
                'required',
                'integer',
                Rule::exists(
                    'services',
                    'id'
                )->whereNull('deleted_at'),
            ],

            'description' => [
                'required',
                'string',
                'max:10000',
            ],

            'budget' => [
                'nullable',
                'numeric',
                'min:0',
                'max:9999999999.99',
            ],
        ]);

        $clientId =
            $authUser->hasRole('admin')
            ? (
                $validated['client_id']
                ?? $authUser->id
            )
            : $authUser->id;

        $client = User::with('roles')
            ->find($clientId);

        if (
            ! $client
            || ! $client->is_active
            || ! $client->hasAnyRole([
                'cliente',
                'empresa',
                'admin',
            ])
        ) {
            return response()->json([
                'success' => false,
                'message' =>
                'El usuario solicitante debe estar activo y tener rol cliente o empresa.',
            ], 422);
        }

        $service = Service::with(
            'freelancerProfile.user.roles'
        )->find(
            $validated['service_id']
        );

        if (
            ! $service
            || ! $service->freelancerProfile
        ) {
            return response()->json([
                'success' => false,
                'message' =>
                'El servicio seleccionado no tiene un perfil freelancer válido.',
            ], 422);
        }

        if (! $service->is_active) {
            return response()->json([
                'success' => false,
                'message' =>
                'El servicio seleccionado no está activo.',
            ], 422);
        }

        $freelancerProfile =
            $service->freelancerProfile;

        $freelancerUser =
            $freelancerProfile->user;

        if (
            ! $freelancerUser
            || ! $freelancerUser->is_active
            || ! $freelancerUser
                ->hasRole('freelancer')
        ) {
            return response()->json([
                'success' => false,
                'message' =>
                'El freelancer del servicio no se encuentra disponible.',
            ], 422);
        }

        if (
            $freelancerProfile->user_id
            === $clientId
        ) {
            return response()->json([
                'success' => false,
                'message' =>
                'No puedes solicitar contratación sobre tu propio servicio.',
            ], 422);
        }

        $contractRequest =
            DB::transaction(
                function () use (
                    $validated,
                    $clientId,
                    $service,
                    $client
                ) {
                    $contractRequest =
                        ContractRequest::create([
                            'client_id' =>
                            $clientId,

                            'freelancer_id' =>
                            $service
                                ->freelancer_id,

                            'service_id' =>
                            $service->id,

                            'description' =>
                            trim(
                                $validated['description']
                            ),

                            'budget' =>
                            $validated['budget'] ?? null,

                            'status' =>
                            ContractRequest::STATUS_PENDING,
                        ]);

                    ActivityLoggerService::logCreate(
                        module: 'CONTRACT_REQUESTS',

                        entity: 'contract_requests',

                        entityId: $contractRequest->id,

                        description: "Contract request created for service ID {$service->id}"
                    );

                    $clientName = trim(
                        $client->name
                            . ' '
                            . $client->last_name
                    );

                    NotificationService::contractRequest(
                        $service
                            ->freelancerProfile
                            ->user_id,

                        $clientName
                    );

                    return $contractRequest;
                }
            );

        $contractRequest->load([
            'client.roles',
            'freelancer.user.roles',
            'service',
        ]);

        return response()->json([
            'success' => true,
            'message' =>
            'Solicitud de contratación creada exitosamente',

            'data' => [
                'contract_request' =>
                $this
                    ->formatContractRequestResponse(
                        $contractRequest
                    ),
            ],
        ], 201);
    }

    /*
    |--------------------------------------------------------------------------
    | Mostrar
    |--------------------------------------------------------------------------
    */

    /**
     * @OA\Get(
     *     path="/api/contract-requests/{id}",
     *     operationId="showContractRequest",
     *     tags={"Contract Requests"},
     *     summary="Obtener solicitud de contratación por ID",
     *     description="Retorna los detalles de una solicitud. Solo puede consultarla el cliente que la envió, el freelancer que la recibió o un administrador.",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Identificador de la solicitud de contratación",
     *         @OA\Schema(
     *             type="integer",
     *             example=1
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Solicitud obtenida exitosamente",
     *
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="success",
     *                 type="boolean",
     *                 example=true
     *             ),
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="Solicitud obtenida exitosamente"
     *             ),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *
     *                 @OA\Property(
     *                     property="contract_request",
     *                     type="object",
     *
     *                     @OA\Property(
     *                         property="id",
     *                         type="integer",
     *                         example=1
     *                     ),
     *                     @OA\Property(
     *                         property="client_id",
     *                         type="integer",
     *                         example=2
     *                     ),
     *                     @OA\Property(
     *                         property="freelancer_id",
     *                         type="integer",
     *                         example=4
     *                     ),
     *                     @OA\Property(
     *                         property="service_id",
     *                         type="integer",
     *                         example=3
     *                     ),
     *                     @OA\Property(
     *                         property="description",
     *                         type="string",
     *                         example="Necesito desarrollar una API REST para mi plataforma."
     *                     ),
     *                     @OA\Property(
     *                         property="budget",
     *                         type="string",
     *                         nullable=true,
     *                         example="7500.00"
     *                     ),
     *                     @OA\Property(
     *                         property="status",
     *                         type="string",
     *                         enum={
     *                             "pending",
     *                             "accepted",
     *                             "rejected",
     *                             "canceled"
     *                         },
     *                         example="pending"
     *                     ),
     *                     @OA\Property(
     *                         property="created_at",
     *                         type="string",
     *                         format="date-time"
     *                     ),
     *                     @OA\Property(
     *                         property="updated_at",
     *                         type="string",
     *                         format="date-time"
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="Usuario no autenticado",
     *
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="success",
     *                 type="boolean",
     *                 example=false
     *             ),
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="No autorizado."
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=403,
     *         description="El usuario no tiene permisos para consultar la solicitud",
     *
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="success",
     *                 type="boolean",
     *                 example=false
     *             ),
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="No tienes permisos para ver esta solicitud."
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Solicitud de contratación no encontrada",
     *
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="success",
     *                 type="boolean",
     *                 example=false
     *             ),
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="Solicitud no encontrada."
     *             )
     *         )
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

        $contractRequest =
            ContractRequest::with([
                'client.roles',
                'freelancer.user.roles',
                'service',
            ])->find($id);

        if (! $contractRequest) {
            return response()->json([
                'success' => false,
                'message' =>
                'Solicitud no encontrada.',
            ], 404);
        }

        if (
            ! $this->canViewContractRequest(
                $contractRequest,
                $authUser
            )
        ) {
            return response()->json([
                'success' => false,
                'message' =>
                'No tienes permisos para ver esta solicitud.',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'message' =>
            'Solicitud obtenida exitosamente',

            'data' => [
                'contract_request' =>
                $this
                    ->formatContractRequestResponse(
                        $contractRequest
                    ),
            ],
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Actualizar
    |--------------------------------------------------------------------------
    */

    /**
     * @OA\Get(
     *     path="/api/service/contractRequest/{id}",
     *     operationId="listContractRequestsByService",
     *     tags={"Contract Requests"},
     *     summary="Listar solicitudes por servicio",
     *     description="Retorna las solicitudes ligadas a un servicio específico.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID del servicio",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(response=200, description="Solicitudes obtenidas exitosamente"),
     *     @OA\Response(response=401, description="No autorizado. Token requerido o inválido"),
     *     @OA\Response(response=403, description="Sin permisos"),
     *     @OA\Response(response=404, description="Servicio no encontrado")
     * )
     */
    public function byService(int $id)
    {
        $service = Service::with('freelancerProfile')->find($id);

        if (! $service) {
            return response()->json([
                'success' => false,
                'message' => 'Servicio no encontrado.',
            ], 404);
        }

        if (! $this->canViewServiceContractRequests($service)) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permisos para ver las solicitudes de este servicio.',
            ], 403);
        }

        $requests = $service->contractRequests()
            ->with([
                'client.roles',
                'freelancer.user.roles',
                'service',
            ])
            ->latest('created_at')
            ->get()
            ->map(fn (ContractRequest $contractRequest) => $this->formatContractRequestResponse($contractRequest))
            ->values();

        return response()->json([
            'success' => true,
            'message' => 'Solicitudes del servicio obtenidas exitosamente',
            'data' => [
                'service_id' => $service->id,
                'contract_requests' => $requests,
            ],
        ]);
    }

    /**
     * @OA\Put(
     *     path="/api/contract-requests/{id}",
     *     operationId="updateContractRequest",
     *     tags={"Contract Requests"},
     *     summary="Actualizar solicitud de contratación",
     *     description="El cliente puede editar o cancelar una solicitud pendiente. El freelancer puede aceptarla o rechazarla.",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Solicitud actualizada correctamente"
     *     ),
     *
     *     @OA\Response(
     *         response=409,
     *         description="La solicitud ya fue finalizada"
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

        $contractRequest =
            ContractRequest::with([
                'client.roles',
                'freelancer.user.roles',
                'service',
            ])->find($id);

        if (! $contractRequest) {
            return response()->json([
                'success' => false,
                'message' =>
                'Solicitud no encontrada.',
            ], 404);
        }

        if (
            ! $this->canViewContractRequest(
                $contractRequest,
                $authUser
            )
        ) {
            return response()->json([
                'success' => false,
                'message' =>
                'No tienes permisos para actualizar esta solicitud.',
            ], 403);
        }

        if (
            $contractRequest->isFinalized()
        ) {
            return response()->json([
                'success' => false,
                'message' =>
                'La solicitud ya fue aceptada, rechazada o cancelada y no puede modificarse.',

                'data' => [
                    'current_status' =>
                    $contractRequest->status,
                ],
            ], 409);
        }

        $validated = $request->validate([
            'description' => [
                'sometimes',
                'required',
                'string',
                'max:10000',
            ],

            'budget' => [
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
                Rule::in(
                    ContractRequest::STATUSES
                ),
            ],
        ]);

        if (empty($validated)) {
            return response()->json([
                'success' => false,
                'message' =>
                'Debes enviar al menos un campo para actualizar.',
            ], 422);
        }

        $isAdmin =
            $authUser->hasRole('admin');

        $isClientOwner =
            $this->isClientOwner(
                $contractRequest,
                $authUser
            );

        $isFreelancerOwner =
            $this->isFreelancerOwner(
                $contractRequest,
                $authUser
            );

        if (
            ! $isAdmin
            && $isFreelancerOwner
        ) {
            $invalidFields = array_diff(
                array_keys($validated),
                ['status']
            );

            if (! empty($invalidFields)) {
                return response()->json([
                    'success' => false,
                    'message' =>
                    'El freelancer no puede modificar la descripción ni el presupuesto del cliente.',
                ], 403);
            }

            if (
                ! isset(
                    $validated['status']
                )
                || ! in_array(
                    $validated['status'],
                    [
                        ContractRequest::STATUS_ACCEPTED,
                        ContractRequest::STATUS_REJECTED,
                    ],
                    true
                )
            ) {
                return response()->json([
                    'success' => false,
                    'message' =>
                    'El freelancer solamente puede aceptar o rechazar la solicitud.',
                ], 403);
            }
        } elseif (
            ! $isAdmin
            && $isClientOwner
        ) {
            if (
                isset($validated['status'])
                && $validated['status']
                !== ContractRequest::STATUS_CANCELED
            ) {
                return response()->json([
                    'success' => false,
                    'message' =>
                    'El cliente solamente puede cancelar la solicitud.',
                ], 403);
            }
        } elseif (! $isAdmin) {
            return response()->json([
                'success' => false,
                'message' =>
                'No tienes permisos para actualizar esta solicitud.',
            ], 403);
        }

        DB::transaction(
            function () use (
                $contractRequest,
                $validated
            ) {
                if (
                    isset(
                        $validated['description']
                    )
                ) {
                    $validated['description'] =
                        trim(
                            $validated['description']
                        );
                }

                $contractRequest->update(
                    $validated
                );

                ActivityLoggerService::logUpdate(
                    module: 'CONTRACT_REQUESTS',

                    entity: 'contract_requests',

                    entityId: $contractRequest->id,

                    description: "Contract request ID {$contractRequest->id} updated"
                );

                if (
                    isset(
                        $validated['status']
                    )
                ) {
                    if (
                        in_array(
                            $validated['status'],
                            [
                                ContractRequest::STATUS_ACCEPTED,
                                ContractRequest::STATUS_REJECTED,
                            ],
                            true
                        )
                    ) {
                        NotificationService::contractRequestStatus(
                            $contractRequest
                                ->client_id,

                            $validated['status']
                        );
                    }

                    if (
                        $validated['status']
                        === ContractRequest::STATUS_CANCELED
                        && $contractRequest
                        ->freelancer
                    ) {
                        NotificationService::contractRequestStatus(
                            $contractRequest
                                ->freelancer
                                ->user_id,

                            ContractRequest::STATUS_CANCELED
                        );
                    }
                }
            }
        );

        $contractRequest->refresh();

        $contractRequest->load([
            'client.roles',
            'freelancer.user.roles',
            'service',
        ]);

        return response()->json([
            'success' => true,
            'message' =>
            'Solicitud actualizada correctamente',

            'data' => [
                'contract_request' =>
                $this
                    ->formatContractRequestResponse(
                        $contractRequest
                    ),
            ],
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Eliminar
    |--------------------------------------------------------------------------
    */
    /**
     * @OA\Delete(
     *     path="/api/contract-requests/{id}",
     *     operationId="deleteContractRequest",
     *     tags={"Contract Requests"},
     *     summary="Eliminar solicitud de contratación",
     *     description="Realiza un borrado lógico de la solicitud. El cliente propietario solo puede eliminar solicitudes pendientes. El administrador puede eliminar cualquier solicitud.",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Identificador de la solicitud de contratación",
     *         @OA\Schema(
     *             type="integer",
     *             example=1
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Solicitud eliminada correctamente",
     *
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="success",
     *                 type="boolean",
     *                 example=true
     *             ),
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="Solicitud eliminada correctamente."
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="Usuario no autenticado",
     *
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="success",
     *                 type="boolean",
     *                 example=false
     *             ),
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="No autorizado."
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=403,
     *         description="El usuario no tiene permisos para eliminar la solicitud",
     *
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="success",
     *                 type="boolean",
     *                 example=false
     *             ),
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="No tienes permisos para eliminar esta solicitud."
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Solicitud de contratación no encontrada",
     *
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="success",
     *                 type="boolean",
     *                 example=false
     *             ),
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="Solicitud no encontrada."
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=409,
     *         description="La solicitud no puede eliminarse porque ya fue finalizada",
     *
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="success",
     *                 type="boolean",
     *                 example=false
     *             ),
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="Solo puedes eliminar una solicitud pendiente."
     *             ),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *
     *                 @OA\Property(
     *                     property="current_status",
     *                     type="string",
     *                     enum={
     *                         "pending",
     *                         "accepted",
     *                         "rejected",
     *                         "canceled"
     *                     },
     *                     example="accepted"
     *                 )
     *             )
     *         )
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

        $contractRequest =
            ContractRequest::with(
                'freelancer'
            )->find($id);

        if (! $contractRequest) {
            return response()->json([
                'success' => false,
                'message' =>
                'Solicitud no encontrada.',
            ], 404);
        }

        if (
            ! $this->canDeleteContractRequest(
                $contractRequest,
                $authUser
            )
        ) {
            return response()->json([
                'success' => false,
                'message' =>
                'No tienes permisos para eliminar esta solicitud.',
            ], 403);
        }

        if (
            ! $authUser->hasRole('admin')
            && ! $contractRequest
                ->isPending()
        ) {
            return response()->json([
                'success' => false,
                'message' =>
                'Solo puedes eliminar una solicitud pendiente.',

                'data' => [
                    'current_status' =>
                    $contractRequest->status,
                ],
            ], 409);
        }

        DB::transaction(
            function () use (
                $contractRequest
            ) {
                ActivityLoggerService::logDelete(
                    module: 'CONTRACT_REQUESTS',

                    entity: 'contract_requests',

                    entityId: $contractRequest->id,

                    description: "Contract request ID {$contractRequest->id} deleted"
                );

                $contractRequest->delete();
            }
        );

        return response()->json([
            'success' => true,
            'message' =>
            'Solicitud eliminada correctamente.',
        ]);
    }
}
