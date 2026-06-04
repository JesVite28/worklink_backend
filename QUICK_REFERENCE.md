# 📖 REFERENCIA RÁPIDA - CAMBIOS REALIZADOS

## 🎯 OBJETIVOS CUMPLIDOS

✅ Adaptación completa de la estructura de usuarios, roles y auditoría
✅ Tabla pivote `roles_usuarios` con relación Many-to-Many
✅ Sistema centralizado de auditoría (Activity Logs)
✅ Middleware mejorado para control de acceso basado en roles
✅ Validaciones con FormRequests
✅ Seeders actualizados
✅ Reutilización máxima de código existente

---

## 📋 MAPEO DE CAMBIOS

### 1️⃣ MODELOS (3 actualizados)

**app/Models/User.php**
```php
// ANTES
protected $fillable = ['name', 'email', 'password', 'branch_id'];

// DESPUÉS
protected $fillable = [
    'nombre', 'apellido', 'email', 'password_hash',
    'tipo_cuenta', 'telefono', 'foto_perfil', 'activo', 'branch_id'
];

// RELACIONES NUEVAS
public function activityLogs(): HasMany { ... }
public function hasAllRoles(array $roles): bool { ... }
```

**app/Models/Role.php**
```php
// Cambio de campo 'name' a 'nombre'
// Cambio de tabla pivote 'user_roles' a 'roles_usuarios'
public function users(): BelongsToMany {
    return $this->belongsToMany(User::class, 'roles_usuarios', ...);
}
```

**app/Models/ActivityLog.php**
```php
// NUEVOS CAMPOS
'accion', 'modulo', 'entidad', 'entidad_id', 'user_agent'

// NUEVA RELACIÓN
public function user(): BelongsTo { ... }
```

### 2️⃣ MIGRACIONES (4 creadas)

| Migración | Tabla | Campos Principales |
|-----------|-------|-------------------|
| 2024_01_01_000001 | roles | id, nombre, descripcion, creado_en |
| 2024_01_01_000002 | users | id, nombre, apellido, email, password_hash, tipo_cuenta, activo, creado_en |
| 2024_01_01_000003 | roles_usuarios | id, usuario_id, rol_id, asignado_en |
| 2024_01_01_000004 | activity_logs | id, usuario_id, accion, modulo, entidad, entidad_id, ip_address, user_agent, creado_en |

### 3️⃣ SERVICIOS (1 creado)

**app/Services/ActivityLoggerService.php**
```php
// Métodos principales
static::log()              // Genérico
static::logLogin()         // LOGIN
static::logLogout()        // LOGOUT
static::logRegister()      // REGISTER
static::logCreate()        // CREATE
static::logUpdate()        // UPDATE
static::logDelete()        // DELETE
static::getLogs()          // Consultar
```

### 4️⃣ CONTROLADORES (4 actualizados)

**UserController**
- ✅ CRUD completo con nuevos campos
- ✅ Auditoría en cada operación
- ✅ Asignación automática de roles por tipo_cuenta
- ✅ Filtros avanzados en index

**AuthController**
- ✅ Login con auditoría
- ✅ Logout con auditoría
- ✅ Register con asignación automática de roles
- ✅ Endpoint /me mejorado

**RoleController**
- ✅ CRUD completo para roles
- ✅ Validación para no eliminar roles con usuarios
- ✅ Contador de usuarios por rol

**ActivityLogController**
- ✅ Índice paginado con filtros
- ✅ Endpoint para logs de usuario específico
- ✅ Resumen de actividades

### 5️⃣ MIDDLEWARE (1 mejorado)

**app/Http/Middleware/CheckRole.php**
```php
// ANTES
middleware('role:admin')

// DESPUÉS: Soporta múltiples roles
middleware('role:admin')          // Necesita ser admin
middleware('role:admin,empresa')  // Necesita ser admin O empresa
```

### 6️⃣ REQUESTS (3 creados)

- ✅ `StoreUserRequest` - Crear usuario
- ✅ `UpdateUserRequest` - Actualizar usuario
- ✅ `StoreRoleRequest` - Crear rol

### 7️⃣ SEEDERS (2 actualizados)

**RoleSeeder**
```php
// Crea 4 roles iniciales
'user'       => Usuario estándar
'admin'      => Administrador total
'empresa'    => Cuenta empresarial
'freelancer' => Cuenta freelancer
```

**UserSeeder**
```php
// Crea 4 usuarios demo
admin@worklink.com / admin123 (Admin + Empresa)
cliente@worklink.com / cliente123 (User)
freelancer@worklink.com / freelancer123 (User + Freelancer)
empresa@worklink.com / empresa123 (User + Empresa)
```

### 8️⃣ RUTAS (routes/api.php)

```php
// PÚBLICAS
POST   /api/login              (sin auth)
POST   /api/register           (sin auth)

// AUTENTICADAS
GET    /api/me                 (JWT)
POST   /api/logout             (JWT)
POST   /api/refresh            (JWT)

// ADMIN O EMPRESA
GET    /api/users              (role:admin,empresa)
POST   /api/users              (role:admin,empresa)
PUT    /api/users/{id}         (role:admin,empresa)
DELETE /api/users/{id}         (role:admin,empresa)

// SOLO ADMIN
GET    /api/roles              (all authenticated)
POST   /api/roles              (role:admin)
PUT    /api/roles/{id}         (role:admin)
DELETE /api/roles/{id}         (role:admin)

// ACTIVITY LOGS
GET    /api/activity-logs      (all authenticated)
GET    /api/activity-logs/summary (all authenticated)
```

---

## 🔄 FLUJOS COMPLETOS

