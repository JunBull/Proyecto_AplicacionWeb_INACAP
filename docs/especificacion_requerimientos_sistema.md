# ESPECIFICACIÓN FUNCIONAL Y TÉCNICA DEL SISTEMA (REQUERIMIENTOS Y GESTIÓN)

## 1. CONTEXTO, ALCANCE Y STAKEHOLDERS

### 1.1 Identificación Institucional, Académica y Actores Clave
* **Institución:** Instituto Profesional / Centro de Formación Técnica INACAP.
* **Sede:** Sede Temuco.
* **Área Académica:** Área de Informática y Telecomunicaciones (carreras de Ingeniería en Informática y Analista Programador).
* **Asignatura:** Proyecto de Título.
* **Docente Guía y Evaluador:** Roberto Alexis Alveal Ortega.
* **Autores del Proyecto:** Fernanda Anaís Vallejos Quintana, Matías Antonio Vargas Aliaga.
* **Contraparte Técnica y Cliente Interno:** Dirección de Carrera del Área de Informática (Directora de Carrera).

### 1.2 Objetivos del Sistema y Problemática Actual del Pañol
El pañol de informática de INACAP Sede Temuco cumple una función operativa neurálgica en el soporte a las actividades académicas prácticas y docentes. Sin embargo, su operación tradicional presenta una serie de ineficiencias críticas que limitan el aprovechamiento de los recursos institucionales:
1. **Descentralización y dispersión de la información:** Inexistencia de un repositorio centralizado y consistente; la información de inventario se gestiona de manera fragmentada en hojas de cálculo desactualizadas, registros informales y carpetas físicas, lo que genera desinformación sobre la existencia y ubicación real de los materiales.
2. **Pérdida de trazabilidad en préstamos y entregas:** Dificultad para rastrear de forma fidedigna la custodia de equipos entregados a docentes y estudiantes, careciendo de mecanismos de control sobre fechas de retorno, devoluciones pendientes, mermas o daños físicos ocurridos durante el uso académico.
3. **Quiebres recurrentes de inventario de insumos:** Inexistencia de monitoreo automático sobre el stock crítico o niveles de reposición mínima de consumibles esenciales (cables, conectores, soldadura, microcontroladores, componentes pasivos), derivando en compras de urgencia y demoras en talleres prácticos.
4. **Ausencia de control estructurado de mantenimiento:** Los equipos de laboratorio (computadores de laboratorio, switches, routers, osciloscopios, fuentes de poder) no cuentan con una calendarización sistemática de mantenimientos preventivos (semestrales/anuales) vinculada a su nivel de criticidad operativa, operando bajo un esquema meramente correctivo tras la falla.
5. **Canal deficiente para el reporte de averías:** Los docentes y personal técnico que identifican fallas en los equipos de laboratorio carecen de una vía expedita y formal para informar anomalías, lo que retrasa los diagnósticos y provoca que equipos defectuosos permanezcan disponibles para nuevos préstamos.
6. **Falta de respaldo técnico y económico en bajas de activos:** Carencia de un procedimiento estandarizado que fundamente técnicamente cuándo la reparación de un equipo supera su costo de reposición, generando acumulación de chatarra electrónica o compras desprovistas de auditoría previa.
7. **Procesos manuales y no auditables de adquisición:** Registro manual de compras mediante digitación de facturas, cotizaciones gestionadas de manera artesanal sin garantizar una cuota mínima de comparación para auditorías de transparencia, y descontrol en la imputación y saldo presupuestario disponible por carrera.

**Objetivo general del sistema:**
Diseñar, desarrollar e implementar una aplicación web modular para la gestión integral de equipos, insumos, adquisiciones, presupuesto y préstamos del pañol del Área de Informática de INACAP Sede Temuco, asegurando la trazabilidad absoluta de las operaciones, la consistencia de inventario en tiempo real, el control del ciclo de vida de los activos y una experiencia de usuario ágil y responsiva para administradores, pañoleros y docentes.

### 1.3 Restricciones de Tiempo y Planificación Temporal
* **Plazo de Ejecución:** Ventana de desarrollo e implementación estrictamente acotada a **31 días calendario**, comprendida entre el **21 de octubre y el 20 de noviembre de 2026**.
* **Condicionante de Planificación:** La duración de un mes calendario impone una restricción severa sobre la gestión del alcance y el esfuerzo técnico. La arquitectura y la estrategia de construcción priorizan la cohesión modular, la reutilización de componentes estándar y la prevención de sobreingeniería, avanzando de manera incremental a través de hitos de seguimiento rigurosos:
  * **Hito H1 (25 de octubre de 2026):** Definiciones y viabilidad técnica (reglas de negocio documentadas, entorno de desarrollo y pruebas de integración base preparadas).
  * **Hito H2 (01 de noviembre de 2026):** Equipos e inventario (fichas técnicas, catálogo estandarizado, movimientos de stock, alertas de stock mínimo, gestión de fallas y etiquetado con códigos de barras).
  * **Hito H3 (07 de noviembre de 2026):** Compras y presupuesto (módulo de proveedores, flujo de cotizaciones masivas, adjudicación con cuotas, imputación presupuestaria e ingreso documental vía OCR).
  * **Hito H4 (11 de noviembre de 2026):** Préstamos integrados (solicitudes web/móviles anticipadas, despacho en mesón, control dual consumibles/retornables y gestión de devoluciones con daños).
  * **Hito H5 (15 de noviembre de 2026):** Calidad de la versión integrada (ejecución de pruebas funcionales, concurrencia, carga y aseguramiento de ausencia de defectos críticos).
  * **Hito H6 (20 de noviembre de 2026):** Validación y entrega (pruebas de aceptación formal con la contraparte, ensayo de recuperación ante desastres y cierre documental).

### 1.4 Criterios de Delimitación del Alcance Operativo y Tecnológico
Con el propósito de maximizar la eficiencia y mitigar riesgos en el plazo de 31 días, se establecen tres criterios fundamentales de delimitación:
1. **Aplicación Web Adaptable (Responsive Web App) vs. Aplicación Móvil Nativa:**
   * *Justificación Operativa:* El desarrollo de aplicaciones móviles nativas independientes (iOS y Android) añadiría una sobrecarga prohibitiva de tiempo, exigiendo el mantenimiento de múltiples bases de código, configuración de entornos SDK diferenciados y procesos de publicación/distribución en tiendas institucionales.
   * *Decisión Técnica:* Se implementa una **aplicación web adaptable** optimizada mediante Tailwind CSS y componentes reactivos Livewire. Esto permite a los docentes acceder desde cualquier navegador en sus teléfonos celulares para consultar disponibilidad y formular solicitudes anticipadas sin necesidad de descargar ni instalar aplicaciones adicionales, manteniendo un único núcleo de desarrollo y despliegue para escritorio y móviles.
2. **Códigos de Barras vs. Códigos QR:**
   * *Justificación de Infraestructura Existente:* El pañol del Área de Informática dispone de una **impresora de etiquetas física** y dispositivos lectores ópticos (pistolas lectoras 1D tipo emulación teclado / HID).
   * *Decisión Técnica:* Se prioriza formalmente la generación e impresión de **etiquetas con códigos de barras lineales (1D - Code 128)**, adaptadas a las dimensiones y especificaciones del hardware disponible en pañol. Cada etiqueta incorpora un identificador único numérico/alfanumérico y texto descriptivo estandarizado (nombre, carrera y pañol responsable). La arquitectura mantiene la capacidad de extenderse hacia códigos QR si la institución adquiere hardware bidimensional a futuro, pero el alcance operativo del piloto se consolida sobre códigos de barras.
3. **Roles con Acceso al Sistema vs. Receptores sin Cuenta:**
   * *Justificación de Seguridad y Gestión de Identidades:* Los estudiantes y colaboradores externos no gestionan compras, presupuestos ni inventarios; su interacción se restringe a recibir y devolver materiales en el mesón de atención. Crear y administrar cientos de cuentas estudiantiles en una base de datos local introduciría fricción operativa, problemas de restablecimiento de claves y potenciales vectores de exposición de accesos.
   * *Decisión Técnica:* La autenticación en la plataforma se limita estrictamente a tres roles: **Administrador**, **Encargado de Pañol** y **Docente**. Los estudiantes actúan exclusivamente como **receptores físicos**; su identidad (RUT, nombre y carrera) es registrada fiduciariamente por el Encargado de Pañol en el momento de la entrega presencial, asegurando la trazabilidad total del préstamo sin requerir que el estudiante posea credenciales activas en la aplicación web.

---

## 2. MATRIZ DE REQUERIMIENTOS FUNCIONALES Y ROLES

### 2.1 Detalle Exhaustivo por Módulo Funcional

#### Módulo 1: Equipos y Activos (Hardware y Bienes de Uso)
Este módulo gestiona el ciclo de vida completo de los bienes durables y equipos tecnológicos del laboratorio y pañol:
* **Ficha Técnica Digital Estandarizada:** Cada equipo cuenta con una ficha técnica única y persistente en el sistema. Los atributos obligatorios son: identificador interno unívoco, nombre estandarizado, tipo de equipo, marca, modelo, número de serie de fábrica, laboratorio o sala de ubicación habitual, carrera a la que pertenece, fotografía representativa en formato digital y estado operativo actual (Operativo, En Mantenimiento, Con Falla, En Reparación Externa, De Baja).
* **Política de Nombres Estandarizados:** Se prohíbe el uso de abreviaturas o siglas ambiguas (tales como "PC-01" o "SW-L2"). El sistema exige denominaciones técnicas descriptivas (por ejemplo: *"Computador de Escritorio All-in-One Intel Core i7 - Lab Redes"* o *"Switch Administrable Capa 3 24 Puertos Gigabit"*), permitiendo que nuevo personal técnico identifique los activos sin inducción previa.
* **Matriz de Criticidad y Mantenimiento Preventivo:** Clasificación de cada equipo según su criticidad operativa (Alta, Media, Baja). El sistema permite programar planes de mantenimiento preventivo periódicos (semestrales o anuales) y emite alertas preventivas automáticas previas al vencimiento de las fechas de intervención, registrando las labores efectuadas, fechas de ejecución y responsable técnico.
* **Informe y Gestión de Novedades (Fallas):** Canal interactivo para que los docentes y pañoleros registren reportes de anomalías o fallas detectadas en el uso diario. El reporte captura automáticamente la fecha, usuario informante, descripción detallada del desperfecto y genera un cambio de estado temporal del equipo para bloquear preventivamente su despacho a nuevos préstamos hasta su revisión y cierre técnico.
* **Bajas Técnicas y Solicitudes de Reemplazo:** Cuando un equipo sufre daños irreparables o su costo de reparación técnica y económica supera el costo de adquirir un activo nuevo de reposición, el sistema permite formalizar la solicitud de baja. Es requisito mandatorio adjuntar los informes técnicos de diagnóstico y las cotizaciones de reparación de proveedores externos que justifiquen la inviabilidad económica de repararlo. Estos antecedentes quedan archivados como respaldo auditable para la Dirección de Carrera al momento de justificar adquisiciones de reposición.

