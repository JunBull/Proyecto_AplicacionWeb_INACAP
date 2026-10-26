# Especificación Técnica Extraída de las Ilustraciones del Proyecto
**Proyecto:** Aplicación web para la gestión de equipos, insumos y procesos de pañol — Área de Informática, INACAP Sede Temuco  
**Stack Base:** PHP + Laravel + Livewire + Blade + Tailwind CSS (Vite) + PostgreSQL  
**Propósito de este documento:** Proveer una transcripción exacta y accionable de las **17 ilustraciones** técnicas del informe (`INFORME PROYECTO 24-10.docx`) para asistentes de programación e ingenieros sin acceso visual a los diagramas originales.

---

## 1. Procesos de Negocio y Máquinas de Estado (Diagramas BPMN)

### Ilustración 1: Proceso 1. Cotización, adjudicación y registro de compra
* **Participantes (Pools y Lanes):**
  * **Pool 1: Institución**
    * Lane 1.1: `Encargado de pañol`
    * Lane 1.2: `Sistema`
    * Lane 1.3: `Administrador`
  * **Pool 2: Proveedor (externo)**
* **Secuencia exacta de actividades, compuertas y mensajes:**
  1. **Evento de Inicio (`Encargado de pañol`):** `Necesidad de compra o alerta de stock mínimo`.
  2. **Tarea de Usuario (`Encargado de pañol`):** `Registrar necesidad y seleccionar proveedores`.
  3. **Tarea del Sistema (`Sistema`):** `Registrar solicitud y procesar envío de correos`.
     * *Flujo de Mensaje saliente (`Solicitud de cotización`):* Dispara el Evento de Inicio de Mensaje en `Proveedor (externo)`: `Solicitud de cotización recibida` -> Tarea `Preparar y enviar cotización`.
     * *Flujo de Mensaje entrante (`Cotización`):* Desde `Preparar y enviar cotización` (`Proveedor`) hacia `Incorporar cotizaciones recibidas` (`Encargado de pañol`).
  4. **Tarea de Usuario (`Encargado de pañol`):** `Incorporar cotizaciones recibidas`.
  5. **Compuerta Exclusiva XOR (`Encargado de pañol`):** `¿Cantidad mínima de cotizaciones?`
     * **Rama `Sí`:** Pasa a la tarea `Comparar cotizaciones y seleccionar propuesta` (`Administrador`).
     * **Rama `No`:** Pasa a la tarea de usuario `Solicitar propuestas adicionales o evaluar excepción` (`Encargado de pañol`).
       * Conecta a **Compuerta Exclusiva XOR:** `¿Excepción autorizada?`
         * **Rama `No: se esperan adicionales`:** Regresa (bucle) a `Incorporar cotizaciones recibidas` (`Encargado de pañol`).
         * **Rama `Sí: excepción`:** Avanza a `Comparar cotizaciones y seleccionar propuesta` (`Administrador`).
  6. **Tarea de Usuario (`Administrador`):** `Comparar cotizaciones y seleccionar propuesta`.
  7. **Tarea del Sistema (`Sistema`):** `Verificar presupuesto disponible`.
  8. **Compuerta Exclusiva XOR (`Sistema`):** `¿Presupuesto suficiente?`
     * **Rama `No`:** Baja a la tarea de usuario `Revisar o cancelar solicitud conservando antecedentes` (`Administrador`) -> **Evento de Fin:** `Sin compra confirmada`.
     * **Rama `Sí`:** Baja a la tarea de usuario `Confirmar adjudicación` (`Administrador`).
  9. **Tarea de Usuario (`Administrador`):** `Confirmar adjudicación`.
  10. **Tarea del Sistema (`Sistema`):** `Registrar compra y comunicar decisión al proveedor`.
      * *Flujo de Mensaje saliente (`Decisión de compra`):* Llega al Evento Intermedio de Mensaje en `Proveedor (externo)`: `Decisión de compra recibida` -> Tarea `Despachar materiales y documentos` -> **Evento de Fin (`Proveedor`):** `Despacho completado`.
      * *Flujo de Mensaje entrante (`Despacho`):* Desde `Despachar materiales y documentos` (`Proveedor`) hacia el Evento Intermedio de Mensaje en `Sistema`: `Despacho de materiales y documentos recibido`.
  11. **Evento Intermedio de Mensaje (`Sistema`):** `Despacho de materiales y documentos recibido` -> **Evento de Fin:** `Continúa en proceso de recepción e ingreso documental` (conecta con el Proceso 2).

---

### Ilustración 2: Proceso 2. Recepción e ingreso documental al inventario
* **Participantes:** Pool `Institución` con dos Lanes: `Encargado de pañol` y `Sistema`.
* **Secuencia exacta de actividades y compuertas:**
  1. **Evento de Inicio (`Encargado de pañol`):** `Llegada de materiales del proveedor`.
  2. **Tarea de Usuario (`Encargado de pañol`):** `Comparar lo recibido con la compra y sus documentos`.
  3. **Compuerta Exclusiva XOR (`Encargado de pañol`):** `¿Hay diferencias?`
     * **Rama `Sí`:** -> Tarea de usuario `Registrar y gestionar las diferencias` -> converge en `Confirmar cantidades recibidas`.
     * **Rama `No`:** -> Avanza directo a `Confirmar cantidades recibidas`.
  4. **Tarea de Usuario (`Encargado de pañol`):** `Confirmar cantidades recibidas`.
  5. **Tarea de Usuario (`Encargado de pañol`):** `Cargar factura u orden de compra`.
  6. **Tarea del Sistema (`Sistema`):** `Validar formato y detectar duplicidad`.
  7. **Compuerta Exclusiva XOR (`Sistema`):** `¿Documento válido?`
     * **Rama `No: documento inválido o duplicado`:** Regresa (bucle) a `Cargar factura u orden de compra` (`Encargado de pañol`).
     * **Rama `Sí`:** Avanza a `Extraer nombres, cantidades y costos` (`Sistema`).
  8. **Tarea del Sistema (`Sistema`):** `Extraer nombres, cantidades y costos` (vía OCR).
  9. **Compuerta Exclusiva XOR (`Sistema`):** `¿Reconocimiento exitoso?`
     * **Rama `No`:** Sube a Tarea de Usuario `Completar datos manualmente` (`Encargado de pañol`) -> converge en `Revisar y corregir campos extraídos`.
     * **Rama `Sí`:** Sube directo a `Revisar y corregir campos extraídos` (`Encargado de pañol`).
  10. **Tarea de Usuario (`Encargado de pañol`):** `Revisar y corregir campos extraídos`.
  11. **Tarea de Usuario (`Encargado de pañol`):** `Relacionar materiales, asignar ubicación y confirmar`.
  12. **Tarea del Sistema (`Sistema`):** `Actualizar existencias y guardar movimientos`.
  13. **Compuerta Exclusiva XOR (`Sistema`):** `¿Descuento presupuestario en esta etapa?`
      * **Rama `Sí`:** -> Tarea del Sistema `Aplicar el descuento presupuestario una sola vez` -> converge en `Generar etiquetas con los identificadores`.
      * **Rama `No`:** -> Salta directo a `Generar etiquetas con los identificadores`.
  14. **Tarea del Sistema (`Sistema`):** `Generar etiquetas con los identificadores`.
      * *Anotación técnica del diagrama:* `"Usar el identificador ya registrado del insumo. Crear un código nuevo solo si la unidad o el envase requiere identificación propia."`
  15. **Tarea de Usuario (`Encargado de pañol`):** `Imprimir etiquetas`.
  16. **Tarea de Usuario (`Encargado de pañol`):** `Colocar etiquetas y ubicar los materiales`.
  17. **Evento de Fin (`Encargado de pañol`):** `Materiales identificados y ubicados`.

