<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Reporte de Incidencia Técnica</title>
</head>
<body style="font-family: Arial, sans-serif; color: #333; line-height: 1.5; padding: 20px;">
    <div style="max-width: 600px; margin: 0 auto; border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden;">
        <div style="background-color: #b91c1c; color: white; padding: 16px 20px;">
            <h2 style="margin: 0; font-size: 18px;">INACAP Sede Temuco - Gestión de Pañol</h2>
            <p style="margin: 4px 0 0; font-size: 13px; opacity: 0.9;">Alerta Reactiva de Incidencia en Equipamiento / Insumo</p>
        </div>
        <div style="padding: 20px; background-color: #ffffff;">
            <p>Estimado equipo de Pañol y Administración,</p>
            <p>Se ha registrado un nuevo reporte de falla o desperfecto técnico que requiere atención preventiva:</p>
            
            <table style="width: 100%; border-collapse: collapse; margin: 15px 0;">
                <tr>
                    <td style="padding: 8px; background-color: #f8fafc; font-weight: bold; width: 35%;">Folio Incidencia:</td>
                    <td style="padding: 8px; border-bottom: 1px solid #e2e8f0;">#{{ $falla->id }}</td>
                </tr>
                <tr>
                    <td style="padding: 8px; background-color: #f8fafc; font-weight: bold;">Docente Informante:</td>
                    <td style="padding: 8px; border-bottom: 1px solid #e2e8f0;">{{ $falla->reportador?->name }} ({{ $falla->reportador?->email }})</td>
                </tr>
                <tr>
                    <td style="padding: 8px; background-color: #f8fafc; font-weight: bold;">Ubicación / Sala:</td>
                    <td style="padding: 8px; border-bottom: 1px solid #e2e8f0;">{{ $falla->ubicacion?->nombre ?? 'No especificada' }}</td>
                </tr>
                <tr>
                    <td style="padding: 8px; background-color: #f8fafc; font-weight: bold;">Recurso Afectado:</td>
                    <td style="padding: 8px; border-bottom: 1px solid #e2e8f0;">
                        @if($falla->equipo)
                            Equipo: {{ $falla->equipo->nombre }} (Serie: {{ $falla->equipo->numero_serie }})
                        @elseif($falla->insumo)
                            Insumo: {{ $falla->insumo->nombre }} (Código: {{ $falla->insumo->codigo_barras }})
                        @endif
                    </td>
                </tr>
                <tr>
                    <td style="padding: 8px; background-color: #f8fafc; font-weight: bold;">Detalle:</td>
                    <td style="padding: 8px; border-bottom: 1px solid #e2e8f0;">{{ $falla->descripcion }}</td>
                </tr>
            </table>

            <p style="font-size: 13px; color: #64748b;">
                Se adjunta el comprobante formal en formato PDF con la firma y hash de verificación SHA-256.
            </p>
        </div>
        <div style="background-color: #f1f5f9; padding: 12px 20px; font-size: 11px; color: #64748b; text-align: center;">
            Sistema de Pañol e Inventario &copy; {{ date('Y') }} INACAP Temuco. Notificación automática.
        </div>
    </div>
</body>
</html>
