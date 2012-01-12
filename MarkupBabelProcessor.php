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

require_once "$IP/includes/ImageFunctions.php";

class MarkupBabelProcessor
{
    var $Content = '';
    var $Source = '';
    var $Filename = '';
    var $Hash = '';
    var $Mode = '';
    var $Cache = '';
    var $URI = '';

    function MarkupBabelProcessor($content, $path, $mode, $uri)
    {
        $this->Content = $content;
        $this->Source = $path;
        $this->Filename = basename($path);
        $this->BaseDir = dirname($path);
        $this->Mode = $mode;
        $this->Cache = $path.".cache";
        $this->URI = $uri;
        $this->parserOptions = new ParserOptions();
        $this->dotpath = "";
        $this->gnuplotpath = "";
        $this->texpath = "";
        $this->inkscapepath = "";
        if (wfIsWindows())
        {
            global $IP;
            $this->dotpath = realpath($IP."/../../app/graphviz/bin") . "/";
            $this->gnuplotpath = realpath($IP."/../../app/gnuplot/bin")."/p";
            //$this->texpath = realpath($IP."/../../app/tex/miktex/bin") . "/";
            $this->texpath = realpath($IP."/../../app/xetex/bin/win32") . "/";
            $this->inkscapepath = realpath($IP."/../../app/inkscape") . "/";
            $this->umlgraphpath = realpath($IP."/../../app/umlgraph/bin" . "/");
        }
    }

    /**
     * Format the error string to HTML, including context information.
     */
    function format_error($err)
    {
        $str = "<div class=\"screenonly\">'$this->Mode' generation for '$this->Source' failed:<pre>$err</pre></div>\n";
        return $str;
    }

    /**
     * Main function: render and return HTML-content.
     */
    function rendme($args)
    {
        global $wgRequest;
        if (file_exists($this->Cache) && $wgRequest->getVal('action') !== 'purge')
            return file_get_contents($this->Cache);
        file_put_contents($this->Source, $this->Content);
        if (!file_exists($this->Source))
            return $this->format_error("No write permission to $this->Source");
        chdir($this->BaseDir);
        $mode = $this->Mode;
        $res = $this->$mode($args);
        file_put_contents($this->Cache, $res);
        return $res;
    }

    static function imagesizes($file, $divby = 1)
    {
        $r = "";
        if ($image = imagecreatefrompng($file))
        {
            $width = ceil(imagesx($image)/$divby);
            $height = ceil(imagesy($image)/$divby);
            $r = "width=\"$width\" height=\"$height\"";
            imagedestroy($image);
        }
        return array($r, $width, $height);
    }

    /**
     * Generate all kinds of Grapviz graphs (dot/neato/fdp/circo/...)
     */
    function generate_graphviz($dot, $mode = "")
    {
        $mapname = md5($this->BaseDir);
        $this->myexec("{$this->dotpath}$dot -Tsvg -o {$this->Source}.svg {$this->Source} 2>{$this->Source}.err");

        // Set xlinks' targets to _parent
        $svg = file_get_contents($this->Source.'.svg');
        $svg = preg_replace('#<a([^<>]*xlink:href=[^<>]*[^/])(/?)>#is', '<a\1 target="_parent"\2>', $svg);
        file_put_contents($this->Source.'.svg', $svg);

        $this->myexec("{$this->dotpath}$dot -Tcmap -o {$this->Source}.map {$this->Source}");
        $this->myexec("{$this->dotpath}$dot -Tpng  -o {$this->Source}.png {$this->Source}");
        if ($mode == "print")
            $this->myexec("{$this->dotpath}$dot -Tpng -Gdpi=196 -o {$this->Source}.print.png {$this->Source}");
        if (!file_exists("{$this->Source}.png"))
        {
            $err = file_get_contents("{$this->Source}.err");
            return "<div class=\"error\">\n$err\n</div>";
        }

        list($wh, $w, $h) = self::imagesizes("{$this->Source}.png");
        // Hack for Google Chrome to hide SVG scrollbars
        $w++; $h++;

        $map = file_get_contents("{$this->Source}.map");
        $str = <<<EOT
<object width="$w" height="$h" type="image/svg+xml" data="{$this->URI}{$this->Filename}.svg" style="overflow: hidden">
<map name="$mapname">$map</map>
<img $wh src="{$this->URI}{$this->Filename}.png" usemap="#{$mapname}"/>
<a class="dotsvg" href="{$this->URI}{$this->Filename}.svg">[svg]</a>
</object>
EOT;
        if ($mode == "print")
        {
            list($wh) = self::imagesizes("{$this->Source}.print.png", 2);
            $str = <<<EOT
<div class="screenonly">$str</div>
<div class="printonly">
<img $wh src="{$this->URI}{$this->Filename}.print.png" />
</div>
EOT;
        }
        return str_replace("\n", "", $str);
    }

