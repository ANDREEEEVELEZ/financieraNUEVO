<?php

namespace App\Traits;

trait HasUpperCaseAttributes
{
    /**
     * Campos que deben convertirse automáticamente a mayúsculas
     */
    protected $upperCaseFields = [];

    /**
     * Campos que deben mantenerse en minúsculas (como emails)
     */
    protected $lowerCaseFields = [];

    /**
     * Boot del trait
     */
    protected static function bootHasUpperCaseAttributes()
    {
        static::saving(function ($model) {
            $model->convertToUpperCase();
        });
    }

    /**
     * Convierte los campos especificados a mayúsculas
     */
    protected function convertToUpperCase()
    {
        // Obtener campos que deben estar en mayúsculas
        $upperFields = $this->getUpperCaseFields();

        foreach ($upperFields as $field) {
            if (isset($this->attributes[$field]) && !is_null($this->attributes[$field])) {
                $this->attributes[$field] = strtoupper(trim($this->attributes[$field]));
            }
        }

        // Obtener campos que deben estar en minúsculas
        $lowerFields = $this->getLowerCaseFields();

        foreach ($lowerFields as $field) {
            if (isset($this->attributes[$field]) && !is_null($this->attributes[$field])) {
                $this->attributes[$field] = strtolower(trim($this->attributes[$field]));
            }
        }
    }

    /**
     * Obtiene los campos que deben convertirse a mayúsculas
     */
    protected function getUpperCaseFields()
    {
        return $this->upperCaseFields ?? [];
    }

    /**
     * Obtiene los campos que deben convertirse a minúsculas
     */
    protected function getLowerCaseFields()
    {
        return $this->lowerCaseFields ?? [];
    }

    /**
     * Establece los campos que deben convertirse a mayúsculas
     */
    public function setUpperCaseFields(array $fields)
    {
        $this->upperCaseFields = $fields;
        return $this;
    }

    /**
     * Establece los campos que deben convertirse a minúsculas
     */
    public function setLowerCaseFields(array $fields)
    {
        $this->lowerCaseFields = $fields;
        return $this;
    }
}