### Flujo: Registrar Nuevo Usuario
```
1. POST /api/register
   ├─ Validar datos (StoreUserRequest)
   ├─ Crear usuario con password_hash
   ├─ Asignar rol 'user' automáticamente
   ├─ Si tipo_cuenta='Empresa' → agregar rol 'empresa'
   ├─ Si tipo_cuenta='Freelancer' → agregar rol 'freelancer'
   ├─ Registrar actividad: REGISTER
   └─ Retornar usuario creado

2. Activity Log: REGISTER / AUTENTICACION / users / usuario_id / Email guardado / IP / User Agent
```

### Flujo: Login
```
1. POST /api/login
   ├─ Validar credenciales
   ├─ Generar JWT
   ├─ Cargar roles del usuario
   ├─ Registrar actividad: LOGIN
   └─ Retornar token + datos usuario

2. Activity Log: LOGIN / AUTENTICACION / users / usuario_id / Usuario inició sesión / IP / User Agent
```

### Flujo: Crear Usuario (Admin/Empresa)
```
1. POST /api/users (con JWT + role:admin,empresa)
   ├─ Validar datos (StoreUserRequest)
   ├─ Crear usuario
   ├─ Asignar roles según tipo_cuenta
   ├─ Registrar actividad: CREATE
   └─ Retornar usuario

2. Activity Log: CREATE / USUARIOS / users / usuario_id / Usuario X creado / IP / User Agent
```

---

## 🛡️ SEGURIDAD

### Passwords
```php
// ALMACENAMIENTO
password_hash = Hash::make($password)

// VALIDACIÓN
Hash::check($input, $stored_hash)
```

### Tokens JWT
```php
// En .env
JWT_SECRET=tu_secreto_aqui
JWT_ALGORITHM=HS256
JWT_TTL=60  // minutos
```

### Roles y Permisos
```php
// Nivel de ruta
Route::middleware('role:admin')->post(...);

// Nivel de controlador
$user->hasRole('admin');
$user->hasAnyRole(['admin', 'empresa']);
```

---

## 📊 EJEMPLOS DE QUERIES

### Obtener usuarios con sus roles
```php
$users = User::with('roles')->get();

// Con paginación
$users = User::with('roles')->paginate(15);
```

### Obtener logs de un usuario
```php
$logs = $user->activityLogs()->get();

// Los últimos 10
$logs = $user->activityLogs()->latest('creado_en')->take(10)->get();
```

### Obtener usuarios de un rol específico
```php
$admins = Role::where('nombre', 'admin')->first()->users;
```

### Contar acciones por módulo
```php
$stats = ActivityLog::groupBy('modulo')
    ->selectRaw('modulo, count(*) as total')
    ->get();
```

---

## ⚡ COMANDOS ÚTILES

```bash
# Migrar base de datos
php artisan migrate

# Ejecutar seeders
php artisan db:seed

# Migrar + seed
php artisan migrate:fresh --seed

# Revertir última migración
php artisan migrate:rollback

# Ver migraciones ejecutadas
php artisan migrate:status

# Tinker (REPL interactivo)
php artisan tinker

# Generar documentación Swagger
php artisan l5-swagger:generate

# Limpiar caché
php artisan cache:clear
php artisan config:clear
```

---

## 🧪 TESTS CON TINKER

```bash
php artisan tinker

# Verificar que las tablas existen
>>> DB::table('roles')->count()
4

>>> DB::table('users')->count()
4

>>> DB::table('roles_usuarios')->count()
4

# Obtener usuario con roles
>>> $user = User::with('roles')->first()
>>> $user->nombre
>>> $user->roles

# Verificar acceso
>>> $user->hasRole('admin')
>>> $user->hasAnyRole(['admin', 'empresa'])

# Ver activity logs
>>> ActivityLog::count()
>>> ActivityLog::latest('creado_en')->first()
```

---

## 🔗 TABLA DE RELACIONES

```
users
  ├─ ONE TO MANY → activity_logs
  └─ MANY TO MANY → roles (via roles_usuarios)

roles
  ├─ MANY TO MANY → users (via roles_usuarios)
  └─ ONE TO MANY → role_permissions (existente)

roles_usuarios (pivote)
  ├─ MANY TO ONE → users
  └─ MANY TO ONE → roles

activity_logs
  └─ MANY TO ONE → users
```

---

## 📝 CONVENCIONES USADAS

| Aspecto | Convención | Ejemplo |
|---------|-----------|---------|
| Campos | snake_case | `usuario_id`, `creado_en`, `password_hash` |
| Tablas | plural | `users`, `roles`, `activity_logs` |
| Modelos | singular/PascalCase | `User`, `Role`, `ActivityLog` |
| Métodos | camelCase | `logLogin()`, `hasRole()` |
| Constantes | UPPER_SNAKE | `'LOGIN'`, `'CREATE'` |
| Booleanos | prefijo `is_` o `has_` | `activo`, `hasRole()` |

---

## 🚀 LISTA DE VERIFICACIÓN PRE-PRODUCCIÓN

- [ ] Ejecutar `php artisan migrate`
- [ ] Ejecutar `php artisan db:seed`
- [ ] Verificar que la tabla `roles` tiene 4 registros
- [ ] Verificar que la tabla `users` tiene 4 registros
- [ ] Probar login: `admin@worklink.com` / `admin123`
- [ ] Probar registro de nuevo usuario
- [ ] Verificar que se registran logs de actividad
- [ ] Verificar que el middleware `role:` funciona
- [ ] Verificar que los tokens JWT se generan correctamente
- [ ] Ejecutar tests (si existen)
- [ ] Revisar logs en `storage/logs/`

---

**Última actualización: Junio 2026**
**Estado: ✅ COMPLETO**