---

### Ilustración 3: Proceso 3. Solicitud, entrega y devolución de materiales
* **Participantes:** Pool `Institución` con tres Lanes: `Docente`, `Encargado de pañol` y `Sistema`.
* **Nota importante de modelado:** Este diagrama usa tanto **Compuertas Exclusivas (XOR)** como **Compuertas Inclusivas (OR)**, lo que significa que una misma entrega puede incluir simultáneamente materiales *retornables* y *consumibles*, y una misma devolución puede traer simultáneamente unidades *en buen estado* y unidades *con daño*.
* **Secuencia exacta de actividades y compuertas:**
  1. **Evento de Inicio (`Docente`):** `Docente requiere materiales`.
  2. **Tarea de Usuario (`Docente`):** `Enviar solicitud de materiales`.
  3. **Tarea del Sistema (`Sistema`):** `Registrar solicitud`.
  4. **Tarea de Usuario (`Encargado de pañol`):** `Revisar la solicitud`.
  5. **Compuerta Exclusiva XOR (`Encargado de pañol`):** `¿Solicitud aprobada?`
     * *Anotación técnica del diagrama:* `"Pendiente de confirmación: reserva de materiales al aprobar la solicitud."`
     * **Rama `No: rechazada`:** Baja a Tarea del Sistema `Notificar al docente y cerrar la solicitud` -> **Evento de Fin:** `Solicitud no atendida`.
     * **Rama `Sí`:** Baja a Tarea del Sistema `Verificar nuevamente la disponibilidad`.
  6. **Tarea del Sistema (`Sistema`):** `Verificar nuevamente la disponibilidad`.
  7. **Compuerta Exclusiva XOR (`Sistema`):** `¿Hay disponibilidad?`
     * **Rama `No: sin disponibilidad`:** Va a `Notificar al docente y cerrar la solicitud` -> **Evento de Fin:** `Solicitud no atendida`.
     * **Rama `Sí`:** Sube a Tarea de Usuario `Entregar materiales y confirmar la entrega` (`Encargado de pañol`).
  8. **Tarea de Usuario (`Encargado de pañol`):** `Entregar materiales y confirmar la entrega`.
  9. **Tarea del Sistema (`Sistema`):** `Registrar entrega confirmada`.
  10. **Compuerta Inclusiva OR (`Sistema`):** `Tipo de material entregado` (se evalúa por ítem entregado):
      * **Rama `Consumible`:** -> Tarea del Sistema `Registrar consumo definitivo y descontar stock` -> **Evento de Fin:** `Consumo definitivo registrado`.
      * **Rama `Retornable`:** -> Tarea del Sistema `Registrar préstamo con devolución pendiente` -> **Evento Intermedio de Temporizador:** `Esperar devolución`.
  11. **Desde `Esperar devolución` (`Sistema`):** Sube a Tarea de Usuario `Devolver materiales al pañol` (`Docente`).
  12. **Tarea de Usuario (`Encargado de pañol`):** `Recibir y revisar lo devuelto`.
  13. **Tarea de Usuario (`Encargado de pañol`):** `Registrar cantidades y estado de cada devolución`.
  14. **Compuerta Inclusiva OR (`Encargado de pañol`):** `Estado de lo devuelto`:
      * **Rama `En buen estado`:** -> Tarea del Sistema `Devolver unidades al stock disponible`.
      * **Rama `Con daño`:** -> Tarea del Sistema `Registrar falla y dejar pendiente de revisión`.
      * *(Ambas ramas convergen en una Compuerta Inclusiva OR de cierre en `Sistema`).*
  15. **Compuerta Exclusiva XOR (`Sistema`):** `¿Quedan materiales pendientes?`
      * **Rama `Sí`:** -> Tarea del Sistema `Mantener el préstamo abierto` -> regresa (bucle `Regresa a la espera de devolución`) al Evento de Temporizador `Esperar devolución`.
      * **Rama `No`:** -> Tarea del Sistema `Cerrar el préstamo` -> **Evento de Fin:** `Préstamo cerrado`.

---

### Ilustración 4: Proceso 4. Mantenimiento, reporte de fallas y baja o reemplazo de equipos
* **Participantes:** Pool `Institución` con cuatro Lanes: `Docente`, `Encargado de pañol`, `Administrador` y `Sistema`.
* **Secuencia exacta de actividades y compuertas:**
  1. **Dos Eventos de Inicio posibles:**
     * **Inicio A (Evento normal en `Docente`):** `Docente detecta una falla` -> Tarea de Usuario `Reportar la falla del equipo` (`Docente`) -> Tarea del Sistema `Registrar reporte de falla` (`Sistema`) -> sube a `Inspeccionar y diagnosticar el equipo` (`Encargado de pañol`).
     * **Inicio B (Evento de Temporizador en `Encargado de pañol`):** `Mantenimiento programado` -> entra directo a `Inspeccionar y diagnosticar el equipo` (`Encargado de pañol`).
  2. **Tarea de Usuario (`Encargado de pañol`):** `Inspeccionar y diagnosticar el equipo`.
  3. **Compuerta Exclusiva XOR (`Encargado de pañol`):** `¿La condición o la intervención impide usarlo?`
     * **Rama `Sí`:** Baja a Tarea del Sistema `Registrar equipo como no disponible` (`Sistema`) -> sube a `Evaluar viabilidad técnica y conveniencia económica`.
     * **Rama `No`:** Avanza directo a `Evaluar viabilidad técnica y conveniencia económica`.
  4. **Tarea de Usuario (`Encargado de pañol`):** `Evaluar viabilidad técnica y conveniencia económica`.
     * *Anotación técnica del diagrama:* `"Se compara el costo de reparación frente al costo de reemplazo para decidir si conviene reparar o dar de baja."`
  5. **Compuerta Exclusiva XOR (`Encargado de pañol`):** `¿Reparación viable y conveniente?`
     * **Rama `Sí`:**
       1. Tarea de Usuario (`Encargado de pañol`): `Realizar mantenimiento o reparación`.
       2. Tarea de Usuario (`Encargado de pañol`): `Verificar funcionamiento del equipo`.
       3. **Compuerta Exclusiva XOR:** `¿Funciona correctamente?`
          * **Rama `No: volver a evaluar`:** Regresa (bucle) a `Evaluar viabilidad técnica y conveniencia económica`.
          * **Rama `Sí`:** -> Tarea de Usuario `Declarar equipo operativo y disponible` (`Encargado de pañol`) -> **Evento de Fin:** `Equipo operativo`.
     * **Rama `No`:**
       1. Tarea de Usuario (`Encargado de pañol`): `Proponer baja o reemplazo con su sustento`.
       2. Baja a Tarea de Usuario (`Administrador`): `Revisar propuesta de baja o reemplazo`.
       3. **Compuerta Exclusiva XOR (`Administrador`):** `¿Propuesta aprobada?`
          * **Rama `No: rechazada`:** Baja a Tarea del Sistema `Mantener equipo no disponible pendiente de resolución` -> **Evento de Fin:** `Equipo pendiente de resolución`.
          * **Rama `Sí`:** Baja a Tarea del Sistema `Registrar baja y mantener equipo no disponible`.
       4. Desde `Registrar baja y mantener equipo no disponible` -> **Compuerta Exclusiva XOR (`Sistema`):** `¿Reemplazo aprobado?`
          * **Rama `No`:** -> **Evento de Fin:** `Baja registrada sin reemplazo`.
          * **Rama `Sí`:** Sube a Tarea de Usuario `Generar requerimiento de compra del reemplazo` (`Encargado de pañol`) -> **Evento de Fin:** `Continúa en el Proceso 1: cotización, adjudicación y registro de compra`.

