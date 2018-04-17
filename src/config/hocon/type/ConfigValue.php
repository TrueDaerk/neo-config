<?php

namespace Neo\Commons\Config\HOCON\Type;

use Neo\Commons\Config\HOCON\HoconConfiguration;

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
    * @var HoconConfiguration
    */
   private $config;

   /**
    * ConfigValue constructor.
    *
    * @param HoconConfiguration $config
    */
   public function __construct($config) {
      $this->config = $config;
   }

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
            // TODO: Add reference (check if there is a value in the config already).
            // $this->_addValue($value, self::TYPE_STRING);

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

   private function compile() {
      // TODO: Create a compiled version of the value.
   }

   public function compileString() {

   }

   public function compileInteger() {

   }

   public function compileDouble() {

   }
}