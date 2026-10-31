<?php

use Colors\Color;
use Monolog\Formatter\LineFormatter;
use Monolog\Level;
use Monolog\LogRecord;

/**
 * Console log line formatter: "LEVEL    message", with continuation lines
 * indented under the message and the line colorized by severity.
 */
class DBStewardConsoleLogFormatter extends LineFormatter {
  public function __construct() {
    parent::__construct('%extra.padded_level% %message%', null, true, false);
  }

  public function format(LogRecord $record): string {
    $record = $record->with(
      message: str_replace("\n", "\n         ", $record->message),
      extra: $record->extra + ['padded_level' => str_pad($record->level->getName(), 8)]
    );
    $output = parent::format($record);
    if (!dbsteward::$ENABLE_COLOR) {
      return $output . PHP_EOL;
    }
    $c = new Color($output);
    match ($record->level) {
      Level::Debug => $c->dark_gray(),
      Level::Warning => $c->yellow(),
      Level::Error => $c->red(),
      default => null,
    };
    return $c . PHP_EOL;
  }
}
