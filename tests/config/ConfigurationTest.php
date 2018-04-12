<?php

namespace Neo\Commons\Config;

use PHPUnit\Framework\TestCase;

class ConfigurationTest extends TestCase {

   public function testSimpleConfig() {
      $config = new Configuration([
         "key" => "value",
         "key-with" => [
            "children" => "yes",
            "array" => ["a", "b", "c"],
            "use" => "Hehe \${key}",
            "boolval" => true,
            "boolvalstr" => "true",
            "boolvalnum" => 1,
            "boolval_false" => false,
            "boolval_falsestr" => "false",
            "boolval_falsenum" => 0
         ]
      ]);
      $this->assertNotNull($config);
      $this->assertEquals("value", $config->getValue("key"));
      $this->assertEquals("yes", $config->getValue("key-with.children"));
      $this->assertEquals(["a", "b", "c"], $config->getValue("key-with.array"));
      $this->assertEquals("Hehe value", $config->getValue("key-with.use"));
      $this->assertTrue($config->getBoolean("key-with.boolval"));
      $this->assertTrue($config->getBoolean("key-with.boolvalstr"));
      $this->assertTrue($config->getBoolean("key-with.boolvalnum"));
      $this->assertFalse($config->getBoolean("key-with.boolval_false"));
      $this->assertFalse($config->getBoolean("key-with.boolval_falsestr"));
      $this->assertFalse($config->getBoolean("key-with.boolval_falsenum"));
      $this->assertNull($config->getBoolean("key-with.children"));
      $this->assertNull($config->getValue("key-with.nonexisting"));
   }

   public function testConfigFactory() {
      $config = ConfigurationFactory::load(__DIR__ . "/resources/config_unexisting.json");
      $this->assertNull($config);

      $config = ConfigurationFactory::load(__DIR__ . "/resources/config.json");
      $this->assertNotNull($config);
      $this->assertEquals("json-config", $config->getString("simple"));
      $subConfig = $config->getConfig("parent");
      $this->assertNotNull($subConfig);
      $this->assertInstanceOf(Configuration::class, $subConfig);
      // Should be null, because object is not string
      $this->assertNull($config->getString("parent"));
      $this->assertEquals("Should contain value of \"simple\": json-config and 1", $config->getString("parent.substitute"));
      $this->assertEquals("Should contain value of \"simple\": json-config and 1", $subConfig->getString("substitute"));

      $this->assertTrue($config->getValue("overwrite"));
      $this->assertEquals("yes", $config->getValue("overwrite-object.hello"));
      $this->assertEquals("yes", $config->getValue("overwrite-object.hello_"));

      // Comes from second config, should not be recognized.
      $this->assertNull($subConfig->getString("another"));
   }

   public function testConfigFactoryLoadDir() {
      $config = ConfigurationFactory::load(__DIR__ . "/resources/non.json");
      $this->assertNull($config);

      $config = ConfigurationFactory::loadDirectory(__DIR__ . "/resources");
      $this->assertNotNull($config);
      $this->assertEquals("json-config", $config->getString("simple"));
      $subConfig = $config->getConfig("parent");
      $this->assertNotNull($subConfig);
      $this->assertInstanceOf(Configuration::class, $subConfig);
      // Should be null, because object is not string
      $this->assertNull($config->getString("parent"));
      $this->assertEquals("Should contain value of \"simple\": json-config and 1", $config->getString("parent.substitute"));
      $this->assertEquals("Should contain value of \"simple\": json-config and 1", $subConfig->getString("substitute"));

      $this->assertInstanceOf(Configuration::class, $config->getValue("overwrite"));
      $this->assertEquals("image", $config->getValue("overwrite.know"));
      $this->assertEquals("yes", $config->getValue("overwrite-object.hello"));
      $this->assertEquals("no", $config->getValue("overwrite-object.hello_"));

      // Comes from second config, should be recognized.
      $this->assertEquals("file", $config->getString("another"));

      // Should be found.
      $this->assertEquals("Try replace: json-config", $config->getString("try"));

      $this->assertEquals(["simple", "parent", "overwrite", "overwrite-object", "another", "better", "try"], $config->getKeys());
      $this->assertEquals(["with-list", "qubit", "substitute"], $subConfig->getKeys());
      $this->assertEquals([4, 1], $config->getArray("overwrite-object.array"));
   }

   public function testReplaceInOtherConfig() {
      $config = ConfigurationFactory::load(__DIR__ . "/resources/config.json");
      $config = (new Configuration([
         "value" => "value",
         "with" => "\${simple}"
      ]))->withFallback($config);

      $this->assertEquals("value", $config->getString("value"));
      $this->assertEquals("json-config", $config->getString("with"));

      $config = (new Configuration([
         "value" => "Will be local: \${simple}",
         "simple" => "NO!"
      ]))->withFallback($config);
      $this->assertEquals("Will be local: NO!", $config->getString("value"));
   }
}