---

## 2. Permisos y Alcance Funcional por Rol (Diagramas de Casos de Uso)

Límite del sistema en los 5 diagramas: `Sistema web de gestión de equipos e inventario — INACAP Temuco`.  
Regla transversal: Todos los perfiles (`Administrador`, `Encargado de pañol` y `Docente`) deben iniciar sesión para acceder a sus funciones autorizadas, verificadas en el servidor.

### Ilustración 5: Casos de uso de administración, catálogos y parámetros
| Caso de Uso | Actores Asociados | Relaciones / Notas del Diagrama |
| :--- | :--- | :--- |
| `Gestionar usuarios y roles` | `Administrador` | Exclusivo del Administrador. |
| `Gestionar carreras` | `Administrador` | Exclusivo del Administrador. |
| `Gestionar ubicaciones (pañol, laboratorio, subdivisión)` | `Administrador`, `Encargado de pañol` | Ambos roles pueden mantener la jerarquía física. |
| `Gestionar proveedores (insumos y mantenimiento)` | `Administrador`, `Encargado de pañol` | Clasificados en proveedores de insumos y de mantenimiento. |
| `Asignar y consultar presupuesto por carrera` | `Administrador` | Asignación presupuestaria exclusiva del Administrador. |
| `Configurar parámetros operativos` | `Administrador` | Incluye: stock mínimo, criticidad, frecuencias de mantenimiento y cantidad mínima de cotizaciones. |
| `Consultar reportes e historial de movimientos` | `Administrador`, `Encargado de pañol` | Limitado a las carreras autorizadas del usuario. |

### Ilustración 6: Casos de uso de equipos, fallas y mantenimiento
| Caso de Uso | Actores Asociados | Relaciones (`<<include>>` / `<<extend>>`) y Notas |
| :--- | :--- | :--- |
| `Consultar equipos y disponibilidad` | `Docente`, `Encargado de pañol` | Consulta general de catálogo de activos. |
| `Reportar falla de un equipo` | `Docente`, `Encargado de pañol` | Permite informar equipos defectuosos o dañados. |
| `Revisar y atender falla` | `Encargado de pañol` | Gestión diagnóstica de la incidencia. |
| `Registrar y editar ficha de equipo` | `Administrador`, `Encargado de pañol` | `<<include>>` -> `Adjuntar fotografía`. |
| `Programar mantenimiento semestral o anual` | `Encargado de pañol` | Según nivel de criticidad configurado. |
| `Registrar mantenimiento realizado` | `Encargado de pañol` | `<<include>>` -> `Adjuntar informes y documentos`. |
| `Proponer baja o reemplazo` | `Encargado de pañol` | `<<include>>` -> `Adjuntar informes y documentos`. |
| `Aprobar o rechazar baja o reemplazo` | `Administrador` | Decisión administrativa sobre la propuesta. |
| `Generar alertas de mantenimiento` | `Planificador de tareas` (actor de sistema) | Proceso automático que revisa periódicamente las fechas de mantenimiento. |

### Ilustración 7: Casos de uso de inventario, etiquetas e ingreso documental
| Caso de Uso | Actores Asociados | Relaciones (`<<include>>` / `<<extend>>`) y Notas |
| :--- | :--- | :--- |
| `Registrar insumo y ubicación detallada` | `Encargado de pañol` | Creación/edición de insumos y su ubicación física exacta. |
| `Registrar ingresos y salidas de inventario` | `Encargado de pañol` | Movimientos manuales controlados. |
| `Consultar existencias y alertas de stock mínimo` | `Encargado de pañol`, `Administrador` | Supervisión de niveles críticos. |
| `Generar e imprimir etiquetas con código de barras` | `Encargado de pañol`, `Impresora y lector de códigos` (actor externo) | Impresión y lectura física de identificadores. |
| `Registrar ingreso desde factura u orden de compra` | `Encargado de pañol` | `<<include>>` -> `Extraer datos y detectar documento duplicado` (conectado a `Servicio de reconocimiento documental`).<br>`<<include>>` -> `Revisar y confirmar datos extraídos` (conectado también a `Encargado de pañol`).<br>*Nota del diagrama:* Los datos extraídos nunca actualizan existencias ni presupuesto sin la revisión y confirmación del encargado. |

### Ilustración 8: Casos de uso de cotizaciones, adjudicación, compras y presupuesto
| Caso de Uso | Actores Asociados | Relaciones (`<<include>>` / `<<extend>>`) y Notas |
| :--- | :--- | :--- |
| `Crear solicitud de cotización` | `Encargado de pañol` | `<<include>>` -> `Enviar solicitud por correo a proveedores` (conectado a `Servicio de correo` y `Proveedor`). |
| `Asociar cotizaciones recibidas` | `Encargado de pañol` | Carga de propuestas PDF/documentales enviadas por proveedores. |
| `Adjudicar proveedor` | `Administrador` | `<<include>>` -> `Validar cantidad mínima de cotizaciones`.<br>`<<extend>>` <- `Autorizar excepción de cotizaciones` (ejecutado por `Administrador` cuando hay menos cotizaciones del mínimo). |
| `Registrar compra` | `Administrador` | `<<include>>` -> `Validar saldo presupuestario de la carrera`.<br>`<<include>>` -> `Actualizar presupuesto de la carrera`. |
| `Consultar presupuesto por carrera` | `Encargado de pañol`, `Administrador` | Consulta de saldo actual y movimientos. |

### Ilustración 9: Casos de uso de solicitudes, entregas y devoluciones
| Caso de Uso | Actores Asociados | Relaciones (`<<include>>` / `<<extend>>`) y Notas |
| :--- | :--- | :--- |
| `Consultar disponibilidad de materiales` | `Docente`, `Encargado de pañol` | Consulta desde PC o celular. |
| `Registrar solicitud anticipada (computador o celular)` | `Docente` | Solicitud previa de insumos y/o equipos. |
| `Consultar estado de mis solicitudes` | `Docente` | Seguimiento de aprobación/rechazo/entregas. |
| `Revisar solicitud` | `Encargado de pañol` | Aprobación o rechazo de la solicitud anticipada. |
| `Entregar materiales a docente o estudiante` | `Encargado de pañol` | `<<include>>` -> `Verificar disponibilidad`.<br>`<<include>>` -> `Registrar entrega y actualizar existencias`.<br>`<<extend>>` <- `Registrar consumo definitivo` (extiende a `Registrar entrega y actualizar existencias` para insumos no retornables; conectado a `Encargado de pañol`). |
| `Registrar devolución (completa, parcial o con daños)` | `Encargado de pañol` | `<<extend>>` <- `Registrar incidencia o saldo pendiente` (conectado a `Encargado de pañol`). |
| `Consultar historial y trazabilidad` | `Encargado de pañol`, `Administrador` | Auditoría completa de préstamos, entregas y devoluciones. |

