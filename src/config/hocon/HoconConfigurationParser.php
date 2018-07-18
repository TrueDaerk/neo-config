<?php

namespace Neo\Commons\Config\HOCON;

use Neo\Commons\Config\Configuration;
use Neo\Commons\Config\HOCON\Type\ConfigValue;

class HoconConfigurationParser {
   /* Constants */
   const OBJECT_START = "{",
      OBJECT_END = "}",
      ARRAY_START = "[",
      ARRAY_END = "]",
      KEY_SEPARATORS = [":", "="],
      COMMA = ",",
      NULL_VALUE = "null",
      REFERENCE_INDICATOR = "\${",
      REFERENCE_START = "$",
      REFERENCE_END = "}",
      COMMENT = "//",
      NEW_LINE = "\n",
      NEW_LINE_CHARACTERS = "\n\r",
      WHITESPACES = [
      // Space characters
      "\u{0009}",
      "\u{0020}",
      "\u{00a0}",
      "\u{1680}",
      "\u{2000}",
      "\u{2001}",
      "\u{2002}",
      "\u{2003}",
      "\u{2004}",
      "\u{2005}",
      "\u{2006}",
      "\u{2007}",
      "\u{2008}",
      "\u{2009}",
      "\u{200a}",
      "\u{202f}",
      "\u{205f}",
      "\u{3000}",
      "\u{feff}",
      // Line characters
      "\u{2028}",
      "\u{000a}",
      "\u{000b}",
      "\u{000c}",
      "\u{000d}",
      "\u{001c}",
      "\u{001d}",
      "\u{001e}",
      "\u{001f}",
      // Paragraph characters
      "\u{2029}"
   ],
      UNQUOTED_INVALID = [
      '$',
      '"',
      "{",
      "}",
      "[",
      "]",
      ":",
      "=",
      ",",
      "+",
      "#",
      "`",
      "^",
      "?",
      "!",
      "@",
      "*",
      "&",
      "\\"
   ],
      UNQUOTED_STOP = ["\n", "\r", ","],
      MULTILINE_QUOTES = '"""',
      QUOTE = '"';

   /* Parser fields */
   private $originalContent;
   private $start;
   private $end;
   private $currentIndex;
   private $ignoreLastCharacter = false;
   private $lookoutForArrayEnd = false;

   /**
    * HoconConfigurationParser constructor (private).
    *
    * @param string $content HOCON content for the parser.
    */
   private function __construct($content) {
      $this->setContent($content);
   }

   /**
    * Creates a new configuration from the given HOCON content.
    *
    * @param string $content HOCON content for the parser.
    * @return Configuration Configuration
    * @throws HoconFormatException
    */
   public static function parse($content) {
      if (!is_string($content) || empty($content)) {
         throw new \InvalidArgumentException("\$content must be of string type and not empty.");
      }
      $parser = new HoconConfigurationParser($content);
      $object = $parser->parseContent();
      if (isset($object)) {
         return $object;
      }
      throw new HoconFormatException("Configuration could not be parsed, retrieved object is null");
   }

   /**
    * Sets the content in the current parser.
    *
    * @param string $content HOCON content for the parser.
    */
   private function setContent($content) {
      $this->originalContent = $content;
      $this->start = 0;
      $this->end = mb_strlen($content);
      $this->trimLeft();
      $this->trimRight();
      $this->currentIndex = $this->start;
   }

   /**
    * Parses the hocon content to a config instance.
    *
    * @return HoconParsingObject Configuration object.
    * @throws HoconFormatException
    */
   private function parseContent() {
      $object = new HoconParsingObject("");
      $this->currentIndex = $this->end;
      $end = $this->previousChar();
      $this->currentIndex = $this->start;
      $start = $this->nextChar();
      if ($start === self::OBJECT_START && $end !== self::OBJECT_END) {
         throw new HoconFormatException("Config starts with '{' but does not end with '}'");
      } elseif ($start === self::OBJECT_START) {
         $this->ignoreLastCharacter = true;
      } else {
         $this->currentIndex = $this->start;
      }
      if ($start === self::OBJECT_START) {
         $this->backtrack();
         $this->_parseObject($object);

      } else {
         $this->_parseFields($object);
      }
      return $object;
   }