    function graph()
    {
        return $this->generate_graphviz("dot");
    }
    function graph_print()
    {
        return $this->generate_graphviz("dot", "print");
    }
    function neato()
    {
        return $this->generate_graphviz("neato");
    }
    function neato_print()
    {
        return $this->generate_graphviz("neato", "print");
    }
    function twopi()
    {
        return $this->generate_graphviz("twopi");
    }
    function twopi_print()
    {
        return $this->generate_graphviz("twopi", "print");
    }
    function circo()
    {
        return $this->generate_graphviz("circo");
    }
    function circo_print()
    {
        return $this->generate_graphviz("circo", "print");
    }
    function fdp()
    {
        return $this->generate_graphviz("fdp");
    }
    function fdp_print()
    {
        return $this->generate_graphviz("fdp", "print");
    }

    function plot($args)
    {
        $blackList = array(
            'cd', 'call', 'exit', 'load', 'pause', 'print',
            'pwd', 'quit', 'replot', 'reread', 'reset', 'save',
            'shell', 'system', 'test', 'update', '!', 'path', 'historysize', 'mouse', 'out', 'term', 'file', '\'/', '\'.','"'
        );
        foreach($blackList as $strBlack)
            if (stristr($this->Content, $strBlack) !== false)
                return "Sorry, directive {$strBlack} is forbidden!";
        $lines = explode("\n", $this->Content);
        $datasets = array();
        $src_filtered = "";
        $activedataset = "";
        foreach($lines as $line)
        {
            if (strpos($line, "ENDDATASET") !== false)
                $activedataset = "";
            elseif (strpos($line, "DATASET") !== false) // If line like DATASET
            {
                $terms = explode(' ', trim($line));
                if (sizeof($terms) >= 2)
                    $datasetname = $terms[1];
                $datasetlabel = substr($line, strlen("DATASET ".$datasetname));
                $dataset = array(
                    'name'  => $datasetname,
                    'label' => $datasetlabel,
                    'src'   => '',
                );
                $datasets[$datasetname] =$dataset;
                $activedataset =$datasetname;
            }
            else
            {
                $res = preg_match('/^\s*(\d[\deEdDqQ\-\.]+)\s+(\d[eEdDqQ\.]*)\s*(#.*)?/', $line);
                if ($res && $activedataset != "")
                    $datasets[$activedataset]['src'] .= $line."\n";
                else
                    $src_filtered .= $line."\n";
            }
        }

        foreach($datasets as $dataset)
            file_put_contents($dataset['name'] . ".dat", $dataset['src']);

        $outputpath = "{$this->Source}";
        if (wfIsWindows())
            $outputpath = str_replace("\\", "/", $outputpath);
        $width = isset($args['width']) && $args['width'] > 0 ? intval($args['width']) : 640;
        $height = isset($args['height']) && $args['height'] > 0 ? intval($args['height']) : $width/4*3;
        $font = isset($args['font']) ? $args['font'] : 'Arial';
        $font = '"'.addslashes($font).'"';
        $str = <<<EOT
set encoding utf8
set terminal png size {$width}, {$height} font {$font}
set output "{$outputpath}.png"
{$src_filtered}
EOT;
        file_put_contents($this->Source . ".plt", $str);
        $cmd = "{$this->gnuplotpath}gnuplot < {$this->Source}.plt 2>{$this->Source}.err";
        $this->myexec($cmd);
        $filename = "{$this->Source}.png";
        usleep(100000);
        $resexists = file_exists("{$this->Source}.png");
        if (!$resexists)
        {
            $err = file_get_contents("{$this->Source}.err");
            $str = <<<EOT
<div class="error">
$resexists
<p>
$filename
<p>
$cmd
$err
</div>
EOT;
            return $str;
        }
        $str = <<<EOT
set encoding utf8
set terminal svg size {$width}, {$height} font {$font}
set output "{$outputpath}.svg"
{$src_filtered}
EOT;
        file_put_contents($this->Source . ".plt2", $str);
        $this->myexec("{$this->gnuplotpath}gnuplot {$this->Source}.plt2 2>{$this->Source}.err2");
        $str = <<<EOT
<img src="{$this->URI}{$this->Filename}.png">
EOT;
        if (file_exists("{$this->Source}.svg"))
        {
            $str =<<<EOT
<object type="image/svg+xml" width="{$width}" height="{$height}" data="{$this->URI}{$this->Filename}.svg">
{$str}
</object>
EOT;
        }
        else
        {
            $err = file_get_contents("{$this->Source}.err");
            $str .= <<<EOT
<div class="error">
$err
</div>
EOT;
        }
        return $str;
    }

