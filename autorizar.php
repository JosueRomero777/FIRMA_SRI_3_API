<?php

$webRecepcion = 'https://celcer.sri.gob.ec/comprobantes-electronicos-ws/RecepcionComprobantesOffline?wsdl';
$webAutoriza = 'https://celcer.sri.gob.ec/comprobantes-electronicos-ws/AutorizacionComprobantesOffline?wsdl';

$dir_firmado = '/home/axcalle/Downloads/FIRMA_SRI_3/firmados/invoice_84e166b9-6ffe-427d-8326-74af608241eb.xml';
$clave_a = "0107202501019511448100110010010000000171234567811";

// Crear directorio para XMLs autorizados
$rutaAutorizados = '/home/axcalle/Downloads/FIRMA_SRI_3/autorizados/';
if (!is_dir($rutaAutorizados)) {
    mkdir($rutaAutorizados, 0755, true);
}

$contenido = file_get_contents($dir_firmado);
$parametros = array("xml" => $contenido);

echo "=== PASO 1: RECEPCIÓN ===\n";
try{
    $webServiceRecepcion = new SoapClient($webRecepcion);
    $result = $webServiceRecepcion->validarComprobante($parametros);
    
    if($result->RespuestaRecepcionComprobante->estado == "RECIBIDA"){
        echo "✅ Comprobante RECIBIDO correctamente\n";
    } else {
        echo "❌ Error en recepción\n";
        print_r($result);
        exit;
    }

}catch(SoapFault $e){
    echo "Error SOAP en recepción: " . $e->getMessage();
    exit;
}

echo "\n=== PASO 2: AUTORIZACIÓN ===\n";
$parametrosAutoriza = array("claveAccesoComprobante" => $clave_a);

try{
    $webServiceAutorizacion = new SoapClient($webAutoriza);
    $result = $webServiceAutorizacion->autorizacionComprobante($parametrosAutoriza);
    
    // Verificar si hay autorizaciones
    if(isset($result->RespuestaAutorizacionComprobante->autorizaciones->autorizacion)) {
        $autorizacion = $result->RespuestaAutorizacionComprobante->autorizaciones->autorizacion;
        $estado = $autorizacion->estado;
        
        echo "Estado: " . $estado . "\n";
        
        if($estado == "AUTORIZADO") {
            echo "✅ Comprobante AUTORIZADO!\n";
            
            // **EXTRAER Y GUARDAR EL XML AUTORIZADO**
            $xmlAutorizado = $autorizacion->comprobante;
            $fechaAutorizacion = $autorizacion->fechaAutorizacion;
            $numeroAutorizacion = $autorizacion->numeroAutorizacion ?? $clave_a;
            
            // Crear nombre del archivo autorizado
            $nombreArchivoOriginal = basename($dir_firmado, '.xml');
            $nombreArchivoAutorizado = $nombreArchivoOriginal . '_AUTORIZADO.xml';
            $rutaArchivoAutorizado = $rutaAutorizados . $nombreArchivoAutorizado;
            
            // **CREAR XML CON DATOS DE AUTORIZACIÓN**
            $xmlConAutorizacion = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
            $xmlConAutorizacion .= '<autorizacion>' . "\n";
            $xmlConAutorizacion .= '  <estado>' . $estado . '</estado>' . "\n";
            $xmlConAutorizacion .= '  <numeroAutorizacion>' . $numeroAutorizacion . '</numeroAutorizacion>' . "\n";
            $xmlConAutorizacion .= '  <fechaAutorizacion>' . $fechaAutorizacion . '</fechaAutorizacion>' . "\n";
            $xmlConAutorizacion .= '  <ambiente>' . $autorizacion->ambiente . '</ambiente>' . "\n";
            $xmlConAutorizacion .= '  <comprobante><![CDATA[' . $xmlAutorizado . ']]></comprobante>' . "\n";
            $xmlConAutorizacion .= '</autorizacion>';
            
            // **GUARDAR ARCHIVO AUTORIZADO**
            if (file_put_contents($rutaArchivoAutorizado, $xmlConAutorizacion)) {
                echo "✅ XML autorizado guardado en: $rutaArchivoAutorizado\n";
            } else {
                echo "❌ Error al guardar XML autorizado\n";
            }
            
            // **TAMBIÉN GUARDAR SOLO EL COMPROBANTE AUTORIZADO**
            $rutaComprobanteAutorizado = $rutaAutorizados . $nombreArchivoOriginal . '_comprobante_autorizado.xml';
            if (file_put_contents($rutaComprobanteAutorizado, $xmlAutorizado)) {
                echo "✅ Comprobante autorizado guardado en: $rutaComprobanteAutorizado\n";
            }
            
            echo "\n=== INFORMACIÓN DE AUTORIZACIÓN ===\n";
            echo "Número de autorización: " . $numeroAutorizacion . "\n";
            echo "Fecha de autorización: " . $fechaAutorizacion . "\n";
            echo "Ambiente: " . $autorizacion->ambiente . "\n";
            echo "Clave de acceso: " . $clave_a . "\n";
            
        } else if($estado == "EN PROCESO") {
            echo "⏳ Comprobante EN PROCESO - Reintente en unos minutos\n";
        } else if($estado == "ERROR SECUENCIAL REGISTRADO") {
            echo "❌ ERROR SECUENCIAL REGISTRADO\n";
            if(isset($autorizacion->mensajes)) {
                print_r($autorizacion->mensajes);
            }
        } else {
            echo "❓ Estado desconocido: " . $estado . "\n";
            if(isset($autorizacion->mensajes)) {
                print_r($autorizacion->mensajes);
            }
        }
    } else {
        echo "❌ No se encontraron autorizaciones\n";
        print_r($result);
    }

}catch(SoapFault $e){
    echo "Error SOAP en autorización: " . $e->getMessage();
}

?>