---

## 3. Componentes Internos y Arquitectura en Capas

### Ilustración 10: Diagrama de componentes de la aplicación web
 Flujo descendente desde el cliente hasta la persistencia y servicios externos:

1. **Cliente Externo:** `Navegador web (computador y celular)` -> se comunica por `HTTPS` con la Capa de Presentación.
2. **Capa de presentación** *(envía `solicitudes de usuario` a Seguridad)*:
   * `Vistas Blade`: formularios, tablas y menús.
   * `Componentes Livewire`: búsquedas, filtros y estados.
   * `Tailwind CSS y JavaScript`: activos procesados con Vite.
3. **Seguridad y control de acceso** *(envía `operaciones autorizadas` a Capa de aplicación)*:
   * `Autenticación`: kit de inicio de Laravel con Livewire.
   * `Autorización por rol`: administrador, pañol y docente.
   * `Validación de entradas`: restricciones y control de archivos.
4. **Capa de aplicación: módulos de negocio** *(delega `tareas asíncronas y transacciones` a Procesos de fondo)*:
   * `Equipos y mantenimiento`: fichas, fallas, bajas.
   * `Inventario y ubicaciones`: existencias y stock mínimo.
   * `Compras y presupuesto`: cotizaciones y adjudicaciones.
   * `Solicitudes y préstamos`: entregas y devoluciones.
   * `Administración y reportes`: usuarios, carreras, parámetros.
5. **Procesos de fondo y trazabilidad** *(invocan a Servicios de integración y acceso a datos)*:
   * `Colas de trabajos`: envíos y reconocimiento.
   * `Planificador de tareas`: alertas de mantenimiento y stock.
   * `Transacciones y bloqueos`: concurrencia sin duplicados.
   * `Registro de movimientos`: bitácora de operaciones.
6. **Servicios de integración y acceso a datos** *(conectados a sistemas externos y almacenamiento)*:
   * `Servicio de documentos` (adaptador de reconocimiento) -> vía `API / HTTPS` -> **`Servicio de reconocimiento (por seleccionar)`**.
   * `Servicio de etiquetas` (códigos de barras) -> vía `formato de etiqueta` -> **`Impresora de etiquetas institucional`**.
   * `Servicio de correo` (solicitudes de cotización) -> vía `SMTP con TLS` -> **`Servicio de correo autorizado`**.
   * `Acceso a datos` (modelos Eloquent y migraciones) -> vía `SQL (transacciones)` -> **`PostgreSQL`**.
   * `Gestión de archivos` (fotos y documentos) -> vía `lectura y escritura` -> **`Almacenamiento de archivos`**.

### Ilustración 16: Diagrama de arquitectura de la aplicación web
* **Pila Principal (6 niveles bidireccionales):**
  1. `Cliente`: Navegador web en computadores y celulares · HTML, CSS (Tailwind CSS) y JavaScript (usado por `Administrador`, `Encargado de pañol` y `Docente`).
  2. `Presentación`: Vistas Blade y componentes Livewire · formularios, búsquedas, filtros, estados y alertas.
  3. `Lógica de aplicación (Laravel)`: Rutas · middleware · controladores y componentes · políticas de acceso · validaciones · servicios de los 5 módulos (`equipos y mantenimiento`, `inventario`, `compras y presupuesto`, `solicitudes y préstamos`, `administración`).
  4. `Adaptadores de integración`: Reconocimiento documental (OCR, por seleccionar) · correo autorizado · códigos de barras y etiquetas · colas de trabajos.
  5. `Acceso a datos`: Modelos Eloquent · migraciones versionadas · transacciones y control de concurrencia.
  6. `Persistencia`: PostgreSQL (datos relacionales) · almacenamiento de archivos · respaldos diarios.
* **Servicios Transversales (aplican a todas las capas):**
  * **Seguridad:** HTTPS · autenticación · autorización por rol en el servidor · validación de entradas y archivos · gestión de credenciales fuera del código (`.env`).
  * **Integridad y concurrencia:** Transacciones (`DB::transaction`) · bloqueos (`lockForUpdate`) · prevención de duplicados · stock sin valores negativos.
  * **Trazabilidad y supervisión:** Registro de movimientos y operaciones · registro de errores · comprobaciones de acceso y de base de datos.
  * **Calidad y entrega:** PHPUnit y pruebas de Laravel · pruebas manuales · Git · despliegue con respaldo previo.

---

## 4. Modelo de Datos Relacional Completo en PostgreSQL (24 Tablas)

El esquema de base de datos está dividido visualmente en **3 diagramas (Ilustraciones 11, 12 y 13)** que conforman un único modelo relacional de **24 tablas** en PostgreSQL.  
*Nota crítica sobre nulabilidad:* Las reglas `NOT NULL` vs `NULLABLE` indicadas abajo han sido extraídas directamente de los símbolos de cardinalidad *Crow's Foot* (`||` obligatorio `1` vs `|o` opcional `0..1`) y de las notas al pie de las ilustraciones.

---

### Dominio A: Acceso, Estructura Institucional, Equipos y Mantenimiento (Ilustración 11 — 9 tablas)

#### 1. Tabla `carreras`
| Columna | Tipo en Diagrama | Restricciones / Índices | Notas de Implementación |
| :--- | :--- | :--- | :--- |
| `id` | `bigint` | `PK` | Autoincremental (`id()`). |
| `codigo` | `varchar` | `UK`, `NOT NULL` | Código institucional único de la carrera. |
| `nombre` | `varchar` | `NOT NULL` | Nombre de la carrera. |
| `activa` | `boolean` | `NOT NULL` | Estado activo/inactivo. |

#### 2. Tabla `usuarios`
| Columna | Tipo en Diagrama | Restricciones / Índices | Notas de Implementación |
| :--- | :--- | :--- | :--- |
| `id` | `bigint` | `PK` | Autoincremental. |
| `nombre` | `varchar` | `NOT NULL` | Nombre completo. |
| `email` | `varchar` | `UK`, `NOT NULL` | Correo único de autenticación. |
| `password` | `varchar` | `NOT NULL` | Hash de contraseña. |
| `rol` | `enum` | `NOT NULL` | Valores del negocio: `administrador`, `encargado_panol`, `docente`. |
| `activo` | `boolean` | `NOT NULL` | Permite deshabilitar acceso sin borrar historial. |

#### 3. Tabla pivote `carrera_usuario`
| Columna | Tipo en Diagrama | Restricciones / Índices | Notas de Implementación |
| :--- | :--- | :--- | :--- |
| `usuario_id` | `bigint` | `PK`, `FK -> usuarios.id` | Clave primaria compuesta (`usuario_id`, `carrera_id`). |
| `carrera_id` | `bigint` | `PK`, `FK -> carreras.id` | Restringe qué carreras puede gestionar/consultar cada usuario. |

