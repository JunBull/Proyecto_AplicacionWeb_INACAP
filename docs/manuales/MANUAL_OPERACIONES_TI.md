# Manual de Operaciones TI y Administración del Sistema
**Institución:** INACAP Sede Temuco — Dirección de Tecnologías de la Información  
**Sistema:** Plataforma Web de Gestión de Pañol y Laboratorios  
**Versión de Entrega:** 1.0.0 (Producción)  
**Clasificación:** Confidencial - Solo Personal TI y Administradores de Infraestructura  

---

## 1. Arquitectura y Topología de Infraestructura

El sistema responde a una arquitectura **Monolítica Modular** de alto rendimiento basada en Laravel 11 y PHP 8.3, diseñada para garantizar atomicidad transaccional, control pesimista de concurrencia e independencia de despliegue sin microservicios innecesarios (filosofía *Ponytail*).

```
                      +-----------------------------+
                      |   Nginx / SSL Reverse Proxy |
                      |    (HTTPS - Puerto 443)     |
                      +--------------+--------------+
                                     |
                                     v
                      +-----------------------------+
                      |       PHP 8.3 - FPM         |
                      |   Laravel 11 + Livewire 3   |
                      +-------+-------------+-------+
                              |             |
           +------------------+             +------------------+
           v                                                   v
+-----------------------+                             +-----------------------+
|  PostgreSQL 15 / 18   |                             |   Supervisor Workers  |
| 24 Tablas Normalizadas|                             |   Colas SMTP (Queue)  |
| Bloqueos Pesimistas   |                             |   Scheduler (Cron)    |
+-----------------------+                             +-----------------------+
```

---

## 2. Requisitos de Servidor y Entorno Operativo

### 2.1 Especificaciones de Hardware Recomendadas (Sede Temuco)
* **CPU:** 4 vCPUs o superior (arquitectura x86_64).
* **Memoria RAM:** 8 GB RAM mínimo (16 GB recomendado para buffer pool de PostgreSQL y caché).
* **Almacenamiento:** 100 GB SSD/NVMe (particionado para `/var/www/` y `/var/lib/postgresql/`).
* **Conectividad:** Enlace LAN institucional dedicado, interfaz Gigabit Ethernet.

### 2.2 Requisitos de Software y Dependencias
* **Sistema Operativo:** Ubuntu Server 24.04 LTS / Debian 12 / RHEL 9 (o Windows Server 2022 con IIS/Nginx).
* **Motor Web:** Nginx 1.24+ o Apache 2.4+ con módulo HTTP/2 y TLS 1.3 activado.
* **Intérprete:** PHP 8.3.x (CLI y FPM) con las siguientes extensiones obligatorias activas:
  * `pdo_pgsql` y `pgsql`: Conexión de alto rendimiento a PostgreSQL.
  * `pdo_sqlite` y `sqlite3`: Soporte para suites de pruebas automatizadas en memoria.
  * `zip`: Compresión nativa de archivos documentales para respaldos.
  * `mbstring`, `fileinfo`, `intl`, `bcmath`, `curl`, `openssl`, `tokenizer`, `xml`.
* **Motor de Base de Datos:** PostgreSQL 15, 16, 17 o 18 oficial con utilitarios cliente (`pg_dump`, `pg_restore`, `createdb`, `dropdb`, `psql`).
* **Gestor de Procesos en Segundo Plano:** `supervisor` (Linux) o servicio de Windows para el procesamiento de colas.
* **Herramientas de Construcción:** Composer 2.7+, Node.js 20+ y NPM 10+ (solo para la compilación de bundles Vite).

---

## 3. Diccionario Completo de Variables de Entorno (`.env`)

A continuación se detalla la configuración requerida para el archivo de entorno en producción:

| Variable | Tipo / Valor Ejemplo | Propósito Operativo |
| :--- | :--- | :--- |
| `APP_NAME` | `"Pañol INACAP Temuco"` | Identificador del sistema para correos y encabezados institucionales. |
| `APP_ENV` | `production` | Modo de ejecución del framework. **Crítico:** En producción deshabilita trazas de depuración sensibles. |
| `APP_KEY` | `base64:...` | Clave de cifrado de 32 bytes para sesiones, cookies y hashes de seguridad (generada con `php artisan key:generate`). |
| `APP_DEBUG` | `false` | Desactiva pantallas de depuración de errores que puedan exponer información técnica (OWASP). |
| `APP_URL` | `https://panol.temuco.inacap.cl` | Dominio institucional FQDN con esquema HTTPS. |
| `DB_CONNECTION` | `pgsql` | Driver de base de datos obligatorio (PostgreSQL). |
| `DB_HOST` | `127.0.0.1` | Dirección IP o socket del motor PostgreSQL. |
| `DB_PORT` | `5432` | Puerto estándar del servicio PostgreSQL. |
| `DB_DATABASE` | `panol_inacap` | Nombre de la base de datos de producción con el esquema de 24 tablas. |
| `DB_USERNAME` | `panol_user` | Usuario de PostgreSQL con permisos restringidos de DDL/DML. |
| `DB_PASSWORD` | `[SECURE_PASSWORD]` | Contraseña de acceso al motor de datos. |
| `SESSION_DRIVER` | `database` | Almacena sesiones en la tabla `sessions` de PostgreSQL para auditoría y persistencia multi-worker. |
| `QUEUE_CONNECTION` | `database` | Driver de colas asíncronas para correo de cotizaciones y procesamiento documental en segundo plano. |
| `CACHE_STORE` | `database` | Mecanismo de caché y locks transaccionales atómicos en base de datos (`cache_locks`). |
| `FILESYSTEM_DISK` | `local` | Almacenamiento base para facturas y cotizaciones confidenciales (`storage/app`). |
| `MAIL_MAILER` | `smtp` | Protocolo para el despacho de solicitudes de cotización a proveedores. |
| `MAIL_HOST` | `smtp.office365.com` / `smtp.inacap.cl` | Servidor SMTP institucional autenticado. |
| `MAIL_PORT` | `587` | Puerto de envío TLS. |
| `MAIL_ENCRYPTION` | `tls` | Cifrado obligatorio en tránsito para cumplimiento de confidencialidad. |
| `MAIL_FROM_ADDRESS`| `panol.temuco@inacap.cl` | Dirección de remitente institucional autorizada. |
| `PG_DUMP_PATH` | *(Opcional)* `/usr/bin/pg_dump` | Ruta absoluta personalizada al binario `pg_dump` si no se encuentra en el PATH. |
| `PG_RESTORE_PATH`| *(Opcional)* `/usr/bin/pg_restore` | Ruta absoluta personalizada al binario `pg_restore`. |

---

## 4. Procedimiento de Despliegue en Servidor Limpio (Paso a Paso)

Ejecute la siguiente secuencia en la consola del servidor con permisos administrativos:

```bash
# 1. Clonar el repositorio oficial de producción
cd /var/www
git clone https://github.com/JunBull/Proyecto_AplicacionWeb_INACAP.git panol
cd panol

# 2. Configurar permisos de directorios de almacenamiento y caché
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

# 3. Instalar dependencias de backend optimizadas para producción
composer install --no-dev --optimize-autoloader --no-interaction

# 4. Crear y configurar variables de entorno
cp .env.example .env
nano .env  # Ajustar credenciales de PostgreSQL, SMTP y APP_URL
php artisan key:generate --force

# 5. Ejecutar migraciones de base de datos PostgreSQL (24 tablas normalizadas)
php artisan migrate --force

# 6. Cargar datos maestros y registros base institucionales (seeders)
php artisan db:seed --force

# 7. Compilar activos de frontend con Vite (Tailwind CSS y Livewire)
npm ci
npm run build

# 8. Vincular almacenamiento público para imágenes de equipos y mantenimientos
php artisan storage:link

# 9. Compilar cachés de alto rendimiento del framework Laravel
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

---

## 5. Configuración de Servicios del Sistema Operativo

### 5.1 Worker de Colas con Supervisor (`/etc/supervisor/conf.d/panol-worker.conf`)
Para cumplir el **SLA-05** y **KPI-10** (procesamiento y despacho de solicitudes de cotización en $\le 15$ minutos), configure el demonio de Supervisor:

```ini
[program:panol-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/panol/artisan queue:work database --sleep=3 --tries=3 --max-time=3600 --timeout=90
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/panol/storage/logs/worker.log
stopwaitsecs=3600
```

Para aplicar los cambios en el servidor:
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start panol-worker:*
```

### 5.2 Laravel Scheduler en Cron (`/etc/cron.d/panol-scheduler` o `crontab -e -u www-data`)
El planificador de Laravel coordina dos procesos automáticos críticos:
1. **07:00 AM Diaria:** Evaluación de alertas preventivas y vencimientos de mantenimiento de equipos (CP-05, KPI-06).
2. **02:00 AM Diaria:** Respaldo integral de base de datos PostgreSQL y archivos documentales con retención de 30 días (SLA-08, SLA-09).

Agregue la siguiente línea al crontab del usuario del servidor web (`www-data`):
```cron
* * * * * cd /var/www/panol && php artisan schedule:run >> /dev/null 2>&1
```

---

## 6. Plan de Continuidad Operativa y Gestión de Respaldos (DRP)

### 6.1 Política de Respaldos Diarios (SLA-08)
El sistema ejecuta automáticamente cada madrugada el comando:
```bash
php artisan app:backup --keep-days=30
```
O de forma directa mediante el script shell:
```bash
/var/www/panol/scripts/backup.sh
```

**Artefactos Generados por cada Respaldo:**
* Directorio con estampa de tiempo: `storage/app/backups/backup_YYYYMMDD_HHmmss/`
* `database.dump`: Volcado completo de PostgreSQL mediante `pg_dump` con compresión nativa zlib (`-F c`).
* `storage_files.zip`: Archivo comprimido con todas las facturas, cotizaciones, actas de baja y fotos de equipos (`storage/app/`).
* `manifest.json`: Manifiesto criptográfico que incluye el conteo de tablas, bytes exactos y firmas SHA-256 independientes de cada archivo para garantizar que el respaldo no ha sido adulterado.
* **Rotación Automática:** Los respaldos que superen los 30 días de antigüedad son eliminados automáticamente, registrando la auditoría en `storage/logs/backup.log`.

### 6.2 Protocolo de Recuperación ante Siniestro Total (PI-08, SLA-09, SLA-10)
En caso de pérdida catastrófica del servidor o daño en el almacenamiento principal:

1. **Aprovisionar la nueva instancia de servidor** con el software base (PHP 8.3, PostgreSQL 15+, extensiones).
2. **Transferir el directorio de respaldos** desde el almacenamiento secundario/externo hacia la nueva máquina (`/var/www/panol/storage/app/backups/`).
3. **Ejecutar la restauración íntegra:**
   ```bash
   cd /var/www/panol
   php artisan app:restore --force
   ```
   *(O mediante script: `/var/www/panol/scripts/restore.sh`)*

4. **Validación Automática del Proceso:**
   * El sistema verifica primero las sumas criptográficas SHA-256 de los archivos contra `manifest.json`. Si hay corrupción, la restauración se detiene inmediatamente.
   * Ejecuta `pg_restore` restableciendo las 24 tablas, llaves foráneas, índices y restricciones CHECK.
   * Descomprime las carpetas documentales en `storage/app/`.
   * Realiza un test de integridad (*Smoke Test*) verificando registros en `carreras`, `usuarios`, `equipos`, `insumos`, `presupuestos` y `documentos`.
   * Cronometra el tiempo total de recuperación (RTO) certificando el cumplimiento del SLA-10 ($\le 8$ horas de soporte) y SLA-09 (RPO $\le 24$ horas).
   * Registra el resultado en `storage/logs/restore.log`.

### 6.3 Ejecución de Simulacro de Desastre en Entorno Aislado (Modo Simulación)
Para auditorías técnicas o simulacros periódicos sin afectar la base de producción:
```bash
php artisan app:restore --simulate
```
Este comando crea una base de datos temporal aislada (`panol_inacap_restore_test`), restaura los datos, ejecuta las comprobaciones de integridad, cronometra el RTO y realiza la limpieza automática de los recursos temporales.

---

## 7. Monitoreo y Supervisión de Salud Operativa (SLA-01, SLA-02)

### 7.1 Monitoreo de Disponibilidad (SLA-01: $\ge 98\%$)
Configure una sonda externa institucional (ej. Uptime Kuma, Zabbix o Nagios) apuntando a la ruta de verificación cada 5 minutos:
```http
GET https://panol.temuco.inacap.cl/up
```
* **Respuesta HTTP 200 OK:** Indica que la aplicación web, el motor PHP y la conexión a PostgreSQL se encuentran operativas.
* **Respuesta HTTP 500 / Timeout:** Dispara una alarma crítica de guardia técnica TI para cumplimiento del SLA-11 ($\le 2$ horas de atención).

### 7.2 Bitácoras de Auditoría del Sistema
* `storage/logs/laravel.log`: Errores no controlados de la aplicación, excepciones de backend y trazas de depuración de componentes Livewire.
* `storage/logs/backup.log`: Historial cronológico de respaldos diarios, tamaños, hashes y purga de rotación (SLA-08).
* `storage/logs/restore.log`: Historial de restauraciones y ensayos de simulacro DRP con medición de RPO y RTO (PI-08, SLA-10).
* `storage/logs/worker.log`: Trazabilidad del demonio de colas y envíos de correo a proveedores (SLA-05).

---
**INACAP Sede Temuco** — Manual de Operaciones TI v1.0.0 — Documentación Técnica de Entrega H6.
