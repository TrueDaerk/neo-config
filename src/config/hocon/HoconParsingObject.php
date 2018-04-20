<?php

namespace Neo\Commons\Config\HOCON;

use Neo\Commons\Config\Configuration;
use Neo\Commons\Config\HOCON\Type\ConfigValue;

class HoconParsingObject extends Configuration {
   /**
    * Use this value to make a new stdClass() value when using registerValue.
    *
    * @var object
    */
   private static $EMPTY_OBJECT;
   /**
    * Use this value to initialize a null value when using registerValue. null values are replaced with $EMPTY_OBJECT by default.
    *
    * @var object
    */
   public static $NULL_OBJECT;
   private $baseKey;
   /**
    * @var HoconParsingObject
    */
   private $parent;

   /**
    * Initializes the values for $EMPTY_OBJECT and $NULL_OBJECT.
    */
   private static function _initialize() {
      if (!isset(self::$EMPTY_OBJECT)) {
         self::$EMPTY_OBJECT = new \stdClass();
         self::$NULL_OBJECT = new \stdClass();
      }
   }

   /**
    * HoconParsingObject constructor.
    *
    * @param string $keyBase Base of the key. When using testKey or getValue, the baseKey will be removed for the search, since it is likely
    * that the base key is <b>not</b> part of this object.
    * @param HoconParsingObject $parent Optional parent for the parsing object. Will be used as backup in testKey and getValue.
    */
   public function __construct($keyBase, $parent = null) {
      parent::__construct();
      self::_initialize();
      $this->baseKey = $keyBase;
      $this->parent = $parent;
      $this->values = new \stdClass();
   }

   /**
    * Registers a value for a key and created the parent object, if it does not exist or if it is not an object.
    *
    * @param string $key Key to create and initialize with the given value.
    * @param mixed $value Any value to use for the key.
    * The value ::$EMPTY_OBJECT will be initialized with new stdClass(), the value ::$NULL_OBJECT will be initialized with null.
    */
   private function registerKey($key, $value = null) {
      $route = explode(".", $key);
      $lastKey = array_pop($route);
      $curObj = $this->values;
      if (count($route) > 0) {
         foreach ($route as $key) {
            if (!property_exists($curObj, $key) || !is_object($curObj->$key)) {
               $curObj->$key = new \stdClass();
            }
            $curObj = $curObj->$key;
         }
      }
      if ($value === self::$NULL_OBJECT) {
         $curObj->$lastKey = null;

      } elseif ($value === self::$EMPTY_OBJECT) {
         $curObj->$lastKey = new \stdClass();

      } else {
         $curObj->$lastKey = $value;
      }
   }

   /**
    * Tests if the given key exists in the current parsing object.
    *
    * @param string $key Key to test in the parsing object.
    * @return boolean True if the key exists in this object (or parent).
    */
   public function testKey($key) {
      $originalKey = $key;
      if (mb_strlen($this->baseKey) > 0 && mb_strpos($key, $this->baseKey) === 0) {
         $key = mb_substr($key, mb_strlen($this->baseKey));
      }
      $route = explode(".", $key);
      $curObject = $this->values;
      $ok = true;
      foreach ($route as $key) {
         if (is_object($curObject) && property_exists($curObject, $key)) {
            $curObject = $curObject->$key;
            continue;
         }
         $ok = false;
         break;
      }
      if (!$ok && isset($this->parent)) {
         $ok = $this->parent->testKey($originalKey);
      }
      return $ok;
   }

   /**
    * Tests if the given key exists in the current parsing object.
    *
    * @param string $key Key to test in the parsing object.
    * @return mixed Value stored in the current parsing object (or parent).
    * @throws HoconFormatException
    */
   protected function _getValue($key) {
      $originalKey = $key;
      if (mb_strlen($this->baseKey) > 0 && mb_strpos($key, $this->baseKey) === 0) {
         $key = mb_substr($key, mb_strlen($this->baseKey));
      }
      $route = explode(".", $key);
      $curObject = $this->values;
      $ok = true;
      foreach ($route as $key) {
         if (is_object($curObject) && property_exists($curObject, $key)) {
            $curObject = $curObject->$key;
            continue;
         }
         $ok = false;
         $curObject = null;
         break;
      }
      if ($ok) {
         if ($curObject instanceof ConfigValue) {
            $curObject = $curObject->compile();

         } elseif (is_array($curObject)) {
            // Iterate over values and compile the ConfigValue instances
            foreach ($curObject as &$object) {
               if ($object instanceof ConfigValue) {
                  // First update the configuration of the ConfigValue.
                  $object->setConfig($this);
                  $object = $object->compile();
               }
            }

         } elseif ((!$curObject instanceof Configuration) && is_object($curObject)) {
            $parsingObject = new HoconParsingObject($key, $this);
            $parsingObject->values = $curObject;
            $curObject = $parsingObject;
         }
      } elseif (isset($this->parent)) {
         $curObject = $this->parent->getValue($originalKey);
      }
      return $curObject;
   }

   /**
    * Adds the given value as a key to the HoconParsingObject.
    * When the given value is null, an empty object is created. To register null values, please use HoconParsingObject::$NULL_OBJECT.
    *
    * @param string $key Name of the key to register. Will create all parent objects.
    * @param mixed $value Value to set for the key.
    */
   public function registerValue($key, $value) {
      $this->registerKey($key, isset($value) ? $value : self::$EMPTY_OBJECT);
   }

   /**
    * Retrieves the object created with the registered keys.
    *
    * @return object Object created by registering keys.
    */
   public function getObject() {
      return $this->values;
   }

   /**
    * @return HoconConfiguration Configuration created from this object.
    */
   public function getConfiguraion() {
      return $this->configuraion;
   }
}

// Create an instance of HoconParsingObject, so that the values $EMPTY_OBJECT and $NULL_OBJECT are always initialized.
new HoconParsingObject("");