#### Módulo 2: Insumos e Inventario (Consumibles y Repuestos)
Este módulo administra los materiales fungibles, cables, componentes electrónicos y existencias de stock del pañol:
* **Ingreso Documental Asistido por Escaneo / OCR (Human-in-the-Loop):** Carga digital de facturas u órdenes de compra en formato PDF o imagen (PNG/JPG). El sistema ejecuta un procesamiento asistido de extracción de texto y datos para identificar nombre de insumos, cantidades adquiridas, costos unitarios e impuestos.
  * *Validación Humana Obligatoria:* El reconocimiento óptico no realiza inserciones directas ni desatendidas en la base de datos; los datos extraídos se presentan en una pantalla interactiva de confirmación donde el Encargado de Pañol revisa, corrige inconsistencias y aprueba formalmente el registro.
  * *Prevención de Duplicidad:* El sistema valida el número de documento y emisor para evitar que una misma factura u orden sea procesada e imputada más de una vez.
* **Jerarquía de Ubicaciones Físicas Granulares:** Estructura de almacenamiento multinivel que permite registrar y consultar con precisión milimétrica el lugar de guardado de cada insumo. La jerarquía contempla: *Sede -> Área/Carrera -> Pañol/Laboratorio -> Mueble/Estante -> Cajón/Puerta/Nivel*.
* **Motor de Etiquetado Institucional con Códigos de Barras Propios:** Generación dinámica de etiquetas identificadoras en formato estándar lineal 1D (Code 128). Cada etiqueta incluye el código de barras legible por pistola óptica, identificador único, nombre estandarizado del insumo/equipo, pañol asignado y carrera propietaria.
  * *Capacidad de Impresión y Reimpresión:* El módulo permite generar lotes de etiquetas optimizados para la impresora institucional y reimprimir etiquetas deterioradas manteniendo el mismo identificador, sin crear registros duplicados.
* **Monitor y Notificaciones de Stock Crítico:** Parametrización de un umbral de stock mínimo para cada tipo de insumo. Cuando las existencias alcanzan o caen por debajo de dicho umbral tras una entrega, el sistema gatilla automáticamente alertas visuales en el panel de pañol y dashboard administrativo para gestionar oportunamente la compra de reposición.
* **Control de Movimientos, Saldos y Kardex Transaccional:** Registro inmutable de toda entrada (por compra o devolución) y salida (por consumo de taller, préstamo o merma debidamente justificada), impidiendo en todos los casos que una transacción derive en existencias negativas mediante bloqueos transaccionales a nivel de servidor.

#### Módulo 3: Compras, Cotizaciones, Proveedores y Presupuesto
Módulo orientado a la gestión transparente y controlada de las adquisiciones del área:
* **Directorio Categorizado de Proveedores:** Base de datos relacional de proveedores comerciales clasificados estrictamente por rubro y servicio: *Proveedores de Insumos y Materiales Fungibles* versus *Proveedores de Servicios Técnicos y Mantenimiento Especializado*. Almacena razón social, RUT, dirección, ejecutivos de contacto, teléfonos y correos electrónicos.
* **Envío Automatizado y Masivo de Solicitudes de Cotización:** A partir de una necesidad de compra o alerta de stock crítico, el sistema permite seleccionar múltiples proveedores correspondientes al rubro y despachar correos electrónicos masivos y personalizados con la solicitud formal de cotización y los requerimientos técnicos adjuntos. El despacho se procesa de forma asíncrona mediante colas de trabajo en segundo plano para no bloquear la interfaz web del usuario.
* **Política de Cuotas de Cotización y Gestión de Adjudicaciones:** Almacenamiento y vinculación de las propuestas económicas recibidas a la solicitud de compra. Para cumplir con las normas institucionales y auditorías de transparencia, el sistema exige adjuntar obligatoriamente un mínimo de **2 a 3 cotizaciones** formales antes de permitir al Administrador autorizar la adjudicación de una compra.
* **Flujo de Autorización de Excepciones de Compra:** Si por condiciones de mercado, exclusividad de distribución de repuestos o desabastecimiento general no es posible reunir la cuota mínima de 2 o 3 cotizaciones, el sistema bloquea el flujo habitual e implementa un subproceso de excepción. El Administrador debe registrar una justificación explícita y documentada en la plataforma autorizando la excepción, quedando registrado el usuario, fecha y motivo para efectos de auditoría posterior.
* **Imputación Presupuestaria y Control de Saldos en Tiempo Real:** El sistema mantiene el saldo presupuestario asignado anualmente o semestralmente a cada carrera del área de informática. Antes de confirmar cualquier adjudicación, el motor transaccional comprueba que la carrera cuente con fondos suficientes. El descuento del presupuesto se ejecuta de manera atómica, impidiendo sobregiros y registrando el monto comprometido, número de compra y fecha exacta.

#### Módulo 4: Préstamos y Solicitudes de Materiales
Este módulo regula la interacción diaria entre los usuarios académicos y el pañol de herramientas y laboratorios:
* **Solicitud Anticipada Multiplataforma (Móvil / Web) para Docentes:** Interfaz web liviana y adaptable a teléfonos móviles que permite a los docentes consultar la disponibilidad de equipos e insumos y formular solicitudes anticipadas de préstamo indicando: fecha y bloque de inicio, fecha estimada de retorno, asignatura, laboratorio de uso y listado de ítems y cantidades. El docente puede revisar en todo momento el estado de sus solicitudes (Pendiente, Aprobada, Rechazada, Despachada, Cerrada).
* **Proceso de Despacho y Entrega Física en Pañol:** El Encargado de Pañol visualiza las solicitudes aprobadas en su estación de trabajo. Al momento de la entrega física en mesón, comprueba la disponibilidad actual, identifica y valida la identidad del receptor presencial (RUT/Nombre de docente o estudiante) y registra formalmente el despacho mediante la lectura óptica de las etiquetas con código de barras.
* **Clasificación Operativa: Consumibles vs. Retornables:**
  * *Insumos Consumibles:* Su despacho en mesón descuenta inmediatamente las existencias del inventario de forma definitiva, sin generar deuda de devolución.
  * *Materiales y Equipos Retornables:* Su entrega genera un registro de préstamo activo, vinculando el número de serie o código de activo con el receptor y fijando la fecha límite de retorno.
* **Control de Devoluciones, Daños, Pérdidas e Incidencias:** Al momento de la recepción física del retorno, el pañolero revisa los elementos, las cantidades devueltas y su estado físico y funcional.
  * *Devoluciones Parciales:* El sistema mantiene abierta la orden de préstamo actualizando el balance de ítems pendientes.
  * *Devoluciones con Daño o Avería:* Se registra el desperfecto como una incidencia formal en la devolución, lo que dispara automáticamente un informe de falla y cambia el estado del equipo a "Con Falla" para derivarlo a diagnóstico técnico, evitando que el daño sea categorizado erróneamente como un ítem faltante en inventario.
  * *Cierre de Préstamo:* La operación se da por finalizada únicamente cuando el 100% de los elementos retornables ha sido reintegrado o regularizado por incidencia.
* **Control de Duplicidad e Idempotencia en Movimientos:** El sistema incorpora validaciones para evitar la doble confirmación accidental de un despacho o devolución (por doble clic en la interfaz o concurrencia de solicitudes de red), garantizando que las existencias y los movimientos del kardex no se dupliquen.

#### Módulo 5: Administración, Seguridad y Parámetros del Sistema
Módulo de control de acceso, configuración estructural y gobernanza del sistema:
* **Gestión de Cuentas de Usuario y Autenticación Segura:** Altas, modificaciones, suspensiones y bajas lógicas de cuentas de usuario. Almacenamiento seguro de credenciales mediante algoritmos de hashing robustos (Bcrypt/Argon2id), control de sesiones seguras por HTTPS y políticas contra ataques de fuerza bruta (bloqueo transitorio de IP o cuenta tras intentos fallidos).
* **Asignación de Roles y Ámbitos de Carrera:** Asignación de perfiles de acceso (Administrador, Encargado de Pañol, Docente) y vinculación de usuarios a una o más carreras específicas. La visualización de presupuestos, compras y activos se encuentra restringida al ámbito institucional autorizado para cada usuario.
* **Configuración Paramétrica Global y Límites del Sistema:** Panel de control para que el Administrador defina parámetros de operación institucional, tales como: umbrales de stock crítico por defecto, periodicidad de planes de mantenimiento preventivo por categoría de criticidad, cuotas mínimas de cotización por compra, límites de tamaño para carga de documentos digitales y políticas de retención de respaldos.

---

### 2.2 Matriz de Permisos por Rol

A continuación se detalla la matriz de acceso y permisos por funcionalidad, especificando los tres perfiles de usuario que operan la aplicación web:

