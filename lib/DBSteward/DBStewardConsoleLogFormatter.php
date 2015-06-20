<?php

use Monolog\Logger;

class DBStewardConsoleLogFormatter extends Monolog\Formatter\LineFormatter {
  public function __construct() {
    parent::__construct('%level_name% %message%', null, true, false);
  }
  public function format(array $record) {
    $output = parent::format($record);
    switch ($record['level']) {
      case Logger::WARNING:
        $output = "\033[33m" . $output . "\033[0m";
        break;
      case Logger::ERROR:
        $output = "\033[31m" . $output . "\033[0m";
        break;
    }
    return $output."\n";
  }
}