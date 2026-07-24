<?php

namespace Database\Seeders;

use App\Models\ActivityLog;
use App\Models\Application;
use App\Models\Availability;
use App\Models\Briefcase;
use App\Models\CompanyProfile;
use App\Models\Contract;
use App\Models\ContractRequest;
use App\Models\FreelancerProfile;
use App\Models\Message;
use App\Models\Notification;
use App\Models\Report;
use App\Models\Review;
use App\Models\Role;
use App\Models\Service;
use App\Models\User;
use App\Models\Vacancy;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class WorkLinkDemoSeeder extends Seeder
{
    /**
     * Contraseña para las cuentas ficticias adicionales.
     */
    private const DEFAULT_PASSWORD = 'password123';

    /**
     * Ejecuta la carga completa de datos ficticios.
     */
    public function run(): void
    {
        Model::unguard();

        DB::transaction(function (): void {
            $users = $this->seedUsers();
            $freelancers = $this->seedFreelancerProfiles($users);
            $companies = $this->seedCompanyProfiles($users);

            $services = $this->seedServices($freelancers);
            $this->seedBriefcases($freelancers);
            $this->seedAvailabilities($freelancers);

            $vacancies = $this->seedVacancies($companies);
            $this->seedApplications($vacancies, $freelancers);

            $contractRequests = $this->seedContractRequests(
                $users,
                $freelancers,
                $services
            );

            $contracts = $this->seedContracts($contractRequests);

            $this->seedMessages($users);
            $this->seedReviews($users, $contracts);
            $this->seedNotifications($users);
            $this->seedReports($users);
            $this->seedActivityLogs($users, $services, $vacancies);

            $this->recalculateProfileRatings(
                $freelancers,
                $companies
            );
        });

        Model::reguard();

        $this->command?->info(
            'Datos ficticios de WorkLink creados correctamente.'
        );

        $this->command?->newLine();
        $this->command?->table(
            ['Rol', 'Correo', 'Contraseña'],
            [
                ['Administrador', 'admin@worklink.com', 'admin123'],
                ['Cliente', 'cliente@worklink.com', 'cliente123'],
                ['Freelancer', 'freelancer@worklink.com', 'freelancer123'],
                ['Empresa', 'empresa@worklink.com', 'empresa123'],
                ['Cuentas adicionales', 'Revisar el seeder', self::DEFAULT_PASSWORD],
            ]
        );
    }

    /**
     * Crea usuarios de todos los roles y devuelve un arreglo indexado.
     */
    private function seedUsers(): array
    {
        $usersData = [
            'admin' => [
                'role' => 'admin',
                'name' => 'Administrador',
                'last_name' => 'Sistema',
                'maternal_last_name' => null,
                'email' => 'admin@worklink.com',
                'password' => 'admin123',
                'phone' => '7710000001',
                'profile_photo' => 'https://i.pravatar.cc/300?img=12',
            ],

            'client_1' => [
                'role' => 'cliente',
                'name' => 'Juan',
                'last_name' => 'Pérez',
                'maternal_last_name' => 'García',
                'email' => 'cliente@worklink.com',
                'password' => 'cliente123',
                'phone' => '7711001001',
                'profile_photo' => 'https://i.pravatar.cc/300?img=11',
            ],
            'client_2' => [
                'role' => 'cliente',
                'name' => 'Sofía',
                'last_name' => 'Martínez',
                'maternal_last_name' => 'Cruz',
                'email' => 'sofia.cliente@worklink.com',
                'password' => self::DEFAULT_PASSWORD,
                'phone' => '7711001002',
                'profile_photo' => 'https://i.pravatar.cc/300?img=47',
            ],
            'client_3' => [
                'role' => 'cliente',
                'name' => 'Carlos',
                'last_name' => 'Hernández',
                'maternal_last_name' => 'Luna',
                'email' => 'carlos.cliente@worklink.com',
                'password' => self::DEFAULT_PASSWORD,
                'phone' => '7711001003',
                'profile_photo' => 'https://i.pravatar.cc/300?img=15',
            ],

            'freelancer_1' => [
                'role' => 'freelancer',
                'name' => 'María',
                'last_name' => 'López',
                'maternal_last_name' => 'Hernández',
                'email' => 'freelancer@worklink.com',
                'password' => 'freelancer123',
                'phone' => '7712002001',
                'profile_photo' => 'https://i.pravatar.cc/300?img=32',
            ],
            'freelancer_2' => [
                'role' => 'freelancer',
                'name' => 'Diego',
                'last_name' => 'Ramírez',
                'maternal_last_name' => 'Soto',
                'email' => 'diego.dev@worklink.com',
                'password' => self::DEFAULT_PASSWORD,
                'phone' => '7712002002',
                'profile_photo' => 'https://i.pravatar.cc/300?img=13',
            ],
            'freelancer_3' => [
                'role' => 'freelancer',
                'name' => 'Valeria',
                'last_name' => 'Torres',
                'maternal_last_name' => 'Mendoza',
                'email' => 'valeria.design@worklink.com',
                'password' => self::DEFAULT_PASSWORD,
                'phone' => '7712002003',
                'profile_photo' => 'https://i.pravatar.cc/300?img=44',
            ],
            'freelancer_4' => [
                'role' => 'freelancer',
                'name' => 'Luis',
                'last_name' => 'Gómez',
                'maternal_last_name' => 'Vargas',
                'email' => 'luis.marketing@worklink.com',
                'password' => self::DEFAULT_PASSWORD,
                'phone' => '7712002004',
                'profile_photo' => 'https://i.pravatar.cc/300?img=53',
            ],
            'freelancer_5' => [
                'role' => 'freelancer',
                'name' => 'Fernanda',
                'last_name' => 'Castro',
                'maternal_last_name' => 'Ortiz',
                'email' => 'fernanda.data@worklink.com',
                'password' => self::DEFAULT_PASSWORD,
                'phone' => '7712002005',
                'profile_photo' => 'https://i.pravatar.cc/300?img=49',
            ],

            'company_1' => [
                'role' => 'empresa',
                'name' => 'TechCorp',
                'last_name' => 'Solutions',
                'maternal_last_name' => null,
                'email' => 'empresa@worklink.com',
                'password' => 'empresa123',
                'phone' => '7713003001',
                'profile_photo' => 'https://i.pravatar.cc/300?img=8',
            ],
            'company_2' => [
                'role' => 'empresa',
                'name' => 'Innovación',
                'last_name' => 'Digital',
                'maternal_last_name' => 'México',
                'email' => 'innovacion@worklink.com',
                'password' => self::DEFAULT_PASSWORD,
                'phone' => '7713003002',
                'profile_photo' => 'https://i.pravatar.cc/300?img=5',
            ],
            'company_3' => [
                'role' => 'empresa',
                'name' => 'Agencia',
                'last_name' => 'Norte',
                'maternal_last_name' => 'Creativo',
                'email' => 'norte.creativo@worklink.com',
                'password' => self::DEFAULT_PASSWORD,
                'phone' => '7713003003',
                'profile_photo' => 'https://i.pravatar.cc/300?img=3',
            ],
        ];

        $users = [];

        foreach ($usersData as $key => $data) {
            $role = Role::where('name', $data['role'])->firstOrFail();

            $user = User::withTrashed()
                ->where('email', $data['email'])
                ->first();

            if (! $user) {
                $user = new User();
                $user->email = $data['email'];
            }

            if ($user->trashed()) {
                $user->restore();
            }

            $user->fill([
                'name' => $data['name'],
                'last_name' => $data['last_name'],
                'maternal_last_name' => $data['maternal_last_name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'phone' => $data['phone'],
                'profile_photo' => $data['profile_photo'],
                'is_active' => true,
            ]);

            $user->save();

            $user->roles()->sync([
                $role->id => [
                    'assigned_at' => now(),
                ],
            ]);

            $users[$key] = $user;
        }

        return $users;
    }

    /**
     * Crea perfiles profesionales de freelancer.
     */
    private function seedFreelancerProfiles(array $users): array
    {
        $profilesData = [
            'freelancer_1' => [
                'description' => 'Desarrolladora full stack especializada en aplicaciones web modernas con React, Laravel y MySQL.',
                'specialty' => 'Desarrollo web full stack',
                'location' => 'Pachuca, Hidalgo',
                'service_area' => 'México y trabajo remoto internacional',
                'work_mode' => 'remote',
                'experience' => '4 años desarrollando tiendas en línea, sistemas administrativos y APIs REST.',
                'rate_type' => 'project',
                'rate' => 8500.00,
                'languages' => ['Español', 'Inglés intermedio'],
                'website' => 'https://maria-dev.example.com',
                'linkedin' => 'https://linkedin.com/in/maria-worklink',
                'github' => 'https://github.com/maria-worklink',
                'portfolio_url' => 'https://maria-dev.example.com/portafolio',
                'available' => true,
            ],
            'freelancer_2' => [
                'description' => 'Ingeniero backend enfocado en Laravel, Node.js, bases de datos y servicios en la nube.',
                'specialty' => 'Backend y APIs',
                'location' => 'Ciudad de México',
                'service_area' => 'Remoto',
                'work_mode' => 'remote',
                'experience' => '5 años creando APIs seguras, integraciones de pagos y arquitecturas escalables.',
                'rate_type' => 'hourly',
                'rate' => 420.00,
                'languages' => ['Español', 'Inglés avanzado'],
                'website' => 'https://diego-backend.example.com',
                'linkedin' => 'https://linkedin.com/in/diego-worklink',
                'github' => 'https://github.com/diego-worklink',
                'portfolio_url' => 'https://diego-backend.example.com/proyectos',
                'available' => true,
            ],
            'freelancer_3' => [
                'description' => 'Diseñadora UX/UI con experiencia en productos digitales, sistemas de diseño y prototipos de alta fidelidad.',
                'specialty' => 'Diseño UX/UI',
                'location' => 'Querétaro, Querétaro',
                'service_area' => 'México',
                'work_mode' => 'hybrid',
                'experience' => '3 años diseñando aplicaciones móviles, sitios web y experiencias de usuario.',
                'rate_type' => 'project',
                'rate' => 6200.00,
                'languages' => ['Español', 'Inglés intermedio'],
                'website' => 'https://valeria-ui.example.com',
                'instagram' => 'https://instagram.com/valeria.worklink',
                'linkedin' => 'https://linkedin.com/in/valeria-worklink',
                'portfolio_url' => 'https://behance.net/valeria-worklink',
                'available' => true,
            ],
            'freelancer_4' => [
                'description' => 'Especialista en marketing digital, campañas publicitarias y administración de redes sociales.',
                'specialty' => 'Marketing digital',
                'location' => 'Monterrey, Nuevo León',
                'service_area' => 'México',
                'work_mode' => 'remote',
                'experience' => '6 años gestionando marcas, campañas de Meta Ads y estrategias de contenidos.',
                'rate_type' => 'negotiable',
                'rate' => 9500.00,
                'languages' => ['Español'],
                'website' => 'https://luis-marketing.example.com',
                'facebook' => 'https://facebook.com/luis.worklink',
                'instagram' => 'https://instagram.com/luis.worklink',
                'linkedin' => 'https://linkedin.com/in/luis-worklink',
                'available' => false,
            ],
            'freelancer_5' => [
                'description' => 'Analista de datos con experiencia en Python, Power BI, machine learning y automatización de reportes.',
                'specialty' => 'Análisis de datos',
                'location' => 'Guadalajara, Jalisco',
                'service_area' => 'Remoto',
                'work_mode' => 'remote',
                'experience' => '4 años trabajando con dashboards, modelos predictivos y limpieza de datos.',
                'rate_type' => 'daily',
                'rate' => 2800.00,
                'languages' => ['Español', 'Inglés avanzado'],
                'website' => 'https://fernanda-data.example.com',
                'linkedin' => 'https://linkedin.com/in/fernanda-worklink',
                'github' => 'https://github.com/fernanda-worklink',
                'portfolio_url' => 'https://fernanda-data.example.com/casos',
                'available' => true,
            ],
        ];

        $profiles = [];

        foreach ($profilesData as $key => $data) {
            $user = $users[$key];

            $profile = FreelancerProfile::withTrashed()
                ->where('user_id', $user->id)
                ->first();

            if (! $profile) {
                $profile = new FreelancerProfile([
                    'user_id' => $user->id,
                ]);
            }

            if ($profile->trashed()) {
                $profile->restore();
            }

            $profile->fill($data);
            $profile->user_id = $user->id;
            $profile->save();

            $profiles[$key] = $profile;
        }

        return $profiles;
    }

    /**
     * Crea perfiles empresariales.
     */
    private function seedCompanyProfiles(array $users): array
    {
        $profilesData = [
            'company_1' => [
                'company_name' => 'TechCorp Solutions',
                'description' => 'Empresa de desarrollo de software, transformación digital y soluciones empresariales.',
                'industry' => 'Tecnologías de la información',
                'location' => 'Pachuca, Hidalgo',
            ],
            'company_2' => [
                'company_name' => 'Innovación Digital México',
                'description' => 'Consultora dedicada a comercio electrónico, automatización y productos digitales.',
                'industry' => 'Consultoría tecnológica',
                'location' => 'Ciudad de México',
            ],
            'company_3' => [
                'company_name' => 'Norte Creativo',
                'description' => 'Agencia creativa especializada en branding, publicidad y contenido para redes sociales.',
                'industry' => 'Publicidad y diseño',
                'location' => 'Monterrey, Nuevo León',
            ],
        ];

        $profiles = [];

        foreach ($profilesData as $key => $data) {
            $user = $users[$key];

            $profile = CompanyProfile::withTrashed()
                ->where('user_id', $user->id)
                ->first();

            if (! $profile) {
                $profile = new CompanyProfile([
                    'user_id' => $user->id,
                ]);
            }

            if ($profile->trashed()) {
                $profile->restore();
            }

            $profile->fill($data);
            $profile->user_id = $user->id;
            $profile->save();

            $profiles[$key] = $profile;
        }

        return $profiles;
    }

    /**
     * Crea servicios para todos los perfiles freelancer.
     */
    private function seedServices(array $freelancers): array
    {
        $servicesData = [
            'service_web' => [
                'freelancer' => 'freelancer_1',
                'title' => 'Desarrollo de sitio web profesional',
                'description' => 'Diseño y desarrollo de un sitio web responsivo, optimizado y administrable.',
                'price' => 8500.00,
                'category' => 'Desarrollo web',
                'location' => 'Remoto',
                'is_active' => true,
            ],
            'service_store' => [
                'freelancer' => 'freelancer_1',
                'title' => 'Tienda en línea con panel administrativo',
                'description' => 'Comercio electrónico con catálogo, carrito, pedidos y panel de administración.',
                'price' => 18500.00,
                'category' => 'Comercio electrónico',
                'location' => 'Remoto',
                'is_active' => true,
            ],
            'service_api' => [
                'freelancer' => 'freelancer_2',
                'title' => 'API REST con Laravel',
                'description' => 'Desarrollo de API REST segura con JWT, documentación Swagger y base de datos MySQL.',
                'price' => 12000.00,
                'category' => 'Backend',
                'location' => 'Remoto',
                'is_active' => true,
            ],
            'service_cloud' => [
                'freelancer' => 'freelancer_2',
                'title' => 'Despliegue de aplicación en la nube',
                'description' => 'Configuración de servidor, despliegue, dominio, SSL y automatización básica.',
                'price' => 6500.00,
                'category' => 'DevOps',
                'location' => 'Remoto',
                'is_active' => true,
            ],
            'service_ui' => [
                'freelancer' => 'freelancer_3',
                'title' => 'Diseño UX/UI para aplicación móvil',
                'description' => 'Investigación, wireframes, prototipo navegable y diseño visual en Figma.',
                'price' => 7200.00,
                'category' => 'Diseño UX/UI',
                'location' => 'Remoto',
                'is_active' => true,
            ],
            'service_branding' => [
                'freelancer' => 'freelancer_3',
                'title' => 'Identidad visual para emprendimiento',
                'description' => 'Logotipo, paleta de colores, tipografías y aplicaciones principales de marca.',
                'price' => 4800.00,
                'category' => 'Diseño gráfico',
                'location' => 'Querétaro',
                'is_active' => true,
            ],
            'service_social' => [
                'freelancer' => 'freelancer_4',
                'title' => 'Administración mensual de redes sociales',
                'description' => 'Planeación de contenido, diseño de publicaciones, programación y reporte mensual.',
                'price' => 9500.00,
                'category' => 'Marketing digital',
                'location' => 'Remoto',
                'is_active' => true,
            ],
            'service_ads' => [
                'freelancer' => 'freelancer_4',
                'title' => 'Campaña publicitaria en Meta Ads',
                'description' => 'Configuración, segmentación, optimización y seguimiento de campaña.',
                'price' => 5500.00,
                'category' => 'Publicidad',
                'location' => 'Remoto',
                'is_active' => false,
            ],
            'service_dashboard' => [
                'freelancer' => 'freelancer_5',
                'title' => 'Dashboard interactivo en Power BI',
                'description' => 'Limpieza de datos, modelado y creación de tablero con indicadores clave.',
                'price' => 7800.00,
                'category' => 'Análisis de datos',
                'location' => 'Remoto',
                'is_active' => true,
            ],
            'service_ml' => [
                'freelancer' => 'freelancer_5',
                'title' => 'Modelo predictivo con Python',
                'description' => 'Preparación de datos, entrenamiento, evaluación y documentación del modelo.',
                'price' => 14500.00,
                'category' => 'Machine Learning',
                'location' => 'Remoto',
                'is_active' => true,
            ],
        ];

        $services = [];

        foreach ($servicesData as $key => $data) {
            $freelancer = $freelancers[$data['freelancer']];

            $service = Service::withTrashed()
                ->where('freelancer_id', $freelancer->id)
                ->where('title', $data['title'])
                ->first();

            if (! $service) {
                $service = new Service();
            }

            if ($service->trashed()) {
                $service->restore();
            }

            $service->fill([
                'freelancer_id' => $freelancer->id,
                'title' => $data['title'],
                'description' => $data['description'],
                'price' => $data['price'],
                'category' => $data['category'],
                'location' => $data['location'],
                'is_active' => $data['is_active'],
            ]);

            $service->save();
            $services[$key] = $service;
        }

        return $services;
    }

    /**
     * Crea proyectos de portafolio.
     */
    private function seedBriefcases(array $freelancers): void
    {
        $projects = [
            ['freelancer_1', 'Sistema de punto de venta', 'Aplicación web para inventario, ventas y reportes.', 'pos-worklink', 'https://demo.example.com/pos'],
            ['freelancer_1', 'Plataforma de reservaciones', 'Sistema responsivo para administrar citas y clientes.', 'reservas-worklink', 'https://demo.example.com/reservas'],
            ['freelancer_2', 'API de facturación electrónica', 'Servicio REST documentado con autenticación JWT.', 'api-worklink', 'https://github.com/demo/api-facturacion'],
            ['freelancer_2', 'Arquitectura de microservicios', 'Diseño e implementación de servicios desacoplados.', 'microservices-worklink', 'https://github.com/demo/microservicios'],
            ['freelancer_3', 'Diseño de app financiera', 'Prototipo UX/UI completo para una aplicación móvil.', 'ux-finance-worklink', 'https://www.figma.com/community'],
            ['freelancer_3', 'Identidad visual cafetería', 'Branding y manual básico de identidad corporativa.', 'branding-worklink', 'https://www.behance.net'],
            ['freelancer_4', 'Campaña de lanzamiento', 'Estrategia de contenido y anuncios para producto digital.', 'marketing-worklink', 'https://demo.example.com/campana'],
            ['freelancer_5', 'Dashboard de ventas', 'Tablero con métricas, tendencias y segmentación.', 'dashboard-worklink', 'https://demo.example.com/dashboard'],
            ['freelancer_5', 'Predicción de abandono', 'Modelo de clasificación para detectar clientes en riesgo.', 'ml-worklink', 'https://github.com/demo/churn-model'],
        ];

        foreach ($projects as [$freelancerKey, $title, $description, $imageSeed, $projectUrl]) {
            $freelancer = $freelancers[$freelancerKey];

            $briefcase = Briefcase::withTrashed()
                ->where('freelancer_id', $freelancer->id)
                ->where('title', $title)
                ->first();

            if (! $briefcase) {
                $briefcase = new Briefcase();
            }

            if ($briefcase->trashed()) {
                $briefcase->restore();
            }

            $briefcase->fill([
                'freelancer_id' => $freelancer->id,
                'title' => $title,
                'description' => $description,
                'image_url' => "https://picsum.photos/seed/{$imageSeed}/900/600",
                'project_url' => $projectUrl,
            ]);

            $briefcase->save();
        }
    }

    /**
     * Crea periodos de disponibilidad.
     */
    private function seedAvailabilities(array $freelancers): void
    {
        $today = Carbon::today();

        $rows = [
            ['freelancer_1', $today, $today->copy()->addDays(20), Availability::STATUS_AVAILABLE],
            ['freelancer_1', $today->copy()->addDays(21), $today->copy()->addDays(30), Availability::STATUS_BUSY],
            ['freelancer_2', $today, $today->copy()->addDays(15), Availability::STATUS_AVAILABLE],
            ['freelancer_3', $today->copy()->addDays(3), $today->copy()->addDays(25), Availability::STATUS_AVAILABLE],
            ['freelancer_4', $today, $today->copy()->addDays(12), Availability::STATUS_BUSY],
            ['freelancer_4', $today->copy()->addDays(13), $today->copy()->addDays(20), Availability::STATUS_VACATION],
            ['freelancer_5', $today, $today->copy()->addDays(30), Availability::STATUS_AVAILABLE],
        ];

        foreach ($rows as [$freelancerKey, $startDate, $endDate, $status]) {
            $freelancer = $freelancers[$freelancerKey];

            Availability::updateOrCreate(
                [
                    'freelancer_id' => $freelancer->id,
                    'start_date' => $startDate->toDateString(),
                    'end_date' => $endDate->toDateString(),
                ],
                [
                    'status' => $status,
                ]
            );
        }
    }

    /**
     * Crea vacantes empresariales.
     */
    private function seedVacancies(array $companies): array
    {
        $data = [
            'vacancy_frontend' => [
                'company' => 'company_1',
                'title' => 'Desarrollador Frontend React',
                'description' => 'Buscamos experiencia con React, TypeScript, Tailwind CSS y consumo de APIs.',
                'category' => 'Desarrollo web',
                'location' => 'Remoto',
                'salary' => 28000.00,
                'status' => Vacancy::STATUS_OPEN,
            ],
            'vacancy_backend' => [
                'company' => 'company_1',
                'title' => 'Desarrollador Backend Laravel',
                'description' => 'Responsable de APIs REST, seguridad JWT, MySQL y documentación Swagger.',
                'category' => 'Backend',
                'location' => 'Pachuca / híbrido',
                'salary' => 32000.00,
                'status' => Vacancy::STATUS_OPEN,
            ],
            'vacancy_designer' => [
                'company' => 'company_2',
                'title' => 'Diseñador UX/UI freelance',
                'description' => 'Diseño de flujos, prototipos, sistema visual y pruebas de usabilidad.',
                'category' => 'Diseño UX/UI',
                'location' => 'Remoto',
                'salary' => 18000.00,
                'status' => Vacancy::STATUS_OPEN,
            ],
            'vacancy_data' => [
                'company' => 'company_2',
                'title' => 'Analista de datos',
                'description' => 'Creación de dashboards, análisis de información y automatización de reportes.',
                'category' => 'Datos',
                'location' => 'Ciudad de México',
                'salary' => 26000.00,
                'status' => Vacancy::STATUS_PAUSED,
            ],
            'vacancy_community' => [
                'company' => 'company_3',
                'title' => 'Community Manager',
                'description' => 'Administración de redes, calendario editorial y atención a comunidad.',
                'category' => 'Marketing',
                'location' => 'Monterrey / híbrido',
                'salary' => 17000.00,
                'status' => Vacancy::STATUS_OPEN,
            ],
            'vacancy_brand' => [
                'company' => 'company_3',
                'title' => 'Diseñador de marca',
                'description' => 'Creación de identidad visual y materiales de comunicación.',
                'category' => 'Diseño gráfico',
                'location' => 'Remoto',
                'salary' => 15000.00,
                'status' => Vacancy::STATUS_CLOSED,
            ],
        ];

        $vacancies = [];

        foreach ($data as $key => $row) {
            $company = $companies[$row['company']];

            $vacancy = Vacancy::withTrashed()
                ->where('company_id', $company->id)
                ->where('title', $row['title'])
                ->first();

            if (! $vacancy) {
                $vacancy = new Vacancy();
            }

            if ($vacancy->trashed()) {
                $vacancy->restore();
            }

            $vacancy->fill([
                'company_id' => $company->id,
                'title' => $row['title'],
                'description' => $row['description'],
                'category' => $row['category'],
                'location' => $row['location'],
                'salary' => $row['salary'],
                'status' => $row['status'],
            ]);

            $vacancy->save();
            $vacancies[$key] = $vacancy;
        }

        return $vacancies;
    }

    /**
     * Crea postulaciones con distintos estados.
     */
    private function seedApplications(
        array $vacancies,
        array $freelancers
    ): void {
        $rows = [
            ['vacancy_frontend', 'freelancer_1', 'Tengo experiencia con React, TypeScript y diseño responsivo.', Application::STATUS_ACCEPTED],
            ['vacancy_frontend', 'freelancer_3', 'Puedo apoyar con implementación visual y experiencia de usuario.', Application::STATUS_PENDING],
            ['vacancy_backend', 'freelancer_2', 'He desarrollado múltiples APIs con Laravel y JWT.', Application::STATUS_ACCEPTED],
            ['vacancy_backend', 'freelancer_5', 'Tengo experiencia en Python y servicios de datos para backend.', Application::STATUS_REJECTED],
            ['vacancy_designer', 'freelancer_3', 'Adjunto mi portafolio de proyectos UX/UI recientes.', Application::STATUS_PENDING],
            ['vacancy_community', 'freelancer_4', 'Cuento con seis años administrando comunidades digitales.', Application::STATUS_PENDING],
        ];

        foreach ($rows as [$vacancyKey, $freelancerKey, $message, $status]) {
            Application::updateOrCreate(
                [
                    'vacancy_id' => $vacancies[$vacancyKey]->id,
                    'freelancer_id' => $freelancers[$freelancerKey]->id,
                ],
                [
                    'message' => $message,
                    'status' => $status,
                ]
            );
        }
    }

    /**
     * Crea solicitudes de contratación.
     */
    private function seedContractRequests(
        array $users,
        array $freelancers,
        array $services
    ): array {
        $rows = [
            'request_completed_1' => [
                'client' => 'client_1',
                'freelancer' => 'freelancer_1',
                'service' => 'service_web',
                'description' => 'Necesito un sitio web para mi negocio con catálogo, formulario y panel básico.',
                'budget' => 9200.00,
                'status' => 'accepted',
            ],
            'request_in_process' => [
                'client' => 'company_1',
                'freelancer' => 'freelancer_2',
                'service' => 'service_api',
                'description' => 'Requerimos una API para administrar vacantes y postulaciones.',
                'budget' => 14500.00,
                'status' => 'accepted',
            ],
            'request_completed_2' => [
                'client' => 'client_2',
                'freelancer' => 'freelancer_3',
                'service' => 'service_branding',
                'description' => 'Busco identidad visual para una cafetería local.',
                'budget' => 5200.00,
                'status' => 'accepted',
            ],
            'request_canceled' => [
                'client' => 'client_3',
                'freelancer' => 'freelancer_1',
                'service' => 'service_store',
                'description' => 'Tienda en línea para productos artesanales.',
                'budget' => 17000.00,
                'status' => 'canceled',
            ],
            'request_pending' => [
                'client' => 'company_2',
                'freelancer' => 'freelancer_5',
                'service' => 'service_dashboard',
                'description' => 'Dashboard ejecutivo para ventas y rendimiento de campañas.',
                'budget' => 8500.00,
                'status' => 'pending',
            ],
            'request_rejected' => [
                'client' => 'client_1',
                'freelancer' => 'freelancer_4',
                'service' => 'service_social',
                'description' => 'Administración de redes sociales durante tres meses.',
                'budget' => 7000.00,
                'status' => 'rejected',
            ],
        ];

        $requests = [];

        foreach ($rows as $key => $row) {
            $client = $users[$row['client']];
            $freelancer = $freelancers[$row['freelancer']];
            $service = $services[$row['service']];

            $request = ContractRequest::withTrashed()
                ->where('client_id', $client->id)
                ->where('freelancer_id', $freelancer->id)
                ->where('service_id', $service->id)
                ->first();

            if (! $request) {
                $request = new ContractRequest();
            }

            if ($request->trashed()) {
                $request->restore();
            }

            $request->fill([
                'client_id' => $client->id,
                'freelancer_id' => $freelancer->id,
                'service_id' => $service->id,
                'description' => $row['description'],
                'budget' => $row['budget'],
                'status' => $row['status'],
            ]);

            $request->save();
            $requests[$key] = $request;
        }

        return $requests;
    }

    /**
     * Crea contratos en progreso, completados y cancelados.
     */
    private function seedContracts(array $requests): array
    {
        $today = Carbon::today();

        $rows = [
            'contract_completed_1' => [
                'request' => 'request_completed_1',
                'start_date' => $today->copy()->subDays(35),
                'end_date' => $today->copy()->subDays(8),
                'total_amount' => 9200.00,
                'status' => Contract::STATUS_COMPLETED,
            ],
            'contract_in_process' => [
                'request' => 'request_in_process',
                'start_date' => $today->copy()->subDays(10),
                'end_date' => $today->copy()->addDays(20),
                'total_amount' => 14500.00,
                'status' => Contract::STATUS_IN_PROCESS,
            ],
            'contract_completed_2' => [
                'request' => 'request_completed_2',
                'start_date' => $today->copy()->subDays(50),
                'end_date' => $today->copy()->subDays(20),
                'total_amount' => 5200.00,
                'status' => Contract::STATUS_COMPLETED,
            ],
            'contract_canceled' => [
                'request' => 'request_canceled',
                'start_date' => $today->copy()->subDays(15),
                'end_date' => null,
                'total_amount' => 17000.00,
                'status' => Contract::STATUS_CANCELED,
            ],
        ];

        $contracts = [];

        foreach ($rows as $key => $row) {
            $request = $requests[$row['request']];

            $contract = Contract::withTrashed()
                ->where('request_id', $request->id)
                ->first();

            if (! $contract) {
                $contract = new Contract();
            }

            if ($contract->trashed()) {
                $contract->restore();
            }

            $contract->fill([
                'request_id' => $request->id,
                'start_date' => $row['start_date']->toDateString(),
                'end_date' => $row['end_date']?->toDateString(),
                'total_amount' => $row['total_amount'],
                'status' => $row['status'],
            ]);

            $contract->save();
            $contracts[$key] = $contract;
        }

        return $contracts;
    }

    /**
     * Crea conversaciones entre clientes, empresas y freelancers.
     */
    private function seedMessages(array $users): void
    {
        $rows = [
            ['client_1', 'freelancer_1', 'Hola María, vi tu servicio de desarrollo web y me interesa.', true, 9],
            ['freelancer_1', 'client_1', 'Hola Juan, con gusto. ¿Qué tipo de sitio necesitas?', true, 8],
            ['client_1', 'freelancer_1', 'Es para un negocio local y necesito catálogo de productos.', true, 7],
            ['freelancer_1', 'client_1', 'Perfecto, puedo prepararte una propuesta esta tarde.', false, 6],
            ['company_1', 'freelancer_2', 'Hola Diego, queremos conversar sobre una API para nuestro proyecto.', true, 5],
            ['freelancer_2', 'company_1', 'Claro, puedo revisar los requerimientos y proponer la arquitectura.', false, 4],
            ['client_2', 'freelancer_3', 'Hola Valeria, me gustó mucho tu proyecto de identidad visual.', true, 4],
            ['freelancer_3', 'client_2', 'Gracias Sofía. Cuéntame un poco sobre tu negocio.', false, 3],
            ['company_2', 'freelancer_5', '¿Tienes disponibilidad para crear un dashboard este mes?', false, 2],
            ['freelancer_4', 'company_3', 'Me interesa su vacante de Community Manager.', false, 1],
        ];

        foreach ($rows as [$senderKey, $receiverKey, $content, $isRead, $daysAgo]) {
            $message = Message::withTrashed()
                ->where('sender_id', $users[$senderKey]->id)
                ->where('receiver_id', $users[$receiverKey]->id)
                ->where('content', $content)
                ->first();

            if (! $message) {
                $message = new Message();
            }

            if ($message->trashed()) {
                $message->restore();
            }

            $message->fill([
                'sender_id' => $users[$senderKey]->id,
                'receiver_id' => $users[$receiverKey]->id,
                'content' => $content,
                'is_read' => $isRead,
            ]);

            $message->save();

            $createdAt = now()->subDays($daysAgo);
            $message->forceFill([
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ])->saveQuietly();
        }
    }

    /**
     * Crea reseñas válidas para contratos completados.
     */
    private function seedReviews(
        array $users,
        array $contracts
    ): void {
        $rows = [
            [$contracts['contract_completed_1'], 'client_1', 'freelancer_1', 5, 'Excelente comunicación y el sitio quedó mejor de lo esperado.'],
            [$contracts['contract_completed_1'], 'freelancer_1', 'client_1', 5, 'El cliente explicó claramente los requisitos y realizó seguimiento puntual.'],
            [$contracts['contract_completed_2'], 'client_2', 'freelancer_3', 4, 'Muy buen diseño y atención; solamente realizamos un ajuste adicional.'],
            [$contracts['contract_completed_2'], 'freelancer_3', 'client_2', 5, 'Excelente colaboración y retroalimentación durante todo el proyecto.'],
        ];

        foreach ($rows as [$contract, $evaluatorKey, $evaluatedKey, $rating, $comment]) {
            Review::withTrashed()->updateOrCreate(
                [
                    'contract_id' => $contract->id,
                    'evaluator_id' => $users[$evaluatorKey]->id,
                ],
                [
                    'evaluated_id' => $users[$evaluatedKey]->id,
                    'rating' => $rating,
                    'comment' => $comment,
                    'deleted_at' => null,
                ]
            );
        }
    }

    /**
     * Crea notificaciones de todos los tipos principales.
     */
    private function seedNotifications(array $users): void
    {
        $rows = [
            ['company_1', Notification::TYPE_APPLICATION_RECEIVED, 'María López se postuló a la vacante Desarrollador Frontend React.', false],
            ['freelancer_1', Notification::TYPE_APPLICATION_ACCEPTED, 'Tu postulación a Desarrollador Frontend React fue aceptada.', true],
            ['freelancer_5', Notification::TYPE_APPLICATION_REJECTED, 'Tu postulación a Desarrollador Backend Laravel fue rechazada.', true],
            ['freelancer_1', Notification::TYPE_CONTRACT_REQUEST, 'Juan Pérez te envió una solicitud de contratación.', true],
            ['client_1', Notification::TYPE_CONTRACT_REQUEST_ACCEPTED, 'Tu solicitud de contratación fue aceptada.', true],
            ['freelancer_2', Notification::TYPE_CONTRACT_CREATED, 'Se creó un nuevo contrato relacionado con tu cuenta.', false],
            ['client_1', Notification::TYPE_CONTRACT_COMPLETED, 'Uno de tus contratos fue marcado como completado.', false],
            ['freelancer_1', Notification::TYPE_REVIEW_RECEIVED, 'Recibiste una nueva calificación de 5 estrellas.', false],
            ['client_1', Notification::TYPE_MESSAGE, 'María López te envió un mensaje.', false],
            ['company_2', Notification::TYPE_MESSAGE, 'Fernanda Castro te envió un mensaje.', false],
        ];

        foreach ($rows as [$userKey, $type, $messageText, $isRead]) {
            $notification = Notification::withTrashed()
                ->where('user_id', $users[$userKey]->id)
                ->where('type', $type)
                ->where('message', $messageText)
                ->first();

            if (! $notification) {
                $notification = new Notification();
            }

            if ($notification->trashed()) {
                $notification->restore();
            }

            $notification->fill([
                'user_id' => $users[$userKey]->id,
                'type' => $type,
                'message' => $messageText,
                'is_read' => $isRead,
            ]);

            $notification->save();
        }
    }

    /**
     * Crea reportes con todos sus estados.
     */
    private function seedReports(array $users): void
    {
        $rows = [
            ['client_3', 'freelancer_4', 'Comunicación inapropiada', 'El usuario utilizó expresiones poco profesionales durante la conversación.', Report::STATUS_PENDING],
            ['client_2', 'freelancer_2', 'Información confusa', 'La descripción del servicio no coincidía completamente con lo conversado.', Report::STATUS_REVIEWED],
            ['freelancer_3', 'client_3', 'Cancelación constante', 'El usuario canceló reuniones en múltiples ocasiones sin aviso previo.', Report::STATUS_RESOLVED],
        ];

        foreach ($rows as [$reporterKey, $reportedKey, $reason, $description, $status]) {
            $report = Report::withTrashed()
                ->where('reporter_id', $users[$reporterKey]->id)
                ->where('reported_id', $users[$reportedKey]->id)
                ->where('reason', $reason)
                ->first();

            if (! $report) {
                $report = new Report();
            }

            if ($report->trashed()) {
                $report->restore();
            }

            $report->fill([
                'reporter_id' => $users[$reporterKey]->id,
                'reported_id' => $users[$reportedKey]->id,
                'reason' => $reason,
                'description' => $description,
                'status' => $status,
            ]);

            $report->save();
        }
    }

    /**
     * Crea registros de auditoría representativos.
     */
    private function seedActivityLogs(
        array $users,
        array $services,
        array $vacancies
    ): void {
        $rows = [
            [$users['admin']->id, 'LOGIN', 'AUTH', 'users', $users['admin']->id, 'Administrador inició sesión.', 12],
            [$users['client_1']->id, 'REGISTER', 'AUTH', 'users', $users['client_1']->id, 'Cliente registrado en WorkLink.', 28],
            [$users['freelancer_1']->id, 'CREATE', 'SERVICES', 'services', $services['service_web']->id, 'Servicio de desarrollo web creado.', 20],
            [$users['company_1']->id, 'CREATE', 'VACANCIES', 'vacancies', $vacancies['vacancy_frontend']->id, 'Vacante de frontend publicada.', 15],
            [$users['freelancer_2']->id, 'VIEW', 'VACANCIES', 'vacancies', $vacancies['vacancy_backend']->id, 'Freelancer consultó una vacante.', 5],
            [$users['client_1']->id, 'CREATE', 'CONTRACT_REQUESTS', 'contract_requests', null, 'Cliente envió una solicitud de contratación.', 10],
            [$users['admin']->id, 'UPDATE', 'REPORTS', 'reports', null, 'Administrador revisó un reporte.', 2],
            [$users['company_2']->id, 'LOGIN', 'AUTH', 'users', $users['company_2']->id, 'Empresa inició sesión.', 1],
        ];

        foreach ($rows as [$userId, $action, $module, $entity, $entityId, $description, $daysAgo]) {
            $log = ActivityLog::firstOrNew([
                'user_id' => $userId,
                'action' => $action,
                'module' => $module,
                'entity' => $entity,
                'entity_id' => $entityId,
                'description' => $description,
            ]);

            $createdAt = now()->subDays($daysAgo);

            $log->fill([
                'ip_address' => '127.0.0.1',
                'user_agent' => 'WorkLink Demo Seeder',
            ]);

            $log->created_at = $createdAt;
            $log->updated_at = $createdAt;
            $log->save();
        }
    }

    /**
     * Recalcula el promedio de los perfiles que recibieron reseñas.
     */
    private function recalculateProfileRatings(
        array $freelancers,
        array $companies
    ): void {
        foreach ($freelancers as $profile) {
            $average = Review::where(
                'evaluated_id',
                $profile->user_id
            )->avg('rating');

            $profile->forceFill([
                'average_rate' => $average !== null
                    ? round((float) $average, 2)
                    : null,
            ])->saveQuietly();
        }

        foreach ($companies as $profile) {
            $average = Review::where(
                'evaluated_id',
                $profile->user_id
            )->avg('rating');

            $profile->forceFill([
                'average_rate' => $average !== null
                    ? round((float) $average, 2)
                    : null,
            ])->saveQuietly();
        }
    }
}