<?php

namespace Neo\Commons\Config\HOCON;

class HoconParsingObject {
   /**
    * DO NOT USE BEFORE INITIALIZING AT LEAST ONE TYPE WITH CONSTRUCTOR.
    *
    * @var object
    */
   public static $EMPTY_OBJECT;
   /**
    * DO NOT USE BEFORE INITIALIZING AT LEAST ONE TYPE WITH CONSTRUCTOR.
    *
    * @var object
    */
   public static $NULL_OBJECT;
   private $internalObject;

   private static function _initialize() {
      if (!isset(self::$EMPTY_OBJECT)) {
         self::$EMPTY_OBJECT = new \stdClass();
         self::$NULL_OBJECT = new \stdClass();
      }
   }

   public function __construct() {
      self::_initialize();
      $this->internalObject = new \stdClass();
   }

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

   public function registerValue($key, $value) {
      $this->registerKey($key, isset($value) ? $value : self::$EMPTY_OBJECT);
   }

   public function getObject() {
      return $this->internalObject;
   }
}