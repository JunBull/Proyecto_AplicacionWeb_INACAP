<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Solicitud de Cotización</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
    <div style="max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e2e8f0; border-radius: 8px;">
        <h2 style="color: #c53030; border-bottom: 2px solid #c53030; padding-bottom: 8px;">INACAP Sede Temuco</h2>
        <p>Estimados <strong>{{ $proveedor->razon_social }}</strong>,</p>
        <p>Junto con saludar, por medio de la presente solicitamos a ustedes formalmente cotización comercial para los siguientes insumos o servicios:</p>
        
        <div style="background-color: #f7fafc; padding: 15px; border-left: 4px solid #c53030; margin: 20px 0;">
            <p style="margin: 0; white-space: pre-line;">{{ $solicitud->descripcion }}</p>
        </div>

        <p><strong>Detalles de la Solicitud:</strong></p>
        <ul>
            <li><strong>Código de Solicitud:</strong> #{{ $solicitud->id }}</li>
            <li><strong>Carrera Solicitante:</strong> {{ $solicitud->carrera->nombre ?? 'Pañol Central' }}</li>
            <li><strong>Fecha de Emisión:</strong> {{ $solicitud->fecha_creacion->format('d/m/Y') }}</li>
        </ul>

        <p>Agradecemos remitir su propuesta comercial con desglose de valores netos, impuestos y tiempos estimados de entrega.</p>
        
        <p style="margin-top: 30px; font-size: 12px; color: #718096; border-top: 1px solid #e2e8f0; padding-top: 10px;">
            Este mensaje ha sido generado automáticamente por el Sistema de Gestión de Pañol de INACAP Sede Temuco.
        </p>
    </div>
</body>
</html>