| Módulo / Funcionalidad Operativa | Administrador | Encargado de Pañol | Docente | Validaciones y Restricciones a Nivel de Servidor |
| :--- | :---: | :---: | :---: | :--- |
| **Autenticación y Sesión de Usuario** | Total | Total | Total | Validación obligatoria de credenciales hasheadas; regeneración de sesión para evitar Session Fixation; terminación por inactividad. |
| **Gestión de Usuarios, Roles y Accesos** | Total | Solo lectura | Sin acceso | Middleware `role:administrador`; bloqueo estricto por servidor a nivel de rutas y controladores; inmutabilidad del rol docente. |
| **Configuración de Parámetros y Carreras** | Total | Sin acceso | Sin acceso | Validación en servidor de rangos numéricos y formatos; trazabilidad auditable en logs de base de datos. |
| **Consulta de Fichas de Equipos y Activos** | Total | Total | Lectura (filtros) | Los docentes solo visualizan equipos asignados a laboratorios académicos de su área; sin acceso a costos contables sensibles. |
| **Creación y Edición de Fichas Técnicas** | Total | Total | Sin acceso | Validación de unicidad de número de serie y formato de nombre estandarizado en Form Requests de Laravel. |
| **Reporte de Novedades y Fallas** | Total | Total | Creación y Consulta | El docente solo puede reportar fallas y ver el estado de sus propios reportes; el pañolero actualiza el diagnóstico técnico. |
| **Programación de Mantenimiento Preventivo**| Total | Total | Sin acceso | Validación de fechas futuras; reglas de criticidad configuradas en el servidor; verificación de rol autorizado. |
| **Gestión de Bajas Técnicas y Reemplazos** | Autorización final | Registro técnico | Sin acceso | El pañolero sube informes técnicos y cotizaciones de reposición; solo el Administrador puede marcar el activo formalmente "De Baja". |
| **Ingreso Documental por Escaneo / OCR** | Total | Total | Sin acceso | Validación de extensiones permitidas (PDF, JPG, PNG) y tamaño máximo de archivo; validación humana antes de persistir en base de datos. |
| **Gestión de Insumos y Ubicaciones Físicas** | Total | Total | Solo lectura | Validación obligatoria de existencia de clave foránea de ubicación antes de registrar inventario. |
| **Generación e Impresión de Códigos de Barras**| Total | Total | Sin acceso | Comprobación de existencia del identificador unívoco; prevención de duplicación de identificadores de activo. |
| **Visualización de Alertas de Stock Crítico** | Total | Total | Sin acceso | Consultas optimizadas con índices relacionales; triggers de servidor al descender del umbral mínimo. |
| **Directorio de Proveedores** | Total | Total (gestión) | Sin acceso | Verificación de formato de RUT chileno válido y dirección de correo electrónico institucional o comercial. |
| **Envío Masivo de Solicitudes de Cotización**| Autorización | Creación y envío | Sin acceso | Procesamiento asíncrono en cola (Job Queue); validación de lista de destinatarios registrados en directorio. |
| **Adjudicación de Compras y Excepciones** | Autorización final | Registro ofertas | Sin acceso | El servidor rechaza adjudicaciones con menos de 2 cotizaciones a menos que exista una excepción firmada por el Administrador. |
| **Gestión y Descuento Presupuestario** | Asignación y control| Solo lectura saldo | Sin acceso | Transacciones ACID con bloqueo de fila (`SELECT FOR UPDATE`); verificación obligatoria de saldo disponible antes del commit. |
| **Solicitud Anticipada de Préstamo** | Total | Total | Creación y seguimiento| Los docentes solo pueden solicitar ítems para sus asignaturas y horarios académicos autorizados. |
| **Despacho y Entrega de Préstamos en Mesón** | Supervisión | Total | Sin acceso | Comprobación atómica de disponibilidad de stock en el instante del despacho; captura obligatoria del identificador del receptor. |
| **Recepción de Devoluciones y Registro Daños** | Supervisión | Total | Sin acceso | Transacciones atómicas de actualización de balance; generación automática de incidencias técnicas en caso de daños. |

---

## 3. SELECCIÓN TECNOLÓGICA Y JUSTIFICACIÓN TÉCNICA

### 3.1 Síntesis del Análisis Cualitativo y Cuantitativo

La selección de la arquitectura y tecnologías de la aplicación web fue sustentada en un análisis exhaustivo orientado a satisfacer la totalidad de los requerimientos funcionales del pañol de informática de INACAP Sede Temuco, asegurando máxima confiabilidad operativa en estricto cumplimiento del plazo disponible de 31 días calendario.

#### Análisis Cualitativo de Alternativas Tecnológicas
A continuación se presenta de forma íntegra la evaluación cualitativa de los componentes de arquitectura:

| Componente Arquitectónico | Alternativas Consideradas | Análisis Aplicado al Proyecto | Selección Propuesta |
| :--- | :--- | :--- | :--- |
| **Framework de desarrollo** | Laravel (PHP) / Django (Python) | Ambos permiten construir aplicaciones empresariales de gestión. Laravel ofrece un ecosistema altamente integrado para acceso a datos (Eloquent ORM), validaciones robustas, autenticación integrada, colas de trabajo en segundo plano y planificador de tareas, esencial para articular inventario, compras, presupuestos, préstamos y notificaciones. Django es viable pero introduce componentes adicionales fuera del ecosistema web estándar requerido por la institución. | **Laravel**, para concentrar la aplicación, las reglas de negocio y los procesos del sistema en un entorno PHP robusto y unificado. |
| **Interfaz de usuario** | Livewire y Blade / React integrado con Laravel | Livewire permite desarrollar interfaces dinámicas, formularios interactivos y búsquedas en tiempo real directamente mediante PHP y plantillas Blade, minimizando la duplicación de validaciones entre frontend y backend. React permite mayor lógica del lado del cliente, pero añade dependencias de compilación complejas, bifurcación de modelos de datos y curva de aprendizaje que elevan el esfuerzo de integración en el plazo acotado de un mes. | **Livewire y Blade**, por su óptima adecuación a formularios administrativos, filtros reactivos, solicitudes de préstamo y operaciones de pañol. |
| **Base de datos relacional** | PostgreSQL / MySQL | Ambos motores permiten implementar el modelo relacional y transacciones ACID. Sin embargo, PostgreSQL ofrece soporte superior para restricciones complejas, claves foráneas estrictas, concurrencia avanzada mediante MVCC, integridad referencial sólida y tratamiento eficiente de datos estructurados, aspectos indispensables para coordinar presupuestos por carrera, kardex de insumos y préstamos simultáneos. | **PostgreSQL**, como motor relacional común para centralizar y proteger todos los módulos del sistema. |
| **Extracción de datos desde documentos** | Motor OCR local (Tesseract OCR) / Servicio externo en la nube | Una solución de OCR local permite procesar facturas sin costos recurrentes por API ni dependencia de servicios de terceros en la nube, aunque requiere calibración de modelos y control de procesamiento. Un servicio externo (cloud) facilita la extracción pero añade dependencias externas, costos variables y revisión de políticas de privacidad institucional sobre facturas. | **Pendiente de selección definitiva con la contraparte**, mediante pruebas controladas con documentos representativos y autorizados. |
| **Correos y notificaciones** | Envío síncrono inmediato / Procesamiento asíncrono en segundo plano | El envío inmediato (síncrono) bloquea la interfaz del usuario hasta que el servidor SMTP responde, provocando latencias o caídas ante fallas de red. El procesamiento en segundo plano (Job Queues de Laravel) desacopla la solicitud de cotización de su despacho efectivo, registrando estados de envío, reintentos y bitácoras de error sin degradar la experiencia de usuario. | **Procesamiento en segundo plano**, mediante colas de Laravel conectadas a un servicio de correo SMTP autorizado. |
| **Identificación de materiales** | Etiquetas con códigos de barras / Etiquetas con códigos QR | La institución cuenta con una impresora física de etiquetas y lectores ópticos 1D en el pañol. Cada etiqueta debe incluir un código unívoco legible y texto descriptivo estandarizado (nombre, carrera y pañol responsable). El código de barras satisface el 100% de la infraestructura instalada sin exigir adquisiciones complementarias. | **Generación de etiquetas propias con códigos de barras (Code 128)**, manteniendo compatibilidad con la impresora institucional. |
| **Acceso desde celulares** | Aplicación web adaptable / Aplicación móvil independiente | Una web adaptable permite a los docentes acceder desde el navegador de su teléfono celular sin requerir instalación ni distribución a través de tiendas de aplicaciones (Play Store / App Store). Una app nativa agrega desarrollo específico para iOS/Android y mantenimiento duplicado incompatible con el plazo de un mes. | **Aplicación web adaptable (Responsive Web App)**, construida con Tailwind CSS y Blade/Livewire. |

#### Análisis Cuantitativo Ponderado de Configuración Tecnológica
Para complementar la evaluación de viabilidad frente a las condiciones del proyecto (cobertura funcional y restricción temporal de 31 días), se ejecutó una evaluación matricial cuantitativa comparando la alternativa seleccionada frente a la combinación con framework desacoplado de frontend (React):

| Criterio de Decisión Técnica | Ponderación | Laravel con Livewire y PostgreSQL | Laravel con React y PostgreSQL |
| :--- | :---: | :---: | :---: |
| **Capacidad para implementar los módulos requeridos** | 30 % | 5 (1,50) | 5 (1,50) |
| **Menor esfuerzo de integración previsto** | 25 % | 5 (1,25) | 3 (0,75) |
| **Adecuación al plazo de desarrollo (31 días)** | 20 % | 4 (0,80) | 3 (0,60) |
| **Mantenimiento y soporte futuro de la aplicación** | 15 % | 4 (0,60) | 4 (0,60) |
| **Flexibilidad para ampliar la interfaz visual** | 10 % | 4 (0,40) | 5 (0,50) |
| **Resultado Ponderado Total (Escala 1 a 5)** | **100 %** | **4,55** | **3,95** |

*Interpretación Técnica:* La configuración **Laravel + Livewire + PostgreSQL** obtiene una calificación de **4,55 sobre 5,00**, superando con holgura a la opción desacoplada (3,95). La ventaja determinante radica en el *menor esfuerzo de integración* (5 frente a 3) y la *adecuación al plazo estricto de desarrollo* (4 frente a 3), al unificar la lógica del negocio, las validaciones de entrada y la renderización visual en un único lenguaje (PHP 8+) y motor de plantillas reactivo, evitando el desarrollo redundante de APIs REST intermedias y el doble mantenimiento de modelos en JavaScript y PHP.

#### Detalle de Lenguajes, Herramientas y Componentes
* **PHP:** Lenguaje base en el servidor para implementar reglas de negocio, validaciones, control de transacciones, políticas de acceso y gestión de archivos.
* **SQL:** Definición de esquemas de datos, integridad referencial, índices de rendimiento y consultas complejas en PostgreSQL.
* **HTML y Blade:** Construcción estructurada de vistas reutilizables, componentes modulares y formularios interactivos.
* **CSS y Tailwind CSS (con Vite):** Diseño visual adaptable, diseño responsivo optimizado para móviles y computadores sin sobrecarga de hojas de estilo.
* **JavaScript:** Interacciones específicas en el navegador que complementen los componentes Livewire (control de periféricos, disparadores de lectura óptica).
* **Composer:** Administrador de paquetes y dependencias del entorno PHP.
* **Git:** Control de versiones distribuido para trazabilidad de código y colaboración.
* **pgAdmin:** Administración y supervisión del motor relacional PostgreSQL.
* **PHPUnit y Laravel Testing Framework:** Automatización de pruebas unitarias, de integración y de seguridad.

