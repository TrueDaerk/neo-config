<?php

namespace Neo\Commons\Config\HOCON;

use PHPUnit\Framework\TestCase;

class HoconParsingObjectTest extends TestCase {

   public function testRegisterKey() {
      $hocon = new HoconParsingObject("");
      $hocon->registerValue("object", 5);
      $this->assertEquals(json_decode('{"object":5}'), $hocon->getObject());

      $hocon->registerValue("object.88", 1);
      $hocon->registerValue("object.82", 2);
      $this->assertEquals(json_decode('{"object":{"88":1,"82":2}}'), $hocon->getObject());

      $hocon->registerValue("object.82", [1, "5", "98"]);
      $this->assertEquals(json_decode('{"object":{"88":1,"82":[1,"5","98"]}}'), $hocon->getObject());

   }

   public function testTestKeyAndGetValue() {
      $hocon = new HoconParsingObject("");
      $hocon->registerValue("object.key", 5);
      $hocon->registerValue("object.kumbaja.er", "honk");
      $hocon->registerValue("object.kumbaja.ds", "Nintendo");
      $hocon->registerValue("object.arg", "beeline");

      $this->assertTrue($hocon->testKey("object.key"));
      $this->assertTrue($hocon->testKey("object.kumbaja"));
      $this->assertTrue($hocon->testKey("object.kumbaja.er"));
      $this->assertTrue($hocon->testKey("object.kumbaja.ds"));
      $this->assertFalse($hocon->testKey("object.kumbaja.k9"));
      $this->assertFalse($hocon->testKey("object.invalid"));

      $this->assertEquals(5, $hocon->getValue("object.key"));
      $this->assertEquals("honk", $hocon->getValue("object.kumbaja.er"));
      $this->assertEquals("Nintendo", $hocon->getValue("object.kumbaja.ds"));
      $this->assertNull($hocon->getValue("object.kumbaja.k9"));

      $hoconChild = new HoconParsingObject("object.", $hocon);
      $hoconChild->registerValue("object.clue", 29);
      $hoconChild->registerValue("clue", 98);
      $hoconChild->registerValue("object.key", "key");

      $this->assertTrue($hoconChild->testKey("object.clue"));
      $this->assertTrue($hoconChild->testKey("object.object.clue"));
      $this->assertTrue($hoconChild->testKey("object.key"));

      $this->assertEquals(98, $hoconChild->getValue("object.clue"));
      $this->assertEquals(29, $hoconChild->getValue("object.object.clue"));
      $this->assertEquals(5, $hoconChild->getValue("object.key"));
      $this->assertEquals("key", $hoconChild->getValue("object.object.key"));
   }
}
