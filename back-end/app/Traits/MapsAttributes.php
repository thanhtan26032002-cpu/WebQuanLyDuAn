<?php

namespace App\Traits;

trait MapsAttributes
{
    /**
     * Get the attribute mapping from database column to API property name.
     * e.g. ['project_code' => 'code', 'project_name' => 'name']
     */
    abstract public function getAttributeMapping(): array;

    /**
     * Map model attributes to API format when serializing to array/JSON.
     */
    public function toArray()
    {
        $array = parent::toArray();
        $mapping = $this->getAttributeMapping();
        $result = [];
        foreach ($array as $key => $value) {
            $mappedKey = $mapping[$key] ?? $key;
            $result[$mappedKey] = $value;
        }
        return $result;
    }

    /**
     * Override getAttribute to allow accessing by API property names (e.g. $model->name).
     */
    public function getAttribute($key)
    {
        $value = parent::getAttribute($key);
        if ($value === null && !array_key_exists($key, $this->attributes ?? [])) {
            $mapping = $this->getAttributeMapping();
            $reverseMapping = array_flip($mapping);
            if (isset($reverseMapping[$key])) {
                return parent::getAttribute($reverseMapping[$key]);
            }
        }
        return $value;
    }

    /**
     * Override setAttribute to allow setting by API property names (e.g. $model->name = 'foo').
     */
    public function setAttribute($key, $value)
    {
        $mapping = $this->getAttributeMapping();
        $reverseMapping = array_flip($mapping);
        if (isset($reverseMapping[$key])) {
            $key = $reverseMapping[$key];
        }
        return parent::setAttribute($key, $value);
    }

    /**
     * Override getFillable so that both DB column names and mapped API property names are fillable.
     */
    public function getFillable()
    {
        $fillable = parent::getFillable();
        $mapping = $this->getAttributeMapping();
        foreach ($fillable as $field) {
            if (isset($mapping[$field])) {
                $fillable[] = $mapping[$field];
            }
        }
        return array_unique($fillable);
    }

    /**
     * Override isFillable to allow fillable/guarded checks to recognize mapped API attributes.
     */
    public function isFillable($key)
    {
        if (parent::isFillable($key)) {
            return true;
        }
        $mapping = $this->getAttributeMapping();
        $reverseMapping = array_flip($mapping);
        if (isset($reverseMapping[$key]) && parent::isFillable($reverseMapping[$key])) {
            return true;
        }
        return false;
    }

    /**
     * Map incoming API attributes to database column names for create/update.
     */
    public static function mapToDbAttributes(array $data): array
    {
        $instance = new static;
        $mapping = $instance->getAttributeMapping();
        $reverseMapping = array_flip($mapping);
        $result = [];
        foreach ($data as $key => $value) {
            $dbKey = $reverseMapping[$key] ?? $key;
            $result[$dbKey] = $value;
        }
        return $result;
    }
}
