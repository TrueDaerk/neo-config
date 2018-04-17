<?php

namespace Neo\Commons\Config\HOCON;

use Neo\Commons\Config\Configuration;

class HoconConfiguration extends Configuration {

   public function __construct() {
      parent::__construct(null);
   }

   protected function _getValue($name) {
      return parent::_getValue($name);
   }
}