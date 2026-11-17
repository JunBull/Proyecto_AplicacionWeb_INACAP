<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Alerta de Retraso en Devolución de Préstamo</title>
</head>
<body style="font-family: Arial, sans-serif; color: #333333; line-height: 1.5; padding: 20px; background-color: #f8fafc;">
    <div style="max-width: 620px; margin: 0 auto; border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden; background-color: #ffffff; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
        <!-- Encabezado Institucional INACAP -->
        <div style="background-color: #b91c1c; color: #ffffff; padding: 18px 24px;">
            <h2 style="margin: 0; font-size: 18px; font-weight: bold; letter-spacing: 0.5px;">INACAP Sede Temuco - Gestión de Pañol</h2>
            <p style="margin: 4px 0 0; font-size: 13px; opacity: 0.95;">Alerta de Préstamo a Estudiante con Retraso en Devolución</p>
        </div>

        <!-- Cuerpo del Correo -->
        <div style="padding: 24px;">
            <p style="font-size: 14px; margin-top: 0; margin-bottom: 16px; color: #1e293b; line-height: 1.6;">
                Junto con saludar, se informa que una solicitud de equipo por parte de un estudiante no ha sido devuelta aún, se detallan los datos de la solicitud:
            </p>

            <!-- Tabla de Datos de la Solicitud y Retraso -->
            <table style="width: 100%; border-collapse: collapse; margin-bottom: 20px; font-size: 13px;">
                <tr>
                    <td style="padding: 9px 12px; background-color: #f1f5f9; font-weight: bold; width: 36%; border-bottom: 1px solid #e2e8f0; color: #475569;">
                        Nombre Estudiante:
                    </td>
                    <td style="padding: 9px 12px; border-bottom: 1px solid #e2e8f0; font-weight: bold; color: #0f172a;">
                        {{ $entrega->receptor_nombre }}
                    </td>
                </tr>
                <tr>
                    <td style="padding: 9px 12px; background-color: #f1f5f9; font-weight: bold; border-bottom: 1px solid #e2e8f0; color: #475569;">
                        RUT / Credencial:
                    </td>
                    <td style="padding: 9px 12px; border-bottom: 1px solid #e2e8f0; font-family: monospace; color: #0f172a; font-weight: bold;">
                        {{ $entrega->receptor_credencial_rut ?? 'No registrado' }}
                    </td>
                </tr>
                <tr>
                    <td style="padding: 9px 12px; background-color: #f1f5f9; font-weight: bold; border-bottom: 1px solid #e2e8f0; color: #475569; vertical-align: top;">
                        Equipo(s) / Material(es):
                    </td>
                    <td style="padding: 9px 12px; border-bottom: 1px solid #e2e8f0;">
                        <ul style="margin: 0; padding-left: 18px; color: #0f172a;">
                            @forelse($itemsPendientes as $det)
                                <li style="margin-bottom: 4px;">
                                    @if($det->equipo)
                                        <strong>{{ $det->equipo->nombre }}</strong>
                                        <span style="color: #64748b; font-size: 11px;">(Cód. Barras: {{ $det->equipo->codigo_barras ?? 'S/C' }})</span>
                                    @elseif($det->insumo)
                                        <strong>{{ $det->insumo->nombre }}</strong>
                                        <span style="color: #64748b; font-size: 11px;">(Cód. Barras: {{ $det->insumo->codigo_barras ?? 'S/C' }})</span>
                                        &bull; Cantidad pendiente: {{ (float)$det->cantidad_entregada - (float)$det->cantidad_devuelta }} {{ $det->insumo->unidad }}
                                    @endif
                                </li>
                            @empty
                                <li style="color: #64748b;">No se registran ítems pendientes de devolución.</li>
                            @endforelse
                        </ul>
                    </td>
                </tr>
                <tr>
                    <td style="padding: 9px 12px; background-color: #f1f5f9; font-weight: bold; border-bottom: 1px solid #e2e8f0; color: #475569;">
                        Fecha y Hora Acordada:
                    </td>
                    <td style="padding: 9px 12px; border-bottom: 1px solid #e2e8f0; color: #b91c1c; font-weight: bold;">
                        {{ $fechaHoraAcordada->format('d/m/Y H:i') }} hrs
                    </td>
                </tr>
                <tr>
                    <td style="padding: 9px 12px; background-color: #f1f5f9; font-weight: bold; border-bottom: 1px solid #e2e8f0; color: #475569;">
                        Fecha y Hora Actual:
                    </td>
                    <td style="padding: 9px 12px; border-bottom: 1px solid #e2e8f0; color: #0f172a;">
                        {{ $fechaActual->format('d/m/Y H:i') }} hrs
                    </td>
                </tr>
                <tr>
                    <td style="padding: 9px 12px; background-color: #fef2f2; font-weight: bold; border-bottom: 1px solid #fecaca; color: #991b1b;">
                        Retraso Acumulado:
                    </td>
                    <td style="padding: 9px 12px; border-bottom: 1px solid #fecaca; background-color: #fef2f2; color: #991b1b; font-weight: bold;">
                        ⚠️ Retraso considerable de {{ $retrasoHumano }} ({{ $retrasoMinutos }} minutos de mora)
                    </td>
                </tr>
                <tr>
                    <td style="padding: 9px 12px; background-color: #f1f5f9; font-weight: bold; border-bottom: 1px solid #e2e8f0; color: #475569;">
                        Folio Entrega / Solicitud:
                    </td>
                    <td style="padding: 9px 12px; border-bottom: 1px solid #e2e8f0; color: #475569;">
                        #ENT-{{ str_pad($entrega->id, 5, '0', STR_PAD_LEFT) }} &bull; Solicitud #SOL-{{ str_pad($entrega->solicitud_id, 5, '0', STR_PAD_LEFT) }}
                    </td>
                </tr>
                <tr>
                    <td style="padding: 9px 12px; background-color: #f1f5f9; font-weight: bold; border-bottom: 1px solid #e2e8f0; color: #475569;">
                        Despachado por:
                    </td>
                    <td style="padding: 9px 12px; border-bottom: 1px solid #e2e8f0; color: #475569;">
                        {{ $entrega->entregador?->name }} ({{ $entrega->entregador?->email }})
                    </td>
                </tr>
            </table>

            <p style="font-size: 12px; color: #64748b; margin-bottom: 0;">
                Este reporte ha sido generado por el personal de Pañol mediante el botón de reporte al detectarse que el estudiante no devolvió el material dentro del horario comprometido.
            </p>
        </div>

        <!-- Pie de Página -->
        <div style="background-color: #f8fafc; padding: 12px 24px; font-size: 11px; color: #64748b; text-align: center; border-top: 1px solid #e2e8f0;">
            Sistema de Pañol e Inventario &copy; {{ date('Y') }} INACAP Temuco. Notificación automática a Administradores.
        </div>
    </div>
</body>
</html>