#### 4. Tabla `ubicaciones`
| Columna | Tipo en Diagrama | Restricciones / Índices | Notas de Implementación |
| :--- | :--- | :--- | :--- |
| `id` | `bigint` | `PK` | Autoincremental. |
| `carrera_id` | `bigint` | `FK -> carreras.id`, `NOT NULL` | Relación `1:N` obligatoria con `carreras`. |
| `padre_id` | `bigint` | `FK -> ubicaciones.id`, `NULLABLE` | **Autorreferencia recursiva.** Modela la jerarquía: `pañol -> laboratorio -> subdivisión` (mueble, cajón, puerta). `NULL` para nodos raíz. |
| `tipo` | `enum` | `NOT NULL` | Nivel jerárquico (ej. `panol`, `laboratorio`, `mueble`, `cajon`, `puerta`, `subdivision`). |
| `nombre` | `varchar` | `NOT NULL` | Nombre descriptivo de la ubicación. |

#### 5. Tabla `equipos`
| Columna | Tipo en Diagrama | Restricciones / Índices | Notas de Implementación |
| :--- | :--- | :--- | :--- |
| `id` | `bigint` | `PK` | Activo individualizado. |
| `codigo_barras` | `varchar` | `UK`, `NOT NULL` | Código único para etiqueta e identificación por lector. |
| `nombre` | `varchar` | `NOT NULL` | Nombre estandarizado (evitando siglas ambiguas). |
| `marca` | `varchar` | `NOT NULL` | Marca del equipo. |
| `modelo` | `varchar` | `NOT NULL` | Modelo del equipo (nota: el requerimiento inicial también pide tipo/área; verificar si se incluye como atributo adicional o clasificación). |
| `numero_serie` | `varchar` | `NOT NULL` | Serie de fábrica del activo. |
| `estado` | `enum` | `NOT NULL` | Estados según BPMN: `disponible` (operativo), `prestado`, `en_mantenimiento` / `no_disponible`, `pendiente_resolucion`, `dado_de_baja`. |
| `criticidad` | `enum` | `NOT NULL` | Nivel de criticidad para alertas (ej. `alta`, `media`, `baja`). |
| `ubicacion_id` | `bigint` | `FK -> ubicaciones.id`, `NOT NULL` | Laboratorio o pañol de ubicación (`1:N` obligatorio). |
| `compra_id` | `bigint` | `FK -> compras.id`, `NULLABLE` | Cardinalidad `0..1` en `compras` -> permite equipos históricos cargados sin compra asociada. |
| `fotografia` | `varchar` | `NOT NULL` (según caso de uso `<<include>>` Adjuntar fotografía) | Ruta del archivo de imagen representativa. |
| `fecha_adquisicion` | `date` | `NULLABLE` / `DATE` | Fecha de incorporación del equipo. |

#### 6. Tabla `mantenimientos`
| Columna | Tipo en Diagrama | Restricciones / Índices | Notas de Implementación |
| :--- | :--- | :--- | :--- |
| `id` | `bigint` | `PK` | Autoincremental. |
| `equipo_id` | `bigint` | `FK -> equipos.id`, `NOT NULL` | Relación `1:N` obligatoria con `equipos`. |
| `proveedor_id` | `bigint` | `FK -> proveedores.id`, `NULLABLE` | Cardinalidad `0..1` con `proveedores` (puede ser mantenimiento interno o con proveedor especializado). |
| `tipo` | `enum` | `NOT NULL` | Ej. `preventivo`, `correctivo`. |
| `frecuencia` | `enum` | `NULLABLE` / `ENUM` | Valores requeridos: `semestral`, `anual` (o `no_aplica` si es correctivo puntual). |
| `fecha_programada` | `date` | `NOT NULL` | Fecha objetivo revisada por el Planificador de tareas. |
| `fecha_realizada` | `date` | `NULLABLE` | Se informa al ejecutar el mantenimiento. |
| `estado` | `enum` | `NOT NULL` | Ej. `programado`, `en_proceso`, `realizado`, `vencido`, `cancelado`. |
| `observaciones` | `text` | `NULLABLE` | Detalles de la intervención técnica. |

#### 7. Tabla `fallas`
| Columna | Tipo en Diagrama | Restricciones / Índices | Notas de Implementación |
| :--- | :--- | :--- | :--- |
| `id` | `bigint` | `PK` | Autoincremental. |
| `equipo_id` | `bigint` | `FK -> equipos.id`, `NOT NULL` | Equipo afectado (`1:N`). |
| `reportada_por` | `bigint` | `FK -> usuarios.id`, `NOT NULL` | Docente o encargado que reporta la novedad. |
| `atendida_por` | `bigint` | `FK -> usuarios.id`, `NULLABLE` | Encargado de pañol que revisa/atiende la falla. |
| `descripcion` | `text` | `NOT NULL` | Detalle del desperfecto o daño detectado. |
| `fecha_reporte` | `timestamp` | `NOT NULL` | Marca temporal del reporte. |
| `estado` | `enum` | `NOT NULL` | Ej. `reportada`, `en_revision`, `en_reparacion`, `resuelta`, `derivada_a_baja`. |

#### 8. Tabla `bajas_reemplazos`
| Columna | Tipo en Diagrama | Restricciones / Índices | Notas de Implementación |
| :--- | :--- | :--- | :--- |
| `id` | `bigint` | `PK` | Autoincremental. |
| `equipo_id` | `bigint` | `FK -> equipos.id`, `NOT NULL` | Equipo evaluado (`1:N`). |
| `falla_id` | `bigint` | `FK -> fallas.id`, `NULLABLE` | Cardinalidad `0..1` con `fallas` (puede originarse de una falla o de diagnóstico preventivo). |
| `tipo` | `enum` | `NOT NULL` | Valores según BPMN: `baja`, `reemplazo` (baja con reposición). |
| `motivo` | `text` | `NOT NULL` | Sustento técnico/económico (ej. reparación supera costo de reposición). |
| `estado` | `enum` | `NOT NULL` | Ej. `propuesta`, `aprobada`, `rechazada`. |
| `propuesta_por` | `bigint` | `FK -> usuarios.id`, `NOT NULL` | Encargado de pañol que propone. |
| `aprobada_por` | `bigint` | `FK -> usuarios.id`, `NULLABLE` | Administrador que aprueba o rechaza. |
| `fecha` | `date` | `NOT NULL` | Fecha de la propuesta/resolución. |

#### 9. Tabla `documentos` (Polimórfica)
| Columna | Tipo en Diagrama | Restricciones / Índices | Notas de Implementación |
| :--- | :--- | :--- | :--- |
| `id` | `bigint` | `PK` | Autoincremental. |
| `tipo` | `enum` | `NOT NULL` | Ej. `factura`, `orden_compra`, `cotizacion`, `informe_tecnico`, `informe_proveedor`, `otro`. |
| `ruta` | `varchar` | `NOT NULL` | Path privado en almacenamiento administrado por Laravel. |
| `nombre_original` | `varchar` | `NOT NULL` | Nombre original del archivo subido. |
| `hash_sha256` | `varchar` | `NOT NULL` (Indexado) | Hash SHA-256 para **detectar duplicidad de documentos** (Proceso 2, CP-10). |
| `documentable_type` | `varchar` | `NOT NULL` | Clase Eloquent asociada. Nota del diagrama: se asocia con `mantenimientos`, `bajas_reemplazos`, `compras` y `cotizaciones`. |
| `documentable_id` | `bigint` | `NOT NULL` | ID del registro padre polimórfico. |
| `subido_por` | `bigint` | `FK -> usuarios.id`, `NOT NULL` | Usuario que cargó el respaldo. |
| `creado_en` | `timestamp` | `NOT NULL` | Fecha y hora de subida. |

