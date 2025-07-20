<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;
use Slim\Views\PhpRenderer;
use Tuupola\Middleware\CorsMiddleware;

require __DIR__ . '/../vendor/autoload.php';

// Configurar OpenSSL para algoritmos legacy ANTES de cualquier operación
if (version_compare(OPENSSL_VERSION_NUMBER, '0x30000000', '>=')) {
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
    
    register_shutdown_function(function() use ($configFile) {
        if (file_exists($configFile)) {
            unlink($configFile);
        }
    });
}

// Configuración de la aplicación
$app = AppFactory::create();
$app->addBodyParsingMiddleware();
$app->addErrorMiddleware(true, true, true);

// Configuración CORS
$app->add(new Tuupola\Middleware\CorsMiddleware([
    "origin" => ["*"],
    "methods" => ["GET", "POST", "PUT", "PATCH", "DELETE", "OPTIONS"],
    "headers.allow" => ["Content-Type", "Authorization"],
    "headers.expose" => [],
    "credentials" => false,
    "cache" => 0,
]));

// Ruta para documentación Swagger UI
$app->get('/docs', function (Request $request, Response $response) {
    $renderer = new PhpRenderer(__DIR__ . '/../templates');
    return $renderer->render($response, 'swagger.php');
});

// Ruta para el archivo OpenAPI
$app->get('/openapi.json', function (Request $request, Response $response) {
    $openapi = \OpenApi\Generator::scan([__DIR__ . '/../src']);
    $response->getBody()->write($openapi->toJson());
    return $response->withHeader('Content-Type', 'application/json');
});

// Rutas de la API
$app->group('/api/facturacion', function ($group) {
    // Ruta para procesar factura (antigua)
    $group->post('/procesar', \App\Controllers\FacturacionController::class . ':procesarFactura');
    
    // Nueva ruta para firmar documentos
    $group->post('/firmar', \App\Controllers\FacturacionController::class . ':firmarDocumento');

    // Nueva ruta para enviar documentos ya firmados al SRI
    $group->post('/enviar-sri', \App\Controllers\FacturacionController::class . ':enviarASri');
});

$app->run();