    function pic_svg()
    {
        $opts = " --without-gui --export-area-drawing ";
        $this->myexec("{$this->inkscapepath}inkscape $opts --export-plain-svg={$this->Source}.svg {$this->Source}");
        $this->myexec("{$this->inkscapepath}inkscape $opts --export-png={$this->Source}.png {$this->Source} 2>{$this->Source}.err");
        if (!file_exists("{$this->Source}.png"))
        {
            $err = file_get_contents("{$this->Source}.err");
            return "<div class=\"error\">\n$err\n</div>";
        }
        list($wh) = self::imagesizes("{$this->Source}.png");
        $str = <<<EOT
<a href="{$this->URI}{$this->Filename}.svg">
<img $wh src="{$this->URI}{$this->Filename}.png">
</a>
EOT;
        return str_replace("\n", "", $str);
    }

    function umlgraph()
    {
        copy($this->Source, $this->Source.".java");
        $this->myexec("umlgraph {$this->Source} png -outputencoding UTF-8 1>{$this->Source}.err 2>&1");
        $this->myexec("umlgraph {$this->Source} svg -outputencoding UTF-8");
        $this->myexec("umlgraph {$this->Source} dot -outputencoding UTF-8");
        if (!file_exists("{$this->Source}.png"))
        {
            $err = file_get_contents("{$this->Source}.err");
            return "<div class=\"error\">\n$err\n</div>";
        }
        $str = "<a href=\"{$this->URI}{$this->Filename}.svg\"><img src=\"{$this->URI}{$this->Filename}.png\"></a>";
        return $str;
    }

    function umlsequence()
    {
        $sequencefilename = dirname(__FILE__) . '/sequence.pic';
        $this->DiplomaTemplateName = $dir . "diploma.png";

        $this->Content = <<<EOT
.PS
copy "{$sequencefilename}";
{$this->Content}
.PE
EOT;
        $this->Content = str_replace("\r", "", $this->Content);
        file_put_contents($this->Source, $this->Content);
        $this->myexec("pic2plot -Tsvg {$this->Source} > {$this->Source}.svg 2>{$this->Source}.err");
        $this->myexec("inkscape --without-gui --export-area-drawing  --export-plain-svg={$this->Source}.svg {$this->Filename}.svg");
        $this->myexec("inkscape --without-gui --export-area-drawing  --export-png={$this->Source}.png {$this->Filename}.svg 2>{$this->Source}.err");
        if (!file_exists("{$this->Source}.png"))
        {
            $err = file_get_contents("{$this->Source}.err");
            return "<div class=\"error\">\n$err\n</div>";
        }
    }

    function umlet()
    {
        $this->myexec("UMLet -action=convert -format=svg -filename={$this->Source}");
        $opts = " --without-gui --export-area-drawing ";
        $this->myexec("{$this->inkscapepath}inkscape $opts --export-plain-svg={$this->Source}.svg {$this->Filename}.svg");
        $this->myexec("{$this->inkscapepath}inkscape $opts --export-png={$this->Source}.png {$this->Filename}.svg 2>{$this->Source}.err");
        if (!file_exists("{$this->Source}.png"))
        {
            $err = file_get_contents("{$this->Source}.err");
            return "<div class=\"error\">\n$err\n</div>";
        }
        $str = "<a href=\"{$this->URI}{$this->Filename}.svg\"><img src=\"{$this->URI}{$this->Filename}.png\"></a>";
        return $str;
    }

