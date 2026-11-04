# ANEXO DE EVOLUCIÓN FUNCIONAL Y TÉCNICA (VERSIÓN 1.1)
**Proyecto:** Sistema Web de Gestión de Equipos, Insumos y Pañol — INACAP Sede Temuco
**Estado:** Aprobado para implementación aditiva sobre la línea base v1.0.0 (Hitos H1–H6)

---

## 1. PROPÓSITO Y REGLA DE INTEGRIDAD ADITIVA (CERO REGRESIÓN)
El presente documento extiende formalmente a `docs/especificacion_diagramas_sistema.md` y `docs/especificacion_requerimientos_sistema.md`. 
Toda implementación derivada de este anexo DEBE cumplir las siguientes reglas innegociables:
1. **Prohibición de alteración destructiva:** Queda estrictamente prohibido editar migraciones históricas de las 24 tablas base o ejecutar `migrate:fresh`. Todo cambio de esquema se realizará mediante nuevas migraciones aditivas (`ALTER TABLE ... ADD COLUMN` o nuevas tablas) con columnas `nullable` o con valores `default` seguros.
2. **Preservación de casos de prueba previos:** Los casos funcionales `CP-01` a `CP-22` y de integración `PI-01` a `PI-08` deben continuar aprobando al 100%.
3. **Delimitación de ambigüedades descartadas:**
   - Se descarta cualquier sistema de "reserva de aulas académicas generales"; la selección de sala en solicitudes de docentes se circunscribe exclusivamente a indicar el **Laboratorio/Taller de destino** registrado en la tabla `ubicaciones` para que el pañolero sepa dónde despachar o preparar los materiales.
   - Las estadísticas de etiquetas no se imprimen dentro del papel adhesivo del código de barras lineal (`CODE128`), sino que se visualizan como un panel de indicadores (KPIs) en la vista del módulo de Etiquetas.

---

## 2. EXTENSIÓN DEL MODELO DE DATOS EN POSTGRESQL (MIGRACIONES ADITIVAS)

### 2.1 Nuevas Tablas de Catálogo Académico y Parámetros
1. **Tabla `asignaturas` (Talleres / Materias por Carrera):**
   - `id` (`bigIncrements`, PK)
   - `carrera_id` (`foreignId` -> `carreras.id`, `ON DELETE CASCADE`)
   - `codigo` (`varchar(30)`, `unique`) — Ej. `INF-101`
   - `nombre` (`varchar(150)`) — Nombre estandarizado de la asignatura o taller práctico.
   - `activo` (`boolean`, `default(true)`)
   - `timestamps`

2. **Tabla pivote `asignatura_usuario` (Asignación de Talleres a Docentes):**
   - `id` (`bigIncrements`, PK)
   - `asignatura_id` (`foreignId` -> `asignaturas.id`, `ON DELETE CASCADE`)
   - `usuario_id` (`foreignId` -> `usuarios.id`, `ON DELETE CASCADE`)
   - Restricción `UNIQUE (asignatura_id, usuario_id)`.

3. **Tabla/Extensión de `parametros_sistema` (Configuración Global del Administrador):**
   *(Si la tabla ya existe en la implementación actual de H1, insertar las claves mediante Seeder; si se maneja por tabla clave-valor, asegurar los siguientes registros)*:
   - `stock_minimo_clases_default` (`integer`, default: `5`): Cantidad piso de unidades operativas que deben permanecer reservadas en pañol para el dictado de clases antes de autorizar préstamos espontáneos a estudiantes.
   - `notificar_incidencias_pdf` (`boolean`, default: `true`): Activa el despacho por cola de correo del PDF de incidencias a Pañoleros y Administradores.

### 2.2 Columnas Aditivas sobre las 24 Tablas Existentes
1. **Sobre `solicitudes_material`:**
   - `asignatura_id` (`foreignId` -> `asignaturas.id`, `nullable`, `ON DELETE SET NULL`): Ramo o taller para el cual el docente solicita el material.
   - `ubicacion_destino_id` (`foreignId` -> `ubicaciones.id`, `nullable`, `ON DELETE SET NULL`): Sala o laboratorio donde se utilizará el equipo/insumo.
   - `hora_requerida` (`time`, `nullable`): Hora exacta en la que el docente necesita los insumos en el laboratorio.

2. **Sobre `equipos` e `insumos`:**
   - En `insumos`: agregar `stock_minimo_clases` (`integer`, `default(2)`): Reserva mínima intocable para uso docente en talleres.
   - En `equipos`: agregar `reservado_clases` (`boolean`, `default(false)`): Indica si el activo es de uso prioritario para laboratorios docentes o apto para préstamo a estudiantes cuando exista holgura de unidades del mismo tipo.

