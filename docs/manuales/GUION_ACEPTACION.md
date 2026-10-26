# Guión y Pauta de Aceptación de Entrega Técnica
**Proyecto:** Aplicación Web para la Gestión de Equipos, Insumos y Procesos de Pañol  
**Institución:** INACAP Sede Temuco — Área de Informática y Telecomunicaciones  
**Hito:** H6 — Validación y Entrega Final del Sistema (Cierre Formal)  
**Versión Entregada:** v1.0.0  
**Fecha:** Noviembre 2026  

---

## 1. Ficha General de la Sesión de Validación

* **Lugar:** Sala de Reuniones y Laboratorio de Redes / Pañol de Informática — INACAP Sede Temuco.
* **Participantes Convocados:**
  * **Dirección de Carrera:** Director(a) de Carrera del Área de Informática y Telecomunicaciones.
  * **Coordinación Académica:** Coordinador(a) de Especialidad y Docente Representante.
  * **Operación de Pañol:** Encargado Oficial de Pañol de Informática.
  * **Equipo de Desarrollo y Entrega:** Ingeniero DevOps / Gestor de Entrega Técnica de Software.

---

## 2. Marco Metodológico de Verificación

La presente pauta establece los pasos exactos que se ejecutarán en vivo durante la reunión de entrega. Cada prueba está directamente vinculada a los Requerimientos Funcionales, Casos de Prueba (`CP-01` a `CP-22`), Escenarios de Integración (`PI-01` a `PI-08`) y Acuerdos de Nivel de Servicio (`SLA-01` a `SLA-11`) definidos en la documentación oficial del proyecto.

**Criterios de Calificación:**
* **Conforme (C):** El comportamiento del sistema satisface al 100 % el resultado esperado sin errores ni demoras.
* **No Conforme (NC):** Se detecta inconsistencia funcional, vulnerabilidad o retraso que vulnere los SLAs.

---

## 3. Protocolo de Pruebas en Vivo (Bloques de Validación)

### Bloque 1: Autenticación, Control de Acceso por Rol y Seguridad Backend
**Objetivo:** Verificar la segregación estricta de funciones en el servidor y la protección perimetral OWASP.

| Paso | Acción Operativa Ejecutada en Vivo | Resultado Técnico Esperado | Referencia | Estado |
| :---: | :--- | :--- | :---: | :---: |
| **1.1** | Iniciar sesión como **Administrador** (`admin@inacap.cl`). | Acceso concedido al Dashboard de Administrador con métricas globales y acceso a gestión presupuestaria y de usuarios. | CP-01 | [ ] C  [ ] NC |
| **1.2** | Intentar acceder con rol **Docente** a la ruta `/admin/presupuestos`. | El middleware y policies de Laravel interceptan la petición y retornan código **HTTP 403 Prohibido**. Jamás se confía únicamente en ocultar botones en Blade. | CP-02 | [ ] C  [ ] NC |
| **1.3** | Verificar la tabla de usuarios en PostgreSQL (`usuarios`). | Se comprueba que **los alumnos no poseen cuentas de usuario** en el sistema; solo existen administradores, pañoleros y docentes. | GEMINI.md Ley 3.3 | [ ] C  [ ] NC |

---

### Bloque 2: Catálogo de Inventario y Generación de Códigos de Barras
**Objetivo:** Validar la administración de existencias y el rendimiento en la emisión de etiquetas térmicas.

| Paso | Acción Operativa Ejecutada en Vivo | Resultado Técnico Esperado | Referencia | Estado |
| :---: | :--- | :--- | :---: | :---: |
| **2.1** | Iniciar sesión como **Encargado de Pañol** y consultar el inventario de insumos y equipos en `/panol/inventario`. | Visualización instantánea del catálogo con indicación clara de stock actual, stock mínimo y estado de disponibilidad. | CP-03 | [ ] C  [ ] NC |
| **2.2** | Buscar un equipo disparando el lector de código de barras físico sobre una etiqueta institucional. | El sistema detecta el escaneo en el campo de búsqueda y carga la ficha técnica del equipo en menos de 1 segundo sin recargar la página. | SLA-02 | [ ] C  [ ] NC |
| **2.3** | Seleccionar un lote de **20 equipos** y presionar **"Generar Lote de Etiquetas"**. | El sistema renderiza el documento PDF de impresión térmica con códigos de barras lineales Code 128 descriptivos en **menos de 10 segundos**. | SLA-07, CP-04 | [ ] C  [ ] NC |