### 3.2 Factibilidad Técnica y Condiciones a Comprobar
El éxito operativo de la solución requiere verificar y comprobar rigurosamente una serie de condiciones técnicas previas a la puesta en marcha:
1. **Documentos de Compra y Reconocimiento OCR:**
   * *Condiciones:* Variaciones en el formato tipográfico de facturas electrónicas chilenas (DTE) y órdenes de compra, calidad de resolución de escaneo o fotografía (mínimo recomendado de 300 DPI), y campos exactos a capturar (RUT emisor, número de factura, ítems, cantidades, montos netos e impuestos).
   * *Acción:* Ejecutar pruebas de extracción sobre un lote inicial de 10 documentos representativos antes de fijar los parámetros del motor de reconocimiento.
2. **Servicio de Correo Electrónico (SMTP Institucional):**
   * *Condiciones:* Disponibilidad de una cuenta de correo institucional autorizada con protocolo SMTP autenticado y cifrado TLS/SSL (puertos 587 o 465).
   * *Acción:* Configurar límites de despacho (rate limiting) para prevenir bloqueos por spam y asegurar que las colas de trabajo manejen reintentos automáticos y registros de fallo en base de datos.
3. **Impresora de Etiquetas Física y Lectores Ópticos:**
   * *Condiciones:* Compatibilidad del hardware de pañol (impresora térmica existente), modelo exacto, método de conexión (USB directo o red Ethernet), dimensiones físicas del rollo de etiquetas (por ejemplo, 50x25 mm) y calibración de márgenes en el formato de salida.
   * *Acción:* Validar la lectura fidedigna del código de barras generado (simbología Code 128) utilizando las pistolas ópticas tipo teclado en pañol antes del despliegue masivo.
4. **Red, Conectividad y Alojamiento:**
   * *Condiciones:* Definición del servidor de alojamiento (servidor local institucional dentro de la intranet de la sede versus máquina virtual/VPS externa), apertura de puertos de comunicación, asignación de nombre de dominio/DNS institucional y emisión de certificados digitales SSL/TLS para navegación segura mediante protocolo HTTPS.

---

## 4. CATÁLOGO COMPLETO DE INDICADORES DE DESEMPEÑO (KPI)

### 4.1 Enfoque Metodológico SMART
Los indicadores clave de desempeño (KPI) han sido diseñados bajo el estándar SMART (*Specific, Measurable, Achievable, Relevant, Time-bound*). Permiten medir con precisión cuantitativa la eficiencia operativa, la exactitud del inventario, la integridad de los datos y el cumplimiento de las normativas de compra y mantenimiento.

### 4.2 Tabla Exhaustiva de Indicadores de Desempeño (KPI-01 al KPI-11)
A continuación se transcriben de forma íntegra las fórmulas matemáticas, líneas base, metas propuestas, fuentes de datos y mecanismos de evaluación de todos los indicadores del proyecto:

| Código e Indicador | Método y Fórmula Matemática de Cálculo | Línea Base | Meta Propuesta | Fuente de Información y Mecanismo/Periodo de Evaluación |
| :--- | :--- | :--- | :--- | :--- |
| **KPI-01. Integridad de fichas de equipos** | $$\text{KPI-01} = \left( \frac{\text{Equipos con campos obligatorios completos}}{\text{Total de equipos registrados}} \right) \times 100$$ | Pendiente de revisión de los registros existentes. | $\ge 95\,\%$ al finalizar la carga y revisión inicial. | Fichas técnicas registradas y listado de campos obligatorios validados. Medición al cierre de la carga inicial de datos. |
| **KPI-02. Exactitud del inventario de insumos** | $$\text{KPI-02} = \left( \frac{\text{Referencias revisadas cuya cantidad y ubicación coinciden}}{\text{Total de referencias revisadas en verificación física}} \right) \times 100$$ | Pendiente de conteo inicial físico. | $\ge 95\,\%$ de coincidencia en la muestra acordada. | Inventario del sistema comparado contra acta de conteo físico en pañol. Medición inicial y al cierre del piloto operativo. |
| **KPI-03. Trazabilidad de entregas y devoluciones** | $$\text{KPI-03} = \left( \frac{\text{Operaciones con todos los datos de trazabilidad exigidos}}{\text{Total de operaciones de entrega y devolución revisadas}} \right) \times 100$$ | Pendiente de revisión del registro documental actual. | $100\,\%$ durante la validación del sistema. | Historial transaccional de movimientos, solicitantes y encargados de pañol. Revisión semanal durante el piloto. |
| **KPI-04. Detección de stock crítico** | $$\text{KPI-04} = \left( \frac{\text{Escenarios con stock } \le \text{ al mínimo que generan alerta correcta}}{\text{Total de escenarios de stock crítico ejecutados}} \right) \times 100$$ | No aplica como medición histórica; se verifica mediante pruebas. | $100\,\%$ de los escenarios definidos antes de la aceptación. | Casos de prueba de salidas de stock y bitácora de alertas visuales generadas. Evaluación durante las pruebas funcionales. |
| **KPI-05. Identificación mediante códigos de barras** | $$\text{KPI-05} = \left( \frac{\text{Etiquetas leídas que recuperan el registro de material correcto}}{\text{Total de etiquetas probadas con lector óptico}} \right) \times 100$$ | Pendiente de revisión del etiquetado actual. | $100\,\%$ de una muestra de al menos 20 etiquetas (o de todas si existen menos). | Etiquetas físicas impresas y registro de captura del lector de código de barras. Evaluación previa a la aceptación formal. |
| **KPI-06. Cumplimiento del mantenimiento preventivo** | $$\text{KPI-06} = \left( \frac{\text{Mantenimientos preventivos realizados dentro del plazo}}{\text{Total de mantenimientos preventivos con vencimiento en el periodo}} \right) \times 100$$ | Pendiente de revisión de las bitácoras de mantención actuales. | $\ge 90\,\%$ en el primer semestre de operación (sujeto a validación con la contraparte). | Módulo de programación, fechas límite de intervención e informes de servicio. Seguimiento mensual y evaluación semestral. |
| **KPI-07. Exactitud de la extracción documental** | $$\text{KPI-07} = \left( \frac{\text{Campos extraídos correctamente por OCR antes de corrección manual}}{\text{Total de campos evaluados en la muestra documental}} \right) \times 100$$ | Pendiente de prueba inicial con documentos representativos. | $\ge 90\,\%$ para nombres, cantidades y costos en documentos con calidad acordada. | Comparación automatizada entre el documento fuente y los datos sugeridos por OCR. Evaluación con al menos 10 documentos autorizados. |
| **KPI-08. Respaldo de adjudicaciones** | $$\text{KPI-08} = \left( \frac{\text{Adjudicaciones con cuota de cotizaciones exigida o excepción autorizada}}{\text{Total de compras adjudicadas revisadas}} \right) \times 100$$ | Pendiente de revisión del procedimiento de compra vigente. | $100\,\%$ durante la validación y el primer mes de operación. | Solicitudes de compra, cotizaciones adjuntas (mínimo 2 a 3) y actas de excepción firmadas. Revisión caso por caso. |
| **KPI-09. Exactitud del saldo presupuestario** | $$\text{KPI-09} = \left( \frac{\text{Carreras cuyo saldo en sistema coincide con el cálculo de control}}{\text{Total de carreras evaluadas en el sistema}} \right) \times 100$$ | Pendiente de identificación de presupuestos y movimientos iniciales. | $100\,\%$ en las dos carreras participantes al cierre de las pruebas. | Presupuesto inicial asignado, movimientos de compra confirmados y auditoría de cálculo independiente. Revisión tras cada ciclo de pruebas. |
| **KPI-10. Procesamiento de solicitudes de cotización** | $$\text{KPI-10} = \left( \frac{\text{Correos de cotización aceptados por el SMTP en } \le 15 \text{ minutos}}{\text{Total de correos válidos encolados para envío}} \right) \times 100$$ | No aplica a procesos anteriores; requiere medición inicial. | $\ge 95\,\%$ durante las pruebas integradas con servicio de correo disponible. | Registro de trabajos en cola (Queue Jobs) y bitácoras de respuesta del servidor SMTP. Revisión diaria durante la validación. |
| **KPI-11. Respaldo de bajas y reemplazos** | $$\text{KPI-11} = \left( \frac{\text{Registros de baja o reemplazo con la totalidad de documentos exigidos}}{\text{Total de registros de baja o reemplazo revisados}} \right) \times 100$$ | Pendiente de revisión de antecedentes y carpetas existentes. | $100\,\%$ de los casos de baja generados durante la validación. | Ficha del equipo, motivo de desecho, diagnóstico técnico y cotización de reparación/reemplazo. Revisión por caso generado. |

---

## 5. ACUERDOS DE NIVEL DE SERVICIO (SLA) Y CONDICIONES OPERATIVAS

### 5.1 Catálogo Exhaustivo de Niveles de Servicio (SLA-01 al SLA-11)
Los Acuerdos de Nivel de Servicio (SLA) establecen los parámetros mínimos de calidad, disponibilidad, desempeño y contingencia que norman la operación de la aplicación web:

| Código y Nombre del Servicio / Parámetro | Meta Propuesta | Ventana de Medición Temporal | Mecanismo y Forma de Verificación |
| :--- | :--- | :--- | :--- |
| **SLA-01. Disponibilidad del Sistema** | Disponibilidad de al menos **98 %** dentro del horario de servicio operativo acordado. | Mensual durante la operación. En validación se informará el periodo efectivamente observado. | Registro automatizado de comprobaciones de acceso (health checks cada 5 min) e incidentes de caída de funciones principales. |
| **SLA-02. Tiempo de Respuesta en Operaciones Habituales** | Completar en **3 segundos o menos** el **95 %** de las consultas, búsquedas y registros evaluados. Excluye OCR, subida masiva de archivos y jobs de fondo. | Durante las pruebas de rendimiento y tras despliegues o cambios relevantes. | Medición de tiempos de carga en cliente (Network panel) desde el envío de la petición hasta el renderizado del resultado en pantalla. |
| **SLA-03. Capacidad de Usuarios Concurrentes** | Soportar inicialmente **10 usuarios simultáneos** ejecutando operaciones representativas de pañol, sin errores ni pérdida de consistencia, manteniendo el SLA-02. | Durante la ejecución de pruebas de carga iniciales y pruebas de concurrencia. | Ejecución coordinada de sesiones concurrentes realizando préstamos, consultas y movimientos simultáneos en base de datos. |
| **SLA-04. Tiempo de Reconocimiento Documental (OCR)** | Presentar el resultado de la extracción o un mensaje de fallo controlado en **60 segundos o menos** para el **95 %** de documentos de hasta 2 páginas válidas. | Durante las pruebas de integración y validación del módulo de ingreso documental. | Medición del tiempo transcurrido desde la confirmación de subida del archivo hasta la presentación de los campos sugeridos en pantalla. |
| **SLA-05. Envío de Solicitudes de Cotización** | Obtener aceptación del servidor de correo para al menos el **95 %** de los mensajes válidos encolados en **15 minutos o menos**, estando el SMTP disponible. | Revisión diaria durante las pruebas de integración y la fase de operación piloto. | Bitácora de trabajos en cola (Laravel Queue Jobs), verificando marcas temporales de encolado, intentos y respuesta del servidor de correo. |
| **SLA-06. Actualización Atómica de Existencias y Presupuesto** | Mostrar los valores estrictamente actualizados al consultar tras confirmar la operación, sin duplicar movimientos ni admitir saldos parciales inconsistentes. | Durante las pruebas funcionales, transaccionales y de concurrencia. | Comparación cruzada de balances de inventario y saldos de presupuesto inmediatamente antes y después de compras, préstamos y devoluciones. |
| **SLA-07. Rendimiento en Generación de Etiquetas** | Generar y preparar para impresión un lote de hasta **20 etiquetas** de código de barras en **10 segundos o menos** en el entorno de prueba definido. | Durante la fase de integración con la impresora de etiquetas institucional. | Medición del tiempo de procesamiento y renderizado del documento imprimible. El tiempo mecánico de impresión del hardware se mide aparte. |
| **SLA-08. Periodicidad y Respaldo de Seguridad** | Ejecutar un **respaldo completo diario** de la base de datos PostgreSQL y los archivos documentales adjuntos, con copia externa y **retención de 30 días**. | Verificación diaria programada. | Auditoría del log del cron de respaldo: comprobación de existencia física, tamaño de archivo, fecha y ubicación en almacenamiento secundario. |
| **SLA-09. Punto Objetivo de Recuperación (RPO)** | Limitar la pérdida potencial máxima de datos a **24 horas** de operación transaccional frente a una caída o interrupción catastrófica del servidor. | Medición por incidente crítico y durante el ensayo de restauración ante desastres. | Diferencia temporal entre el momento exacto del fallo catastrófico y la estampa de tiempo del último respaldo íntegro restaurado. |
| **SLA-10. Tiempo Objetivo de Recuperación (RTO)** | Restablecer el acceso web y las funciones esenciales del sistema en un tiempo máximo de **8 horas de soporte**, con infraestructura de reemplazo disponible. | Medición por incidente crítico y durante el ensayo de restauración ante desastres. | Cronómetro desde la confirmación formal del incidente de indisponibilidad hasta la validación operativa del servicio restaurado. |
| **SLA-11. Tiempo de Atención de Incidentes Críticos** | Iniciar la revisión técnica y diagnóstico en un tiempo máximo de **2 horas de soporte** desde la emisión del reporte de falla grave. | Registro por cada incidente de prioridad crítica notificado. | Bitácora de incidentes registrando hora de recepción, primera respuesta técnica, clasificación del impacto y plan de contención aplicado. |

### 5.2 Formulación Matemática de Disponibilidad Porcentual
La disponibilidad del sistema se calculará mensualmente aplicando la siguiente expresión formal estandarizada:

$$\text{Disponibilidad (\%)} = \left[ \frac{\text{Tiempo de servicio acordado} - \text{Tiempo de indisponibilidad}}{\text{Tiempo de servicio acordado}} \right] \times 100$$

* **Tiempo de servicio acordado ($T_{SA}$):** Horario operativo pactado con la contraparte, correspondiente a días hábiles institucionales de **lunes a viernes**, en jornada diurna de **08:00 a 18:00 horas** (10 horas diarias), excluyendo fines de semana y feriados oficiales.
* **Tiempo de indisponibilidad ($T_I$):** Sumatoria de minutos en que el sistema estuvo inaccesible o inoperativo para los usuarios finales dentro de la ventana de servicio acordada.

### 5.3 Definición Operativa de Indisponibilidad y Tolerancia a Fallas Parciales
* **Indisponibilidad Operativa Total:** Se considera que el sistema se encuentra indisponible cuando una falla crítica en la aplicación web, el motor de base de datos PostgreSQL, el servidor de alojamiento o la infraestructura de red impide de manera generalizada el inicio de sesión, el registro de préstamos en mesón, la consulta de existencias o la confirmación de compras.
* **Ventanas de Mantenimiento Planificado:** Las intervenciones técnicas programadas, despliegues de nuevas versiones o respaldos estructurales no computarán como tiempo de indisponibilidad ($T_I$) siempre que se ejecuten **fuera del horario de servicio acordado** (es decir, después de las 18:00 hrs, antes de las 08:00 hrs o durante fines de semana) y sean notificadas con al menos 24 horas de antelación.
* **Tratamiento de Fallas Parciales y Degradación Controlada:**
  * Si un servicio complementario o externo experimenta una interrupción (por ejemplo: indisponibilidad transitoria del servidor SMTP institucional de correos o falla del motor OCR al procesar un documento dañado), el sistema no se declara indisponible en su totalidad.
  * La arquitectura implementa mecanismos de tolerancia a fallas: los correos no enviados se mantienen seguros encolados en base de datos para reintento automático, y el ingreso de documentos permite al pañolero digitar o completar manualmente los ítems de compra sin interrumpir el flujo administrativo. Estas anomalías se clasifican como incidentes de soporte no bloqueantes.

### 5.4 Objetivos de Recuperación ante Desastres (RPO y RTO)
* **Punto Objetivo de Recuperación (RPO $\le$ 24 horas):** La pérdida máxima tolerable de datos se establece en 24 horas. Esta métrica se garantiza ejecutando respaldos lógicos y físicos diarios de la base de datos PostgreSQL de forma automatizada al término de cada jornada laboral, respaldando igualmente la totalidad de los archivos adjuntos (fotografías, cotizaciones e informes técnicos).
* **Tiempo Objetivo de Recuperación (RTO $\le$ 8 horas):** Ante una falla catastrófica del servidor principal, el equipo técnico garantiza la reconstrucción del entorno y la restauración íntegra de la base de datos y archivos en un servidor alternativo o local en un plazo máximo de 8 horas de soporte hábil, permitiendo reanudar la operación del pañol al día hábil siguiente.

---

## 6. PLAN DE PRUEBAS Y NORMAS DE CALIDAD

### 6.1 Estrategia y Tipos de Pruebas en el Ciclo de Vida
La estrategia de aseguramiento de calidad combina pruebas automatizadas a nivel de código con pruebas manuales estructuradas basadas en guiones de usuario, cubriendo los siguientes tipos de prueba:

| Tipo de Prueba | Aplicación en el Proyecto | Mecanismo y Ejecución Prevista |
| :--- | :--- | :--- |
| **Unitarias** | Validación aislada de algoritmos de cálculo presupuestario, reglas de stock mínimo, validación de fechas y formatos de datos. | Pruebas automatizadas ejecutadas con **PHPUnit** en el servidor de desarrollo. |
| **Integración** | Verificación de la consistencia relacional entre módulos: compras, presupuesto de carreras, actualización de existencias, préstamos y documentos. | Pruebas automatizadas con PHPUnit y PostgreSQL sobre una base de datos aislada e idéntica a producción. |
| **Funcionales** | Verificación del cumplimiento exacto de cada requerimiento funcional de la plataforma. | Ejecución de casos de prueba formales con datos válidos, datos inválidos y valores de frontera. |
| **Seguridad y Permisos** | Comprobación estricta del aislamiento de privilegios por perfil (Administrador, Pañolero, Docente) y prevención de vulnerabilidades. | Pruebas de inyección SQL, bypass de middleware, escalamiento de privilegios y solicitudes HTTP directas a rutas protegidas. |
| **Concurrencia** | Validación de control de accesos simultáneos sobre un mismo registro crítico (doble entrega de un equipo o sobregiro de existencias). | Operaciones coordinadas y simultáneas ejecutadas mediante sesiones web paralelas o scripts automatizados. |
| **Rendimiento** | Comprobación de tiempos de respuesta en consultas de inventario, generación de etiquetas y concurrencia. | Mediciones de tiempos de carga en navegadores y monitoreo de consultas SQL lentas bajo condiciones del SLA. |
| **Usabilidad y Compatibilidad** | Verificación de la experiencia del usuario y adaptabilidad responsiva en computadores de escritorio y celulares móviles. | Recorridos interactivos de navegación en pantallas de diferentes resoluciones (navegadores Chrome, Safari, Firefox y Edge). |
| **Recuperación (DR)** | Verificación práctica de los objetivos de contingencia RPO y RTO ante incidentes mayores. | Ensayo real de restauración de base de datos y archivos documentales en una máquina limpia o servidor aislado de pruebas. |
| **Aceptación** | Comprobación de la adecuación de los flujos de trabajo al quehacer real de la sede y el pañol. | Guiones de prueba integrales ejecutados directamente con la Directora de Carrera y usuarios autorizados. |
| **Humo y Regresión** | Validación de estabilidad operativa tras la incorporación de nuevas funcionalidades o correcciones de defectos. | Batería rápida de pruebas sobre el núcleo del sistema antes de promover versiones a validación final. |

### 6.2 Catálogo Exhaustivo de Casos de Prueba Funcionales (CP-01 a CP-22)
A continuación se transcriben de forma íntegra los 22 casos de prueba funcionales diseñados para verificar la totalidad del sistema:

