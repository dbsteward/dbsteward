<?php

use Monolog\Logger;
use Colors\Color;

class DBStewardConsoleLogFormatter extends Monolog\Formatter\LineFormatter {
  public function __construct() {
    parent::__construct('%padded_level% %message%', null, true, false);
  }
  public function format(array $record) {
    $record['padded_level'] = str_pad($record['level_name'], 8);
    $record['message'] = str_replace("\n", "\n         ", $record['message']);
    $output = parent::format($record);
    $c = new Color($output);
    switch ($record['level']) {
      case Logger::DEBUG:
        $c->dark_gray();
        break;
      case Logger::WARNING:
        $c->yellow();
        break;
      case Logger::ERROR:
        $c->red();
        break;
    }
    return $c . PHP_EOL;
  }
}