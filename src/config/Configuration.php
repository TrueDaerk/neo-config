<?php

namespace Neo\Commons\Config;

class Configuration {
   /* Constants */
   const MATCH_CONFIG_VALUES = "/\\$\\{([\\w\\+\\-\\_\\d\\.]+)\\}/";
   /* Class fields */
   /**
    * @var object
    */
   protected $values;
   /**
    * @var Configuration
    */
   protected $parentConfiguration;
   /**
    * @var array
    */
   protected $keys = [];

   /**
    * Creates a configuration object with the given configuration.
    *
    * @param string|object|array $configuration Direct object configuration, associative array or a string json value.
    */
   public function __construct($configuration = null) {
      if ($this->whoAmI() !== __CLASS__) {
         // Is inherited class, so
         return;

      } elseif (is_string($configuration)) {
         // Tries to read the string as a json value.
         $configuration = @json_decode($configuration);
      } elseif (is_array($configuration)) {
         // Tries to transform the array to an object.
         $configuration = @json_decode(json_encode($configuration, JSON_HEX_QUOT | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_TAG));
      }
      if (!is_object($configuration)) {
         throw new \InvalidArgumentException("Configuration object must be array, json string or direct object.");
      }
      $this->values = $configuration;
   }

   /**
    * Registers the given configuration as a fallback.
    *
    * @param Configuration $fallback Configuration to register as fallback.
    * @return Configuration $this for chaining.
    */
   public function withFallback($fallback) {
      if (isset($this->parentConfiguration)) {
         throw new \RuntimeException("Partial configuration cannot register a fallback");
      }
      if ($fallback instanceof Configuration) {
         // Fuse configuration objects together
         $this->values = $this->overwriteValues($fallback->values, $this->values);
         // Reset keys, because they may have changed
         return $this;
      }
      throw new \InvalidArgumentException("Fallback configuration must be " . __CLASS__);
   }

   /**
    * Overwrites duplicate configuration values with the new object.
    *
    * @param object $old Old configuration data.
    * @param object $new New configuration data.
    * @return object New configuration object.
    */
   protected function overwriteValues($old, $new) {
      foreach ($new as $key => $value) {
         if (is_object($value) && property_exists($old, $key) && is_object($old->$key)) {
            $old->$key = $this->overwriteValues($old->$key, $value);
            continue;
         }
         $old->$key = $value;
      }
      return $old;
   }

   /**
    * Retrieves a string value from the configuration.
    *
    * @param string $name Name of the configuration to retrieve.
    * @return string String value from the config.
    */
   public function getString($name) {
      $value = $this->getValue($name);
      if (isset($value) && !is_array($value) && !is_object($value)) {
         return strval($value);
      }
      return null;
   }

   /**
    * Retrieves an integer value from the configuration.
    *
    * @param string $name Name of the configuration to retrieve.
    * @return int Integer value from the config.
    */
   public function getInt($name) {
      $value = $this->getValue($name);
      if (is_int($value)) {
         return $value;
      } elseif (is_numeric($value)) {
         return intval($value);
      }
      return null;
   }

   /**
    * Retrieves a double value from the configuration.
    *
    * @param string $name Name of the configuration to retrieve.
    * @return double Double value from the config.
    */
   public function getDouble($name) {
      $value = $this->getValue($name);
      if (is_double($value)) {
         return $value;
      } elseif (is_numeric($value)) {
         return doubleval($value);
      }
      return null;
   }

   /**
    * Retrieves a float value from the configuration.
    *
    * @param string $name Name of the configuration to retrieve.
    * @return float Float value from the config.
    */
   public function getFloat($name) {
      $value = $this->getValue($name);
      if (is_float($value)) {
         return $value;
      } elseif (is_numeric($value)) {
         return floatval($value);
      }
      return null;
   }

   /**
    * Retrieves a boolean value from the configuration.
    *
    * @param string $name Name of the configuration to retrieve.
    * @return bool Boolean value from the config.
    */
   public function getBoolean($name) {
      $value = $this->getValue($name);
      if (is_bool($value)) {
         return $value;

      } elseif (is_string($value)) {
         $value = strtolower($value);
         if ($value === "true") {
            return true;
         } elseif ($value === "false") {
            return false;
         }

      } elseif ($value === 1) {
         return true;

      } elseif ($value === 0) {
         return false;
      }
      return null;
   }

   /**
    * Retrieves a configuration value from the configuration.
    *
    * @param string $name Name of the configuration to retrieve.
    * @return Configuration Configuration value from the config.
    */
   public function getConfig($name) {
      $value = $this->getValue($name);
      if ($value instanceof Configuration) {
         return $value;
      }
      return null;
   }

