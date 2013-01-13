<?php
/**
 * Preprocess XML to expand nodes where appropriate.  Base clase for shared
 * functionality.
 * 
 * The purposes of this class is to do alterations to the XML data before it
 * is processed into SQL/diffed/or anything else.
 * 
 * At this time, there is a lot of functionality currenlty in xml_parser that
 * should be migrated into driver-specific parsers.  This class provies a
 * framework to start doing that.
 *
 * @package DBSteward
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Bill Moran <william.moran@intermedix.com>
 */

class sql99_xml_parser {
  public static function process(&$doc) {
    return;
  }
}

?>
