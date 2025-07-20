<?php
/**
 * @OA\Info(
 *   title="API de Firma Electrónica SRI",
 *   version="1.0.0",
 *   description="API para firmar y autorizar documentos electrónicos ante el SRI Ecuador"
 * )
 */

/**
 * @OA\Post(
 *   path="/api/facturacion/procesar",
 *   summary="Procesa un documento XML, lo firma y autoriza",
 *   tags={"Facturación"},
 *   @OA\RequestBody(
 *     required=true,
 *     @OA\MediaType(
 *       mediaType="multipart/form-data",
 *       @OA\Schema(
 *         required={"archivo_xml", "certificado_p12", "clave_certificado"},
 *         @OA\Property(
 *           property="archivo_xml",
 *           type="string",
 *           format="binary",
 *           description="Archivo XML a firmar y autorizar"
 *         ),
 *         @OA\Property(
 *           property="certificado_p12",
 *           type="string",
 *           format="binary",
 *           description="Archivo .p12 del certificado"
 *         ),
 *         @OA\Property(
 *           property="clave_certificado",
 *           type="string",
 *           description="Contraseña del certificado"
 *         )
 *       )
 *     )
 *   ),
 *   @OA\Response(
 *     response=200,
 *     description="Documento procesado exitosamente",
 *     @OA\JsonContent(
 *       @OA\Property(property="success", type="boolean", example=true),
 *       @OA\Property(property="data", type="object",
 *         @OA\Property(property="documento_firmado", type="string", example="<xml>...</xml>"),
 *         @OA\Property(property="numero_autorizacion", type="string", example="1234567890"),
 *         @OA\Property(property="fecha_autorizacion", type="string", format="date-time"),
 *         @OA\Property(property="ruta_guardado", type="string", example="/var/www/facturacion/autorizados/documento_autorizado.xml")
 *       )
 *     )
 *   ),
 *   @OA\Response(
 *     response=400,
 *     description="Error en la solicitud",
 *     @OA\JsonContent(
 *       @OA\Property(property="success", type="boolean", example=false),
 *       @OA\Property(property="error", type="object",
 *         @OA\Property(property="code", type="string", example="INVALID_REQUEST"),
 *         @OA\Property(property="message", type="string", example="Faltan campos requeridos")
 *       )
 *     )
 *   )
 * )
 */ 