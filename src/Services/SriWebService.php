<?php

namespace App\Services;



/**
 * Servicio para interactuar con los web services del SRI
 */
class SriWebService
{
    // Puedes cambiar estas URLs a las de producción si ambiente = '2'
    private $webRecepcion = 'https://celcer.sri.gob.ec/comprobantes-electronicos-ws/RecepcionComprobantesOffline?wsdl';
    private $webAutoriza = 'https://celcer.sri.gob.ec/comprobantes-electronicos-ws/AutorizacionComprobantesOffline?wsdl';
    private $ambiente = '1'; // 1: Pruebas, 2: Producción (Cambiar para producción)

    public function __construct(string $ambiente = '1')
    {
        $this->ambiente = $ambiente;
        if ($this->ambiente == '2') {
            $this->webRecepcion = 'https://cel.sri.gob.ec/comprobantes-electronicos-ws/RecepcionComprobantesOffline?wsdl';
            $this->webAutoriza = 'https://cel.sri.gob.ec/comprobantes-electronicos-ws/AutorizacionComprobantesOffline?wsdl';
        }
    }

    /**
     * Envía un documento al SRI para su recepción
     * @param string $contenidoXml El contenido del XML firmado.
     * @return array Un array con el estado y los mensajes si hay errores.
     * @throws Exception Si hay un error en la comunicación SOAP o la recepción no es 'RECIBIDA'.
     */
    public function enviarRecepcion(string $contenidoXml): array
    {
        try {
            $webService = new \SoapClient($this->webRecepcion);
            $resultado = $webService->validarComprobante(["xml" => $contenidoXml]);

            $respuesta = $resultado->RespuestaRecepcionComprobante;

            if ($respuesta->estado === "RECIBIDA") {
                return ['estado' => 'RECIBIDA'];
            } else {
                // Parsear mensajes de error detalladamente
                $errorMessages = [];
                if (isset($respuesta->comprobantes->comprobante->mensajes->mensaje)) {
                    // Si es un solo mensaje, lo convertimos a array para iterar
                    $mensajes = is_array($respuesta->comprobantes->comprobante->mensajes->mensaje) ?
                                $respuesta->comprobantes->comprobante->mensajes->mensaje :
                                [$respuesta->comprobantes->comprobante->mensajes->mensaje];

                    foreach ($mensajes as $msg) {
                        $errorMessages[] = [
                            'identificador' => $msg->identificador ?? 'N/A',
                            'mensaje' => $msg->mensaje ?? 'Error desconocido',
                            'tipo' => $msg->tipo ?? 'ERROR',
                            'informacionAdicional' => $msg->informacionAdicional ?? null
                        ];
                    }
                } else if (isset($respuesta->estado)) {
                    // Si hay un estado pero no mensajes detallados
                    $errorMessages[] = [
                        'identificador' => '000',
                        'mensaje' => "El SRI devolvió el estado: " . $respuesta->estado,
                        'tipo' => 'ERROR'
                    ];
                }

                $errorMessageString = !empty($errorMessages) ? json_encode($errorMessages) : 'Error desconocido en la recepción.';
                throw new \Exception("Error en la recepción del comprobante: " . $errorMessageString);
            }
        } catch (\SoapFault $e) {
            throw new \Exception("Error SOAP en recepción: " . $e->getMessage(), $e->getCode(), $e);
        } catch (\Exception $e) {
            // Re-lanzar las excepciones ya generadas para mantener el stack trace
            throw $e;
        }
    }

