<?php

namespace Tests\Feature\HitoTres;

use App\Models\Proveedor;
use App\Services\ProveedorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CasoPruebaCP16Test extends TestCase
{
    use RefreshDatabase;

    protected ProveedorService $proveedorService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->proveedorService = app(ProveedorService::class);
    }

    public function test_registra_proveedor_de_insumos_correctamente(): void
    {
        $proveedor = $this->proveedorService->registrarProveedor([
            'razon_social' => 'Ferretería Industrial Frontera SpA',
            'rut' => '76.890.123-4',
            'correo' => 'ventas@ferreteriafrontera.cl',
            'telefono' => '+56 45 221 3456',
            'tipo' => 'insumos',
            'activo' => true,
        ]);

        $this->assertInstanceOf(Proveedor::class, $proveedor);
        $this->assertDatabaseHas('proveedores', [
            'id' => $proveedor->id,
            'razon_social' => 'Ferretería Industrial Frontera SpA',
            'rut' => '76.890.123-4',
            'tipo' => 'insumos',
            'activo' => true,
        ]);
    }

    public function test_registra_proveedor_de_mantenimiento_especializado(): void
    {
        $proveedor = $this->proveedorService->registrarProveedor([
            'razon_social' => 'Servicios Técnicos y Calibración del Sur Ltda.',
            'rut' => '77.456.789-0',
            'correo' => 'contacto@calibracionsur.cl',
            'telefono' => '+56 45 298 7654',
            'tipo' => 'mantenimiento',
            'activo' => true,
        ]);

        $this->assertInstanceOf(Proveedor::class, $proveedor);
        $this->assertDatabaseHas('proveedores', [
            'id' => $proveedor->id,
            'tipo' => 'mantenimiento',
        ]);
    }

    public function test_rechaza_proveedor_con_rut_duplicado(): void
    {
        $this->proveedorService->registrarProveedor([
            'razon_social' => 'Proveedor Original',
            'rut' => '78.111.222-3',
            'correo' => 'original@proveedor.cl',
            'tipo' => 'insumos',
        ]);

        $this->expectException(ValidationException::class);

        $this->proveedorService->registrarProveedor([
            'razon_social' => 'Proveedor Duplicado',
            'rut' => '78.111.222-3',
            'correo' => 'duplicado@proveedor.cl',
            'tipo' => 'mantenimiento',
        ]);
    }

    public function test_rechaza_proveedor_con_correo_invalido(): void
    {
        $this->expectException(ValidationException::class);

        $this->proveedorService->registrarProveedor([
            'razon_social' => 'Proveedor Sin Correo Valido',
            'rut' => '79.333.444-5',
            'correo' => 'correo-invalido-sin-arroba',
            'tipo' => 'insumos',
        ]);
    }

    public function test_filtra_proveedores_con_precision_segun_rubro_comercial(): void
    {
        $pInsumos = $this->proveedorService->registrarProveedor([
            'razon_social' => 'Insumos Tech SpA',
            'rut' => '70.001.001-1',
            'correo' => 'tech@insumos.cl',
            'tipo' => 'insumos',
        ]);

        $pMant = $this->proveedorService->registrarProveedor([
            'razon_social' => 'Mantenimiento Electrónico Temuco',
            'rut' => '70.002.002-2',
            'correo' => 'mant@temuco.cl',
            'tipo' => 'mantenimiento',
        ]);

        $pAmbos = $this->proveedorService->registrarProveedor([
            'razon_social' => 'Soluciones Integrales del Sur',
            'rut' => '70.003.003-3',
            'correo' => 'soluciones@integral.cl',
            'tipo' => 'ambos',
        ]);

        // Filtrar por insumos: debe traer pInsumos y pAmbos
        $filtroInsumos = $this->proveedorService->listarProveedores('insumos');
        $this->assertTrue($filtroInsumos->contains('id', $pInsumos->id));
        $this->assertTrue($filtroInsumos->contains('id', $pAmbos->id));
        $this->assertFalse($filtroInsumos->contains('id', $pMant->id));

        // Filtrar por mantenimiento: debe traer pMant y pAmbos
        $filtroMant = $this->proveedorService->listarProveedores('mantenimiento');
        $this->assertTrue($filtroMant->contains('id', $pMant->id));
        $this->assertTrue($filtroMant->contains('id', $pAmbos->id));
        $this->assertFalse($filtroMant->contains('id', $pInsumos->id));
    }

    public function test_alterna_estado_activo_inactivo(): void
    {
        $proveedor = $this->proveedorService->registrarProveedor([
            'razon_social' => 'Proveedor Transitorio',
            'rut' => '71.123.456-7',
            'correo' => 'transitorio@proveedor.cl',
            'tipo' => 'insumos',
            'activo' => true,
        ]);

        $this->assertTrue($proveedor->activo);

        $this->proveedorService->toggleActivo($proveedor);
        $this->assertFalse($proveedor->fresh()->activo);

        $this->proveedorService->toggleActivo($proveedor);
        $this->assertTrue($proveedor->fresh()->activo);
    }
}
