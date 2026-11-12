<?php

namespace Tests\Unit\Services;

use App\Services\FormatoHelper;
use PHPUnit\Framework\TestCase;

class FormatoHelperTest extends TestCase
{
    public function test_formatear_rut_sin_formato_agrega_puntos_y_guion(): void
    {
        // Caso solicitado por el usuario: 215148319 -> 21.514.831-9
        $this->assertEquals('21.514.831-9', FormatoHelper::formatearRut('215148319'));

        // Con guion previo: 21514831-9 -> 21.514.831-9
        $this->assertEquals('21.514.831-9', FormatoHelper::formatearRut('21514831-9'));

        // Ya formateado: 21.514.831-9 -> 21.514.831-9
        $this->assertEquals('21.514.831-9', FormatoHelper::formatearRut('21.514.831-9'));

        // Proveedor: 761234567 -> 76.123.456-7
        $this->assertEquals('76.123.456-7', FormatoHelper::formatearRut('761234567'));
    }

    public function test_formatear_rut_admite_k_como_digito_verificador_y_bloquea_letras_en_cuerpo(): void
    {
        // DV con 'k' minúscula se convierte a mayúscula 'K'
        $this->assertEquals('20.987.654-K', FormatoHelper::formatearRut('20987654k'));
        $this->assertEquals('20.987.654-K', FormatoHelper::formatearRut('20.987.654-K'));

        // Letras en el cuerpo son eliminadas conservando el DV
        $this->assertEquals('21.514.831-9', FormatoHelper::formatearRut('ABC215148319XYZ'));
    }

    public function test_formatear_telefono_chileno_con_prefijo_y_9_numeros(): void
    {
        // 9 dígitos simples
        $this->assertEquals('+56 9 8765 4321', FormatoHelper::formatearTelefono('987654321'));

        // Ya con +56 junto
        $this->assertEquals('+56 9 8765 4321', FormatoHelper::formatearTelefono('+56987654321'));

        // Con espacios o guiones
        $this->assertEquals('+56 9 8765 4321', FormatoHelper::formatearTelefono('+56-9-8765-4321'));
    }

    public function test_formatear_moneda_clp_sin_decimales(): void
    {
        $this->assertEquals('$ 2.000', FormatoHelper::formatearMoneda(2000));
        $this->assertEquals('$ 250.000', FormatoHelper::formatearMoneda(250000));
        $this->assertEquals('$ 1.500.000', FormatoHelper::formatearMoneda(1500000));
        $this->assertEquals('$ 0', FormatoHelper::formatearMoneda(0));
    }

    public function test_sanitizar_monto_entero_redondea_y_descarta_decimales(): void
    {
        $this->assertEquals(2000, FormatoHelper::sanitizarMontoEntero('2000.40'));
        $this->assertEquals(2001, FormatoHelper::sanitizarMontoEntero('2000.50'));
        $this->assertEquals(250000, FormatoHelper::sanitizarMontoEntero(250000.00));
        $this->assertEquals(0, FormatoHelper::sanitizarMontoEntero(-500));
    }
}