| Código | Escenario Evaluado | Resultado Esperado del Sistema |
| :---: | :--- | :--- |
| **CP-01** | Iniciar sesión con credenciales válidas e inválidas. | El acceso se permite únicamente con credenciales válidas y vigentes; las credenciales incorrectas muestran mensajes genéricos de error sin revelar información interna de la base de datos o estructura de cuentas. |
| **CP-02** | Intentar operaciones y acceso a documentos desde un rol no autorizado. | El servidor rechaza la acción mediante código HTTP 403 Forbidden o redirección segura, impidiendo consultar, descargar o modificar información y documentos restringidos. |
| **CP-03** | Registrar un equipo con ficha técnica completa y repetir con datos obligatorios ausentes. | La ficha con datos completos conserva exitosamente sus atributos técnicos y fotografía; la ficha incompleta es rechazada por el servidor informando con claridad los campos obligatorios pendientes de corrección. |
| **CP-04** | Registrar y buscar equipos mediante nombres estandarizados. | El sistema exige y aplica la convención de nombres estandarizada acordada, facilitando la búsqueda e identificación unívoca del activo sin depender de siglas ambiguas o acrónimos informales. |
| **CP-05** | Programar mantenimientos semestrales o anuales con distintos niveles de criticidad. | Se conservan las fechas programadas en el calendario técnico y se generan las alertas preventivas en las fechas configuradas según el nivel de criticidad del activo. |
| **CP-06** | Reportar una falla en un equipo y registrar su revisión técnica. | El reporte de anomalía queda indisolublemente vinculado al equipo, usuario informante, fecha y hora; el activo cambia de estado preventivo y se registra la intervención técnica de revisión. |
| **CP-07** | Registrar una baja técnica o propuesta de reemplazo con informes adjuntos. | Se almacena la justificación técnica y los documentos respaldatorios de cotización/diagnóstico; el equipo formalmente dado de baja queda inhabilitado de forma definitiva para préstamos. |
| **CP-08** | Extraer datos de facturas y órdenes de compra con formatos y calidades diferentes. | Los campos reconocidos por el motor OCR se presentan en la interfaz interactiva para supervisión humana; las lecturas dudosas o fallidas se notifican sin confirmar ingresos automáticos erróneos. |
| **CP-09** | Corregir datos extraídos por OCR y confirmar el ingreso al inventario. | El sistema persiste con exactitud los valores corregidos por el pañolero, creando los registros de insumos y movimientos correspondientes según la operación validada. |
| **CP-10** | Procesar nuevamente un documento de compra ya registrado con anterioridad. | El sistema detecta la duplicidad por número de documento y emisor, emitiendo una advertencia de bloqueo y evitando confirmar dos veces la misma adquisición y sus costos. |
| **CP-11** | Registrar y modificar la ubicación física detallada de un insumo. | Se registran y actualizan con fidelidad los niveles jerárquicos: carrera, pañol, laboratorio, mueble, estante y cajón, permitiendo su localización física inmediata. |
| **CP-12** | Generar, imprimir y leer códigos de barras institucionales. | La etiqueta generada exhibe el código de barras 1D estandarizado y los datos descriptivos legibles; la lectura óptica mediante pistola captura el identificador y recupera el registro exacto. |
| **CP-13** | Registrar ingresos y salidas con cantidades válidas, cero, negativas y superiores al stock actual. | El servidor solo procesa cantidades enteras positivas válidas que no excedan las existencias actuales; se bloquean valores negativos, nulos o excesivos, manteniendo el kardex consistente. |
| **CP-14** | Alcanzar y superar el límite de stock mínimo configurado. | Se gatilla y despliega automáticamente la alerta visual de stock crítico en los paneles correspondientes al llegar al umbral o quedar por debajo; la alerta se desactiva tras el ingreso de reposición. |
| **CP-15** | Registrar una compra y comprobar el presupuesto de cada carrera. | El descuento presupuestario se realiza en el momento convenido, por el importe exacto de la compra, debitando únicamente los fondos de la carrera correspondiente sin alterar las demás. |
| **CP-16** | Registrar proveedores categorizados de insumos y de mantenimiento especializado. | Se almacena y clasifica adecuadamente a cada proveedor según su rubro comercial, permitiendo filtrarlos con precisión al momento de emitir solicitudes de cotización. |
| **CP-17** | Enviar una solicitud de cotización masiva a varios destinatarios de prueba. | El sistema encola y despacha el correo electrónico formal a la lista de proveedores seleccionados, registrando en bitácora los envíos pendientes, procesados con éxito y fallidos. |
| **CP-18** | Asociar cotizaciones y registrar una adjudicación de compra. | Se conservan los archivos adjuntos de las cotizaciones y se aplica estrictamente la cuota mínima de 2 a 3 propuestas o, en su defecto, la autorización formal de excepción documentada. |
| **CP-19** | Solicitar materiales anticipadamente desde un computador y un teléfono celular. | El formulario web responsivo captura con precisión el solicitante, ítems, cantidades y fechas requeridas, visualizándose de inmediato en la cola de revisión del encargado de pañol. |
| **CP-20** | Entregar materiales a docentes y estudiantes en mesón de pañol. | Se identifica al receptor presencial y al encargado que despacha, actualizando de forma diferenciada la salida definitiva de insumos consumibles y el préstamo activo de retornables. |
| **CP-21** | Registrar devoluciones completas, parciales o con daños físicos. | Se actualizan con exactitud las cantidades retornadas y pendientes; en caso de daños se registra la incidencia técnica y el activo se aparta para diagnóstico sin categorizarlo como faltante. |
| **CP-22** | Repetir la confirmación de una entrega o devolución ya procesada (doble clic / reenvío). | Mecanismos de idempotencia a nivel de servidor detectan la duplicación de la solicitud de confirmación, evitando duplicar movimientos de kardex o modificar erróneamente las existencias. |

### 6.3 Escenarios Críticos de Integración y Concurrencia (PI-01 a PI-08)
Para asegurar la robustez de la aplicación en condiciones extremas o fallos de infraestructura, se definen los siguientes 8 escenarios críticos:

| Código | Escenario Crítico Evaluado | Resultado Esperado del Sistema |
| :---: | :--- | :--- |
| **PI-01** | Confirmar una compra con múltiples insumos, actualización de stock y descuento presupuestario en una transacción. | Todos los cambios en cascada (inserción de ítems, movimientos de inventario, afectación de saldos y vinculación documental) se confirman conjuntamente (Commit); si ocurre cualquier fallo en un registro intermedio, la transacción completa se revierte de forma íntegra (Rollback), evitando inconsistencias parciales. |
| **PI-02** | Intentar entregar simultáneamente el mismo equipo disponible desde dos sesiones de usuario concurrentes. | Gracias a mecanismos de bloqueo de concurrencia a nivel de base de datos (`SELECT FOR UPDATE`), solo una de las sesiones logra procesar y confirmar el préstamo del equipo; la segunda sesión es rechazada atómicamente informando al usuario que el equipo acaba de ser asignado. |
| **PI-03** | Ejecutar salidas simultáneas de un insumo consumible cuyas cantidades sumadas superen las existencias físicas disponibles. | El motor transaccional serializa las solicitudes o aplica bloqueos pesimistas, permitiendo el despacho hasta agotar el stock y bloqueando cualquier egreso que derive en saldo negativo de inventario, preservando la consistencia estricta del kardex. |
| **PI-04** | Confirmar compras simultáneas sobre el saldo presupuestario disponible de una carrera hasta copar el límite. | El saldo incorpora correlativamente cada una de las compras confirmadas; si una compra concurrente excede el saldo remanente, el servidor rechaza la transacción informando falta de fondos conforme a la política presupuestaria acordada. |
| **PI-05** | Interrupción abrupta o falla de red en el servicio de reconocimiento documental (OCR) o servidor de correo (SMTP). | El sistema maneja la excepción de manera controlada sin caída de la aplicación; en OCR se notifica el incidente al usuario permitiendo la digitación manual asistida; en correo, el trabajo se conserva en la cola de base de datos para reintento automático sin duplicar envíos. |
| **PI-06** | Ejecutar operaciones transaccionales representativas de pañol con 10 usuarios concurrentes en el sistema. | El sistema opera de forma estable, sin caídas ni errores 500, manteniendo la consistencia íntegra de la base de datos PostgreSQL y preservando los tiempos de respuesta dentro del límite pactado en el SLA-02 ($\le 3$ segundos). |
| **PI-07** | Operar formularios de inventario, solicitudes de préstamo y consultas de catálogo en distintos dispositivos (desktop y móviles). | La interfaz adaptable responde adecuadamente a las dimensiones de pantalla en computadores, tablets y celulares sin controles desalineados, botones inalcanzables ni pérdida de información al cambiar la orientación de pantalla. |
| **PI-08** | Simular pérdida total de datos del servidor y ejecutar la restauración del respaldo completo de base de datos y archivos. | Se ejecuta la restauración íntegra de los esquemas, relaciones y archivos documentales en un entorno aislado; se comprueba la consistencia de los registros y se verifica el cumplimiento de las metas pactadas de RPO ($\le 24$ h) y RTO ($\le 8$ h). |

### 6.4 Normas y Estándares de Referencia Aplicados
El desarrollo, diseño y aseguramiento de calidad del sistema se rige formalmente bajo cinco marcos y normas internacionales de ingeniería de software:

| Norma o Estándar de Referencia | Propósito Técnico | Aplicación Concreta en el Proyecto | Evidencia de Cumplimiento y Control |
| :--- | :--- | :--- | :--- |
| **ISO/IEC 25010:2023** *(Systems and software engineering — Systems and software Quality Requirements and Evaluation - SQuaRE)* | Orientar la evaluación multidimensional de la calidad del producto software. | Selección y evaluación de características de calidad: adecuación funcional, eficiencia de desempeño, compatibilidad, usabilidad, fiabilidad, seguridad, mantenibilidad y portabilidad en el desarrollo de los 5 módulos. | Matriz de requerimientos cruzada con atributos de calidad y evidencias de cumplimiento en las pruebas de sistema. |
| **ISO/IEC/IEEE 29119-3:2021** *(Software and systems engineering — Software testing — Part 3: Test documentation)* | Proveer un marco estructurado y uniforme para la documentación de pruebas de software. | Estructuración formal de la documentación de pruebas: diseño de casos de prueba con identificador unívoco, precondiciones, datos de prueba, pasos de ejecución y resultados esperados; registro sistemático de anomalías. | Plan de pruebas documentado, catálogo de casos CP-01 a CP-22, bitácoras de ejecución y reportes de incidentes. |
| **OWASP Top 10:2025** *(Open Web Application Security Project - Riesgos Críticos)* | Identificar y mitigar los vectores de riesgo más críticos de seguridad en aplicaciones web contemporáneas. | Implementación de controles estrictos contra: Inyecciones (vía sentencias preparadas de Eloquent ORM), Fallas Criptográficas (hashing de claves seguro y HTTPS), Pérdida de Control de Acceso (validación por roles en servidor), y Fallas de Integridad de Software y Datos. | Lista de verificación de seguridad, escaneos de código estático y casos de prueba específicos de inyección y bypass. |
| **OWASP ASVS** *(Application Security Verification Standard)* | Proporcionar una base técnica y granular para la verificación de controles de seguridad en aplicaciones web. | Aplicación de requisitos seleccionados de Nivel 1 y 2 en autenticación, gestión de sesiones, control de acceso a archivos almacenados, validación de sanitización de entradas y manejo seguro de errores sin revelación de trazas técnicas. | Matriz de comprobación de requisitos ASVS implementados y verificados en el entorno de pruebas. |
| **WCAG 2.2** *(Web Content Accessibility Guidelines - Niveles A y AA)* | Asegurar la accesibilidad y usabilidad universal de la interfaz web para todos los usuarios. | Implementación de diseño con contraste cromático suficiente entre textos y fondos, navegación completa accesible por teclado, foco visual destacado, etiquetas claras en formularios y mensajes de error que no dependan exclusivamente del color. | Lista de verificación de accesibilidad y pruebas de navegación manual por teclado sobre las vistas principales de la aplicación. |

