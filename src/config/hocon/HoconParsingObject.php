<?php

namespace Neo\Commons\Config\HOCON;

class HoconParsingObject {
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
   private $internalObject;

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
    */
   public function __construct() {
      self::_initialize();
      $this->internalObject = new \stdClass();
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
      $curObj = $this->internalObject;
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
      return $this->internalObject;
   }
}

// Create an instance of HoconParsingObject, so that the values $EMPTY_OBJECT and $NULL_OBJECT are always initialized.
new HoconParsingObject();