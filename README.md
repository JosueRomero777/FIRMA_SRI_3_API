# API de Firma Electrónica SRI

API REST para firmar y autorizar documentos electrónicos ante el SRI (Servicio de Rentas Internas) de Ecuador.

## Requisitos

- PHP 7.4 o superior
- Extensión OpenSSL para PHP
- Composer
- Servidor web (Apache/Nginx) con soporte para PHP

## Instalación

1. Clonar el repositorio:
   ```bash
   git clone [URL_DEL_REPOSITORIO]
   cd FIRMA_SRI_3_API
   ```

2. Instalar dependencias:
   ```bash
   composer install
   ```

3. Configurar permisos:
   ```bash
   chmod -R 777 /var/www/facturacion/autorizados
   chmod -R 777 /tmp/sri
   ```

4. Configurar el servidor web para que apunte al directorio `public/`

## Uso

### Endpoint principal

```
POST /api/facturacion/procesar
```

**Parámetros (multipart/form-data):**
- `archivo_xml`: Archivo XML a firmar y autorizar
- `certificado_p12`: Archivo .p12 del certificado
- `clave_certificado`: Contraseña del certificado

**Respuesta exitosa (200 OK):**
```json
{
  "success": true,
  "data": {
    "documento_firmado": "<xml>...</xml>",
    "numero_autorizacion": "1234567890",
    "fecha_autorizacion": "2025-07-07T13:45:30-05:00",
    "ruta_guardado": "/var/www/facturacion/autorizados/documento_autorizado_20250707134530.xml"
  }
}
```

**Ejemplo con cURL:**
```bash
curl -X POST \
  http://localhost/api/facturacion/procesar \
  -H 'Content-Type: multipart/form-data' \
  -F 'archivo_xml=@/ruta/al/archivo.xml' \
  -F 'certificado_p12=@/ruta/al/certificado.p12' \
  -F 'clave_certificado=miclave'
```

## Documentación de la API

Puedes acceder a la documentación interactiva de la API en:
```
GET /docs
```

## Directorios

- `/var/www/facturacion/autorizados`: Almacena los documentos firmados y autorizados
- `/tmp/sri`: Directorio temporal para el procesamiento de archivos

## Configuración

Puedes modificar las rutas de los directorios en el controlador `FacturacionController.php`.

## Licencia

[MIT](LICENSE)
