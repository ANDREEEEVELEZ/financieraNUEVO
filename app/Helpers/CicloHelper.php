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
     * LÓGICA ACUMULATIVA: Un ciclo superior puede acceder a montos de ciclos inferiores
     * Ciclo I: 400
     * Ciclo II: 400, 500, 600
     * Ciclo III: 400, 500, 600, 700, 800
     * Ciclo IV: 400, 500, 600, 700, 800, 900, 1000
     */
    public static function getMontosPermitidos($ciclo)
    {
        // Definir montos acumulativos por ciclo
        $montosAcumulativos = [
            'I' => [400],
            'II' => [400, 500, 600],
            'III' => [400, 500, 600, 700, 800],
            'IV' => [400, 500, 600, 700, 800, 900, 1000]
        ];
        
        // Si el ciclo es un número, convertirlo a romano
        if (is_numeric($ciclo)) {
            $ciclo = self::toRoman($ciclo);
        }
        
        // Normalizar ciclo y retornar montos
        $cicloNormalizado = self::normalize($ciclo);
        return $montosAcumulativos[$cicloNormalizado] ?? [];
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
     * Obtiene el ciclo MÍNIMO al que pertenece un monto específico
     * Esto indica desde qué ciclo está disponible ese monto
     */
    public static function getCicloPorMonto($monto)
    {
        $montoInt = (int) $monto;
        
        // Retorna el ciclo MÍNIMO donde aparece cada monto
        if ($montoInt === 400) return 'I';       // Disponible desde Ciclo I
        if ($montoInt === 500 || $montoInt === 600) return 'II';   // Disponible desde Ciclo II
        if ($montoInt === 700 || $montoInt === 800) return 'III';  // Disponible desde Ciclo III
        if ($montoInt === 900 || $montoInt === 1000) return 'IV';  // Disponible desde Ciclo IV
        
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
     * Obtiene las opciones para el select de montos (solo monto, sin información adicional)
     * LÓGICA ACUMULATIVA: Muestra todos los montos disponibles hasta el ciclo actual
     * Formato simplificado: ['monto' => 'S/ monto']
     */
    public static function getMontosPermitidosParaSelect($ciclo)
    {
        if (empty($ciclo)) {
            return [];
        }
        
        // Obtener montos acumulativos para el ciclo
        $montos = self::getMontosPermitidos($ciclo);
        $options = [];
        
        // Ordenar montos de menor a mayor para mejor UX
        sort($montos);
        
        foreach ($montos as $monto) {
            $options[$monto] = "S/ " . number_format($monto, 0);
        }
        
        return $options;
    }
    
    /**
     * Valida que un monto sea exactamente uno de los permitidos para el ciclo
     * LÓGICA ACUMULATIVA: Valida contra todos los montos disponibles hasta el ciclo actual
     */
    public static function validarMontoExacto($monto, $ciclo)
    {
        if (empty($monto) || empty($ciclo)) {
            return false;
        }
        
        $montosPermitidos = self::getMontosPermitidos($ciclo);
        $montoInt = (int) $monto;
        
        // Validación estricta: el monto debe estar exactamente en la lista
        return in_array($montoInt, $montosPermitidos, true);
    }
    
    /**
     * Verifica si un cliente puede acceder a un monto específico según su ciclo
     * Método adicional para validación robusta
     */
    public static function puedeAccederAMonto($monto, $ciclo)
    {
        $montoInt = (int) $monto;
        $cicloNormalizado = self::normalize($ciclo);
        
        // Definir qué ciclos pueden acceder a cada monto
        $accesoPorMonto = [
            400 => ['I', 'II', 'III', 'IV'],
            500 => ['II', 'III', 'IV'],
            600 => ['II', 'III', 'IV'],
            700 => ['III', 'IV'],
            800 => ['III', 'IV'],
            900 => ['IV'],
            1000 => ['IV']
        ];
        
        return isset($accesoPorMonto[$montoInt]) && in_array($cicloNormalizado, $accesoPorMonto[$montoInt], true);
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
