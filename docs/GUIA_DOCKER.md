# Guía de Despliegue y Réplica con Docker - Pañol INACAP Temuco

Esta guía permite a cualquier evaluador, docente o desarrollador levantar y probar la aplicación de forma idéntica y aislada en cualquier sistema operativo (Windows, Linux, macOS) utilizando **Docker** y **Docker Compose**, **sin necesidad de tener instalados PHP, Composer, Node.js ni PostgreSQL en la máquina física**.

---

## 1. Arquitectura de Contenedores

El stack se compone de cuatro servicios orquestados en una red interna dedicada (`panol_network`):

| Servicio | Contenedor | Descripción / Rol | Puerto Host / Interno |
| :--- | :--- | :--- | :--- |
| **`app`** | `panol_app` | PHP 8.3 CLI con extensiones requeridas (`pdo_pgsql`, `pgsql`, `mbstring`, `openssl`, `gd`, `fileinfo`, `curl`, `zip`, `bcmath`, `pcntl`). Ejecuta el servidor HTTP de Laravel. | `8000:8000` |
| **`db`** | `panol_db` | PostgreSQL 16 oficial. Base de datos `panol_inacap` con persistencia mediante volumen nombrado `panol_postgres_data`. Incluye healthcheck activo. | `5432:5432` |
| **`queue_worker`** | `panol_queue_worker` | Worker asíncrono derivado de `app` que procesa la cola de trabajos (`php artisan queue:work`) para envíos de cotizaciones por correo, PDFs y tareas en segundo plano. | Interno |
| **`mailpit`** | `panol_mailpit` | Servidor SMTP local e interfaz web para inspeccionar correos de prueba generados por el sistema sin enviarlos a servidores reales. | `8025:8025` (Web)<br>`1025:1025` (SMTP) |

---

## 2. Requisitos Previos

- **Docker Desktop** (en Windows/macOS) o **Docker Engine + Docker Compose v2** (en Linux).
- Git para clonar el repositorio.
- *(Opcional)* Cliente PostgreSQL (DBeaver, pgAdmin) si se desea auditar directamente la base de datos desde el host.

> **Nota para usuarios de Windows:** Docker Desktop debe tener habilitado el backend WSL2 o Hyper-V.

---

## 3. Paso a Paso de Puesta en Marcha

### Paso 1: Clonar el Repositorio y Entrar al Directorio
```bash
git clone https://github.com/tu-usuario/Proyecto_AplicacionWeb_INACAP.git
cd Proyecto_AplicacionWeb_INACAP
```

### Paso 2: Crear el Archivo de Entorno `.env`
Copia el archivo de configuración preparado para Docker:

**En Linux / macOS / Git Bash:**
```bash
cp .env.docker.example .env
```

**En Windows (PowerShell):**
```powershell
Copy-Item .env.docker.example .env
```

> [!TIP]
> **¿Tienes PostgreSQL ya instalado en tu máquina física en el puerto 5432?**
> Abre tu archivo `.env` y cambia `FORWARD_DB_PORT=5432` por `FORWARD_DB_PORT=5433` (o cualquier puerto libre). La comunicación interna entre Laravel y el contenedor de PostgreSQL (`DB_HOST=db`, `DB_PORT=5432`) no se verá afectada.

---

### Paso 3: Instalar Dependencias de PHP (sin tener Composer local)
Construye la imagen e instala las dependencias de Composer mediante un contenedor efímero:

```bash
docker compose run --rm app composer install
```

---

### Paso 4: Generar la Clave de Aplicación (`APP_KEY`)
```bash
docker compose run --rm app php artisan key:generate
```

---

### Paso 5: Levantar el Stack Completo en Segundo Plano
Inicia los servicios (`db`, `app`, `queue_worker`, `mailpit`):

```bash
docker compose up -d
```

Verifica que los contenedores estén levantados y el healthcheck de la base de datos se encuentre en estado `healthy`:
```bash
docker compose ps
```

---