---

### Bloque 3: Flujo de Préstamo Móvil/Escritorio y Control de Concurrencia
**Objetivo:** Evaluar la adaptabilidad responsive y la prevención de doble asignación con bloqueos pesimistas.

| Paso | Acción Operativa Ejecutada en Vivo | Resultado Técnico Esperado | Referencia | Estado |
| :---: | :--- | :--- | :---: | :---: |
| **3.1** | Desde un **dispositivo móvil**, iniciar sesión como **Docente** e ingresar una solicitud de 2 equipos Switch Cisco y cables de red para un taller. | La interfaz responsive se adapta perfectamente a la pantalla móvil sin controles rotos ni botones inalcanzables. La solicitud queda en estado `pendiente`. | PI-07, CP-12 | [ ] C  [ ] NC |
| **3.2** | Desde la estación de pañol, revisar y **aprobar** la solicitud del docente. | La solicitud transiciona a estado `aprobada`. El docente visualiza el cambio de estado en tiempo real en su dispositivo. | CP-13 | [ ] C  [ ] NC |
| **3.3** | Simular **dos pañoleros despachando simultáneamente** el mismo equipo disponible. | El sistema ejecuta el despacho bajo bloqueo pesimista de base de datos (`lockForUpdate()`); la primera sesión completa el préstamo y la segunda sesión es rechazada controladamente sin admitir stock negativo ni doble préstamo. | PI-02, PI-03, CP-14 | [ ] C  [ ] NC |
| **3.4** | Registrar la entrega asociando el RUT y Nombre de un **estudiante receptor**. | Los datos del alumno quedan registrados estrictamente en la tabla `entregas`, sin crear usuarios espurios. El equipo cambia a estado `prestado`. | Ley 3.3, CP-15 | [ ] C  [ ] NC |
| **3.5** | Procesar la **devolución** del material con reporte de un cable defectuoso. | El sistema recibe los ítems sanos devolviéndolos a `disponible` y deriva el cable defectuoso al registro de incidencias técnicas. | BPMN Proc. 4, CP-16 | [ ] C  [ ] NC |

---

### Bloque 4: Compras, Cotizaciones Asistidas y Reconocimiento OCR
**Objetivo:** Comprobar la integridad transaccional de compras, colas de correo y la validación asistida por OCR con revisión humana obligatoria.

| Paso | Acción Operativa Ejecutada en Vivo | Resultado Técnico Esperado | Referencia | Estado |
| :---: | :--- | :--- | :---: | :---: |
| **4.1** | Generar una solicitud de cotización para insumos en stock mínimo y encolar envío a proveedores. | Los trabajos de correo se integran en la tabla `jobs` de Laravel y son despachados asíncronamente por el worker en $\le 15$ minutos. | SLA-05, KPI-10 | [ ] C  [ ] NC |
| **4.2** | Cargar una factura de compra en PDF para extracción asistida por OCR en `/panol/compras`. | El motor extrae texto, cantidades y montos en $\le 60$ segundos. | SLA-04, CP-08 | [ ] C  [ ] NC |
| **4.3** | Verificar la pantalla de confirmación previa al guardado definitivo. | **Validación crítica:** Los datos del OCR se presentan en una interfaz de revisión y corrección; ningún stock ni saldo presupuestario se altera hasta que el pañolero confirma manualmente. | Ley 3.3, CP-09 | [ ] C  [ ] NC |
| **4.4** | Confirmar el ingreso de la compra aprobada. | Se ejecuta una transacción atómica (`DB::transaction`) que: inserta ítems, incrementa kardex de existencias y descuenta el saldo presupuestario de la carrera de forma sincronizada. | PI-01, SLA-06, KPI-09 | [ ] C  [ ] NC |

---

### Bloque 5: Mantenimiento Preventivo, Alertas y Gestión de Bajas
**Objetivo:** Auditar la evaluación de planes de mantenimiento preventivo y el control de activos dados de baja.

