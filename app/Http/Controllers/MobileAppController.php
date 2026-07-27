<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * @OA\Tag(
 *     name="Mobile App",
 *     description="Distribución y metadata de la app móvil"
 * )
 */
class MobileAppController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/public/mobile/android/latest",
     *     operationId="mobileAndroidLatest",
     *     tags={"Mobile App"},
     *     summary="Obtener metadata de la app Android",
     *     description="Retorna versión, disponibilidad y URL de descarga del APK Android.",
     *
     *     @OA\Response(
     *         response=200,
     *         description="Metadata obtenida exitosamente"
     *     )
     * )
     */
    public function androidLatest(): JsonResponse
    {
        $androidConfig = config('mobile_app.android', []);
        $relativePath = (string) ($androidConfig['apk_path'] ?? 'apps/worklink-android.apk');
        $disk = Storage::disk('public');
        $available = $disk->exists($relativePath);

        $fileSizeBytes = null;
        $updatedAt = null;

        if ($available) {
            $fileSizeBytes = $disk->size($relativePath);
            $updatedAt = date('c', $disk->lastModified($relativePath));
        }

        return response()->json([
            'success' => true,
            'message' => $available
                ? 'Información de la app Android obtenida exitosamente.'
                : 'APK Android no disponible todavía en el servidor.',
            'data' => [
                'platform' => 'android',
                'available' => $available,
                'version_name' => (string) ($androidConfig['version_name'] ?? '1.0.0'),
                'version_code' => (int) ($androidConfig['version_code'] ?? 1),
                'min_supported_version_code' => (int) ($androidConfig['min_supported_version_code'] ?? 1),
                'force_update' => (bool) ($androidConfig['force_update'] ?? false),
                'changelog' => (string) ($androidConfig['changelog'] ?? ''),
                'sha256' => $androidConfig['sha256'] ?: null,
                'download_url' => route('downloads.android'),
                'download_name' => (string) ($androidConfig['download_name'] ?? 'worklink-android.apk'),
                'file_size_bytes' => $fileSizeBytes,
                'updated_at' => $updatedAt,
            ],
        ]);
    }

    /**
     * @OA\Get(
     *     path="/downloads/android",
     *     operationId="downloadAndroidApk",
     *     tags={"Mobile App"},
     *     summary="Descargar APK Android",
     *     description="Descarga el archivo APK Android publicado por el backend.",
     *
     *     @OA\Response(
     *         response=200,
     *         description="Descarga iniciada"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="APK no encontrada"
     *     )
     * )
     */
    public function downloadAndroid(Request $request): BinaryFileResponse|JsonResponse
    {
        $androidConfig = config('mobile_app.android', []);
        $relativePath = (string) ($androidConfig['apk_path'] ?? 'apps/worklink-android.apk');
        $downloadName = (string) ($androidConfig['download_name'] ?? 'worklink-android.apk');
        $disk = Storage::disk('public');

        if (! $disk->exists($relativePath)) {
            $message = 'APK Android no encontrada. Sube el archivo a storage/app/public/'.$relativePath;

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $message,
                ], 404);
            }

            abort(404, $message);
        }

        return response()->download(
            $disk->path($relativePath),
            $downloadName,
            [
                'Content-Type' => 'application/vnd.android.package-archive',
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
                'Pragma' => 'no-cache',
                'Expires' => '0',
            ]
        );
    }
}
