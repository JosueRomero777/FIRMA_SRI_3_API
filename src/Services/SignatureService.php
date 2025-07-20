<?php

namespace App\Services;



/**
 * Servicio para manejar la firma electrónica de documentos.
 */
class SignatureService
{
    /**
     * Firma un contenido XML usando la clase SignatureWrapper.
     *
     * @param string $xmlContent Contenido XML a firmar.
     * @param string $certPath Ruta al archivo .p12 del certificado.
     * @param string $password Contraseña del certificado.
     * @param string $tipoDocumento Tipo de documento (ej. 'factura', 'notaCredito').
     * @return string El XML firmado.
     * @throws Exception Si la firma falla.
     */
    public function firmar(string $xmlContent, string $certPath, string $password, string $tipoDocumento): string
    {
        try {
            // Instanciar el wrapper que maneja la lógica de la firma y compatibilidad OpenSSL
            $signatureWrapper = new SignatureWrapper(
                $tipoDocumento,
                $certPath,
                $password,
                $xmlContent
            );

            // Llamar al método de firma
            $xmlFirmado = $signatureWrapper->sign();

            return $xmlFirmado;

        } catch (\Exception $e) {
            throw new \Exception("Error en el servicio de firma electrónica: " . $e->getMessage(), $e->getCode(), $e);
        }
    }
}