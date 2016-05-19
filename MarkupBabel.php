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

$wgImgAuthUrlPathMap['/generated/'] = 'mwstore://local-backend/local-public/generated/';

function wf_callback_geshi($str,$lang)
{
    $geshi = new GeSHi($str, $lang);
    // Remove font override
    $geshi->set_overall_style('');
    $geshi->set_code_style('margin:0; padding:0; background:none; vertical-align:top;');
    $code = $geshi->parse_code();
    $code = preg_replace("/(^\s*<pre[^<>]*>\s*)&nbsp;\n|\n&nbsp;\s*(<\/pre>)/is", '\1\2', $code);
    return $code;
}

$wgExtensionFunctions[] = 'MarkupBabelRegister';
$wgHooks['ArticleViewHeader'][] = 'MarkupBabel::AutoHighlight';
$wgHooks['ArticlePurge'][] = 'MarkupBabel::ArticlePurge';
$wgHooks['ParserFirstCallInit'][] = 'MarkupBabel::register';
$wgAutoloadClasses['GeSHi'] = dirname(dirname(__FILE__)).'/SyntaxHighlight_GeSHi/geshi/geshi.php';
$wgAutoloadClasses['MarkupBabelProcessor'] = dirname(__FILE__).'/MarkupBabelProcessor.php';

function MarkupBabelRegister()
{
    global $MarkupBabel;
    $MarkupBabel = new MarkupBabel();
}

if (!isset($wgAutoHighlightExtensions))
{
    // Pages with these extensions will get automatic code highlighting
    // (if there is no <source> or <nowiki> tag in the beginning)
    $wgAutoHighlightExtensions = array(
        'js'    => 'javascript',
        'css'   => 'css',
        'sh'    => 'bash',
        'diff'  => 'diff',
        'patch' => 'diff',
        'htm'   => 'html4strict',
        'html'  => 'html4strict',
        'xml'   => 'xml',
        'svg'   => 'xml',
    );
}

class MarkupBabel
{
    var $BaseDir = "";

    function __construct()
    {
        global $wgUploadDirectory;
        $this->generatedSubDir = 'generated';
        $this->BaseDir = "$wgUploadDirectory/{$this->generatedSubDir}";
        $this->BaseDir = str_replace("\\", "/", $this->BaseDir);
    }

    static function register($parser)
    {
        global $wgUseTex;
        $arr = array (
            'amsmath'     => 'amsmath',
            'm'           => 'amsmath',
            'latex'       => 'latex',
            'circo'       => 'circo',
            'circo-print' => 'circo_print',
            'fdp'         => 'fdp',
            'fdp-print'   => 'fdp_print',
            'graphviz'    => 'graph',
            'graph'       => 'graph',
            'graph-print' => 'graph_print',
            'neato'       => 'neato',
            'neato-print' => 'neato_print',
            'twopi'       => 'twopi',
            'twopi-print' => 'twopi_print',
            'pic-svg'     => 'pic_svg',
            'pic-svg-gif' => 'pic_svg',
            'plot'        => 'plot',
            'hbarchart'   => 'hbarchart',
            'vbarchart'   => 'vbarchart',
            'umlet'       => 'umlet',
            'umlgraph'    => 'umlgraph',
            'umlsequence' => 'umlsequence',
            'gantt'       => 'gantt',
        );
        if (empty($wgUseTex))
        {
            // Also enable standard MediaWiki's <math> tag when $wgUseTex is disabled
            $arr['math'] = 'amsmath';
        }
        foreach ($arr as $tag => $handler)
        {
            $code = 'global $MarkupBabel; return $MarkupBabel->process($text, "'.$handler.'", $args);';
            $parser->setHook($tag, create_function('$text, $args', $code));
        }

        $langArray = array(
            "actionscript","ada","apache","asm","asp",
            "bash","c","c_mac","caddcl","cadlisp","cpp","csharp","css",
            "delphi","html4strict","java","javascript","lisp","lua",
            "mpasm","nsis","objc","oobas","oracle8",
            "pascal","perl","php","php-brief","python",
            "qbasic","smarty","sql",
            "vb","vbnet","visualfoxpro",
            "xml");

        foreach ($langArray as $lang)
        {
            $code = 'return wf_callback_geshi($str,"'.$lang.'");';
            $parser->setHook('code-'. $lang, create_function('$str', $code));
        }

        return true;
    }

