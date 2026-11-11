# Estado de cuenta de crédito

Aplicación de consulta construida con **Laravel 13**, **Vue 3** y **SQL Server**. Recibe un número de crédito, muestra sus pagos y genera un PDF descargable.

## Preparación

1. Copia `.env.example` a `.env` y completa las credenciales de SQL Server.
2. Verifica que las extensiones PHP `sqlsrv` y `pdo_sqlsrv` estén habilitadas.
3. Instala las dependencias y levanta el entorno:

```bash
composer install
npm install
npm run build
php artisan serve
```

Para desarrollo del frontend usa `npm run dev` en otra terminal.

## Variables de conexión

```dotenv
DB_CONNECTION=sqlsrv
DB_HOST=servidor-sql
DB_PORT=1433
DB_DATABASE=nombre_base
DB_USERNAME=usuario
DB_PASSWORD=secreto
DB_ENCRYPT=yes
DB_TRUST_SERVER_CERTIFICATE=true
```

En servidores productivos configura el cifrado según las políticas de tu instancia de SQL Server.

> Para este proyecto se recomienda `SESSION_DRIVER=file`: el reporte no necesita guardar
> sesiones en SQL Server y así no se requiere una tabla `sessions` en la base de créditos.
> Mantén también `CACHE_STORE=file` y `QUEUE_CONNECTION=sync` durante el desarrollo local;
> así los comandos de Laravel no dependen de tablas auxiliares de SQL Server.

## Rutas

- `GET /` — interfaz Vue.
- `GET /api/creditos/{codigo}/estado-cuenta` — JSON para la previsualización.
- `GET /creditos/{codigo}/estado-cuenta.pdf` — descarga del reporte en PDF.

El controlador ejecuta el reporte contra la conexión `sqlsrv`. El valor de crédito se envía como parámetro de la consulta; no se concatena dentro de SQL.
