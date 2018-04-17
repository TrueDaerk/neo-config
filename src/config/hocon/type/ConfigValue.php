<?php

namespace Neo\Commons\Config\HOCON\Type;

use Neo\Commons\Config\HOCON\HoconConfiguration;

class ConfigValue {
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


   public function compileString() {

   }

   public function compileInteger() {

   }

   public function compileDouble() {

   }
}