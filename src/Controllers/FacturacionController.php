<?php
namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use OpenApi\Annotations as OA;
use App\Services\SignatureService;
use App\Services\SriWebService;


class FacturacionController
{
    private $directorioAutorizados;
    private $directorioTemporal;
    private $sriService;
    private $signatureService;

    public function __construct()
    {
        // Rutas fijas dentro del contenedor (los volúmenes mapean a las rutas del host)
        $this->directorioAutorizados = '/var/www/facturacion/autorizados';
        $this->directorioTemporal = $_ENV['UPLOAD_DIR'] ?? '/var/www/html/var/tmp';

        // Remover la barra final si existe para consistencia
        $this->directorioAutorizados = rtrim($this->directorioAutorizados, '/');
        $this->directorioTemporal = rtrim($this->directorioTemporal, '/');

        // Instanciación de servicios
        $this->sriService = new SriWebService();
        $this->signatureService = new SignatureService();

        // Asegurar que los directorios existan al inicio
        $this->crearDirectorios();
    }

    /**
     * @OA\Post(
     * path="/api/facturacion/procesar",
     * summary="Procesa un documento XML, lo firma y autoriza con el SRI",
     * tags={"Facturación"},
     * @OA\RequestBody(
     * required=true,
     * @OA\MediaType(
     * mediaType="multipart/form-data",
     * @OA\Schema(
     * required={"archivo_xml", "certificado_p12", "clave_certificado", "tipo_documento"},
     * @OA\Property(
     * property="archivo_xml",
     * type="string",
     * format="binary",
     * description="Archivo XML a firmar y autorizar"
     * ),
     * @OA\Property(
     * property="certificado_p12",
     * type="string",
     * format="binary",
     * description="Archivo .p12 del certificado"
     * ),
     * @OA\Property(
     * property="clave_certificado",
     * type="string",
     * description="Contraseña del certificado .p12"
     * ),
     * @OA\Property(
     * property="tipo_documento",
     * type="string",
     * description="Tipo de documento (ej. 'factura', 'notaCredito', 'guiaRemision')"
     * )
     * )
     * )
     * ),
     * @OA\Response(
     * response=200,
     * description="Documento procesado y autorizado exitosamente",
     * @OA\JsonContent(
     * @OA\Property(property="success", type="boolean", example=true),
     * @OA\Property(property="message", type="string", example="Documento autorizado exitosamente"),
     * @OA\Property(property="data", type="object",
     * @OA\Property(property="estado", type="string", example="AUTORIZADO"),
     * @OA\Property(property="numero_autorizacion", type="string", example="1234567890"),
     * @OA\Property(property="fecha_autorizacion", type="string", example="18/07/2025 15:00:00"),
     * @OA\Property(property="xml_autorizado_base64", type="string", description="XML autorizado completo del SRI en base64 (incluye estado, número autorización, fecha, etc.)"),
     * @OA\Property(property="ruta_archivo_guardado", type="string", description="Ruta donde se guardó el XML autorizado")
     * )
     * )
     * ),
     * @OA\Response(
     * response=400,
     * description="Error en el procesamiento del documento",
     * @OA\JsonContent(
     * @OA\Property(property="success", type="boolean", example=false),
     * @OA\Property(property="error", type="object",
     * @OA\Property(property="code", type="string", example="ERROR_FIRMA"),
     * @OA\Property(property="message", type="string", example="Error al firmar el XML")
     * )
     * )
     * ),
     * @OA\Response(
     * response=500,
     * description="Error interno del servidor",
     * @OA\JsonContent(
     * @OA\Property(property="success", type="boolean", example=false),
     * @OA\Property(property="error", type="object",
     * @OA\Property(property="code", type="string", example="SERVER_ERROR"),
     * @OA\Property(property="message", type="string", example="Ha ocurrido un error inesperado")
     * )
     * )
     * )
     * )
     */
    public function procesarFactura(Request $request, Response $response): Response
    {
        $xmlPath = null;
        $certPath = null;
        try {
            // Validar que los archivos y datos necesarios existan
            $uploadedFiles = $request->getUploadedFiles();
            $postData = $request->getParsedBody();

            if (empty($uploadedFiles['archivo_xml']) || empty($uploadedFiles['certificado_p12']) || empty($postData['clave_certificado']) || empty($postData['tipo_documento'])) {
                return $this->errorResponse($response, 'Faltan archivos o datos requeridos.', 'MISSING_DATA');
            }

            $archivoXml = $uploadedFiles['archivo_xml'];
            $certificadoP12 = $uploadedFiles['certificado_p12'];
            $claveCertificado = $postData['clave_certificado'];
            $tipoDocumento = $postData['tipo_documento'];

            // Mover archivos temporales
            $xmlPath = $this->directorioTemporal . '/' . uniqid('xml_') . '.xml';
            $certPath = $this->directorioTemporal . '/' . uniqid('cert_') . '.p12';

            $archivoXml->moveTo($xmlPath);
            $certificadoP12->moveTo($certPath);

            $xmlContent = file_get_contents($xmlPath);

            // DEBUG: Log del XML recibido para diagnosticar problema de clave de acceso
            error_log("=== DEBUG XML RECIBIDO ===");
            error_log("XML Content: " . substr($xmlContent, 0, 500) . "...");
            if (preg_match('/<claveAcceso>(.*?)<\/claveAcceso>/', $xmlContent, $debugMatches)) {
                error_log("Clave de acceso en XML recibido: " . $debugMatches[1]);
            } else {
                error_log("ERROR: No se encontró claveAcceso en XML recibido");
            }
            error_log("=== FIN DEBUG XML RECIBIDO ===");


            // 1. Firmar el XML
            error_log("=== DEBUG PROCESO FIRMA ===");
            error_log("Iniciando proceso de firma...");
            try {
                $xmlFirmado = $this->signatureService->firmar(
                    $xmlContent,
                    $certPath,
                    $claveCertificado,
                    $tipoDocumento // Pasar el tipo de documento aquí
                );
                error_log("✅ XML firmado exitosamente. Longitud: " . strlen($xmlFirmado));
            } catch (\Exception $e) {
                error_log("❌ ERROR AL FIRMAR: " . $e->getMessage());
                return $this->errorResponse($response, 'Error al firmar el XML: ' . $e->getMessage(), 'ERROR_FIRMA');
            }

            // Extraer claveAcceso del XML firmado
            if (!preg_match('/<claveAcceso>(.*?)<\/claveAcceso>/', $xmlFirmado, $matches)) {
                return $this->errorResponse($response, 'No se pudo extraer la clave de acceso del XML firmado.', 'CLAVE_ACCESO_NO_ENCONTRADA');
            }
            $claveAcceso = $matches[1];

            // 2. Enviar a recepción del SRI
            error_log("=== DEBUG PROCESO RECEPCION SRI ===");
            error_log("Enviando XML al SRI para recepción...");
            try {
                $recepcionResult = $this->sriService->enviarRecepcion($xmlFirmado);
                error_log("✅ XML recibido por SRI exitosamente");
                // Si la recepción no es 'RECIBIDA', la excepción ya debería haber sido lanzada por SriWebService
                // Si llegó aquí, fue RECIBIDA.
            } catch (\Exception $e) {
                error_log("❌ ERROR EN RECEPCION SRI: " . $e->getMessage());

                // Parsear los mensajes de error del SRI si están en JSON
                $errorDetails = [];
                if (strpos($e->getMessage(), '[{') !== false) {
                    $jsonStart = strpos($e->getMessage(), '[{');
                    $jsonString = substr($e->getMessage(), $jsonStart);
                    $jsonEnd = strrpos($jsonString, '}]') + 2;
                    $jsonString = substr($jsonString, 0, $jsonEnd);

                    try {
                        $errorDetails = json_decode($jsonString, true);
                    } catch (\Exception $jsonError) {
                        // Si no se puede parsear, usar el mensaje original
                    }
                }

                return $this->errorResponse($response, 'Error en la recepción del SRI: ' . $e->getMessage(), 'ERROR_RECEPCION_SRI', 400, [
                    'sri_error_details' => $errorDetails
                ]);
            }

            // 3. Consultar autorización (con reintentos)
            error_log("=== DEBUG PROCESO AUTORIZACION SRI ===");
            error_log("Consultando autorización para clave: " . $claveAcceso);
            $maxIntentos = 10; // Puedes ajustar este valor
            $intervalo = 5; // Segundos entre intentos

            $autorizacionExitosa = false;
            $autorizacionResult = null;

            for ($intento = 1; $intento <= $maxIntentos; $intento++) {
                // Esperar antes de consultar, excepto en el primer intento
                if ($intento > 1) {
                    sleep($intervalo);
                }

                try {
                    $autorizacionResult = $this->sriService->consultarAutorizacion($claveAcceso, $xmlFirmado);

                    if ($autorizacionResult['estado'] == 'AUTORIZADO') {
                        $autorizacionExitosa = true;
                        break; // Salir del bucle si se autorizó
                    } elseif ($autorizacionResult['estado'] == 'EN PROCESO') {
                        // Continuar al siguiente intento
                        error_log("Intento {$intento}: Comprobante EN PROCESO para clave {$claveAcceso}");
                    } elseif ($autorizacionResult['estado'] == 'NO AUTORIZADO' || $autorizacionResult['estado'] == 'DEVUELTA') {
                        // Error definitivo, salir del bucle
                        $mensajes = $autorizacionResult['mensajes'] ? json_encode($autorizacionResult['mensajes']) : 'Sin detalles';
                        return $this->errorResponse($response, "Comprobante NO AUTORIZADO o DEVUELTA por el SRI. Mensajes: {$mensajes}", 'COMPROBANTE_NO_AUTORIZADO', 400, ['sri_mensajes' => $autorizacionResult['mensajes']]);
                    } else {
                        // Cualquier otro estado desconocido
                        $mensajes = $autorizacionResult['mensajes'] ? json_encode($autorizacionResult['mensajes']) : 'Sin detalles';
                        return $this->errorResponse($response, "Estado desconocido del comprobante: {$autorizacionResult['estado']}. Mensajes: {$mensajes}", 'ESTADO_DESCONOCIDO_SRI', 400, ['sri_mensajes' => $autorizacionResult['mensajes']]);
                    }
                } catch (\Exception $e) {
                    error_log("Error al consultar autorización (intento {$intento}): " . $e->getMessage());
                    // Si es un error SOAP temporal, reintentar. Si no, lanzar.
                    if ($intento == $maxIntentos) {
                        return $this->errorResponse($response, 'Error al consultar autorización después de varios intentos: ' . $e->getMessage(), 'ERROR_CONSULTA_SRI');
                    }
                }
            }

            if (!$autorizacionExitosa) {
                return $this->errorResponse($response, 'No se pudo autorizar el comprobante después de los reintentos.', 'TIMEOUT_AUTORIZACION');
            }

            // Preparar respuesta exitosa
            $responseData = [
                'estado' => $autorizacionResult['estado'],
                'numero_autorizacion' => $autorizacionResult['numero_autorizacion'],
                'fecha_autorizacion' => $autorizacionResult['fecha_autorizacion'],
                'ambiente' => $autorizacionResult['ambiente'],
                'xml_autorizado_base64' => base64_encode($autorizacionResult['comprobante']), // Codificar el XML en base64
                'ruta_archivo_guardado' => $autorizacionResult['ruta_archivo'] // Información sobre dónde se guardó
            ];

            $response->getBody()->write(json_encode([
                'success' => true,
                'message' => 'Documento autorizado exitosamente',
                'data' => $responseData
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);

        } catch (\Exception $e) {
            error_log("Error general en procesarFactura: " . $e->getMessage());
            return $this->errorResponse($response, 'Ha ocurrido un error inesperado: ' . $e->getMessage(), 'SERVER_ERROR', 500);
        } finally {
            // Limpiar archivos temporales
            if ($xmlPath && file_exists($xmlPath)) {
                unlink($xmlPath);
            }
            if ($certPath && file_exists($certPath)) {
                unlink($certPath);
            }
        }
    }

    /**
     * @OA\Post(
     * path="/api/facturacion/firmar",
     * summary="Firma un documento XML sin enviarlo al SRI",
     * tags={"Facturación"},
     * @OA\RequestBody(
     * required=true,
     * @OA\MediaType(
     * mediaType="multipart/form-data",
     * @OA\Schema(
     * required={"archivo_xml", "certificado_p12", "clave_certificado", "tipo_documento"},
     * @OA\Property(
     * property="archivo_xml",
     * type="string",
     * format="binary",
     * description="Archivo XML a firmar"
     * ),
     * @OA\Property(
     * property="certificado_p12",
     * type="string",
     * format="binary",
     * description="Archivo .p12 del certificado"
     * ),
     * @OA\Property(
     * property="clave_certificado",
     * type="string",
     * description="Contraseña del certificado"
     * ),
     * @OA\Property(
     * property="tipo_documento",
     * type="string",
     * description="Tipo de documento (ej. 'factura', 'notaCredito', 'guiaRemision')"
     * )
     * )
     * )
     * ),
     * @OA\Response(
     * response=200,
     * description="Documento firmado exitosamente",
     * @OA\JsonContent(
     * @OA\Property(property="success", type="boolean", example=true),
     * @OA\Property(property="data", type="object",
     * @OA\Property(property="documento_firmado_base64", type="string", description="XML firmado en base64")
     * )
     * )
     * ),
     * @OA\Response(
     * response=400,
     * description="Error en la solicitud",
     * @OA\JsonContent(
     * @OA\Property(property="success", type="boolean", example=false),
     * @OA\Property(property="error", type="object",
     * @OA\Property(property="code", type="string", example="INVALID_REQUEST"),
     * @OA\Property(property="message", type="string", example="Faltan campos requeridos")
     * )
     * )
     * )
     * )
     */
    public function firmarDocumento(Request $request, Response $response): Response
    {
        $xmlPath = null;
        $certPath = null;
        try {
            // Verificar si se recibieron los archivos y datos necesarios
            $uploadedFiles = $request->getUploadedFiles();
            $postData = $request->getParsedBody();

            if (empty($uploadedFiles['archivo_xml']) || empty($uploadedFiles['certificado_p12']) || empty($postData['clave_certificado']) || empty($postData['tipo_documento'])) {
                return $this->errorResponse($response, 'Faltan archivos o datos requeridos para la firma.', 'MISSING_SIGN_DATA', 400);
            }

            $xmlFile = $uploadedFiles['archivo_xml'];
            $certFile = $uploadedFiles['certificado_p12'];
            $claveCertificado = $postData['clave_certificado'];
            $tipoDocumento = $postData['tipo_documento'];

            // Crear directorio temporal y mover archivos
            $tempDir = $this->directorioTemporal . '/' . uniqid('sign_');
            if (!mkdir($tempDir, 0755, true) && !is_dir($tempDir)) {
                return $this->errorResponse($response, 'No se pudo crear el directorio temporal para la firma.', 'TEMP_DIR_ERROR', 500);
            }

            $xmlPath = $tempDir . '/documento.xml';
            $certPath = $tempDir . '/certificado.p12';

            $xmlFile->moveTo($xmlPath);
            $certFile->moveTo($certPath);

            // Validar que los archivos se hayan guardado correctamente
            if (!file_exists($xmlPath) || !file_exists($certPath)) {
                return $this->errorResponse($response, 'Error al guardar los archivos temporales para la firma.', 'FILE_SAVE_ERROR', 500);
            }

            $xmlContent = file_get_contents($xmlPath);

            // Firmar el documento
            $xmlFirmado = $this->signatureService->firmar(
                $xmlContent,
                $certPath,
                $claveCertificado,
                $tipoDocumento
            );

            // Respuesta exitosa
            $responseData = [
                'success' => true,
                'data' => [
                    'documento_firmado_base64' => base64_encode($xmlFirmado) // Codificar el XML firmado en base64
                ]
            ];

            $response->getBody()->write(json_encode($responseData));
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(200);

        } catch (\Exception $e) {
            error_log("Error al firmar el documento: " . $e->getMessage());
            return $this->errorResponse(
                $response,
                'Error al firmar el documento: ' . $e->getMessage(),
                'SIGNING_ERROR',
                500,
                ['trace' => $e->getTraceAsString()]
            );
        } finally {
            // Limpiar archivos temporales
            if ($xmlPath && file_exists($xmlPath)) {
                unlink($xmlPath);
            }
            if ($certPath && file_exists($certPath)) {
                unlink($certPath);
            }
            if (isset($tempDir) && is_dir($tempDir)) {
                $this->limpiarTemporales($tempDir); // Asegurarse de limpiar el directorio temporal
            }
        }
    }

    /**
     * Crea los directorios necesarios si no existen.
     * @return void
     * @throws \Exception Si no se pueden crear los directorios
     */
    private function crearDirectorios(): void
    {
        if (!is_dir($this->directorioAutorizados)) {
            if (!mkdir($this->directorioAutorizados, 0755, true) && !is_dir($this->directorioAutorizados)) {
                error_log("Error al crear directorio de autorizados: {$this->directorioAutorizados}");
                throw new \Exception("No se pudo crear el directorio de autorizados: {$this->directorioAutorizados}");
            }
        }
        if (!is_dir($this->directorioTemporal)) {
            if (!mkdir($this->directorioTemporal, 0755, true) && !is_dir($this->directorioTemporal)) {
                error_log("Error al crear directorio temporal: {$this->directorioTemporal}");
                throw new \Exception("No se pudo crear el directorio temporal: {$this->directorioTemporal}");
            }
        }
    }

    /**
     * Elimina los archivos y directorios temporales de forma recursiva.
     * @param string $directorio La ruta del directorio a limpiar.
     * @return void
     */
    private function limpiarTemporales(string $directorio): void
    {
        if (is_dir($directorio)) {
            $files = glob($directorio . '/*');
            foreach ($files as $file) {
                is_dir($file) ? $this->limpiarTemporales($file) : unlink($file);
            }
            rmdir($directorio);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/facturacion/enviar-sri",
     *     summary="Envía un documento XML ya firmado al SRI para recepción y autorización",
     *     tags={"Facturación"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"xml_firmado_base64", "clave_acceso"},
     *             @OA\Property(
     *                 property="xml_firmado_base64",
     *                 type="string",
     *                 description="Contenido del XML firmado en formato base64"
     *             ),
     *             @OA\Property(
     *                 property="clave_acceso",
     *                 type="string",
     *                 description="Clave de acceso del documento XML"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Documento enviado y autorizado exitosamente por el SRI",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Documento autorizado exitosamente por el SRI"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="estado", type="string", example="AUTORIZADO"),
     *                 @OA\Property(property="numero_autorizacion", type="string", example="1234567890"),
     *                 @OA\Property(property="fecha_autorizacion", type="string", example="18/07/2025 15:00:00"),
     *                 @OA\Property(property="xml_autorizado_base64", type="string", description="XML autorizado completo del SRI en base64 (incluye estado, número autorización, fecha, etc.)"),
     *                 @OA\Property(property="ruta_archivo_guardado", type="string", description="Ruta donde se guardó el XML autorizado")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Error en la solicitud o en la comunicación con el SRI",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="error", type="object",
     *                 @OA\Property(property="code", type="string", example="MISSING_DATA"),
     *                 @OA\Property(property="message", type="string", example="Faltan datos requeridos")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error interno del servidor",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="error", type="object",
     *                 @OA\Property(property="code", type="string", example="SERVER_ERROR"),
     *                 @OA\Property(property="message", type="string", example="Ha ocurrido un error inesperado")
     *             )
     *         )
     *     )
     * )
     */
    public function enviarASri(Request $request, Response $response): Response
    {
        try {
            $postData = $request->getParsedBody();

            if (empty($postData['xml_firmado_base64']) || empty($postData['clave_acceso'])) {
                return $this->errorResponse($response, 'Faltan datos requeridos: xml_firmado_base64 y clave_acceso.', 'MISSING_DATA');
            }

            $xmlFirmado = base64_decode($postData['xml_firmado_base64']);
            $claveAcceso = $postData['clave_acceso'];

            // 1. Enviar a recepción del SRI
            try {
                $recepcionResult = $this->sriService->enviarRecepcion($xmlFirmado);
                // Si la recepción no es 'RECIBIDA', la excepción ya debería haber sido lanzada por SriWebService
                // Si llegó aquí, fue RECIBIDA.
            } catch (\Exception $e) {
                return $this->errorResponse($response, 'Error en la recepción del SRI: ' . $e->getMessage(), 'ERROR_RECEPCION_SRI');
            }

            // 2. Consultar autorización (con reintentos)
            $maxIntentos = 10; // Puedes ajustar este valor
            $intervalo = 5; // Segundos entre intentos

            $autorizacionExitosa = false;
            $autorizacionResult = null;

            for ($intento = 1; $intento <= $maxIntentos; $intento++) {
                // Esperar antes de consultar, excepto en el primer intento
                if ($intento > 1) {
                    sleep($intervalo);
                }

                try {
                    $autorizacionResult = $this->sriService->consultarAutorizacion($claveAcceso, $xmlFirmado);

                    if ($autorizacionResult['estado'] == 'AUTORIZADO') {
                        $autorizacionExitosa = true;
                        break; // Salir del bucle si se autorizó
                    } elseif ($autorizacionResult['estado'] == 'EN PROCESO') {
                        // Continuar al siguiente intento
                        error_log("Intento {$intento}: Comprobante EN PROCESO para clave {$claveAcceso}");
                    } elseif ($autorizacionResult['estado'] == 'NO AUTORIZADO' || $autorizacionResult['estado'] == 'DEVUELTA') {
                        // Error definitivo, salir del bucle
                        $mensajes = $autorizacionResult['mensajes'] ? json_encode($autorizacionResult['mensajes']) : 'Sin detalles';
                        return $this->errorResponse($response, "Comprobante NO AUTORIZADO o DEVUELTA por el SRI. Mensajes: {$mensajes}", 'COMPROBANTE_NO_AUTORIZADO', 400, ['sri_mensajes' => $autorizacionResult['mensajes']]);
                    } else {
                        // Cualquier otro estado desconocido
                        $mensajes = $autorizacionResult['mensajes'] ? json_encode($autorizacionResult['mensajes']) : 'Sin detalles';
                        return $this->errorResponse($response, "Estado desconocido del comprobante: {$autorizacionResult['estado']}. Mensajes: {$mensajes}", 'ESTADO_DESCONOCIDO_SRI', 400, ['sri_mensajes' => $autorizacionResult['mensajes']]);
                    }
                } catch (\Exception $e) {
                    error_log("Error al consultar autorización (intento {$intento}): " . $e->getMessage());
                    // Si es un error SOAP temporal, reintentar. Si no, lanzar.
                    if ($intento == $maxIntentos) {
                        return $this->errorResponse($response, 'Error al consultar autorización después de varios intentos: ' . $e->getMessage(), 'ERROR_CONSULTA_SRI');
                    }
                }
            }

            if (!$autorizacionExitosa) {
                return $this->errorResponse($response, 'No se pudo autorizar el comprobante después de los reintentos.', 'TIMEOUT_AUTORIZACION');
            }

            // Preparar respuesta exitosa
            $responseData = [
                'estado' => $autorizacionResult['estado'],
                'numero_autorizacion' => $autorizacionResult['numero_autorizacion'],
                'fecha_autorizacion' => $autorizacionResult['fecha_autorizacion'],
                'ambiente' => $autorizacionResult['ambiente'],
                'xml_autorizado_base64' => base64_encode($autorizacionResult['comprobante']), // Codificar el XML en base64
                'ruta_archivo_guardado' => $autorizacionResult['ruta_archivo'] // Información sobre dónde se guardó
            ];

            $response->getBody()->write(json_encode([
                'success' => true,
                'message' => 'Documento autorizado exitosamente por el SRI',
                'data' => $responseData
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);

        } catch (\Exception $e) {
            error_log("Error general en enviarASri: " . $e->getMessage());
            return $this->errorResponse($response, 'Ha ocurrido un error inesperado: ' . $e->getMessage(), 'SERVER_ERROR', 500);
        }
    }

    /**
     * Devuelve una respuesta de error estandarizada en formato JSON.
     * @param Response $response Objeto de respuesta PSR-7.
     * @param string $message Mensaje de error.
     * @param string $code Código de error.
     * @param int $status Código de estado HTTP.
     * @param array $additionalData Datos adicionales para incluir en el error.
     * @return Response Objeto de respuesta PSR-7 con el error.
     */
    private function errorResponse(
        Response $response,
        string $message,
        string $code = 'ERROR',
        int $status = 400,
        array $additionalData = []
    ): Response {
        $errorData = [
            'success' => false,
            'error' => [
                'code' => $code,
                'message' => $message,
            ]
        ];

        if (!empty($additionalData)) {
            $errorData['error'] = array_merge($errorData['error'], $additionalData);
        }

        $response->getBody()->write(json_encode($errorData));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }
}