    static function AutoHighlight($article, &$outputDone, &$useParserCache)
    {
        global $wgAutoHighlightExtensions, $wgOut;
        $ns = $article->getTitle()->getNamespace();
        if ($wgAutoHighlightExtensions && $ns != NS_FILE &&
            preg_match('!\.('.implode('|', array_keys($wgAutoHighlightExtensions)).')$!u', $article->getTitle()->getText(), $m) &&
            ($article->exists() || $ns == NS_MEDIAWIKI))
        {
            $text = $article->getContent();
            if (!preg_match('#^\s*<(source|code-|nowiki)#is', $text))
            {
                $lang = $wgAutoHighlightExtensions[$m[1]];
                $outputDone = true;
                $wgOut->addHTML(wf_callback_geshi($text, $lang));
                return false;
            }
        }
        return true;
    }

    // Main entry point: generates path and call MarkupBabelProcessor
    function process($strSrc, $strMode, $args)
    {
        global $wgUploadPath;
        $strHash = md5($strSrc . $strMode . var_export($args, true));

        $rel = '/' . $strMode . '/' . $strHash{0} . '/' . substr($strHash, 0, 2) . '/' . $strHash;
        $strDir = $this->BaseDir . $rel;
        $strURI = '$URI'.$rel.'/';

        $oldumask = umask(0);
        if (!file_exists($strDir))
            mkdir($strDir, 0777, true);
        umask($oldumask);

        $strLocalFile = "$strMode.source";
        $strFile = $strDir . "/" . $strLocalFile;

        $processor = new MarkupBabelProcessor($strSrc, $strFile, $strMode, $strURI);
        $html = $processor->rendme($args);

        // Real URL is substituted just before output, to allow using different script paths
        // and absolute URLs via hacking $wgScriptPath
        $html = str_replace($strURI, "{$wgUploadPath}/{$this->generatedSubDir}$rel/", $html);
        return $html;
    }

    // Rebuild cache for $mode
    function rebuild_mode($mode)
    {
        foreach ($this->globr($this->BaseDir."/".$mode, "{$mode}.source") as $file)
        {
            $basefile = basename($file);
            $uri = str_replace($this->BaseDir, '$URI', $file);
            $uri = str_replace($basefile, "", $uri);
            if (file_exists("$file.cache"))
                unlink("$file.cache");
            print "$file\n";
            ob_flush();
            flush();
            $processor = new MarkupBabelProcessor($file, $mode, $uri);
            $processor->rendme();
        }
    }

    function rebuild_all()
    {
        foreach (glob($this->BaseDir."/*") as $dir)
        {
            $mode = basename($dir);
            $this->rebuild_mode($mode);
        }
    }

    function globr($sDir, $sPattern, $nFlags = NULL)
    {
        $sDir = escapeshellcmd($sDir);
        $aFiles = glob("$sDir/$sPattern", $nFlags);

        foreach (glob("$sDir/*", GLOB_ONLYDIR) as $sSubDir)
        {
            $aSubFiles = $this->globr($sSubDir, $sPattern, $nFlags);
            $aFiles = array_merge($aFiles, $aSubFiles);
        }
        return $aFiles;
    }

    static function ArticlePurge($page)
    {
        global $wgParser, $wgUser;
        $wgParser->parse($page->getText(), $page->getTitle(), ParserOptions::newFromUser($wgUser));
        return true;
    }
}
