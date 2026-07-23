<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

/**
 * @OA\Tag(
 *     name="Legal Documents",
 *     description="Endpoints públicos para documentos legales"
 * )
 */
class LegalDocumentController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/public/legal/terms-and-conditions",
     *     operationId="publicTermsAndConditions",
     *     tags={"Legal Documents"},
     *     summary="Obtener Términos y Condiciones",
     *     description="Retorna el contenido de Términos y Condiciones almacenado en el backend.",
     *
     *     @OA\Response(
     *         response=200,
     *         description="Documento obtenido exitosamente"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Documento no encontrado"
     *     )
     * )
     */
    public function publicTermsAndConditions(Request $request)
    {
        $markdownPath = resource_path('legal/terms-and-conditions.md');
        $pdfPath = resource_path('legal/terms-and-conditions.pdf');

        if (! File::exists($markdownPath) && ! File::exists($pdfPath)) {
            return response()->json([
                'success' => false,
                'message' => 'Documento de términos y condiciones no encontrado. Agrega un archivo .md o .pdf en resources/legal.',
            ], 404);
        }

        if (! File::exists($markdownPath) && File::exists($pdfPath)) {
            return response()->json([
                'success' => true,
                'message' => 'Términos y condiciones disponibles en PDF',
                'data' => [
                    'document_key' => 'terms-and-conditions',
                    'format' => 'pdf',
                    'updated_at' => date('c', File::lastModified($pdfPath)),
                    'file_size' => File::size($pdfPath),
                    'pdf_url' => url('/api/public/legal/terms-and-conditions/pdf'),
                ],
            ]);
        }

        $content = File::get($markdownPath);
        $updatedAt = date('c', File::lastModified($markdownPath));
        $format = strtolower((string) $request->query('format', 'markdown'));

        if ($format === 'text') {
            return response($content, 200, [
                'Content-Type' => 'text/plain; charset=UTF-8',
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Términos y condiciones obtenidos exitosamente',
            'data' => [
                'document_key' => 'terms-and-conditions',
                'format' => 'markdown',
                'updated_at' => $updatedAt,
                'content' => $content,
            ],
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/public/legal/terms-and-conditions/pdf",
     *     operationId="publicTermsAndConditionsPdf",
     *     tags={"Legal Documents"},
     *     summary="Obtener Términos y Condiciones en PDF",
     *     description="Retorna el archivo PDF de Términos y Condiciones.",
     *
     *     @OA\Response(
     *         response=200,
    *         description="PDF obtenido exitosamente"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="PDF no encontrado"
     *     )
     * )
     */
    public function publicTermsAndConditionsPdf()
    {
        $path = resource_path('legal/terms-and-conditions.pdf');

        if (! File::exists($path)) {
            return response()->json([
                'success' => false,
                'message' => 'PDF de términos y condiciones no encontrado.',
            ], 404);
        }

        return response()->file($path, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="terms-and-conditions.pdf"',
        ]);
    }
}
