<?php

namespace Neo\Commons\Config;

class ConfigurationFactory {

   /**
    * Loads a configuration from the given file.
    *
    * @param string $file Configuration file to load.
    * @return Configuration Configuration loaded from the given file.
    */
   public static final function load($file) {
      if (file_exists($file) && is_readable($file)) {
         return new Configuration(file_get_contents($file));
      }
      return null;
   }

   /**
    * Loads all configuration files from the given directory.
    *
    * @param string $directory Directory to load configuration files.
    * @return Configuration Configuration loaded from the given directory.
    */
   public static final function loadDirectory($directory) {
      if (is_dir($directory) && is_readable($directory)) {
         $files = scandir($directory);
         /**
          * @var Configuration $configuration
          */
         $configuration = null;
         foreach ($files as $file) {
            if (strtolower(mb_substr($file, -5)) !== ".json") {
               // Skip wrong file types
               continue;
            }
            if (is_file($directory . "/" . $file)) {
               $config = ConfigurationFactory::load($directory . "/" . $file);
               if (isset($configuration)) {
                  $config->withFallback($configuration);
                  $configuration = $config;
               } else {
                  $configuration = $config;
               }
            }
         }
         return $configuration;
      }
      return null;
   }
}