<?php

namespace App\Utils;

/**
 * Clase para cargar las clases del firmador original
 */
class FirmadorLoader
{
    /**
     * Incluye los archivos necesarios del firmador original
     */
    public static function cargarClases()
    {
        // Configurar OpenSSL para permitir algoritmos legacy
        require_once __DIR__ . '/../../config_openssl.php';
        
        // Incluir primero el archivo con las funciones auxiliares
        require_once __DIR__ . '/../../firmador.php';
        
        // Incluir las definiciones de clases del firmador
        require_once __DIR__ . '/../../firmador_classes.php';
        
        // Definir las clases necesarias si no existen
        if (!class_exists('SignDOcumentToSRI', false)) {
            class_alias('\SignDOcumentToSRI', 'App\Services\SignDOcumentToSRI');
        }
        
        if (!trait_exists('TraitXadesbes', false)) {
            class_alias('\TraitXadesbes', 'App\Services\TraitXadesbes');
        }
        
        if (!class_exists('Sign', false)) {
            class_alias('\Sign', 'App\Services\Sign');
        }
    }
}