   /**
    * Reads the next field and writes it in the given object.
    *
    * @param HoconParsingObject $object Object to use to set the field.
    * @param string $keyPrefix Prefix for all keys parsed in this field.
    * @throws HoconFormatException
    */
   private function _parseFields($object, $keyPrefix = "") {
      while ($this->end > $this->currentIndex) {
         $this->_parseField($object, $keyPrefix);
         $this->trimLeft();
         if ($this->nextCharInvisible() === self::OBJECT_END) {
            // Stop. Should not happen, and when it happens, this method was called in _parseObject
            break;
         }
      }
   }

   /**
    * Parses a hocon object in the configuration.
    *
    * @param HoconParsingObject $object Object to write values.
    * @param string $keyPrefix Prefix for all keys parsed in this object.
    * @throws HoconFormatException
    */
   private function _parseObject($object, $keyPrefix = "") {
      if ($this->nextChar() !== self::OBJECT_START) {
         throw new HoconFormatException("Object must start with " . self::OBJECT_START);
      }
      // Test if the next readable character is '}'. If it is, this is an empty object.
      $this->trimLeft();
      if ($this->nextCharInvisible() === self::OBJECT_END) {
         $this->advance();
         $object->registerValue($keyPrefix, new \stdClass());
         return;
      }
      $this->_parseFields($object, $keyPrefix);
      if ($this->nextChar() !== self::OBJECT_END) {
         throw new HoconFormatException("Object must end with " . self::OBJECT_END);
      }
   }

   /**
    * Reads the next field and writes it in the given object.
    *
    * @param HoconParsingObject $object Object to use to set the field.
    * @param string $keyPrefix Prefix for all keys parsed in this field.
    * @throws HoconFormatException
    */
   private function _parseField($object, $keyPrefix = "") {
      $this->trimLeft();
      $this->skipComments();
      $key = $this->_parseKey();
      $dotPos = strpos($key, ".");
      if ($dotPos === 0) {
         throw new HoconFormatException("Property name \"$key\" cannot start with a dot.");
      }
      if (!empty(trim($keyPrefix))) {
         $keyPrefix = "$keyPrefix.$key";
      } else {
         $keyPrefix = $key;
      }
      // Parse the value and register it in the HoconParsingObject.
      $this->_parseValue($object, $keyPrefix);
   }

   /**
    * Parses the next value coming from the configuration and registers it in the HoconParsingObject.
    *
    * @param HoconParsingObject $object Object to use to set the field.
    * @param string $keyPrefix Prefix for all keys parsed in this value (for object values).
    * @throws HoconFormatException
    */
   private function _parseValue($object, $keyPrefix) {
      $this->trimLeft();
      $separator = $this->nextChar();
      if (!in_array($separator, self::KEY_SEPARATORS)) {
         $this->backtrack();
      }
      $this->trimLeft();
      $this->skipComments();
      $nextChar = $this->nextCharInvisible();
      if ($nextChar === self::OBJECT_START) {
         $this->_parseObject($object, $keyPrefix);

      } elseif ($nextChar === self::ARRAY_START) {
         $value = $this->_parseArray();

      } elseif ($nextChar === self::QUOTE) {
         $value = $this->_parseString($object, $keyPrefix);

      } elseif ($nextChar === self::REFERENCE_START) {
         $value = $this->_parseReference($object, $keyPrefix);

      } else {
         $value = $this->_parseUnquotedValue();
      }
      if (isset($value)) {
         $object->registerValue($keyPrefix, $value);
      }
      $this->trimLeft();


      $commaCount = 0;
      while (($ch = $this->nextCharInvisible()) === self::COMMA) {
         $this->advance();
         $this->trimLeft();
         $commaCount++;
      }
      if ($commaCount > 1) {
         throw new HoconFormatException("Only one " . self::COMMA . " is allowed after a value");

      } elseif ($this->lookoutForArrayEnd && $commaCount > 0 && $ch === self::ARRAY_END) {
         throw new HoconFormatException("Array value cannot end with " . self::COMMA);
      }
   }

