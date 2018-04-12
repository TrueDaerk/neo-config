<?php

namespace Neo\Commons\Config\HOCON;

use PHPUnit\Framework\TestCase;

class HoconParsingObjectTest extends TestCase {

   public function testRegisterKey() {
      $hocon = new HoconParsingObject();
      $hocon->registerValue("object", 5);
      $this->assertEquals(json_decode('{"object":5}'), $hocon->getObject());

      $hocon->registerValue("object.88", 1);
      $hocon->registerValue("object.82", 2);
      $this->assertEquals(json_decode('{"object":{"88":1,"82":2}}'), $hocon->getObject());

      $hocon->registerValue("object.82", [1, "5", "98"]);
      $this->assertEquals(json_decode('{"object":{"88":1,"82":[1,"5","98"]}}'), $hocon->getObject());

   }
}
