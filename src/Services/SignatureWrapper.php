<?php

namespace App\Services;

use Exception;

/**
 * Wrapper para la clase SignDOcumentToSRI que maneja problemas de compatibilidad con OpenSSL 3.x
 * Esta clase ahora recibe el tipo de documento en el constructor para pasarlo a SignDOcumentToSRI.
 */
class SignatureWrapper // Puedes renombrar esta a FirmaElectronicaService si es la principal
{
    private $originalClass;
    private $tipoDocumento; // Nuevo: Para pasar a SignDOcumentToSRI
    private $certPath;
    private $password;
    private $xmlString;

    /**
     * @param string $tipoDocumento Tipo de documento (ej. 'factura', 'notaCredito').
     * @param string $certPath Ruta al archivo .p12 del certificado.
     * @param string $password Contraseña del certificado.
     * @param string $xmlString Contenido del XML a firmar.
     */
    public function __construct(string $tipoDocumento, string $certPath, string $password, string $xmlString)
    {
        $this->tipoDocumento = $tipoDocumento;
        $this->certPath = $certPath;
        $this->password = $password;
        $this->xmlString = $xmlString;
    }

    /**
     * Firma el contenido XML.
     * @param string|null $xmlString Opcional: Contenido XML a firmar si es diferente al del constructor.
     * @return string El XML firmado.
     * @throws Exception Si la firma falla.
     */
    public function sign(?string $xmlString = null): string
    {
        $currentXmlString = $xmlString ?? $this->xmlString;

        try {
            $this->originalClass = new SignDOcumentToSRI(
                $this->tipoDocumento,
                $this->certPath,
                $this->password,
                $currentXmlString
            );
            return $this->originalClass->sign($currentXmlString);
        } catch (\Exception $e) {
            throw new \Exception("Error al firmar el documento: " . $e->getMessage(), $e->getCode(), $e);
        }
    }

    

    /**
     * Obtiene el documento firmado de la clase original.
     * @return mixed El documento firmado.
     * @throws Exception Si la clase original no ha sido instanciada.
     */
    public function getDocument()
    {
        if ($this->originalClass) {
            return $this->originalClass->getDocument();
        }
        throw new Exception("No se ha firmado ningún documento aún.");
    }
}