   /**
    * Parses an unquoted value as string, or, if filtered correctly, as int or double.
    *
    * @return string|double|int Unquoted value.
    */
   private function _parseUnquotedValue() {
      $stopUnquoted = array_merge(self::UNQUOTED_STOP, [self::OBJECT_END]);
      if ($this->lookoutForArrayEnd === true) {
         $stopUnquoted = array_merge($stopUnquoted, [self::ARRAY_END]);
      }
      $buffer = $this->readToOneOf($stopUnquoted);
      if (($position = mb_strpos($buffer, "//")) !== false) {
         // Remove comment part from the unquoted value.
         $buffer = mb_substr($buffer, 0, $position);
      }
      $buffer = trim($buffer);
      if ($buffer === self::NULL_VALUE) {
         $buffer = HoconParsingObject::$NULL_OBJECT;

      } elseif (($filtered = filter_var($buffer, FILTER_VALIDATE_INT)) !== false) {
         $buffer = $filtered;

      } elseif (($filtered = filter_var($buffer, FILTER_VALIDATE_FLOAT)) !== false) {
         $buffer = $filtered;
      }
      return $buffer;
   }

   /**
    * Parses a string or multiline string, depending on the next characters.
    *
    * @param HoconParsingObject $object Object to use to retrieve already defined references.
    * @param string $key Key to register the value.
    * @return ConfigValue ConfigValue representing the string content and possible reference concatenation.
    * @throws HoconFormatException
    */
   private function _parseString($object, $key) {
      $multiline = $this->testSequence(self::MULTILINE_QUOTES);
      if (!$multiline) {
         $this->advance();
      }
      $configValue = new ConfigValue($object, $key);
      $buffer = "";
      while (true) {
         $quickValue = $this->readToSequence($multiline ? self::MULTILINE_QUOTES : self::QUOTE);
         $buffer .= $quickValue;
         $configValue->addValue($buffer);
         if ($multiline) {
            $ch2 = $this->previousCharInvisible();
            if ($ch2 !== mb_substr(self::MULTILINE_QUOTES, -1)) {
               throw new HoconFormatException("Multiline string ended prematurely");
            }
            $this->trimLeft();
            // Parse reference, if it exists.
            if ($this->testSequence(self::REFERENCE_INDICATOR, true)) {
               $configValue->merge($this->_parseReference($object, $key));

            } elseif ($this->testSequence(self::MULTILINE_QUOTES, true)) {
               $configValue->merge($this->_parseString($object, $key));
            }

         } elseif (strpos($buffer, "\n") !== false || strpos($buffer, "\r") !== false) {
            throw new HoconFormatException("Multiline string detected. Use '" . self::MULTILINE_QUOTES . "' for multiline strings");
         }
         break;
      }
      return $configValue;
   }

   /**
    * Parses a reference to the inner configuration (${xxx} values).
    *
    * @param HoconParsingObject $object Object to use to retrieve already defined references.
    * @param string $key Path to the reference value.
    * @return ConfigValue Configuration value containing all strings and references directly after this reference.
    * @throws HoconFormatException
    */
   public function _parseReference($object, $key) {
      if (!$this->testSequence(self::REFERENCE_INDICATOR)) {
         throw new HoconFormatException("Config references must start with " . self::REFERENCE_INDICATOR);
      }
      $this->trimLeft();

      $referenceKey = $this->readToChar(self::REFERENCE_END);
      $this->backtrack();

      $this->trimLeft();
      if ($this->nextChar() !== self::REFERENCE_END) {
         throw new HoconFormatException("Config references must end with " . self::REFERENCE_END);
      }
      $this->trimLeft();
      $value = new ConfigValue($object, $key);
      $value->addValue($referenceKey, true);
      // Test for string or concatenated config reference
      if ($this->testSequence(self::REFERENCE_INDICATOR, true)) {
         $value->merge($this->_parseReference($object, $key));

      } elseif ($this->testSequence(self::MULTILINE_QUOTES, true)) {
         $value->merge($this->_parseString($object, key));
      }
      return $value;
   }

