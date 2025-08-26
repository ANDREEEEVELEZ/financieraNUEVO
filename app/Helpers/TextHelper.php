<?php

namespace App\Helpers;

class TextHelper
{
    /**
     * Convierte texto a mayúsculas, manejando casos especiales
     */
    public static function toUpperCase($value)
    {
        if (is_null($value) || empty($value)) {
            return $value;
        }

        return strtoupper(trim($value));
    }

    /**
     * Convierte texto a mayúsculas excepto para emails
     */
    public static function toUpperCaseExceptEmail($value, $isEmail = false)
    {
        if (is_null($value) || empty($value)) {
            return $value;
        }

        if ($isEmail) {
            return strtolower(trim($value));
        }

        return strtoupper(trim($value));
    }

    /**
     * Aplica formato estándar a nombres propios
     */
    public static function formatName($value)
    {
        if (is_null($value) || empty($value)) {
            return $value;
        }

        return strtoupper(trim($value));
    }

    /**
     * Aplica formato estándar a descripciones
     */
    public static function formatDescription($value)
    {
        if (is_null($value) || empty($value)) {
            return $value;
        }

        return strtoupper(trim($value));
    }
}
