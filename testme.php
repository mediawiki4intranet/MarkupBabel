<?php
/*
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * @author Stas Fomin <stas-fomin@yandex.ru>
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License 2.0 or later
 */

require_once("../../maintenance/commandLine.inc");
require_once("MarkupBabel.php");

$parserOptions = new ParserOptions();
$parserOutput = $wgParser->parse( "[ftp://ddd ddd]", $wgTitle, $parserOptions);
print $parserOutput->mText;
$parserOutput = $wgParser->parse( "[http://ddd ddd]", $wgTitle, $parserOptions);
print $parserOutput->mText;

?>