---

### Dominio B: Inventario, Compras, Cotizaciones y Presupuesto (Ilustración 12 — 11 tablas)

#### 10. Tabla `presupuestos`
| Columna | Tipo en Diagrama | Restricciones / Índices | Notas de Implementación |
| :--- | :--- | :--- | :--- |
| `id` | `bigint` | `PK` | Autoincremental. |
| `carrera_id` | `bigint` | `FK -> carreras.id`, `NOT NULL` | Relación `1:N` con `carreras`. |
| `anio` | `smallint` | `NOT NULL` | Año presupuestario (ej. `2026`). **Restricción UNIQUE obligatoria en `(carrera_id, anio)`** según nota al pie del diagrama. |
| `monto_asignado` | `decimal` | `NOT NULL` | Monto inicial asignado al periodo. |
| `saldo_actual` | `decimal` | `NOT NULL` | Saldo vigente disponible en tiempo real. |

#### 11. Tabla `movimientos_presupuesto`
| Columna | Tipo en Diagrama | Restricciones / Índices | Notas de Implementación |
| :--- | :--- | :--- | :--- |
| `id` | `bigint` | `PK` | Autoincremental. |
| `presupuesto_id` | `bigint` | `FK -> presupuestos.id`, `NOT NULL` | Presupuesto afectado (`1:N`). |
| `compra_id` | `bigint` | `FK -> compras.id`, `NULLABLE` | Cardinalidad `0..1` con `compras` (nulo cuando es asignación inicial o ajuste administrativo manual). |
| `usuario_id` | `bigint` | `FK -> usuarios.id`, `NOT NULL` | Usuario responsable de la operación. |
| `tipo` | `enum` | `NOT NULL` | Según texto del modelo: `cargo` (descuento por compra), `ajuste`, `reverso`. |
| `monto` | `decimal` | `NOT NULL` | Importe del movimiento. |
| `fecha` | `timestamp` | `NOT NULL` | Marca temporal exacta. |

#### 12. Tabla `proveedores`
| Columna | Tipo en Diagrama | Restricciones / Índices | Notas de Implementación |
| :--- | :--- | :--- | :--- |
| `id` | `bigint` | `PK` | Autoincremental. |
| `razon_social` | `varchar` | `NOT NULL` | Nombre legal/comercial de la empresa. |
| `rut` | `varchar` | `UK`, `NOT NULL` | RUT único validado. |
| `correo` | `varchar` | `NOT NULL` | Destino para envíos automáticos de cotizaciones. |
| `telefono` | `varchar` | `NULLABLE` / `VARCHAR` | Teléfono de contacto. |
| `tipo` | `enum` | `NOT NULL` | Categorización requerida: `insumos`, `mantenimiento` (o `ambos` si se acuerda). |
| `activo` | `boolean` | `NOT NULL` | Habilitado para nuevas solicitudes. |

#### 13. Tabla `solicitudes_cotizacion`
| Columna | Tipo en Diagrama | Restricciones / Índices | Notas de Implementación |
| :--- | :--- | :--- | :--- |
| `id` | `bigint` | `PK` | Autoincremental. |
| `carrera_id` | `bigint` | `FK -> carreras.id`, `NOT NULL` | Carrera solicitante. |
| `creada_por` | `bigint` | `FK -> usuarios.id`, `NOT NULL` | Encargado o administrador que crea la solicitud. |
| `descripcion` | `text` | `NOT NULL` | Detalle de los requerimientos a cotizar (cuerpo base para correo). |
| `estado` | `enum` | `NOT NULL` | Ej. `borrador`, `enviada`, `en_evaluacion`, `adjudicada`, `cancelada`. |
| `fecha_creacion` | `timestamp` | `NOT NULL` | Fecha de creación. |

#### 14. Tabla `envios_cotizacion`
| Columna | Tipo en Diagrama | Restricciones / Índices | Notas de Implementación |
| :--- | :--- | :--- | :--- |
| `id` | `bigint` | `PK` | Bitácora de despacho por proveedor en colas de Laravel. |
| `solicitud_cotizacion_id` | `bigint` | `FK -> solicitudes_cotizacion.id`, `NOT NULL` | Solicitud padre (`1:N`). |
| `proveedor_id` | `bigint` | `FK -> proveedores.id`, `NOT NULL` | Proveedor destinatario (`1:N`). |
| `estado` | `enum` | `NOT NULL` | Según CP-17: `pendiente` (encolado), `enviado` (aceptado por SMTP), `fallido`. |
| `intentos` | `smallint` | `NOT NULL` | Contador de intentos del worker de colas. |
| `enviado_en` | `timestamp` | `NULLABLE` | Marca de tiempo de aceptación SMTP (usada para medir KPI-10 y SLA-05 <= 15 min). |
| `error` | `text` | `NULLABLE` | Mensaje de error devuelto por el servidor de correo en caso de fallo. |

#### 15. Tabla `cotizaciones`
| Columna | Tipo en Diagrama | Restricciones / Índices | Notas de Implementación |
| :--- | :--- | :--- | :--- |
| `id` | `bigint` | `PK` | Propuesta formal recibida de un proveedor. |
| `solicitud_cotizacion_id` | `bigint` | `FK -> solicitudes_cotizacion.id`, `NOT NULL` | Solicitud asociada (`1:N`). |
| `proveedor_id` | `bigint` | `FK -> proveedores.id`, `NOT NULL` | Proveedor oferente (`1:N`). |
| `monto_total` | `decimal` | `NOT NULL` | Valor total ofertado. |
| `fecha_recepcion` | `date` | `NOT NULL` | Fecha de recepción de la oferta (el archivo se guarda en `documentos`). |

#### 16. Tabla `adjudicaciones`
| Columna | Tipo en Diagrama | Restricciones / Índices | Notas de Implementación |
| :--- | :--- | :--- | :--- |
| `id` | `bigint` | `PK` | Autoincremental. |
| `cotizacion_id` | `bigint` | `UK`, `FK -> cotizaciones.id`, `NOT NULL` | **Relación `1:0..1`** con `cotizaciones` (una cotización solo puede adjudicarse una vez). |
| `adjudicada_por` | `bigint` | `FK -> usuarios.id`, `NOT NULL` | Administrador que aprueba la adjudicación. |
| `excepcion_autorizada` | `boolean` | `NOT NULL` | `true` cuando se adjudica con menos de las 2-3 cotizaciones mínimas exigidas. |
| `justificacion` | `text` | `NULLABLE` (Obligatorio si `excepcion_autorizada = true`) | Respaldo para auditorías de transparencia. |