   /**
    * Retrieves an array value from the configuration.
    *
    * @param string $name Name of the array to retrieve.
    * @return array Array value from the config.
    */
   public function getArray($name) {
      $value = $this->getValue($name);
      if (is_array($value)) {
         return $value;
      }
      return null;
   }

   /**
    * Retrieves a value from the configuration. Names with '.' will be split an searched in the configuration.
    *
    * @param string $name Name of the value to retrieve.
    * @return mixed Value from the configuration.
    */
   public function getValue($name) {
      return $this->_getValue($name);
   }

   /**
    * Retrieves a value from the given configuration. Names with '.' will be split an searched in the configuration.
    *
    * @param string $name Name of the configuration value to retrieve.
    * @return mixed Value from the configuration.
    */
   protected function _getValue($name) {
      if (!empty($name)) {
         $names = explode(".", $name);
         $o = $this->values;
         foreach ($names as $nm) {
            if (property_exists($o, $nm)) {
               $o = $o->$nm;
            } else {
               $o = null;
               break;
            }
         }
         if (!isset($o) && isset($this->parentConfiguration)) {
            $o = $this->parentConfiguration->_getValue($name);
         }
         if (is_string($o)) {
            $o = $this->_parseStringValue($o);
         } elseif (is_array($o)) {
            $this->_transformArrayValues($o);
         } elseif (is_object($o)) {
            $o = new Configuration($o);
            $o->parentConfiguration = $this;
         }
         return $o;
      }
      return null;
   }

   /**
    * Transforms the array values by parsing all references.
    *
    * @param array|object|string $value Value to transform.
    */
   protected function _transformArrayValues(&$value) {
      if (is_array($value) || is_object($value)) {
         foreach ($value as $key => $v) {
            if (is_object($v) || is_array($v) || is_string($v)) {
               $this->_transformArrayValues($v);
            }
            if (is_object($value)) {
               $value->$key = $v;

            } else {
               $value[$key] = $v;
            }
         }
      } elseif (is_string($value)) {
         $value = $this->_parseStringValue($value);
      }
   }


   /**
    * Checks if the given key exists in this configuration.
    *
    * @param string $name Name of the key to test.
    * @return bool True if the key exists (in configuration or in parent), false otherwise.
    */
   public function hasKey($name) {
      $names = explode(".", $name);
      if (count($names) === 0) {
         return false;
      }
      $o = $this->values;
      $hasKey = true;
      foreach ($names as $nm) {
         if (property_exists($o, $nm)) {
            $o = $o->$nm;
         } else {
            $hasKey = false;
            break;
         }
      }
      // Now test the parent.
      if (isset($this->parentConfiguration) && !$hasKey) {
         $hasKey = $this->parentConfiguration->_getValue($name);
      }
      return $hasKey;
   }

   /**
    * Parses the given string value by replacing configuration references.
    *
    * @param string $value String value to replace configuration references.
    * @return string Parsed configuration value.
    */
   protected function _parseStringValue($value) {
      if (is_string($value)) {
         $count = @preg_match_all(self::MATCH_CONFIG_VALUES, $value, $matches) ?: 0;
         while ($count-- > 0) {
            $replace = $matches[0][$count];
            $name = $matches[1][$count];
            $object = $this->_getValue($name);
            if (isset($object)) {
               if (is_string($object) || is_float($object) || is_int($object) || is_double($object)) {
                  $value = str_replace($replace, $object, $value);
                  continue;

               } elseif (is_bool($object)) {
                  $value = str_replace($replace, $object ? "true" : "false", $value);
                  continue;
               }
            } elseif ($this->hasKey($name)) {
               // If the key exists, just replace with empty string.
               $value = str_replace($replace, "", $value);
               continue;
            }
            throw new \RuntimeException("Configuration error, no value to replace $replace");
         }
      }
      // Tries filters
      if (($filter = filter_var($value, FILTER_VALIDATE_INT)) !== false) {
         $value = $filter;

      } elseif (($filter = filter_var($value, FILTER_VALIDATE_FLOAT)) !== false) {
         $value = $filter;
      }
      return $value;
   }

   /**
    * Retrieves the keys list of the configuration.
    *
    * @return array List of keys from the configuration.
    */
   public function getKeys() {
      if (empty($this->keys)) {
         $this->keys = array_keys((array)$this->values);
      }
      return $this->keys;
   }

   /**
    * @return string Called class
    */
   protected function whoAmI() {
      return get_called_class();
   }
}