| Paso | Acción Operativa Ejecutada en Vivo | Resultado Técnico Esperado | Referencia | Estado |
| :---: | :--- | :--- | :---: | :---: |
| **5.1** | Ejecutar la evaluación de mantenimientos programados (`php artisan panol:evaluar-mantenimientos`). | El comando evalúa las fechas límite de intervención y clasifica las alertas en preventivas (15 o 7 días según criticidad) o vencidas. | CP-05, KPI-06 | [ ] C  [ ] NC |
| **5.2** | Un docente registra un reporte de falla adjuntando foto del daño en un proyector de laboratorio. | El reporte se almacena con su hash criptográfico SHA-256 en `documentos` y el equipo se marca con advertencia en pañol. | CP-07 | [ ] C  [ ] NC |
| **5.3** | Dar de baja un equipo irreparable adjuntando ficha técnica, diagnóstico y cotización de reemplazo. | El sistema exige la totalidad de documentos reglamentarios para autorizar la baja, cambiando el estado a `baja` y preservando el historial. | KPI-11 | [ ] C  [ ] NC |

---

### Bloque 6: Continuidad Operativa, Respaldo y Ensayo de Restauración (DRP)
**Objetivo:** Demostrar en vivo el cumplimiento de las metas pactadas de RPO ($\le 24$ horas) y RTO ($\le 8$ horas) ante desastres.

| Paso | Acción Operativa Ejecutada en Vivo | Resultado Técnico Esperado | Referencia | Estado |
| :---: | :--- | :--- | :---: | :---: |
| **6.1** | Ejecutar en vivo el respaldo institucional completo: `php artisan app:backup`. | Se genera el volcado comprimido de PostgreSQL (`database.dump`), el archivo de storage (`storage_files.zip`) y el manifiesto criptográfico (`manifest.json`) con firmas SHA-256 en $\le 5$ segundos. | SLA-08 | [ ] C  [ ] NC |
| **6.2** | Verificar el manifiesto de respaldo generado y la fecha del archivo. | Se comprueba que la estampa temporal corresponde a las últimas 24 horas, garantizando el cumplimiento de **RPO $\le 24$ horas**. | SLA-09 | [ ] C  [ ] NC |
| **6.3** | Ejecutar el simulacro de recuperación ante desastres en entorno aislado: `php artisan app:restore --simulate`. | El sistema verifica hashes SHA-256, recrea la base temporal `panol_inacap_restore_test`, restaura las 32 tablas, valida registros y cronometra el RTO. | PI-08, SLA-10 | [ ] C  [ ] NC |
| **6.4** | Auditar el cronómetro de restauración desplegado en pantalla. | El restablecimiento completo del servicio se ejecuta en **segundos** (resultado medido: $\approx 3.6$ s), cumpliendo holgadamente el límite de **RTO $\le 8$ horas** (28.800 s). | SLA-10 | [ ] C  [ ] NC |

---

## 4. Acta de Aceptación y Certificación de Hitos

En la ciudad de Temuco, a los _____ días del mes de Noviembre de 2026, los participantes abajo firmantes dejan constancia de haber presenciado y certificado la ejecución satisfactoria de la totalidad de pruebas funcionales, de seguridad, concurrencia y continuidad operativa detalladas en el presente guión.

Habiéndose verificado el cumplimiento del 100 % de los casos de prueba (`CP-01` a `CP-22`), escenarios críticos de integración (`PI-01` a `PI-08`) y acuerdos de nivel de servicio (`SLA-01` a `SLA-11`), se declara **FORMALMENTE RECIBIDO Y APROBADO** el Sistema de Gestión de Pañol en su **Versión 1.0.0**, procediendo al cierre definitivo de los hitos del proyecto (H1 a H6).

```
___________________________________           ___________________________________
        Director(a) de Carrera                        Encargado(a) de Pañol
Área Informática y Telecomunicaciones         Área Informática y Telecomunicaciones
         INACAP Sede Temuco                            INACAP Sede Temuco



___________________________________           ___________________________________
     Coordinador(a) Académico(a)                  Ingeniero DevOps / Entrega
Área Informática y Telecomunicaciones         Equipo de Desarrollo de Software
         INACAP Sede Temuco
```