### Paso 6: Ejecutar las 24 Migraciones del Sistema y Seeders
Aplica el esquema completo de base de datos relacional (las 24 tablas del modelo institucional normalizado) y carga los datos maestros y de demostración:

```bash
docker compose exec app php artisan migrate --seed
```

---

### Paso 7: Compilar los Assets de Frontend (Vite & Tailwind CSS)
Instala las dependencias de Node.js y compila los paquetes de estilos y scripts directamente dentro del contenedor:

```bash
docker compose exec app npm install
docker compose exec app npm run build
```

*(Si vas a desarrollar en vivo con recarga automática, puedes usar `docker compose exec app npm run dev`).*

---

## 4. Acceso y Verificación de Servicios

Una vez completados los pasos anteriores, abre tu navegador web:

| Servicio | URL | Descripción |
| :--- | :--- | :--- |
| **Sistema Pañol INACAP** | [http://localhost:8000](http://localhost:8000) | Plataforma web completa (módulos de inventario, pañol, compras y reportes). |
| **Buzón de Correos (Mailpit)** | [http://localhost:8025](http://localhost:8025) | Interfaz web para visualizar correos de cotizaciones y notificaciones en tiempo real. |
| **Base de Datos PostgreSQL** | `localhost:5432`<br>*(o puerto en `FORWARD_DB_PORT`)* | Usuario: `postgres` \| Password: `secret` \| BD: `panol_inacap` |

### Credenciales de Acceso Demo (Generadas por Seeders):
- **Administrador / Jefe de Pañol:**
  - Correo: `admin@inacap.cl`
  - Contraseña: `password`
- **Pañolero / Operador:**
  - Correo: `panolero@inacap.cl`
  - Contraseña: `password`
- **Docente:**
  - Correo: `docente@inacap.cl`
  - Contraseña: `password`

---

## 5. Monitoreo y Tareas en Segundo Plano (Queue Worker)

Para observar cómo el worker procesa en segundo plano las notificaciones por correo y las solicitudes de cotización:

```bash
docker compose logs -f queue_worker
```

Para salir de la visualización en vivo de logs, presiona `Ctrl + C`.

---

## 6. Ejecución de la Suite de Pruebas (PHPUnit)

Para verificar la integridad del sistema y las pruebas unitarias y de integración dentro del entorno contenedorizado:

```bash
docker compose exec app php artisan test
```

---

## 7. Comandos Útiles de Mantenimiento

### Abrir una terminal interactiva (Bash) dentro del contenedor de la aplicación:
```bash
docker compose exec app bash
```

### Ejecutar comandos Artisan:
```bash
docker compose exec app php artisan route:list
docker compose exec app php artisan cache:clear
```

### Detener los servicios:
```bash
docker compose down
```

### Detener los servicios y reiniciar la base de datos limpia (elimina volúmenes):
```bash
docker compose down -v
```

---

## 8. Solución de Problemas Frecuentes

1. **Error: `bind: address already in use` en el puerto 5432 o 8000:**
   - Modifica en tu archivo `.env`:
     ```env
     FORWARD_DB_PORT=5433
     APP_PORT=8080
     ```
   - Vuelve a ejecutar `docker compose up -d`.

2. **Permisos en directorios de almacenamiento (`storage` o `bootstrap/cache`):**
   - El script de entrada `docker/entrypoint.sh` ajusta automáticamente los permisos al iniciar el contenedor. Si necesitas forzar permisos manualmente:
     ```bash
     docker compose exec app chmod -R 777 storage bootstrap/cache
     ```

3. **La base de datos tarda en iniciar en la primera ejecución:**
   - PostgreSQL Alpine inicializa los archivos de datos en el primer arranque. El healthcheck configurado en `docker-compose.yml` previene que la aplicación intente conectarse antes de que PostgreSQL acepte conexiones. Si una migración falla por tiempo de espera inicial, espera 5 segundos y vuelve a correr `docker compose exec app php artisan migrate --seed`.