#### 17. Tabla `compras`
| Columna | Tipo en Diagrama | Restricciones / Índices | Notas de Implementación |
| :--- | :--- | :--- | :--- |
| `id` | `bigint` | `PK` | Autoincremental. |
| `adjudicacion_id` | `bigint` | `FK -> adjudicaciones.id`, `NULLABLE` | Cardinalidad `0..1` con `adjudicaciones` (permite tanto compras nacidas de adjudicación como ingresos directos por factura/OC). |
| `proveedor_id` | `bigint` | `FK -> proveedores.id`, `NOT NULL` | Proveedor emisor (`1:N`). |
| `carrera_id` | `bigint` | `FK -> carreras.id`, `NOT NULL` | Carrera cuyo presupuesto asume la compra. |
| `numero_documento` | `varchar` | `NOT NULL` | Folio/número de factura u orden de compra (combinar con `proveedor_id` para evitar compras duplicadas en CP-10). |
| `fecha` | `date` | `NOT NULL` | Fecha de emisión/registro de la compra. |
| `monto_total` | `decimal` | `NOT NULL` | Importe total de la compra. |
| `estado` | `enum` | `NOT NULL` | Ej. `registrada`, `pendiente_recepcion`, `recibida_con_diferencias`, `ingresada_inventario`, `anulada`. |

#### 18. Tabla `compra_detalles`
| Columna | Tipo en Diagrama | Restricciones / Índices | Notas de Implementación |
| :--- | :--- | :--- | :--- |
| `id` | `bigint` | `PK` | Línea de detalle extraída por OCR o digitada. |
| `compra_id` | `bigint` | `FK -> compras.id`, `NOT NULL` | Compra cabecera (`1:N`). |
| `insumo_id` | `bigint` | `FK -> insumos.id`, `NULLABLE` | Cardinalidad `0..1` con `insumos` (nulo mientras está en borrador OCR o cuando la línea corresponde a un `equipo`). |
| `descripcion` | `varchar` | `NOT NULL` | Nombre del ítem en la factura/OC. |
| `cantidad` | `decimal` | `NOT NULL` | Cantidad adquirida/recibida. |
| `costo_unitario` | `decimal` | `NOT NULL` | Precio unitario neto. |
| `impuesto` | `decimal` | `NOT NULL` | Impuesto aplicable (ej. IVA). |

#### 19. Tabla `insumos`
| Columna | Tipo en Diagrama | Restricciones / Índices | Notas de Implementación |
| :--- | :--- | :--- | :--- |
| `id` | `bigint` | `PK` | Autoincremental. |
| `codigo_barras` | `varchar` | `UK`, `NOT NULL` | Código único propio para etiquetado. |
| `nombre` | `varchar` | `NOT NULL` | Nombre estandarizado del insumo/material. |
| `unidad` | `varchar` | `NOT NULL` | Unidad de medida (ej. unidad, metro, caja, litro). |
| `es_retornable` | `boolean` | `NOT NULL` | `false` = consumible (salida definitiva); `true` = retornable (requiere devolución en Proceso 3). |
| `stock_actual` | `decimal` | `NOT NULL` | Existencia disponible actual (nunca negativa: `stock_actual >= 0`). |
| `stock_minimo` | `decimal` | `NOT NULL` | Umbral que dispara alerta cuando `stock_actual <= stock_minimo`. |
| `ubicacion_id` | `bigint` | `FK -> ubicaciones.id`, `NOT NULL` | Ubicación detallada (`1:N` obligatorio). |

#### 20. Tabla `movimientos_inventario`
| Columna | Tipo en Diagrama | Restricciones / Índices | Notas de Implementación |
| :--- | :--- | :--- | :--- |
| `id` | `bigint` | `PK` | Kardex / bitácora inmutable de stock. |
| `insumo_id` | `bigint` | `FK -> insumos.id`, `NOT NULL` | Insumo afectado (`1:N`). |
| `usuario_id` | `bigint` | `FK -> usuarios.id`, `NOT NULL` | Usuario que ejecutó el movimiento. |
| `compra_id` | `bigint` | `FK -> compras.id`, `NULLABLE` | Informado si el ingreso proviene de una compra (Proceso 2). |
| `solicitud_id` | `bigint` | `FK -> solicitudes_material.id`, `NULLABLE` | Informado si la salida/reingreso proviene de una entrega o devolución (Proceso 3). |
| `tipo` | `enum` | `NOT NULL` | Ej. `ingreso_compra`, `ingreso_manual`, `salida_prestamo`, `salida_consumo`, `reingreso_devolucion`, `ajuste`. |
| `cantidad` | `decimal` | `NOT NULL` | Cantidad movida. |
| `fecha` | `timestamp` | `NOT NULL` | Fecha y hora exacta del movimiento. |

---

### Dominio C: Solicitudes, Entregas y Devoluciones (Ilustración 13 — 4 tablas)

#### 21. Tabla `solicitudes_material`
| Columna | Tipo en Diagrama | Restricciones / Índices | Notas de Implementación |
| :--- | :--- | :--- | :--- |
| `id` | `bigint` | `PK` | Autoincremental. |
| `solicitante_id` | `bigint` | `FK -> usuarios.id`, `NOT NULL` | Docente solicitante (`1:N`). |
| `revisada_por` | `bigint` | `FK -> usuarios.id`, `NULLABLE` | Encargado de pañol que aprueba o rechaza. |
| `fecha_solicitud` | `timestamp` | `NOT NULL` | Fecha/hora de envío de la solicitud. |
| `fecha_requerida` | `date` | `NOT NULL` | Fecha anticipada para la cual el docente requiere los materiales. |
| `estado` | `enum` | `NOT NULL` | Según BPMN Proceso 3: `pendiente`, `aprobada`, `rechazada`, `sin_disponibilidad`, `entregada_abierta` (préstamo activo), `cerrada`. |
| `observaciones` | `text` | `NULLABLE` | Comentarios del docente o motivo de rechazo del pañolero. |

#### 22. Tabla `detalle_solicitud`
| Columna | Tipo en Diagrama | Restricciones / Índices | Notas de Implementación |
| :--- | :--- | :--- | :--- |
| `id` | `bigint` | `PK` | Autoincremental. |
| `solicitud_id` | `bigint` | `FK -> solicitudes_material.id`, `NOT NULL` | Cabecera de solicitud (`1:N`). |
| `insumo_id` | `bigint` | `FK -> insumos.id`, `NULLABLE` | Cardinalidad `0..1` con `insumos`. |
| `equipo_id` | `bigint` | `FK -> equipos.id`, `NULLABLE` | Cardinalidad `0..1` con `equipos`. |
| `cantidad_solicitada` | `decimal` | `NOT NULL` | Para `equipo_id` suele ser `1`; para `insumo_id` la cantidad pedida. |
| `cantidad_entregada` | `decimal` | `NOT NULL` (Default `0`) | Acumulado entregado efectivamente. |
| `cantidad_devuelta` | `decimal` | `NOT NULL` (Default `0`) | Acumulado devuelto en entregas retornables. |
| `estado_devolucion` | `enum` | `NOT NULL` | Ej. `no_aplica` (consumible), `pendiente`, `parcial`, `completa`, `con_danos`. |

> **Restricción `CHECK` obligatoria en PostgreSQL (explícita en el pie de la Ilustración 13):**  
> Exactamente uno de `insumo_id` o `equipo_id` debe estar informado en cada fila:  
> `CHECK ((insumo_id IS NOT NULL AND equipo_id IS NULL) OR (insumo_id IS NULL AND equipo_id IS NOT NULL))`