---

## 7. GESTIÓN DE DISPONIBILIDAD, CONTINUIDAD Y MANTENCIÓN

### 7.1 Plan de Disponibilidad
El plan de disponibilidad asegura que la plataforma se mantenga operativa, accesible y con un rendimiento adecuado durante los periodos académicos pactados.

#### Medidas de Implementación y Preparación del Entorno
La siguiente tabla describe las medidas técnicas implementadas en cada componente para garantizar la disponibilidad continua:

| Elemento Tecnológico | Medida de Implementación de Disponibilidad | Mecanismo de Comprobación y Verificación |
| :--- | :--- | :--- |
| **Servidor de Aplicación Web** | Configuración de PHP y servidor HTTP (Nginx/Apache) con inicio automático de servicios ante reinicios del sistema operativo físico o virtual. | Reinicio controlado del entorno de pruebas comprobando el levantamiento autónomo de la aplicación web y sus rutas principales. |
| **Base de Datos PostgreSQL** | Restricción estricta de conexiones a hosts autorizados, supervisión de número de conexiones simultáneas y monitoreo de espacio libre en disco. | Comprobación de conectividad segura desde Laravel, verificación de logs de base de datos y simulación de alertas de límite de conexiones. |
| **Trabajos en Segundo Plano** | Demonio supervisor (Worker de Laravel Queue) activo de forma permanente para el despacho asíncrono de correos y tareas pesadas. | Encolado de mensajes de prueba y verificación de la tabla `jobs` y `failed_jobs`, comprobando el procesamiento autónomo y el manejo de reintentos. |
| **Tareas Programadas (Scheduler)** | Tarea cron a nivel del sistema operativo ejecutando `php artisan schedule:run` cada minuto para evaluar mantenimientos y alertas. | Revisión de bitácoras de ejecución temporal en el servidor y verificación de la activación programada de revisiones de mantenimiento. |
| **Reconocimiento Documental (OCR)** | Definición de límites máximos de tamaño de archivo (10 MB), timeout estricto de ejecución (60 s) y captura controlada de excepciones de lectura. | Carga de archivos válidos, archivos con formato no admitido y documentos deliberadamente ilegibles para verificar mensajes de error amigables. |
| **Almacenamiento de Documentos** | Configuración de permisos de sistema de archivos (`storage/app`), límites de carga y aislamiento de rutas para evitar descargas no autorizadas. | Carga de fotografías, cotizaciones e informes técnicos; intento de acceso no autorizado y comprobación de espacio utilizado en volumen. |
| **Servicio de Correo (SMTP)** | Configuración de credenciales institucionales seguras, aislamiento de fallas de red para evitar el bloqueo del flujo de compras en pantalla. | Desconexión simulada del SMTP durante una solicitud de cotización; verificación de que la solicitud se guarda y el envío queda en cola para reintento. |
| **Motor de Etiquetado** | Plantilla de código de barras optimizada para el ancho de cinta térmica; mecanismo de regeneración y reimpresión de etiquetas existentes. | Pruebas de impresión de lotes de etiquetas y lectura con pistola óptica, comprobando la preservación del identificador sin duplicar registros. |
| **Conectividad y Acceso Seguro** | Cifrado obligatorio de extremo a extremo mediante protocolo TLS/HTTPS en el entorno operativo final. | Verificación de redirección automática HTTP a HTTPS y comprobación de validez del certificado digital SSL/TLS. |

#### Supervisión Periódica y Ventanas de Mantenimiento
Para mantener la disponibilidad bajo el estándar SLA-01 ($\ge 98\,\%$), se organizan las actividades periódicas de supervisión y mantenimiento preventivo:

| Actividad de Supervisión | Frecuencia Propuesta | Registro y Evidencia Esperada |
| :--- | :--- | :--- |
| **Comprobación de Acceso y Conexión a Base de Datos** | **Cada 5 minutos** durante el horario de servicio acordado (L-V 08:00 a 18:00). | Registro automatizado de peticiones de estado (Health Check HTTP 200) y tiempo de latencia de base de datos. |
| **Revisión de Errores, Trabajos Fallidos y Cron** | **Diaria** durante la fase de marcha blanca; posterior según protocolo operativo. | Bitácora de incidentes técnicos detectados, análisis de la tabla `failed_jobs` y acciones de corrección aplicadas. |
| **Revisión de Almacenamiento y Crecimiento de Archivos** | **Semanal**, con ajuste según la volumetría de documentos cargados. | Reporte de porcentaje de ocupación de disco en la partición de la base de datos y carpeta de archivos adjuntos. |
| **Auditoría de Ejecución de Respaldos Diarios** | **Diaria** al inicio de la jornada laboral. | Verificación de fecha, hora, tamaño de archivo y estado exitoso de la copia de seguridad de la noche anterior. |
| **Evaluación de Actualizaciones de Dependencias** | Previa a cada actualización y ante avisos críticos de seguridad. | Evaluación técnica de compatibilidad, pruebas en entorno de desarrollo y registro de aprobación de despliegue. |
| **Ejecución de Pruebas de Humo (Smoke Tests)** | Inmediatamente posterior a cada despliegue, parche o cambio de infraestructura. | Registro de verificación manual o automatizada de los 5 flujos operativos esenciales del sistema. |
| **Revisión del Cumplimiento de Disponibilidad Mensual** | **Mensual** durante la fase de operación del sistema. | Reporte consolidado con porcentaje de disponibilidad calculado según la fórmula formal, detalle de caídas y planes de acción. |

* **Ventanas de Mantenimiento Programado:** Todo mantenimiento preventivo que conlleve detención o reinicio de los servicios web se programará exclusivamente de lunes a viernes después de las 18:30 horas o durante los días sábados, coordinando previamente con la Dirección de Carrera.

---

### 7.2 Plan de Continuidad Operativa
El plan de continuidad establece los protocolos para mitigar riesgos operacionales mayores, salvaguardar la información institucional y restablecer el servicio dentro de los márgenes pactados en el SLA.

#### Matriz de Riesgos y Medidas Preventivas
A continuación se detalla la matriz de contingencia operativa:

| Riesgo Operativo Identificado | Medida Preventiva Implementada | Evidencia de Control y Mitigación |
| :--- | :--- | :--- |
| **Pérdida o corrupción de la base de datos PostgreSQL** | Automatización de respaldos lógicos diarios (`pg_dump`) y ensayos periódicos de restauración integral en servidor de pruebas. | Bitácora cron de respaldos ejecutados y actas de prueba de restauración con verificación de consistencia de tablas. |
| **Pérdida de documentos y archivos adjuntos** | Inclusión obligatoria del directorio de almacenamiento (`storage/app/public`) en la rutina diaria de respaldos, sincronizado con la BD. | Verificación de la integridad de las carpetas de fotos, cotizaciones y facturas restauradas junto con sus referencias en base de datos. |
| **Falla de hardware del servidor principal de alojamiento** | Mantener copias de seguridad sincronizadas en un almacenamiento secundario independiente físicamente del servidor principal. | Existencia comprobada del repositorio secundario y manual técnico de instalación rápida en hardware o VM de reemplazo. |
| **Despliegues de código defectuoso o cambios incompatibles** | Probar rigurosamente toda modificación en el entorno de desarrollo y pruebas antes de publicar en operación; uso estricto de Git. | Historial de commits en Git, registro de pruebas exitosas y procedimiento de reversión (*Rollback*) documentado. |
| **Acceso no autorizado o robo de respaldos** | Restricción estricta de permisos en el sistema operativo sobre los archivos `.sql` y archivos comprimidos; cifrado de copias en tránsito. | Configuración de permisos de lectura restringidos exclusivamente al usuario de sistema de administración y llaves de acceso protegidas. |
| **Interrupción de servicios de correo o reconocimiento OCR** | Diseño de arquitectura tolerante a fallos: registro de estados intermedios, colas de reintentos y opción de completado manual de datos. | Pruebas de simulación de corte de servicios externos, comprobando que las compras y cotizaciones no se pierden en el sistema. |
| **Dependencia del personal técnico de desarrollo** | Elaboración de manuales técnicos de instalación, configuración, arquitectura, modelo de datos y protocolos de soporte operativo. | Documentación técnica entregada a la contraparte institucional y registro formal de transferencia de conocimientos. |

#### Política de Respaldos y Estrategia de Retención
1. **Frecuencia y Alcance del Respaldo:** Respaldo completo diario y automatizado, ejecutado a las 23:00 horas de cada día hábil mediante un script programado que extrae:
   * Volcado completo de la base de datos PostgreSQL (estructuras, tablas, índices, secuencias y datos transaccionales).
   * Archivo comprimido del árbol de directorios de almacenamiento de archivos adjuntos (fotografías de equipos, facturas escaneadas, cotizaciones en PDF e informes de baja).
2. **Estrategia de Retención de 30 Días:** Las copias de seguridad diarias se almacenan con rotación automática manteniendo un histórico de los últimos **30 días calendario**. Cumplidos los 30 días, las copias más antiguas son depuradas automáticamente para evitar la saturación de almacenamiento, conservando adicionalmente un respaldo consolidado mensual.
3. **Almacenamiento Separado (Offsite):** Tras generarse en el servidor local, el paquete de respaldo es transferido de forma automática y cifrada hacia una ubicación de almacenamiento secundario (servidor de almacenamiento de red institucional o repositorio seguro externo), garantizando su supervivencia en caso de siniestro físico en el servidor principal.

#### Protocolo de Restauración ante Desastres (DRP)
1. **Detección y Declaración de Desastre:** Identificada una falla irrecuperable en el servidor principal, el Administrador o encargado técnico declara formalmente el incidente e inicia el cronómetro del RTO (meta $\le 8$ horas).
2. **Aprovisionamiento del Entorno Alternativo:** Se prepara la infraestructura secundaria o máquina de reemplazo con el sistema operativo base, instalando PHP, PostgreSQL y extensiones necesarias según la especificación técnica.
3. **Descarga y Extracción del Respaldo:** Se recupera el último respaldo íntegro disponible desde el almacenamiento secundario (garantizando el RPO $\le 24$ horas).
4. **Restauración de Datos y Archivos:** Se ejecuta la importación del volcado SQL en PostgreSQL y se descomprime el árbol de archivos en el directorio de almacenamiento de Laravel, ejecutando los enlaces simbólicos correspondientes.
5. **Pruebas de Humo y Puesta en Servicio:** Se ejecutan las pruebas de verificación de integridad (CP-01, CP-03, CP-11 y CP-15). Verificada la consistencia, se actualiza el enrutamiento de red o DNS institucional y se entrega el sistema a operación formal.

