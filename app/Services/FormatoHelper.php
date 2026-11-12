<?php

namespace App\Services;

class FormatoHelper
{
    /**
     * Formatea un RUT chileno al formato estandarizado (00.000.000-0).
     *
     * Acepta entradas sin formato (ej. 215148319 o 21514831-9),
     * bloquea letras en el cuerpo y admite 'K'/'k' exclusivamente como
     * dígito verificador final.
     */
    public static function formatearRut(?string $rut): ?string
    {
        if ($rut === null) {
            return null;
        }

        $limpio = trim($rut);
        if ($limpio === '') {
            return '';
        }

        // Eliminar puntos, guiones y caracteres no permitidos (solo dígitos y K/k)
        $soloValidos = preg_replace('/[^0-9kK]/', '', $limpio);
        if ($soloValidos === null || $soloValidos === '') {
            return '';
        }

        // Si tiene solo 1 carácter, no hay cuerpo para formatear
        if (strlen($soloValidos) < 2) {
            return strtoupper($soloValidos);
        }

        // Limitar a máximo 9 caracteres (8 de cuerpo + 1 verificador)
        if (strlen($soloValidos) > 9) {
            $soloValidos = substr($soloValidos, 0, 9);
        }

        $dv = strtoupper(substr($soloValidos, -1));
        $cuerpoRaw = substr($soloValidos, 0, -1);

        // El cuerpo solo puede contener dígitos numéricos
        $cuerpo = preg_replace('/\D/', '', $cuerpoRaw);
        if ($cuerpo === '' || ! is_numeric($cuerpo)) {
            return $dv;
        }

        $cuerpoFormateado = number_format((int) $cuerpo, 0, '', '.');

        return "{$cuerpoFormateado}-{$dv}";
    }

    /**
     * Limpia un RUT dejándolo únicamente con dígitos y DV en mayúscula (sin puntos ni guion).
     */
    public static function limpiarRut(?string $rut): ?string
    {
        if ($rut === null) {
            return null;
        }

        $soloValidos = preg_replace('/[^0-9kK]/', '', trim($rut));

        return $soloValidos ? strtoupper($soloValidos) : null;
    }

    /**
     * Formatea un teléfono chileno con prefijo internacional +56 y 9 dígitos.
     * Ejemplo: +56 9 8765 4321
     */
    public static function formatearTelefono(?string $telefono): ?string
    {
        if ($telefono === null) {
            return null;
        }

        $limpio = trim($telefono);
        if ($limpio === '') {
            return null;
        }

        // Extraer todos los dígitos numéricos
        $digitos = preg_replace('/\D/', '', $limpio);

        // Si comienza con 56 y tiene más de 9 dígitos, remover el código de país para procesar los 9 dígitos
        if (str_starts_with($digitos, '56') && strlen($digitos) > 9) {
            $digitos = substr($digitos, 2);
        }

        // Limitar a los 9 dígitos oficiales de telefonía móvil/fija en Chile
        if (strlen($digitos) > 9) {
            $digitos = substr($digitos, -9);
        }

        if (strlen($digitos) === 9) {
            $primerDigito = substr($digitos, 0, 1);
            $bloque1 = substr($digitos, 1, 4);
            $bloque2 = substr($digitos, 5, 4);

            return "+56 {$primerDigito} {$bloque1} {$bloque2}";
        }

        // Si tiene longitud parcial pero válida, anteponer prefijo
        return $digitos !== '' ? "+56 {$digitos}" : null;
    }

    /**
     * Formatea una cifra de dinero en Pesos Chilenos (CLP) sin centavos ni decimales.
     * Ejemplo: $ 2.000, $ 250.000
     */
    public static function formatearMoneda(float|int|string|null $monto): string
    {
        if ($monto === null || $monto === '') {
            return '$ 0';
        }

        $entero = (int) round((float) $monto);

        return '$ '.number_format($entero, 0, ',', '.');
    }

    /**
     * Sanitiza y redondea cualquier entrada monetaria a entero estricto sin decimales.
     */
    public static function sanitizarMontoEntero(mixed $monto): int
    {
        if (is_numeric($monto)) {
            return max(0, (int) round((float) $monto));
        }

        if (is_string($monto)) {
            // Eliminar signos monetarios y espacios
            $limpio = preg_replace('/[^0-9,\.]/', '', trim($monto));
            // Reemplazar coma por punto si viniera con formato flotante
            $limpio = str_replace(',', '.', $limpio);

            return max(0, (int) round((float) $limpio));
        }

        return 0;
    }
}
