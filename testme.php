<?php
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


global   $wgParser, $wgTitle, $wgOut;
$parserOptions=new ParserOptions();
$parserOutput = $wgParser->parse( "[ftp://ddd ddd]", $wgTitle, $parserOptions);
print $parserOutput->mText;
$parserOutput = $wgParser->parse( "[http://ddd ddd]", $wgTitle, $parserOptions);
print $parserOutput->mText;


?>