   /**
    * Parses a list of values in the configuration.
    *
    * @return array List of values in the configuration.
    * @throws HoconFormatException
    */
   private function _parseArray() {
      if ($this->nextChar() !== self::ARRAY_START) {
         throw new HoconFormatException("Array must start with " . self::ARRAY_START);
      }
      $this->trimLeft();
      if (($ch = $this->nextCharInvisible()) === self::ARRAY_END) {
         // Empty array.
         $this->advance();
         return [];

      } elseif ($ch === self::COMMA) {
         throw new HoconFormatException(self::COMMA . " is not allowed at the start of an array");
      }
      $arrayValue = [];
      do {
         $object = new HoconParsingObject("");
         $this->lookoutForArrayEnd = true;
         $this->_parseValue($object, "filter");
         $arrayValue[] = $object->getObject()->filter;
         if ($this->nextCharInvisible() === self::COMMA) {
            throw new HoconFormatException(self::COMMA . " not allowed at array end");
         }
      } while ($this->nextCharInvisible() !== self::ARRAY_END);

      if ($this->nextChar() !== self::ARRAY_END) {
         throw new HoconFormatException("Array must start with " . self::ARRAY_START);
      }
      return $arrayValue;
   }

   /**
    * Parses the next key found in the content.
    *
    * @return string Key for the next field.
    * @throws HoconFormatException
    */
   private function _parseKey() {
      $this->trimLeft();
      $nextChar = $this->nextChar();
      if ($nextChar === '"') {
         $key = $this->readToChar('"');
      } else {
         $this->backtrack();
         $key = $this->readToOneOf(self::KEY_SEPARATORS, function ($ch) {
            return $this->isWhitespace($ch) || in_array($ch, [self::OBJECT_START]);
         });
      }
      $this->trimLeft();
      $key = trim($key);
      // Check next character again
      $ch = $this->nextCharInvisible();
      if (in_array($ch, self::KEY_SEPARATORS)) {
         // Everything is fine, nothing to do.
      } elseif ($this->isWhitespace($ch)) {
         $this->trimLeft();
         $okCharacter = [self::ARRAY_START, self::OBJECT_START];
         if (!in_array($this->nextCharInvisible(), $okCharacter)) {
            // Not allowed. Values other than object or array must be separated from the key with : or =
            throw new HoconFormatException("Unexpected character after key, expected one of " . implode(",", $okCharacter));
         }
      }
      if (strpos($key, "\n") !== false || strpos($key, "\r")) {
         throw new HoconFormatException("Invalid key. Key may not contain new line character.");
      }
      return $key;
   }

   /**
    * Retrieves the string read until the next instance of the given character.
    *
    * @param string $char Character to read until.
    * @return string Value to the next given char.
    */
   private function readToChar($char) {
      $buffer = "";
      do {
         if (isset($ch)) {
            $buffer .= $ch;
         }
         $ch = $this->nextChar();
      } while (isset($ch) && $ch !== $char);
      return $buffer;
   }

   /**
    * Reads to one of the given characters. The stopping character should be returned from $this->nextChar(), since after a stop the parser backtracks.
    *
    * @param string|array $characters String containing the characters or an array of characters.
    * @param callable $callback Optional callback function in addition to the characters to check.
    * @return string Content read to one of the given characters.
    */
   private function readToOneOf($characters, $callback = null) {
      if (is_string($characters)) {
         $characters = str_split($characters);
      } elseif (!is_array($characters)) {
         throw new \InvalidArgumentException("Expected array or string as argument in readToOneOf");
      }
      if (count($characters) === 0) {
         // Throw exception, will read to end.
         throw new \InvalidArgumentException("Invalid characters given, empty array not allowed");
      }
      // Check for callable.
      if (isset($callback) && !is_callable($callback)) {
         throw new \InvalidArgumentException("Callback must be callable");

      } elseif (!isset($callback)) {
         $callback = function ($ch) {
            return false;
         };
      }
      $buffer = "";
      do {
         if (isset($ch)) {
            $buffer .= $ch;
         }
         $ch = $this->nextChar();
      } while (isset($ch) && $callback($ch) === false && !in_array($ch, $characters));
      // Backtrack if the character was reached.
      if (in_array($ch, $characters) || $callback($ch) === true) {
         $this->backtrack();
      }
      return $buffer;
   }

   private function readToSequence($sequence) {
      if (!is_string($sequence)) {
         throw new \InvalidArgumentException("Sequence must be a string.");

      } elseif (($length = mb_strlen($sequence)) <= 0) {
         throw new \InvalidArgumentException("Sequence cannot be empty.");

      }
      $buffer = "";
      $backtrackCount = 0;
      $continue = true;
      $firstCharacter = mb_substr($sequence, 0, 1);
      do {
         $buffer .= $this->readToChar($firstCharacter);
         if ($this->previousCharInvisible() === $firstCharacter) {
            $charIndex = 1;
            while ($charIndex < $length && $this->nextChar() === mb_substr($sequence, $charIndex, 1)) {
               $charIndex++;
               $backtrackCount++;
            }
            if ($backtrackCount < $length - 1) {
               while ($backtrackCount-- > 0) {
                  $this->backtrack();
               }
               continue;
            }
            $continue = false;
         } else {
            // Stop, because it read through the end.
            $this->advance();
            $continue = false;
         }
      } while ($continue);
      return $buffer;
   }

