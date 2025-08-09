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
     * Obtiene todos los montos permitidos para un ciclo específico
     * Basado en la tabla oficial de montos y ciclos
     */
    public static function getMontosPermitidos($ciclo)
    {
        $montosPermitidos = [
            'I' => [400],
            'II' => [500, 600],
            'III' => [700, 800],
            'IV' => [900, 1000]
        ];
        
        // Si el ciclo es un número, convertirlo a romano
        if (is_numeric($ciclo)) {
            $ciclo = self::toRoman($ciclo);
        }
        
        return $montosPermitidos[$ciclo] ?? [];
    }
    
    /**
     * Verifica si un monto es válido para un ciclo específico
     */
    public static function esMontoValidoParaCiclo($monto, $ciclo)
    {
        $montosPermitidos = self::getMontosPermitidos($ciclo);
        return in_array((int) $monto, $montosPermitidos);
    }
    
    /**
     * Obtiene el ciclo al que pertenece un monto específico
     */
    public static function getCicloPorMonto($monto)
    {
        $montoInt = (int) $monto;
        
        if ($montoInt === 400) return 'I';
        if ($montoInt === 500 || $montoInt === 600) return 'II';
        if ($montoInt === 700 || $montoInt === 800) return 'III';
        if ($montoInt === 900 || $montoInt === 1000) return 'IV';
        
        return null; // Monto no válido
    }
    
    /**
     * Obtiene el seguro correspondiente a un monto específico
     * Basado en la tabla oficial de montos y seguros
     */
    public static function getSeguroPorMonto($monto)
    {
        $montoInt = (int) $monto;
        
        // Ciclo I
        if ($montoInt === 400) {
            return 7;
        }
        
        // Ciclo II  
        if ($montoInt === 500 || $montoInt === 600) {
            return 8;
        }
        
        // Ciclo III
        if ($montoInt === 700 || $montoInt === 800) {
            return 9;
        }
        
        // Ciclo IV
        if ($montoInt === 900 || $montoInt === 1000) {
            return 10;
        }
        
        // Fallback seguro
        return 7;
    }
    
    /**
     * Obtiene las opciones para el select de montos con información del seguro
     * Formato: ['monto' => 'S/ monto (Seguro: S/ seguro)']
     */
    public static function getMontosPermitidosParaSelect($ciclo)
    {
        if (empty($ciclo)) {
            return [];
        }
        
        $montos = self::getMontosPermitidos($ciclo);
        $options = [];
        
        foreach ($montos as $monto) {
            $seguro = self::getSeguroPorMonto($monto);
            $options[$monto] = "S/ " . number_format($monto, 0) . " (Seguro: S/ " . number_format($seguro, 0) . ")";
        }
        
        return $options;
    }
    
    /**
     * Valida que un monto sea exactamente uno de los permitidos para el ciclo
     */
    public static function validarMontoExacto($monto, $ciclo)
    {
        $montosPermitidos = self::getMontosPermitidos($ciclo);
        $montoInt = (int) $monto;
        
        return in_array($montoInt, $montosPermitidos, true);
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

    /**
     * Calcula el ciclo que debería tener un cliente basado en préstamos completados
     */
    public static function calcularCicloPorPrestamos($prestamosCompletados)
    {
        // Cada 2 préstamos completados sube un ciclo
        // Ciclo base = 1 (I), máximo = 4 (IV)
        $nuevoCiclo = min(4, 1 + floor($prestamosCompletados / 2));
        return self::toRoman($nuevoCiclo);
    }

    /**
     * Verifica si un cliente puede subir de ciclo
     */
    public static function puedeSubirCiclo($cicloActual, $prestamosCompletados)
    {
        $cicloActualNumero = self::toInteger($cicloActual);
        $cicloCalculado = 1 + floor($prestamosCompletados / 2);
        
        return $cicloCalculado > $cicloActualNumero && $cicloActualNumero < 4;
    }
}
