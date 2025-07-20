<?php
/**
 * Configuración para OpenSSL para permitir algoritmos legacy
 * Este archivo debe ser incluido antes de usar las funciones de firma
 */

// Configurar OpenSSL para permitir algoritmos legacy (solución para el error 0308010C)
// Esta es la solución para OpenSSL 3.x

// Verificar si estamos en OpenSSL 3.x
if (version_compare(OPENSSL_VERSION_NUMBER, '0x30000000', '>=')) {
    // Para OpenSSL 3.x, necesitamos cargar el proveedor legacy
    
    // Crear un archivo de configuración temporal para OpenSSL
    $tempDir = sys_get_temp_dir();
    $configFile = $tempDir . '/openssl_legacy_' . getmypid() . '.conf';
    
    $configContent = "
openssl_conf = openssl_init

[openssl_init]
providers = provider_sect

[provider_sect]
default = default_sect
legacy = legacy_sect

[default_sect]
activate = 1

[legacy_sect]
activate = 1
";
    
    file_put_contents($configFile, $configContent);
    putenv("OPENSSL_CONF=$configFile");
    
    // Registrar función de limpieza
    register_shutdown_function(function() use ($configFile) {
        if (file_exists($configFile)) {
            unlink($configFile);
        }
    });
} else {
    // Para versiones anteriores de OpenSSL, no hacer nada especial
    // o simplemente deshabilitar la configuración
    putenv('OPENSSL_CONF=');
}
