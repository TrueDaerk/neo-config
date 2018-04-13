<?php

namespace Neo\Commons\Config\HOCON;

use PHPUnit\Framework\TestCase;

class HoconConfigurationParserTest extends TestCase {

   /**
    *
    * @throws HoconFormatException
    */
   public function testParse() {
      $config = HoconConfigurationFactory::load(__DIR__ . "/resources/application.conf");
      $this->assertSame("This has
Multiline 5 yearsMay", $config->getValue("value.multiline"));
      $this->assertSame(5, $config->getValue("value.stronger"));
      $this->assertSame("another string", $config->getValue("value.string"));
      $this->assertSame(5.89, $config->getValue("value.float"));
      $this->assertEquals([
         "i hope",
         "distraction",
         5.89,
         json_decode('{"shelter":"island"}')
      ], $config->getValue("vlu.try.list"));

      // Single config does not have this value.
      $this->assertNull($config->getValue("remember.me"));
      $this->assertNull($config->getValue("remember.mes"));

   }

   /**
    *
    * @throws HoconFormatException
    */
   public function testParseDirectory() {
      $config = HoconConfigurationFactory::loadDirectory(__DIR__ . "/resources/");
      $this->assertSame("This has
Multiline 13May", $config->getValue("value.multiline"));
      $this->assertSame(5, $config->getValue("value.stronger"));
      $this->assertSame("another string", $config->getValue("value.string"));
      $this->assertSame(5.89, $config->getValue("value.float"));
      $this->assertEquals(["i hope", "distraction", 5.89, json_decode('{"shelter":"island"}')], $config->getValue("vlu.try.list"));

      $this->assertNull($config->getValue("value.null"));
      $this->assertSame("Null will be used as reference: ", $config->getValue("use.nullAsReference"));

      // Directory loaded, so value exists.
      $this->assertSame("Stella", $config->getValue("remember.me"));
      // Value does not exist, because notloaded.config does not end with .conf
      $this->assertNull($config->getValue("remember.mes"));
   }

   /**
    *
    * @throws HoconFormatException
    */
   public function testParseAnyFile() {
      $config = HoconConfigurationFactory::load(__DIR__ . "/resources/notloaded.config");
      $this->assertNull($config->getValue("vlu.try.list"));

      // Value does exist, because notloaded.config was loaded directly
      $this->assertSame("AAAA", $config->getValue("remember.mes"));
   }

   public function testInvalidArrays() {
      try {
         HoconConfigurationFactory::load(__DIR__ . "/resources/invalid-array-multiple-comma.conf");
         $this->fail("Expected exception.");
      } catch (HoconFormatException $e) {
         $this->assertEquals("Only one , is allowed after a value", $e->getMessage());
      }

      try {
         HoconConfigurationFactory::load(__DIR__ . "/resources/invalid-array-comma-end.conf");
         $this->fail("Expected exception.");
      } catch (HoconFormatException $e) {
         $this->assertEquals("Array value cannot end with ,", $e->getMessage());
      }

      try {
         HoconConfigurationFactory::load(__DIR__ . "/resources/invalid-array-comma-start.conf");
         $this->fail("Expected exception.");
      } catch (HoconFormatException $e) {
         $this->assertEquals(", is not allowed at the start of an array", $e->getMessage());
      }
   }
}
