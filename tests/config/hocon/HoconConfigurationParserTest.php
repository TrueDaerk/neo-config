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
      $this->assertEquals("distraction", $config->getValue("vlu.try.list")[1]);

      // Single config does not have this value.
      $this->assertNull($config->getValue("remember.me"));
      $this->assertNull($config->getValue("remember.mes"));

      // Test the array in an array
      $this->assertEquals(["first value", [1, 2, 3]], $config->getValue("array.in-array"));
   }

   /**
    * @throws HoconFormatException
    */
   public function testComments() {
      $config = HoconConfigurationFactory::load(__DIR__ . "/resources/application.conf");
      // Test comment values
      $this->assertEquals("This is a value", $config->getValue("comment-value"));
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
         HoconConfigurationFactory::load(__DIR__ . "/resources/errors/invalid-array-multiple-comma.conf");
         $this->fail("Expected exception.");
      } catch (HoconFormatException $e) {
         $this->assertEquals("Only one , is allowed after a value", $e->getMessage());
      }

      try {
         HoconConfigurationFactory::load(__DIR__ . "/resources/errors/invalid-array-comma-end.conf");
         $this->fail("Expected exception.");
      } catch (HoconFormatException $e) {
         $this->assertEquals("Array value cannot end with ,", $e->getMessage());
      }

      try {
         HoconConfigurationFactory::load(__DIR__ . "/resources/errors/invalid-array-comma-start.conf");
         $this->fail("Expected exception.");
      } catch (HoconFormatException $e) {
         $this->assertEquals(", is not allowed at the start of an array", $e->getMessage());
      }
   }

   /**
    * @throws HoconFormatException
    */
   public function testEquivalents() {
      $config1 = HoconConfigurationParser::parse('{ "foo" : { "a" : 42 }, "foo" : { "b" : 43 } }');
      $config2 = HoconConfigurationParser::parse('{ "foo" : { "a" : 42, "b" : 43 } }');
      $config3 = HoconConfigurationParser::parse('"foo" : { a : 42 }, foo: { "b" : 43 }');
      $config4 = HoconConfigurationParser::parse('foo : { "a" : 42 }, "foo.b": 43');
      $this->assertSame($config1->getValue("foo.a"), $config2->getValue("foo.a"));
      $this->assertSame($config1->getValue("foo.a"), $config3->getValue("foo.a"));
      $this->assertSame($config1->getValue("foo.a"), $config4->getValue("foo.a"));
      $this->assertSame($config1->getValue("foo.b"), $config2->getValue("foo.b"));
      $this->assertSame($config1->getValue("foo.b"), $config3->getValue("foo.b"));
      $this->assertSame($config1->getValue("foo.b"), $config4->getValue("foo.b"));

      $config1 = HoconConfigurationParser::parse('{ "foo" : { "a" : 42 }, "foo" : null, "foo" : { "a" : 43 } }');
      $config2 = HoconConfigurationParser::parse('{ "foo" : { "a" : 43 } }');
      $config3 = HoconConfigurationParser::parse('{ foo.a : 43 }');
      $config4 = HoconConfigurationParser::parse('"foo.a" : 43');
      $this->assertSame($config1->getValue("foo.a"), $config2->getValue("foo.a"));
      $this->assertSame($config1->getValue("foo.a"), $config3->getValue("foo.a"));
      $this->assertSame($config1->getValue("foo.a"), $config4->getValue("foo.a"));
   }

   /**
    * @throws HoconFormatException
    */
   public function testIssue1() {
      $config = HoconConfigurationParser::parse(file_get_contents(__DIR__ . "/resources/issues/1.conf"));
      $this->assertEquals(123, $config->getValue("myKey.value"));
   }
}
