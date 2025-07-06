<?php

namespace App\Helpers;

class CicloHelper
{
    /**
     * Convierte un número entero a número romano
     */
    public static function toRoman($number)
    {
        $map = [
            1 => 'I',
            2 => 'II',
            3 => 'III',
            4 => 'IV',
            5 => 'V',
            6 => 'VI',
            7 => 'VII',
            8 => 'VIII',
            9 => 'IX',
            10 => 'X'
        ];
        
        return $map[$number] ?? 'I';
    }
    
    /**
     * Convierte un número romano a entero
     */
    public static function toInteger($roman)
    {
        $map = [
            'I' => 1,
            'II' => 2,
            'III' => 3,
            'IV' => 4,
            'V' => 5,
            'VI' => 6,
            'VII' => 7,
            'VIII' => 8,
            'IX' => 9,
            'X' => 10
        ];
        
        return $map[$roman] ?? 1;
    }
    
    /**
     * Obtiene el monto máximo para un ciclo dado
     */
    public static function getMontoMaximo($ciclo)
    {
        $montos = [
            'I' => 400,
            'II' => 600,
            'III' => 800,
            'IV' => 1000
        ];
        
        // Si el ciclo es un número, convertirlo a romano
        if (is_numeric($ciclo)) {
            $ciclo = self::toRoman($ciclo);
        }
        
        return $montos[$ciclo] ?? 400;
    }
    
    /**
     * Normaliza un ciclo a formato romano
     */
    public static function normalize($ciclo)
    {
        if (is_numeric($ciclo)) {
            return self::toRoman($ciclo);
        }
        
        return $ciclo;
    }
}