3. **Sobre `entregas` (Préstamos registrados por el Pañolero):**
   - `receptor_credencial_rut` (`varchar(30)`, `nullable`): RUT o número de credencial institucional del estudiante o docente.
   - `credencial_verificada` (`boolean`, `default(false)`): Confirmación obligatoria del pañolero de haber retenido/validado físicamente la credencial del estudiante.
   - `hora_entrega` (`time`, `nullable`): Hora exacta de retiro en ventanilla.
   - `hora_devolucion_acordada` (`time`, `nullable`): Hora límite comprometida para devolver el equipo en el día/jornada.

4. **Sobre `fallas` (Ampliación a Reporte de Incidencias de Equipos e Insumos):**
   - Modificar `equipo_id` para permitir `nullable` (manteniendo FK a `equipos.id`).
   - Agregar `insumo_id` (`foreignId` -> `insumos.id`, `nullable`, `ON DELETE SET NULL`).
   - Agregar `ubicacion_id` (`foreignId` -> `ubicaciones.id`, `nullable`, `ON DELETE SET NULL`): Laboratorio o sala donde se detectó el equipo/insumo en mal estado.
   - Agregar restricción `CHECK ((equipo_id IS NOT NULL AND insumo_id IS NULL) OR (equipo_id IS NULL AND insumo_id IS NOT NULL))` para garantizar que la incidencia apunte exactamente a un equipo o a un insumo.

---

## 3. ESPECIFICACIÓN DE MÓDULOS Y REGLAS DE NEGOCIO NUEVAS

### 3.1 Módulo de Administración ("Gestión de usuarios", "Carreras y presupuesto", "Parámetros del sistema")
1. **Submódulo Gestión de Usuarios:**
   - Al crear o editar un usuario con rol `Docente`, el Administrador podrá asignarle múltiples carreras (`carrera_usuario`) y múltiples asignaturas/talleres (`asignatura_usuario`).
2. **Submódulo Carreras y Presupuesto:**
   - Además de gestionar el presupuesto anual único por `(carrera_id, anio)`, incluye un panel o modal para administrar el catálogo de **Asignaturas/Talleres** (`asignaturas`) pertenecientes a cada carrera.
3. **Submódulo Parámetros del Sistema:**
   - Permite al Administrador editar en tiempo real los parámetros globales: `stock_minimo_clases_default`, frecuencias de alerta de mantenimiento, mínimo de cotizaciones para adjudicación (2 o 3) y activación de envío de reportes PDF de incidencias.

### 3.2 Solicitudes de Docentes Filtradas por Asignatura/Taller, Sala y Hora (Proceso 3)
1. **Formulario del Docente (PC / Móvil):**
   - El selector de **Asignatura / Taller** carga únicamente las materias asociadas al docente autenticado (`asignatura_usuario`). Incluye una opción controlada "Otros / Actividad Académica General" por flexibilidad operativa.
   - El selector de **Sala / Laboratorio** lista las ubicaciones de tipo laboratorio/taller registradas en `ubicaciones`.
   - El campo **Hora Requerida** (`hora_requerida`) es obligatorio al enviar una nueva solicitud desde la interfaz para que el pañolero conozca a qué hora y a qué sala llevar o tener listos los materiales.
2. **Panel de Despacho del Pañolero:**
   - En la bandeja de solicitudes pendientes y aprobadas se muestra de forma destacada un bloque con: **Hora requerida**, **Sala/Laboratorio de destino** y **Asignatura**.

### 3.3 Renombrado Visual y Dashboard de Productos Críticos con Cotización Rápida (Módulo Inventario)
1. **Renombrado en Interfaz:**
   - El enlace en la barra de navegación y el título principal de la vista pasan a llamarse **"Inventario y Mantención de Equipo"**. Se mantienen intactos los nombres internos de rutas (`route('...')`) y clases PHP para no afectar pruebas automatizadas.
2. **Dashboard de Stock Crítico con Acción "Cotizar":**
   - En la cabecera de la vista de Inventario se despliega un panel destacado ("Alertas de Stock Crítico") que lista todos los insumos donde `stock_actual <= stock_minimo` (`KPI-04`).
   - Cada fila crítica incluye un botón de acción directa **"Cotizar"** que redirige o abre el modal del módulo de Cotizaciones (`solicitudes_cotizacion`) precargando automáticamente el `insumo_id`, su carrera asociada y la cantidad sugerida de reposición.

### 3.4 Préstamo a Estudiantes Registrado por el Pañolero con Reserva de Clases y Credencial
1. **Actor que ejecuta la acción:**
   - Cumpliendo la regla de negocio de que los estudiantes no tienen cuenta en el sistema, el estudiante acude presencialmente al pañol y el **Encargado de Pañol** registra la solicitud/entrega en el sistema seleccionando `receptor_tipo = 'estudiante'`.
