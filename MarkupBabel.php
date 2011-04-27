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

require_once("MarkupBabelProcessor.php");
require_once('extensions/geshi/geshi.php');

function wf_callback_generic($str,$mode)
{
    global $MarkupBabel;
    return $MarkupBabel->process($str, $mode);
}

function wf_callback_geshi($str,$lang)
{
    $geshi = new GeSHi($str, $lang);
    $code = $geshi->parse_code();
    $code = preg_replace("/(^\s*<pre[^<>]*>\s*)&nbsp;\n|\n&nbsp;\s*(<\/pre>)/is", '\1\2', $code);
    return $code;
}

settype($MarkupBabel, 'object');
$wgExtensionFunctions[] = 'MarkupBabelRegister';
$wgHooks['ArticleViewHeader'][] = 'MarkupBabel::AutoHighlight';

function MarkupBabelRegister()
{
    global $MarkupBabel;
    $MarkupBabel = new MarkupBabel();
    $MarkupBabel->register();
}

if (!$wgAutoHighlightExtensions)
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

    function MarkupBabel()
    {
        global $IP, $wgScriptPath;

        $this->BaseDir="$IP/images/generated";
        $this->BaseDir=str_replace("\\", "/", $this->BaseDir);
        $this->BaseURI="$wgScriptPath/images/generated";
    }

    function register()
    {
        global $wgParser;
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
        );
        foreach ($arr as $strKey => $strVal)
        {
            $code = 'return wf_callback_generic($str,"'.$strVal.'");';
            $wgParser->setHook($strKey, create_function('$str', $code));
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
            $wgParser->setHook('code-'. $lang, create_function('$str', $code));
        }
    }

    function AutoHighlight($article, &$outputDone, &$useParserCache)
    {
        global $wgAutoHighlightExtensions, $wgOut;
        if ($wgAutoHighlightExtensions &&
            preg_match('!\.('.implode('|', array_keys($wgAutoHighlightExtensions)).')$!u', $article->getTitle()->getText(), $m))
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

    function process($strSrc, $strMode)
    {
        $strHash = md5($strSrc . $strMode);

        $oldumask = umask(0);
        $strDir   = $this->BaseDir;
        if (!is_dir($strDir)) mkdir($strDir, 0777);
        $strURI   = $this->BaseURI;

        if (!is_dir($strDir)) mkdir($strDir, 0777);
        $strDir   .= "/" . $strMode;
        $strURI   .= "/" . $strMode;
        if (!is_dir($strDir)) mkdir($strDir, 0777);
        $strDir   .= "/" . $strHash{0};
        $strURI   .= "/" . $strHash{0};
        if (!is_dir($strDir)) mkdir($strDir, 0777);
        $strDir   .= "/" . substr($strHash, 0, 2);
        $strURI   .= "/" . substr($strHash, 0, 2);
        if (!is_dir($strDir)) mkdir($strDir, 0777);
        $strDir   .= "/" . $strHash  ;
        $strURI   .= "/" . $strHash  ;
        if (!is_dir($strDir)) mkdir($strDir, 0777);
        umask($oldumask);
        $strURI   .= "/";
        $strLocalFile = "$strMode.source";
        $strFile   = $strDir . "/" . $strLocalFile;

        if ($obj = fopen($strFile, 'w'))
        {
            fwrite($obj, $strSrc);
            fclose($obj);
        }

        $processor = new MarkupBabelProcessor($strFile, $strMode, $strURI);
        return $processor->rendme();
    }

    function rebuild_mode($mode)
    {
        foreach ($this->globr($this->BaseDir."/".$mode, "{$mode}.source") as $file)
        {
            $arr = split("/", $file);
            $basefile = $arr[count($arr)-1];
            $uri = str_replace($this->BaseDir, $this->BaseURI, $file);
            $uri = str_replace($basefile, "", $uri);
            if (file_exists("{$file}.cache"))
                unlink("{$file}.cache");
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
}