# Barracuda Backend

Este repositorio contiene la API REST de la aplicación Barracuda.
A continuación se describe cómo preparar un entorno de desarrollo desde cero para que cualquier miembro del equipo pueda clonar y arrancar el proyecto.

---

## Requisitos previos

Antes de empezar asegúrate de tener instalados:

1. **PHP 8.1+** (aprox.)
2. **Composer** — el gestor de dependencias de PHP. [https://getcomposer.org](https://getcomposer.org)
3. **MySQL**
4. `git` para clonar el repositorio.

> En Windows se recomienda usar [Laragon](https://laragon.org) o [XAMPP](https://www.apachefriends.org) para simplificar el stack.

---

## Inicialización del proyecto

```bash
# 1. clonar el repositorio (cualquier rama de trabajo)
git clone <url-del-repositorio> barracuda-backend
cd barracuda-backend

# 2. instalar dependencias PHP
composer install

# 3. copiar el archivo de entorno y generar una clave de aplicación
cp .env.example .env        # (o copy en Windows)
php artisan key:generate

# 4. configurar variables de entorno
#    edita `.env` y ajusta los valores:
#    - DB_CONNECTION, DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD
#    - JWT_SECRET (generado automáticamente con "php artisan jwt:secret")
#    - otras variables según necesites (MAIL, CACHE, etc.)

php artisan jwt:secret

# 5. crear la base de datos vacía en MySQL
#    (ej. mediante cliente, phpMyAdmin, o `mysql -u root -p -e "CREATE DATABASE barracuda;"`)

# 6. ejecutar migraciones y seeders
php artisan migrate
php artisan db:seed

# 7. arrancar el servidor de desarrollo
php artisan serve
```

> El comando `php artisan serve` levantará la aplicación en `http://127.0.0.1:8000`. La documentación Swagger está disponible en `http://127.0.0.1:8000/api/documentation`.

---

## Uso diario

- **Crear usuarios**: usar el endpoint `/api/users` con un token JWT. Los roles y permisos se cargan automáticamente en el seeder.
- **Logs de actividad**: consulte `/api/activity-logs` (requiere permiso `read_activity_logs`).
- **Sucursales**: rutas `GET /api/branches`, `POST /api/branches`, etc. Requieren los permisos `*\_branches`.

Para ejecutar las pruebas:

```bash
./vendor/bin/pest
```

---

## Notas adicionales

- Nombre de la base de datos barracuda_backend
- Si la base de datos ya existe y quieres reiniciarla:

  ```bash
  php artisan migrate:fresh --seed
  ```

- Cualquier archivo nuevo debe seguir el estándar PSR‑12 y, al hacer commits, ejecutar `php artisan test` para no romper la suite.
- Para regenerar la documentación Swagger: `php artisan l5-swagger:generate`.

---

## Distribución Android (sin Play Store)

Este backend ya incluye rutas para descargar la app Android (APK) y consultar metadata de versión.

1. Sube el APK en `storage/app/public/apps/worklink-android.apk`.
2. Si no existe, crea el enlace público una vez:

```bash
php artisan storage:link
```

3. Configura (opcional) las variables en `.env`:

- `ANDROID_APK_PATH=apps/worklink-android.apk`
- `ANDROID_APK_DOWNLOAD_NAME=worklink-android.apk`
- `ANDROID_APP_VERSION_NAME=1.0.0`
- `ANDROID_APP_VERSION_CODE=1`
- `ANDROID_APP_MIN_SUPPORTED_VERSION_CODE=1`
- `ANDROID_APP_FORCE_UPDATE=false`
- `ANDROID_APP_CHANGELOG=`
- `ANDROID_APP_SHA256=`

### Endpoints listos

- `GET /downloads/android` descarga directa del APK.
- `GET /api/public/mobile/android/latest` metadata para la web/app móvil (versión, URL de descarga, etc.).