    function do_tex($tex)
    {
        $blackList = array('\catcode', '\def', '\include', '\includeonly', '\input', '\newcommand', '\newenvironment', '\newtheorem',
                           '\newfont', '\renewcommand', '\renewenvironment', '\typein', '\typeout', '\write', '\let', '\csname', '\read', '\open');
        foreach ($blackList as $strBlack)
            if (stristr($tex, $strBlack) !== false)
                return "Sorry, directive {$strBlack} is forbidden!";
        file_put_contents($this->Source.".tex", $tex);

        $this->myexec("{$this->texpath}latex --interaction=nonstopmode {$this->Source}.tex");
        $this->myexec("{$this->texpath}dvipng -gamma 1.5 -T tight {$this->Source}");
        $this->myexec("{$this->texpath}dvisvgm --exact -TS1.5 --no-fonts --bbox=min --output=\"%f-%p.svg\" {$this->Source}.dvi");
        $str = "";
        $hash = basename($this->Filename, ".source");
        $i = 1;
        foreach (glob("{$this->BaseDir}/{$hash}*.png") as $pngfile)
        {
            $pngfile = basename($pngfile);
            $ipadded = str_pad($i, 2, "0", STR_PAD_LEFT);
            if (file_exists($this->Filename.'-'.$ipadded.'.svg'))
            {
                $size = wfGetSVGsize($this->Filename.'-'.$ipadded.'.svg');
                $size = $size ? $size[3] : '';
                $str .= "<object $size type=\"image/svg+xml\" style=\"vertical-align: middle\" data=\"{$this->URI}{$hash}.source-{$ipadded}.svg\"><img src=\"{$this->URI}{$pngfile}\" /></object>";
            }
            else
            {
                wfDebug(__CLASS__.": dvisvgm not found, disabling vector rendering of TeX\n");
                $str .= "<img src=\"{$this->URI}{$pngfile}\" />";
            }
            $i++;
        }
        return $str;
    }

    function latex()
    {
        $str = <<<EOT
\\documentclass[12pt]{article}
\\usepackage{ucs}
\\usepackage[utf8x]{inputenc}
\\usepackage[english,russian]{babel}
%\\usepackage{amssymb,amsmath,amscd,concmath}
\\usepackage{amssymb,amsmath,amscd}
\\usepackage{color}
\\pagestyle{empty}
\\begin{document}
{$this->Content}
\\end{document}
EOT;
        return $this->do_tex($str);
    }

    function amsmath()
    {
        $str = <<<EOT
\\documentclass[12pt]{article}
\\usepackage{ucs}
\\usepackage[utf8x]{inputenc}
\\usepackage[english,russian]{babel}
%\\usepackage{amssymb,amsmath,amscd,concmath}
\\usepackage{amssymb,amsmath,amscd}
\\pagestyle{empty}
\\begin{document}
\\begin{equation*}{$this->Content}\\end{equation*}
\\end{document}
EOT;
        return $this->do_tex($str);
    }

    function hbarchart()
    {
        return $this->barchart('hBar');
    }

    function vbarchart()
    {
        return $this->barchart('vBar');
    }

    function barchart($charttype='hBar')
    {
        global $wgParser, $wgTitle, $wgOut;
        require_once("graphs.inc.php");
        $graph = new BAR_GRAPH($charttype);
        $graph->showValues = 1;
        $graph->barWidth = 20;
        $graph->labelSize = 12;
        $graph->absValuesSize = 12;
        $graph->percValuesSize = 12;
        $graph->graphBGColor = 'Aquamarine';
        $graph->barColors = 'Gold';
        $graph->barBGColor = 'Azure';
        $graph->labelColor = 'black';
        $graph->labelBGColor = 'LemonChiffon';
        $graph->absValuesColor = '#000000';
        $graph->absValuesBGColor = 'Cornsilk';
        $graph->graphPadding = 15;
        $graph->graphBorder = '1px solid blue';

        $lines = explode("\n", $this->Content);
        $labels = array();
        $values = array();
        foreach($lines as $line)
        {
            $line = preg_replace("/s+/", ' ', trim($line));
            $terms = explode(' ', $line);
            if (sizeof($terms) > 1)
            {
                $value = $terms[sizeof($terms)-1];
                unset($terms[sizeof($terms)-1]);
                $text = join(' ', $terms);
                $parserOutput = $wgParser->parse($text, $wgTitle, $this->parserOptions);
                $label = str_replace("<p>", "", str_replace("</p>", "", $parserOutput->mText));
                $label = str_replace("\r", "", $label);
                $label = str_replace("\n", "", $label);
                array_push($labels, $label);
                array_push($values, $value);
            }
        }
        $graph->values = $values;
        $graph->labels = $labels;
        $res = $graph->create();
        $res = str_replace("<table", "\n<table", $res);
        $res = str_replace("<td", "\n<td", $res);
        $res = str_replace("<tr", "\n<tr", $res);
        return $res;
    }

    function myexec($cmd)
    {
        if (wfIsWindows())
        {
            $shell = new COM('WScript.Shell');
            $str = "cmd /c {$cmd}";
            $shell->CurrentDirectory = "{$this->BaseDir}";
            $shell->Run($str, 0, TRUE);
        }
        else
        {
            $str = $cmd;
            $str = str_replace("/cygdrive/d", "d:", $str);
            return exec($str);
        }
        return 0;
    }
}
