<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ParametroSistema extends Model
{
    use HasFactory;

    protected $table = 'parametros_sistema';

    protected $fillable = [
        'clave',
        'valor',
        'tipo',
        'descripcion',
    ];

    /**
     * Obtiene el valor tipado de un parámetro global del sistema.
     */
    public static function get(string $clave, mixed $default = null): mixed
    {
        $param = static::where('clave', $clave)->first();

        if (! $param) {
            return $default;
        }

        return match ($param->tipo) {
            'integer', 'int' => (int) $param->valor,
            'boolean', 'bool' => filter_var($param->valor, FILTER_VALIDATE_BOOLEAN),
            'float' => (float) $param->valor,
            default => $param->valor,
        };
    }

    /**
     * Establece o actualiza el valor de un parámetro global del sistema.
     */
    public static function set(string $clave, mixed $valor, ?string $tipo = null, ?string $descripcion = null): self
    {
        $tipoCalculado = $tipo;
        if ($tipoCalculado === null) {
            if (is_bool($valor)) {
                $tipoCalculado = 'boolean';
            } elseif (is_int($valor)) {
                $tipoCalculado = 'integer';
            } elseif (is_float($valor)) {
                $tipoCalculado = 'float';
            } else {
                $tipoCalculado = 'string';
            }
        }

        $valorString = is_bool($valor) ? ($valor ? '1' : '0') : (string) $valor;

        return static::updateOrCreate(
            ['clave' => $clave],
            [
                'valor' => $valorString,
                'tipo' => $tipoCalculado,
                'descripcion' => $descripcion,
            ]
        );
    }
}
