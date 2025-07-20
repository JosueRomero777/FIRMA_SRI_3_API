<?php
// Plantilla mínima para Swagger UI
?><!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>API Docs - Swagger UI</title>
    <link rel="stylesheet" type="text/css" href="https://unpkg.com/swagger-ui-dist/swagger-ui.css" />
    <style>body { margin: 0; padding: 0; }</style>
</head>
<body>
<div id="swagger-ui"></div>
<script src="https://unpkg.com/swagger-ui-dist/swagger-ui-bundle.js"></script>
<script>
    window.onload = function() {
        SwaggerUIBundle({
            url: '/openapi.json',
            dom_id: '#swagger-ui',
            presets: [
                SwaggerUIBundle.presets.apis,
                SwaggerUIBundle.SwaggerUIStandalonePreset
            ],
            layout: "BaseLayout",
            docExpansion: "none",
            operationsSorter: "alpha"
        });
    };
</script>
</body>
</html> 