---

### 7.3 Plan de Mantención y Gestión de la Configuración

#### Gestión de Elementos de Configuración (CMDB)
Para garantizar la reproducibilidad del entorno y evitar inconsistencias operativas, se definen los siguientes elementos de configuración bajo control estricto:

| Elemento de Configuración | Información Específica Bajo Control | Mecanismo de Control y Gestión de Cambios |
| :--- | :--- | :--- |
| **Código Fuente del Sistema** | Versión instalada, historial de cambios, ramas de desarrollo y fechas de publicación de versiones. | Repositorio **Git** institucional con control de ramas y etiquetado formal de versiones (*Tags*). |
| **Dependencias del Framework y Librerías** | Versiones exactas de PHP, Laravel, Livewire, Tailwind CSS y paquetes complementarios instalados. | Archivos de bloqueo de dependencias versionados: `composer.lock` para backend y `package-lock.json` para frontend. |
| **Estructura de Base de Datos** | Definición de esquemas, tablas, tipos de datos, índices, llaves foráneas y datos de inicialización (*Seeds*). | Sistema de **Migraciones Versionadas** de Laravel (`database/migrations`), prohibiendo alteraciones manuales en SQL. |
| **Servicios del Sistema y Tareas en Segundo Plano**| Configuración de Workers de colas, parámetros del planificador cron y servicios de sistema operativo. | Archivos de configuración documentados, inventario de scripts de inicio y variables de entorno del servidor. |
| **Integraciones Externas y Servicios TI** | Parámetros de conexión a servidor de correo SMTP, motor de OCR, cuotas y credenciales de acceso. | Parámetros parametrizados centralmente en el archivo de entorno `.env`, excluido del control de versiones público. |
| **Parámetros del Motor de Etiquetado** | Dimensiones de etiqueta (ancho y alto en mm), márgenes de impresión, tipo de simbología (Code 128) y formato visual. | Plantillas de etiquetas versionadas y parámetros almacenados en tabla de configuración del sistema. |
| **Reglas Operativas y Parámetros Institucionales** | Valores de umbral de stock mínimo, frecuencias de mantenimiento preventivo, cuotas de cotización exigidas y días de préstamo. | Tabla de parámetros en base de datos accesible y modificable exclusivamente por el rol de Administrador. |
| **Credenciales Críticas y Claves Criptográficas** | Clave de encriptación de la aplicación (`APP_KEY`), usuarios y contraseñas de base de datos y llaves de correo. | Almacenamiento seguro en archivo `.env` con permisos de lectura restringidos en el servidor; prohibición estricta de subida a Git. |

#### Cronograma de Actividades Periódicas de Mantención Técnica
Las actividades sistemáticas de soporte y conservación de la plataforma se estructuran en el siguiente cronograma:

| Actividad de Mantención Técnica | Frecuencia Propuesta | Mecanismo de Comprobación y Registro |
| :--- | :--- | :--- |
| **Revisión de Bitácoras de Error y Trabajos Fallidos** | **Diaria** en marcha blanca; semanal en operación regular. | Análisis de archivos `storage/logs/laravel.log`, depuración de errores identificados y registro de acciones tomadas. |
| **Supervisión de Capacidad y Depuración de Temporales**| **Semanal**. | Verificación de espacio libre en disco, depuración de archivos temporales de subida y logs antiguos de auditoría. |
| **Evaluación de Parches de Seguridad y Dependencias** | **Semanal** y ante alertas de seguridad de librerías. | Ejecución de `composer audit` y `npm audit`; evaluación del impacto antes de aplicar actualizaciones de software. |
| **Auditoría de Cuentas, Roles y Permisos de Usuarios** | **Mensual** y ante desvinculación o rotación de personal. | Revisión de usuarios activos con la Dirección de Carrera; revocación inmediata de accesos y perfiles caducados. |
| **Verificación Integral de Integraciones Técnicas** | **Mensual** y tras intervenciones en la red de la sede. | Ejecución de pruebas integrales de envío de correo SMTP, generación de etiquetas y prueba de reconocimiento OCR. |
| **Actualización de Documentación de Configuración** | Posterior a cada cambio técnico aprobado y desplegado. | Revisión de que el repositorio, manuales operativos y diagramas reflejen con exactitud el estado del sistema en producción. |
| **Ensayo Periódico de Restauración de Copias de Seguridad**| **Trimestral** durante la operación regular institucional. | Ejecución práctica del protocolo DRP en una máquina virtual de pruebas, documentando tiempo de recuperación y consistencia. |

---

## 8. DEFINICIONES PENDIENTES CON LA CONTRAPARTE

Para asegurar el éxito en la entrega del sistema y garantizar que la implementación coincida plenamente con las políticas institucionales de INACAP Sede Temuco, se identifican las siguientes decisiones y acuerdos técnicos y de negocio que deben ser formalizados con la **Dirección de Carrera** antes del cierre de los hitos programados:

### 8.1 Selección y Arquitectura Definitiva del Motor OCR
* **Definición requerida:** Determinar formalmente si la extracción de datos desde facturas y órdenes de compra se ejecutará mediante un **motor OCR local de código abierto (Tesseract OCR instalado en el servidor)** o a través de un **servicio especializado en la nube (como Google Cloud Document AI, AWS Textract o Azure AI Document Intelligence)**.
* **Impacto técnico y de gestión:** Un motor local elimina costos de licenciamiento o consumo por página y evita enviar documentos fiscales fuera de la infraestructura institucional, pero exige mayor capacidad de procesamiento en el servidor y presenta mayor sensibilidad a la calidad del escaneo. Un servicio en la nube ofrece mayor precisión y extracción semántica de tablas, pero introduce costos variables por documento procesado y requiere gestionar cuentas de facturación institucional y políticas de privacidad sobre facturas con RUT institucional.

### 8.2 Momento Contractual de la Imputación y Descuento Presupuestario
* **Definición requerida:** Formalizar con la contraparte el **hito temporal exacto** en que el costo de una compra debe ser descontado formalmente del saldo presupuestario de la carrera:
  * *Opción A (Al Adjudicar):* El presupuesto se descuenta en el momento en que el Administrador aprueba y adjudica la compra al proveedor. Esto reserva el fondo de manera preventiva evitando sobregiros en adquisiciones paralelas, pero requiere un mecanismo de reverso presupuestario si el proveedor cancela el pedido o no cuenta con stock.
  * *Opción B (Al Recepcionar):* El presupuesto se descuenta únicamente cuando los materiales ingresan físicamente al pañol junto con la factura comercial validada. Esto asegura pagar solo por lo efectivamente recibido, pero deja el saldo temporalmente disponible durante el lapso de despacho, arriesgando sobregiros por compras concurrentes.

### 8.3 Mecánica Operativa de Reserva de Stock en Solicitudes Anticipadas
* **Definición requerida:** Definir la regla de negocio aplicable a las solicitudes de préstamo formuladas por los docentes con anticipación:
  * *Opción A (Reserva Física con Bloqueo de Stock):* Al aprobarse la solicitud del docente para una fecha futura, los ítems se descuentan de la disponibilidad inmediata de pañol, impidiendo que sean prestados a otros usuarios en el intervalo. Garantiza que el docente tenga sus materiales listos, pero inmoviliza equipos que podrían utilizarse en clases intermedias.
  * *Opción B (Prioridad de Despacho sin Bloqueo Temprano):* La solicitud aprobada representa una reserva de turno; la disponibilidad se valida y el descuento físico se concreta en el momento en que el docente o receptor se presenta físicamente en el mesón de pañol. Maximiza el uso diario de los equipos pero expone al solicitante a quiebres imprevistos si otro curso no devuelve a tiempo.

### 8.4 Especificaciones Técnicas y Parámetros de la Impresora de Etiquetas
* **Definición requerida:** Obtener la ficha técnica detallada del hardware institucional de etiquetado ubicado en pañol: marca, modelo exacto, protocolo de comunicación admitido (emulación ZPL/EPL, driver genérico de Windows, o impresión directa vía red/USB), y dimensiones milimétricas exactas de las etiquetas adhesivas en rollo utilizadas en la sede (ejemplo: 50x25 mm, 70x30 mm).
* **Impacto técnico:** Estos parámetros determinan la biblioteca de renderizado a incorporar en Laravel, la calibración de márgenes en la plantilla visual y el nivel de resolución (DPI) requerido para asegurar que el código de barras 1D sea nítido y legible en el primer intento por las pistolas ópticas existentes.

### 8.5 Infraestructura de Alojamiento y Credenciales Institucionales de Servidor y Correo
* **Definición requerida:** Confirmar la provisión formal de:
  1. *Servidor de Alojamiento:* Especificación del entorno donde residirá la versión operativa (servidor físico local en laboratorio de informática, máquina virtual en clúster institucional VMware/Proxmox, o VPS en la nube). Se requiere asignación de IP, puertos, memoria RAM (mínimo 4 GB recomendados), almacenamiento SSD y acceso SSH/Administrador para el despliegue.
  2. *Servicio de Correo Electrónico (SMTP):* Creación o provisión de una cuenta de correo institucional autorizada (ejemplo: `notificaciones.panol@inacap.cl`) con credenciales SMTP activas, habilitada para el envío de notificaciones y solicitudes de cotización masivas a proveedores sin bloqueos automáticos por directivas de seguridad corporativa de INACAP.

### 8.6 Criterios de Depuración y Formato para la Carga Inicial de Datos Maestros
* **Definición requerida:** Establecer la fecha de entrega y el formato estandarizado (planillas Excel/CSV) de las bases de datos de inventario y activos físicos actualmente existentes en el pañol.
* **Impacto técnico:** La Dirección de Carrera debe acordar el criterio de depuración previa: tratamiento de equipos antiguos que carezcan de número de serie visible, homogenización de nombres comerciales, estandarización de categorías y asignación inicial de carreras propietarias para evitar inconsistencias de clave primaria durante la migración al modelo relacional de PostgreSQL.

### 8.7 Protocolo Administrativo Formal para Bajas y Desecho de Equipos
* **Definición requerida:** Formalizar el flujo administrativo que valida el sistema para el proceso de baja técnica de activos: determinar si la autorización del Administrador en el sistema es suficiente para la baja del pañol o si se requiere la firma adicional de un acta formal con la Dirección de Administración y Finanzas de la sede para la posterior donación, reciclaje electrónico o enajenación del equipo declarado irreparable.
