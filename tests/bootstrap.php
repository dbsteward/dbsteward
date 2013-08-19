<?php

if (!class_exists('PHPUnit_Extensions_OutputTestCase')) {
  class_alias('PHPUnit_Framework_TestCase', 'PHPUnit_Extensions_OutputTestCase', TRUE);
}