<?php

namespace App\Services;

use App\Exceptions\SaldoInsuficienteException;
use App\Models\Carrera;
use App\Models\MovimientoPresupuesto;
use App\Models\Presupuesto;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PresupuestoService
{
    /**
     * Obtiene o inicializa el presupuesto de una carrera para un año específico.
     */
    public function obtenerOCrearPresupuesto(Carrera $carrera, int $anio, float $montoInicial): Presupuesto
    {
        if ($montoInicial < 0) {
            throw new InvalidArgumentException('El monto asignado inicial no puede ser negativo.');
        }

        return Presupuesto::firstOrCreate(
            [
                'carrera_id' => $carrera->id,
                'anio' => $anio,
            ],
            [
                'monto_asignado' => $montoInicial,
                'saldo_actual' => $montoInicial,
            ]
        );
    }

    /**
     * Consulta el saldo vigente disponible de una carrera para el año en curso o indicado (KPI-09).
     */
    public function consultarSaldo(Carrera $carrera, ?int $anio = null): float
    {
        $anio = $anio ?? (int) date('Y');

        $presupuesto = Presupuesto::where('carrera_id', $carrera->id)
            ->where('anio', $anio)
            ->first();

        return $presupuesto ? (float) $presupuesto->saldo_actual : 0.0;
    }

    /**
     * Comprueba si la carrera cuenta con fondos suficientes para el monto requerido (CP-15).
     */
    public function verificarDisponibilidad(Carrera $carrera, float $monto, ?int $anio = null): bool
    {
        if ($monto <= 0) {
            return true;
        }

        $saldo = $this->consultarSaldo($carrera, $anio);

        return $saldo >= $monto;
    }

    /**
     * Ejecuta el descuento presupuestario atómico con bloqueo pesimista en base de datos (CP-15, PI-01, PI-04).
     * Garantiza que sólo se debita la carrera correspondiente y previene sobregiro en transacciones concurrentes.
     *
     * @throws SaldoInsuficienteException Si no hay presupuesto o el saldo es inferior al monto requerido.
     */
    public function descontarPresupuesto(
        Carrera $carrera,
        float $monto,
        User $usuario,
        ?int $compraId = null,
        ?int $anio = null
    ): MovimientoPresupuesto {
        if ($monto <= 0) {
            throw new InvalidArgumentException('El monto a descontar del presupuesto debe ser estrictamente positivo.');
        }

        $anio = $anio ?? (int) date('Y');

        return DB::transaction(function () use ($carrera, $monto, $usuario, $compraId, $anio) {
            /** @var Presupuesto|null $presupuesto */
            $presupuesto = Presupuesto::where('carrera_id', $carrera->id)
                ->where('anio', $anio)
                ->lockForUpdate()
                ->first();

            if (! $presupuesto) {
                throw new SaldoInsuficienteException(
                    "No existe presupuesto asignado para la carrera {$carrera->nombre} en el año {$anio}."
                );
            }

            $saldoActual = (float) $presupuesto->saldo_actual;

            if ($saldoActual < $monto) {
                throw new SaldoInsuficienteException(
                    "Saldo presupuestario insuficiente para la carrera {$carrera->nombre}. Saldo disponible: \${$saldoActual}, Requerido: \${$monto}."
                );
            }

            // Descuento exacto y atómico
            $nuevoSaldo = $saldoActual - $monto;
            $presupuesto->saldo_actual = $nuevoSaldo;
            $presupuesto->save();

            // Registro inmutable del movimiento presupuestario
            return MovimientoPresupuesto::create([
                'presupuesto_id' => $presupuesto->id,
                'compra_id' => $compraId,
                'usuario_id' => $usuario->id,
                'tipo' => 'cargo',
                'monto' => $monto,
                'fecha' => now(),
            ]);
        });
    }

    /**
     * Reversa un cargo presupuestario en caso de anulación administrativa justificada.
     */
    public function reversarPresupuesto(
        Presupuesto $presupuesto,
        float $monto,
        User $usuario,
        ?int $compraId = null
    ): MovimientoPresupuesto {
        if ($monto <= 0) {
            throw new InvalidArgumentException('El monto a reversar debe ser un valor positivo.');
        }

        return DB::transaction(function () use ($presupuesto, $monto, $usuario, $compraId) {
            /** @var Presupuesto $presupuestoBloqueado */
            $presupuestoBloqueado = Presupuesto::where('id', $presupuesto->id)->lockForUpdate()->firstOrFail();

            $presupuestoBloqueado->saldo_actual = (float) $presupuestoBloqueado->saldo_actual + $monto;
            $presupuestoBloqueado->save();

            return MovimientoPresupuesto::create([
                'presupuesto_id' => $presupuestoBloqueado->id,
                'compra_id' => $compraId,
                'usuario_id' => $usuario->id,
                'tipo' => 'reverso',
                'monto' => $monto,
                'fecha' => now(),
            ]);
        });
    }
}