#### 23. Tabla `entregas`
| Columna | Tipo en Diagrama | Restricciones / Índices | Notas de Implementación |
| :--- | :--- | :--- | :--- |
| `id` | `bigint` | `PK` | Una solicitud puede originar una o varias entregas (`1:N`). |
| `solicitud_id` | `bigint` | `FK -> solicitudes_material.id`, `NOT NULL` | Solicitud origen (`1:N`). |
| `entregado_por` | `bigint` | `FK -> usuarios.id`, `NOT NULL` | Encargado de pañol que despacha físicamente. |
| `receptor_nombre` | `varchar` | `NOT NULL` | Nombre e identificación de quien retira en ventanilla. |
| `receptor_tipo` | `enum` | `NOT NULL` | Valores: `docente`, `estudiante` (los estudiantes no tienen usuario en `usuarios`). |
| `fecha_entrega` | `timestamp` | `NOT NULL` | Fecha/hora de entrega (nota: si es préstamo retornable, contemplar la fecha comprometida de devolución en la lógica de seguimiento). |

#### 24. Tabla `devoluciones`
| Columna | Tipo en Diagrama | Restricciones / Índices | Notas de Implementación |
| :--- | :--- | :--- | :--- |
| `id` | `bigint` | `PK` | Cada entrega retornable puede cerrarse con una o varias devoluciones parciales (`1:N`). |
| `entrega_id` | `bigint` | `FK -> entregas.id`, `NOT NULL` | Entrega asociada (`1:N`). |
| `recibido_por` | `bigint` | `FK -> usuarios.id`, `NOT NULL` | Encargado de pañol que recibe y verifica el estado físico. |
| `fecha_devolucion` | `timestamp` | `NOT NULL` | Fecha/hora de recepción. |
| `conforme` | `boolean` | `NOT NULL` | `true` = en buen estado; `false` = con daños o faltantes (si hay daño en un equipo, dispara registro en `fallas` y cambia `equipos.estado` a no disponible en la misma transacción). |
| `observaciones` | `text` | `NULLABLE` | Detalle de cantidades parciales recibidas o daños observados. |

---

## 5. Topología de Comunicaciones e Infraestructura de Despliegue

### Ilustración 14: Topología de comunicaciones
* **Zona de Usuarios y Puestos de Trabajo:**
  * `Computador del administrador` -> Navegador web (`HTTPS`).
  * `Computadores y celulares de docentes` -> Navegador vía Wi-Fi o datos móviles (`HTTPS`).
  * `Computador del pañol` -> Navegador web (`HTTPS`), con periféricos conectados localmente:
    * `Lector de códigos de barras`: conectado por **USB o Bluetooth** (emulación de teclado o lectura directa en inputs de búsqueda/despacho).
    * `Impresora de etiquetas`: conectada por **red local o USB**.
* **Tránsito y Seguridad Perimetral:**
  * Tráfico pasa por `Red institucional e Internet` -> `Cortafuegos y proxy inverso HTTPS (puerto 443)` -> `Servidor de aplicación`.
* **Zona de Alojamiento (Servidor y Persistencia):**
  * `Servidor de aplicación` ejecuta: Servidor web + PHP, aplicación Laravel/Livewire, Worker de colas y Planificador de tareas (cron).
  * `Servidor PostgreSQL`: escucha en **`TCP 5432` restringido exclusivamente a conexiones desde la aplicación** (nunca expuesto a clientes externos).
  * `Almacenamiento de archivos`: acceso local/privado solo desde la aplicación para fotos, facturas, cotizaciones e informes.
  * `Respaldo diario`: copia automática cada día tanto de PostgreSQL como del Almacenamiento de archivos en una **ubicación separada del entorno principal**, con **retención de 30 días**.
* **Comunicaciones Salientes a Servicios Externos:**
  * Hacia `Servicio de correo autorizado`: protocolo **`SMTP con TLS`**.
  * Hacia `Servicio de reconocimiento documental`: vía **`HTTPS` (API externa) o ejecución local** según motor elegido.

### Ilustración 15: Diagrama de infraestructura (3 entornos separados)
1. **Entorno de desarrollo:**
   * Herramientas: Visual Studio Code, Composer, Vite, Git, pgAdmin, PHPUnit y pruebas de Laravel.
   * Base de datos local y captura de correos de desarrollo.
   * Publica mediante *despliegue versionado tras las pruebas* hacia Producción.
2. **Entorno de pruebas y restauración (aislado de producción):**
   * `Aplicación de pruebas (versión candidata)` + `PostgreSQL de pruebas`.
   * `Captura de correos de prueba` (garantiza cero envíos accidentales a proveedores reales).
   * `Ensayo de restauración`: recupera copia desde el almacenamiento de respaldo de producción para medir cumplimiento de **RPO (máximo 24 horas)** y **RTO (máximo 8 horas de soporte)** y ejecutar pruebas de humo.
3. **Entorno de producción:**
   * Alberga el `Servidor de aplicación` (Web/PHP, Laravel/Livewire, Worker de colas, Planificador de tareas, Almacenamiento de archivos), el `Servidor de base de datos` (PostgreSQL) y el nodo separado de `Respaldo` con acceso restringido.

---

## 6. Cronograma e Hitos de Construcción (Ilustración 17 — Carta Gantt)

* **Horizonte total:** 31 días calendario, desde el **21 de octubre al 20 de noviembre de 2026** (fines de semana sombreados: 24-25 oct, 31 oct-1 nov, 7-8 nov, 14-15 nov).
* **Actividad transversal continua:** `Documentación y pruebas` durante todo el periodo (**21 oct al 20 nov**).

| Fase de la Carta Gantt | Inicio | Fin | Hito Asociado al Cierre |
| :--- | :--- | :--- | :--- |
| **1. Definición y validación** | 21 oct 2026 | 23 oct 2026 | - |
| **2. Diseño y preparación técnica** | 22 oct 2026 | 25 oct 2026 | **H1 (25 oct):** Reglas documentadas, entorno listo y pruebas iniciales de integraciones. |
| **3. Usuarios y registros base** | 24 oct 2026 | 28 oct 2026 | - |
| **4. Equipos e inventario** | 27 oct 2026 | 1 nov 2026 | **H2 (1 nov):** Fichas, movimientos, estados, alertas, etiquetas, mantenimiento y fallas comprobados. |
| **5. Compras, cotizaciones y presupuesto** | 31 oct 2026 | 7 nov 2026 | **H3 (7 nov):** Compras con antecedentes, descuento presupuestario e ingreso documental operativo. |
| **6. Solicitudes y préstamos** | 6 nov 2026 | 11 nov 2026 | **H4 (11 nov):** Flujo completo de solicitud móvil/PC, entrega y devolución con permisos validados. |
| **7. Integración y calidad** | 12 nov 2026 | 15 nov 2026 | **H5 (15 nov):** Pruebas funcionales/integración documentadas sin defectos críticos o altos. |
| **8. Validación y entrega** | 16 nov 2026 | 20 nov 2026 | **H6 (20 nov):** Aceptación con contraparte, ensayo de restauración RPO/RTO y entrega final. |