2. **Validación de Stock Mínimo Reservado para Clases (Concurrencia `lockForUpdate`):**
   - Antes de autorizar el préstamo a un estudiante, el servicio de negocio bloquea el registro (`lockForUpdate()`) y verifica que existan unidades disponibles por encima del piso reservado para clases:
     * Para insumos retornables: `stock_actual - stock_minimo_clases >= cantidad_solicitada`.
     * Para equipos: contar cuántos equipos del mismo `tipo` están en estado `disponible` y verificar que al restar 1 unidad se respete el parámetro `stock_minimo_clases_default`.
   - Si no hay excedente suficiente, el sistema rechaza la operación con una excepción controlada (`StockReservadoClasesException`) indicando que las unidades restantes son de uso exclusivo para clases.
3. **Verificación de Credencial y Compromiso Horario:**
   - Cuando `receptor_tipo = 'estudiante'`, el servidor exige obligatoriamente: `receptor_nombre`, `receptor_credencial_rut`, `credencial_verificada = true` (checkbox de verificación física de credencial), `hora_entrega` y `hora_devolucion_acordada`.

### 3.5 Panel de Estadísticas en el Módulo de Etiquetas
- En la parte superior de la vista de Etiquetas se incorpora un bloque de **3 tarjetas KPI**:
  1. **Cobertura de Etiquetado (`KPI-05`):** Total y porcentaje de equipos e insumos con `codigo_barras` asignado frente al total del catálogo.
  2. **Pendientes de Impresión:** Cantidad de ítems ingresados recientemente que aún no registran generación de etiqueta.
  3. **Rendimiento de Lotes (`SLA-07`):** Contador de lotes emitidos verificando la meta de generación en $\le 10$ segundos para lotes de hasta 20 etiquetas.

### 3.6 Reporte de Incidencias del Docente con Generación de PDF y Despacho por Cola de Correo
1. **Registro en Módulo del Docente:**
   - El docente dispone del formulario **"Reportar Incidencia (Equipo / Insumo en Mal Estado)"**, donde selecciona si el problema corresponde a un Equipo o a un Insumo, indica el Laboratorio/Sala (`ubicacion_id`), la severidad y la descripción del desperfecto.
2. **Generación de Documento PDF y Registro Polimórfico:**
   - Al guardar el reporte dentro de `DB::transaction()`, un servicio genera un comprobante PDF formal (`Reporte_Incidencia_#ID.pdf`) con membrete institucional, fecha/hora, docente informante, laboratorio y detalle técnico.
   - El archivo se almacena en `storage/app/public/incidencias/` y se vincula en la tabla polimórfica `documentos` (`documentable_type = Falla::class`, `hash_sha256` calculado).
3. **Envío Asíncrono a Pañolero y Administradores:**
   - Se despacha un trabajo en segundo plano (`EnviarReporteIncidenciaJob`) mediante `Laravel Queues` que envía un correo electrónico con el PDF adjunto a todos los usuarios activos con rol `Encargado de pañol` y `Administrador`, garantizando que la interfaz del docente responda en $\le 3$ segundos (`SLA-02`).

---

## 4. NUEVOS CASOS DE PRUEBA FUNCIONALES (QA V1.1)
| Código | Escenario Evaluado | Resultado Esperado |
| :--- | :--- | :--- |
| **CP-23** | Asignar talleres a un docente en Administración y crear solicitud de material. | El docente visualiza sus talleres asignados y registra obligatoriamente asignatura, sala/laboratorio y hora requerida. |
| **CP-24** | Visualizar dashboard de productos críticos en Inventario y accionar botón "Cotizar". | Se listan los insumos con `stock_actual <= stock_minimo` y el botón precarga el insumo en una nueva solicitud de cotización. |
| **CP-25** | Intentar préstamo a estudiante cuando el stock disponible es igual o menor al reservado para clases. | El servidor bloquea el préstamo informando que las unidades están reservadas para docencia. |
| **CP-26** | Registrar préstamo a estudiante con stock suficiente verificando credencial y hora de devolución. | Se exige RUT/credencial, check de credencial verificada y hora de devolución acordada antes de descontar disponibilidad. |
| **CP-27** | Consultar métricas en el módulo de Etiquetas. | Se muestran correctamente los totales y porcentajes de cobertura de etiquetado (`KPI-05`). |
| **CP-28** | Reportar incidencia de equipo o insumo en mal estado desde perfil Docente. | Se registra la incidencia, se guarda el PDF en `documentos` con `hash_sha256` y se encola el correo con el PDF para Pañolero y Administradores. |