   private function testSequence($sequence, $backtrack = false) {
      if (!is_string($sequence)) {
         throw new \InvalidArgumentException("Sequence must be a string");

      } elseif (($length = mb_strlen($sequence)) <= 0) {
         throw new \InvalidArgumentException("Sequence cannot be empty.");

      }
      $backtrackCount = 0;
      $ok = true;
      for ($i = 0; $i < $length; $i++) {
         $backtrackCount++;
         if (mb_substr($sequence, $i, 1) !== $this->nextChar()) {
            $ok = false;
            break;
         }
      }
      if ($backtrack === true || !$ok) {
         while ($backtrackCount-- > 0) {
            $this->backtrack();
         }
      }
      return $ok;
   }

   /**
    * Checks if the given character is a whitespace (definition at https://github.com/lightbend/config/blob/master/HOCON.md#whitespace).
    *
    * @param string $ch Character to check.
    * @return bool True if the character is a whitespace, false otherwise.
    */
   private function isWhitespace($ch) {
      if (isset($ch)) {
         return in_array($ch, self::WHITESPACES);
      }
      return false;
   }

   /**
    * Checks if the given character is a new line (definition at /home/geant/dev/php/neo-config/src/config/hocon/HoconConfigurationParser.php).
    *
    * @param string $ch Character to check.
    * @return bool True if the character is a new line, false otherwise.
    */
   private function isNewline($ch) {
      return $ch === self::NEW_LINE;
   }

   /**
    * Retrieves the previous character of the iteration.
    *
    * @return null|string Previous character of the iteration.
    */
   private function previousChar() {
      $this->backtrack();
      return $this->nextCharInvisible();
   }

   /**
    * Retrieves the previous character of the iteration without moving the current index.
    *
    * @return null|string Previous character of the iteration.
    */
   private function previousCharInvisible() {
      $this->backtrack();
      return $this->nextChar();
   }

   /**
    * Retrieves the next character of the iteration.
    *
    * @return null|string Next character of the iteration.
    */
   private function nextChar() {
      try {
         return $this->nextCharInvisible();
      } finally {
         $this->advance();
      }
   }

   /**
    * Retrieves the next character of the iteration without advancing the current index.
    *
    * @return null|string Next character of the iteration.
    */
   private function nextCharInvisible() {
      if ($this->currentIndex < $this->end) {
         return mb_substr($this->originalContent, $this->currentIndex, 1);
      }
      return null;
   }

   /**
    * Backtracks the current index position.
    */
   private function backtrack() {
      if ($this->currentIndex > $this->start) {
         $this->currentIndex--;
      }
   }

   /**
    * Advances the current index position.
    */
   private function advance() {
      if ($this->end > $this->currentIndex) {
         $this->currentIndex++;
      }
   }


   /**
    * Trims all values that are seen as "blank values" from the left of the current starting position.
    */
   private function trimLeft() {
      while ($this->currentIndex < $this->end && $this->isWhitespace(mb_substr($this->originalContent, $this->currentIndex, 1))) {
         $this->currentIndex++;
      }
   }

   /**
    * Trims all values that are seen as "blank values" from the right of the current end position.
    */
   private function trimRight() {
      for (
         $this->end = mb_strlen($this->originalContent);
         $this->end > 0 && $this->isWhitespace(mb_substr($this->originalContent, $this->end - 1, 1));
         $this->end--
      ) {
         // Nothing to do, just updates end.
      }
   }

   /**
    * @return string Original content used to create the parser.
    */
   public function getContent() {
      return $this->originalContent;
   }

   /**
    * Skips all following comments
    */
   private function skipComments() {
      // Skip comment lines
      while ($this->testSequence(self::COMMENT)) {
         $this->readToOneOf(self::NEW_LINE_CHARACTERS);
         $this->trimLeft();
      }
   }
}