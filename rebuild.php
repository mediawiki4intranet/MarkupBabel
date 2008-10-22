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

define( 'MEDIAWIKI', true );
        
require_once("../../LocalSettings.php");
require_once( "$IP/includes/ProfilerStub.php" );
require_once( "$IP/includes/Setup.php" );
require_once( "$IP/includes/Defines.php" );
require_once( "$IP/includes/StubObject.php");
require_once( "$IP/includes/AutoLoader.php" );
require_once( "$IP/includes/MagicWord.php" );
require_once( "$IP/includes/Namespace.php" );
require_once( "$IP/includes/GlobalFunctions.php" );

global $wgScriptPath;
$strURI   = "$wgScriptPath/images/generated";

require_once("MarkupBabel.php");        

# Initialize MediaWiki base class
require_once( "$IP/includes/Wiki.php" );
$mediaWiki = new MediaWiki();

$MarkupBabel = new MarkupBabel();
$MarkupBabel->rebuild_all();
?>
