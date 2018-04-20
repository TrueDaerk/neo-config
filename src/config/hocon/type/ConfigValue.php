<?php

namespace Neo\Commons\Config\HOCON\Type;

use Neo\Commons\Config\HOCON\HoconFormatException;
use Neo\Commons\Config\HOCON\HoconParsingObject;

class ConfigValue {
   /* Constants */
   const TYPE_INTEGER = 1,
      TYPE_DOUBLE = 2,
      TYPE_STRING = 3,
      TYPE_REFERENCE = 4,
      TYPE_ARRAY = 5,
      TYPE_OBJECT = 6,
      TYPE_NULL = 7;

   /* Class fields */
   private $values = [];
   /**
    * @var HoconParsingObject
    */
   private $config;
   private $path;

   /**
    * ConfigValue constructor.
    *
    * @param HoconParsingObject $config Config to test already existing configurations.
    * @param string $path Path to this config value.
    */
   public function __construct($config, $path) {
      $this->config = $config;
      $this->path = $path;
   }

   /**
    * Adds a value to this config value (used because of multiline-reference combinations)
    *
    * @param mixed $value Value to add.
    * @param boolean $reference If set to true, the added value will be added as a reference.
    */
   public function addValue($value, $reference = false) {
      if (is_int($value)) {
         $this->_addValue($value, self::TYPE_INTEGER);

      } elseif (is_double($value)) {
         $this->_addValue($value, self::TYPE_DOUBLE);

      } elseif (is_array($value)) {
         $this->_addValue($value, self::TYPE_ARRAY);

      } elseif (is_null($value)) {
         $this->_addValue($value, self::TYPE_NULL);

      } elseif (is_string($value)) {
         if ($reference === true) {
            $found = $this->config->getValue($value);
            if (isset($found)) {
               $this->values[] = [self::TYPE_REFERENCE, $value, $found];

            } else {
               $this->_addValue($value, self::TYPE_REFERENCE);
            }
         } else {
            $this->_addValue($value, self::TYPE_STRING);
         }

      } elseif (is_object($value)) {
         $this->_addValue($value, self::TYPE_OBJECT);
      }
   }

   /**
    * Adds a value to this config value (used because of multiline-reference combinations)
    *
    * @param mixed $value Value to add.
    * @param int $type Type of the value, as in ::TYPE_XXX
    */
   private function _addValue($value, $type) {
      $this->values[] = [$type, $value];
   }

   /**
    * @return mixed
    * @throws HoconFormatException
    */
   public function compile() {
      if (count($this->values) === 1) {
         if ($this->values[0][0] === self::TYPE_REFERENCE) {
            if (count($this->values[0]) > 2) {
               // Returned the previously found value.
               return $this->values[0][2];
            }
            // Get referenced value
            return $this->config->getValue($this->values[0][1]);
         }
         // Return direct
         return $this->values[0][1];
      }
      $compiledValue = "";
      foreach ($this->values as $value) {
         if (in_array($value[0], [self::TYPE_OBJECT, self::TYPE_ARRAY])) {
            throw new HoconFormatException("Object or array values are not allowed as concatenated strings");

         } elseif ($value[0] === self::TYPE_REFERENCE) {
            // Get referenced value
            if (count($value) > 2) {
               $v = $value[2];

            } else {
               $v = $this->config->getValue($value[1]);
            }
            if (is_object($v) || is_array($v)) {
               throw new HoconFormatException("Referenced object or array values are not allowed as concatenated strings");
            }
            $compiledValue .= $v;

         } else {
            $compiledValue .= $value[1];
         }
      }
      return $compiledValue;
   }

   /**
    * Adds the values from the other ConfigValue to this.
    *
    * @param ConfigValue $other Other config to merge with this one.
    */
   public function merge($other) {
      if ($other instanceof ConfigValue) {
         $this->values = array_merge($this->values, $other->values);
      }
   }

   /**
    * @return string Path to this value.
    */
   public function getPath() {
      return $this->path;
   }

   /**
    * @param HoconParsingObject $config Configuration to switch to.
    */
   public function setConfig($config) {
      $this->config = $config;
   }
}