    /**
     * Consulta la autorización de un comprobante.
     * Guarda el XML autorizado si el estado es 'AUTORIZADO'.
     *
     * @param string $claveAcceso La clave de acceso del comprobante.
     * @return array Un array con los detalles de la autorización.
     * @throws Exception Si hay un error en la comunicación SOAP o la clave de acceso no es válida.
     */
    public function consultarAutorizacion(string $claveAcceso): array
    {
        try {
            $webService = new \SoapClient($this->webAutoriza);
            $resultado = $webService->autorizacionComprobante([
                "claveAccesoComprobante" => $claveAcceso
            ]);

            if (!isset($resultado->RespuestaAutorizacionComprobante->autorizaciones->autorizacion)) {
                // Podría significar "EN PROCESO" o clave de acceso no encontrada aún
                return [
                    'estado' => 'EN PROCESO', // Asumimos EN PROCESO si no hay autorización aún
                    'numero_autorizacion' => null,
                    'fecha_autorizacion' => null,
                    'ambiente' => null,
                    'comprobante' => null,
                    'mensajes' => [['identificador' => 'SRI_PENDING', 'mensaje' => 'No se ha encontrado una autorización final aún.']],
                    'ruta_archivo' => null
                ];
                //throw new \Exception("No se obtuvo respuesta de autorización válida para la clave: " . $claveAcceso);
            }

            $autorizacion = $resultado->RespuestaAutorizacionComprobante->autorizaciones->autorizacion;

            // Asegurarse de que $autorizacion->mensajes sea un array si existe, para una iteración consistente
            $mensajesParsed = [];
            if (isset($autorizacion->mensajes->mensaje)) {
                $rawMensajes = is_array($autorizacion->mensajes->mensaje) ? $autorizacion->mensajes->mensaje : [$autorizacion->mensajes->mensaje];
                foreach ($rawMensajes as $msg) {
                    $mensajesParsed[] = [
                        'identificador' => $msg->identificador ?? 'N/A',
                        'mensaje' => $msg->mensaje ?? 'Mensaje desconocido',
                        'tipo' => $msg->tipo ?? 'INFO',
                        'informacionAdicional' => $msg->informacionAdicional ?? null
                    ];
                }
            }


            // Guardar el comprobante autorizado si está disponible y autorizado
            $rutaArchivo = null;
            if (isset($autorizacion->comprobante) && !empty($autorizacion->comprobante) && $autorizacion->estado === "AUTORIZADO") {
                $directorioAutorizados = '/var/www/facturacion/autorizados'; // Ajusta según tu ruta
                if (!is_dir($directorioAutorizados)) {
                    mkdir($directorioAutorizados, 0755, true);
                }

                $nombreArchivo = $claveAcceso . '_autorizado.xml';
                $rutaArchivo = $directorioAutorizados . '/' . $nombreArchivo;
                file_put_contents($rutaArchivo, $autorizacion->comprobante);
            }

            return [
                'estado' => $autorizacion->estado,
                'numero_autorizacion' => $autorizacion->numeroAutorizacion ?? $claveAcceso,
                'fecha_autorizacion' => $autorizacion->fechaAutorizacion ?? null,
                'ambiente' => $autorizacion->ambiente ?? null,
                'comprobante' => $autorizacion->comprobante ?? null, // El XML completo si está autorizado
                'mensajes' => $mensajesParsed, // Mensajes ya parseados
                'ruta_archivo' => $rutaArchivo // Ruta donde se guardó el XML (si fue autorizado)
            ];

        } catch (\SoapFault $e) {
            // Manejar errores SOAP que puedan indicar que la clave no se ha procesado aún o hay un problema temporal
            if (strpos($e->getMessage(), 'comprobante no encontrado') !== false || strpos($e->getMessage(), 'Clave de Acceso registrada') !== false) {
                 // Si es un error conocido que implica que está en proceso o ya existe, lo manejamos.
                 // Para el usuario, esto podría seguir siendo 'EN PROCESO' en el controlador
                error_log("SoapFault conocido en consultarAutorizacion para clave {$claveAcceso}: " . $e->getMessage());
                return [
                    'estado' => 'EN PROCESO', // Considerarlo EN PROCESO si el SRI dice que no lo ha encontrado aún
                    'numero_autorizacion' => null,
                    'fecha_autorizacion' => null,
                    'ambiente' => null,
                    'comprobante' => null,
                    'mensajes' => [['identificador' => 'SOAP_TEMPORAL', 'mensaje' => $e->getMessage()]],
                    'ruta_archivo' => null
                ];
            }
            throw new \Exception("Error SOAP al consultar autorización: " . $e->getMessage(), $e->getCode(), $e);
        }
    }
}