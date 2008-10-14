<?php
class MarkupBabelProcessor
{
var $Source='';
var $Filename='';
var $Hash='';
var $Mode='';
var $Cache='';
var $URI='';

  private function stripTags($text, $startRegexp, $endRegexp){
    $stripped = '';
  
    while ( '' != $text ) {
      $p = preg_split($startRegexp, $text, 2 );
      $stripped .= $p[0];
      if ( ( count( $p ) < 2 ) || ( '' == $p[1] ) ) {
        $text = '';
      } else {
        $q = preg_split( $endRegexp, $p[1], 2 );
        $stripped .= $q[0];
        $text = $q[1];
      }
    }
    return $stripped;
  }

  private function isWindows() {
        if (substr(php_uname(), 0, 7) == 'Windows') {
            return true;
        } else {
            return false;
        }
    }

    function MarkupBabelProcessor($path,$mode,$uri)
    {
        $this->Source=$path;
        $this->Filename=basename($path);
        $this->BaseDir=dirname($path);
        $this->Mode=$mode;
        $this->Cache=$path.".cache";
        $this->URI=$uri;
        $this->Source=realpath($this->Source);
        $this->parserOptions = new ParserOptions();
    }

    function format_error($err) {
        $str="<div class=\"screenonly\">'$this->Mode' generation for '$this->Source' failed:<pre>$err</pre></div>\n";
        return $str;
    }

    function rendme()
    {
        if ( !file_exists ( $this->Source )) {
            return $this->format_error("Source file not found.");
        }
        if ( file_exists ( $this->Cache )) {
            return file_get_contents($this->Cache);
        }
        chdir($this->BaseDir);
        $mode=$this->Mode;
        $res=$this->$mode();
        $this->fput_contents($this->Cache,$res);
        return $res;
    }

    function fput_contents($filename, $str)
    {
      $obj = fopen($filename, 'w');
      if ($obj) {
            fwrite($obj, $str);
            fclose($obj);
      }
    }

    function generate_graphviz($dot,$mode="") {
        $mapname=md5($this->BaseDir);
        trim($this->myexec("$dot -Tsvg -o {$this->Source}.svg {$this->Source} 2>{$this->Source}.err"));
        $this->myexec("$dot -Tcmap -o {$this->Source}.map {$this->Source}");
        $this->myexec("$dot -Tpng  -o {$this->Source}.png {$this->Source}");
        if ($mode=="print")
        {
            $this->myexec("$dot -Tpng -Gdpi=196 -o {$this->Source}.print.png {$this->Source}");
        }
        if ( !file_exists ( "{$this->Source}.png" )) {
           $err=file_get_contents("{$this->Source}.err");
           $str=<<<EOT
<div class="error">
  {$err}
</div>
EOT;
            return $str;
        }    
        $image = new Imagick("{$this->Source}.png");
        if ($mode=="print")
        {
            $imageprint = new Imagick("{$this->Source}.print.png");
            $imageprint->trimImage(0);
            $imageprint->writeImage();
//            $this->myexec("mogrify -trim -type palette -depth 8 -colors 64 -density 192x192 -strip -dither -quality 100 -blur 0x0.4 +antialias {$this->Source}.png");
        }

        $width=$image->getImageWidth();
        $height=$image->getImageHeight();
        
        $map=file_get_contents("{$this->Source}.map");
        $str=<<<EOT
<map name="$mapname">$map</map>
<img width="$width" height="$height" src="{$this->URI}{$this->Filename}.png" usemap="#{$mapname}"/>
<a href="{$this->URI}{$this->Filename}.svg">[svg]</a>
EOT;
        if ($mode=="print")
        {
            $width=$imageprint->getImageWidth()/2;
            $height=$imageprint->getImageHeight()/2;
            $str=<<<EOT
<div class="screenonly">$str</div>
<div class="printonly">
<img width="{$width}" height="{$height}" src="{$this->URI}{$this->Filename}.print.png" />
</div>
EOT;
        }
        $image->writeImage();
        return str_replace("\n","",$str);
    }

    function graph()        {    return $this->generate_graphviz("dot"); }
    function graph_print()  {    return $this->generate_graphviz("dot","print");  }
    function neato()        {    return $this->generate_graphviz("neato"); }
    function neato_print()  {    return $this->generate_graphviz("neato","print"); }
    function twopi()        {    return $this->generate_graphviz("twopi"); }
    function twopi_print()  {    return $this->generate_graphviz("twopi","print"); }
    function circo()        {    return $this->generate_graphviz("circo"); }
    function circo_print()  {    return $this->generate_graphviz("circo","print"); }
    function fdp()          {    return $this->generate_graphviz("fdp"); }
    function fdp_print()    {    return $this->generate_graphviz("fdp","print"); }

    function plot() {
        $src=file_get_contents($this->Source);
        $blackList = array('cd', 'call', 'exit', 'load', 'pause', 'print', 'pwd', 'quit', 'replot', 'reread', 'reset', 'save', 'shell', 'system', 'test', 'update', '!', 'path', 'historysize', 'mouse', 'out', 'term', 'file', '\'/', '\'.','"');
            foreach($blackList as $strBlack) {
            if (stristr($src, $strBlack)!=false) {
                return "Sorry, directive {$strBlack} is forbidden!";
            }
        }
        $lines = split("\n",$src);
        $datasets=array();
        $src_filtered="";
        $activedataset="";
        foreach($lines as $line)
        {
          if (strpos($line,"ENDDATASET")!==false){ 
            $activedataset="";
          }
          elseif (strpos($line,"DATASET")!==false) // If line like DATASET
          {
            $terms = explode(' ',trim($line));
            if (sizeof($terms)>=2){
                $datasetname=$terms[1];
            }    
            $datasetlabel=substr($line, strlen("DATASET ".$datasetname));
            $dataset=array(
                'name'  => $datasetname,                           
                'label' => $datasetlabel,                           
                'src'   => '',                           
            );
            $datasets[$datasetname]=$dataset;
            $activedataset=$datasetname;
          }
          else
          {
            if ($activedataset=="")
            {
                $src_filtered.=$line."\n";
            }
            elseif ($activedataset!="" && preg_match( '/^\s*(\d[eEdDqQ\.]*)\s+(\d[eEdDqQ\.]*)\s*(#.*)?/', $line ))
            {
                $datasets[$activedataset]['src'].=$line."\n";
            }
          }  
        }
        foreach($datasets as $dataset){
            $this->fput_contents($dataset['name'] . ".dat", $dataset['src']);
        }
        
        
        $str=<<<EOT
set terminal png
set output "{$this->Source}.png"
{$src_filtered}
EOT;

        $this->fput_contents($this->Source . ".plt", $str);
        $this->myexec("gnuplot {$this->Source}.plt 2>{$this->Source}.err");
        if ( !file_exists ( "{$this->Source}.png" ) || (filesize("{$this->Source}.png")==0)) {
           $err=file_get_contents("{$this->Source}.err");
           $str=<<<EOT
<div class="error">
  {$err}
</div>
EOT;
            return $str;
        }    
        $str=<<<EOT
set terminal svg
set output "{$this->Source}.svg"
{$src_filtered}
EOT;
        $this->fput_contents($this->Source . ".plt2", $str);
        $this->myexec("gnuplot {$this->Source}.plt2 2>{$this->Source}.err2");
        $image= new Imagick("{$this->Source}.png");
        $image->trimImage(0);
        $image->writeImage();
        $str=<<<EOT
<img src="{$this->URI}{$this->Filename}.png">
EOT;
        if ( file_exists ( "{$this->Source}.svg" )) {
        $str=<<<EOT
<object type="image/svg+xml" width="600" height="480" data="{$this->URI}{$this->Filename}.svg">        
{$str}
</object>
EOT;
        }    
        return $str;
    }

    function pic_svg() {
        $this->myexec("inkscape --without-gui --export-area-drawing --export-plain-svg={$this->Source}.svg {$this->Source}");
        $this->myexec("inkscape --without-gui --export-area-drawing --export-png={$this->Source}.png {$this->Source} 2>{$this->Source}.err");
        if ( !file_exists ( "{$this->Source}.png" )) {
           $err=file_get_contents("{$this->Source}.err");
           $str=<<<EOT
<div class="error">
  {$err}
</div>
EOT;
            return $str;
        }    
        $image= new Imagick("{$this->Source}.png");
        $image->trimImage(0);
        $image->writeImage();
        $width=$image->getImageWidth();
        $height=$image->getImageHeight();
//<object type="image/svg+xml" width="{$width}" height="{$height}" data="{$this->URI}{$this->Filename}.svg">        
        $str=<<<EOT
<a href="{$this->URI}{$this->Filename}.svg">
<img width="{$width}" height="{$height}" src="{$this->URI}{$this->Filename}.png">
</a>
EOT;
        return str_replace("\n","",$str);
    }

    function umlgraph() {
        copy($this->Source,$this->Source.".java");
        $this->myexec("umlgraph {$this->Source} png -outputencoding UTF-8 1>{$this->Source}.err 2>&1");
        $this->myexec("umlgraph {$this->Source} svg -outputencoding UTF-8");
        $this->myexec("umlgraph {$this->Source} dot -outputencoding UTF-8");
        if ( !file_exists ( "{$this->Source}.png" )) {
           $err=file_get_contents("{$this->Source}.err");
           $str=<<<EOT
<div class="error">
  {$err}
</div>
EOT;
            return $str;
        }    
        $image= new Imagick("{$this->Source}.png");
        $image->trimImage(0);
        $image->writeImage();
        $str="<a href=\"{$this->URI}{$this->Filename}.svg\"><img src=\"{$this->URI}{$this->Filename}.png\"></a>";
        return $str;
    }


    function umlsequence() {
        $sequencefilename = dirname(__FILE__) . '/sequence.pic';
        $this->DiplomaTemplateName=$dir . "diploma.png";
        
        $src=file_get_contents($this->Source);
        $src=<<<EOT
.PS
copy "{$sequencefilename}";
{$src}        
.PE
EOT;
        $src=str_replace("\r","",$src);
        file_put_contents($this->Source,$src);
        $this->myexec("pic2plot -Tsvg {$this->Source} > {$this->Source}.svg 2>{$this->Source}.err");
        $this->myexec("inkscape --without-gui --export-area-drawing  --export-plain-svg={$this->Source}.svg {$this->Filename}.svg");
        $this->myexec("inkscape --without-gui --export-area-drawing  --export-png={$this->Source}.png {$this->Filename}.svg 2>{$this->Source}.err");
        if ( !file_exists ( "{$this->Source}.png" )) {
           $err=file_get_contents("{$this->Source}.err");
           $str=<<<EOT
<div class="error">
  {$err}
</div>
EOT;
            return $str;
        }    
    }

    function umlet() {
        $this->myexec("UMLet -action=convert -format=svg -filename={$this->Source}");
        $this->myexec("inkscape --without-gui --export-area-drawing  --export-plain-svg={$this->Source}.svg {$this->Filename}.svg");
        $this->myexec("inkscape --without-gui --export-area-drawing  --export-png={$this->Source}.png {$this->Filename}.svg 2>{$this->Source}.err");
        if ( !file_exists ( "{$this->Source}.png" )) {
           $err=file_get_contents("{$this->Source}.err");
           $str=<<<EOT
<div class="error">
  {$err}
</div>
EOT;
            return $str;
        }    
        $image= new Imagick("{$this->Source}.png");
        $image->trimImage(0);
        $image->writeImage();
        $str="<a href=\"{$this->URI}{$this->Filename}.svg\"><img src=\"{$this->URI}{$this->Filename}.png\"></a>";
        return $str;
    }


    function do_tex($tex) {
        $blackList = array('\catcode', '\def', '\include', '\includeonly', '\input', '\newcommand', '\newenvironment', '\newtheorem', '\newfont', '\renewcommand', '\renewenvironment', '\typein', '\typeout', '\write', '\let', '\csname', '\read', '\open');
            foreach($blackList as $strBlack) {
            if (stristr($tex, $strBlack)!=false) {
                return "Sorry, directive {$strBlack} is forbidden!";
            }
        }
        $this->fput_contents($this->Source . ".tex", $tex);
        $this->myexec("latex --interaction=nonstopmode  {$this->Source}.tex");
        $this->myexec("dvipng -gamma 1.5 -T tight {$this->Source}");
        $str="";
        $hash=basename($this->Filename,".source");
        foreach (glob("{$this->BaseDir}/{$hash}*.png") as $pngfile) {
            $pngfile=basename($pngfile);
            $str.="<img src=\"{$this->URI}{$pngfile}\">";
        }
        return $str;
    }

    function latex() {
        $src=file_get_contents($this->Source);
        $str=<<<EOT
\\documentclass[12pt]{article}
\\usepackage{ucs}
\\usepackage[utf8x]{inputenc}
\\usepackage[english,russian]{babel}
\\usepackage{amssymb,amsmath,amscd,concmath}
\\usepackage{color}
\\pagestyle{empty}
\\begin{document}
{$src}
\\end{document}
EOT;
        return $this->do_tex($str);
    }

    function amsmath() {
        $src=file_get_contents($this->Source);
        $str=<<<EOT
\\documentclass[12pt]{article}
\\usepackage{ucs}
\\usepackage[utf8x]{inputenc}
\\usepackage[english,russian]{babel}
\\usepackage{amssymb,amsmath,amscd,concmath}
\\pagestyle{empty}
\\begin{document}
\\begin{equation*}{$src}\\end{equation*}
\\end{document}
EOT;
        return $this->do_tex($str);
    }

    function hbarchart() {
        return $this->barchart('hBar');
    }

    function vbarchart() {
        return $this->barchart('vBar');
    }

    function barchart($charttype='hBar') {
        global   $wgParser, $wgTitle, $wgOut;
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

        $src=file_get_contents($this->Source);
        $lines = split("\n",$src);
        $labels=array();
        $values=array();
        foreach($lines as $line)
        {
          $line=preg_replace("/s+/",' ',trim($line));
          $terms = explode(' ',$line);
          if (sizeof($terms)>1){
            $value=$terms[sizeof($terms)-1];
            unset($terms[sizeof($terms)-1]);
            $text = join(' ',$terms);
            $parserOutput = $wgParser->parse( $text, $wgTitle, $this->parserOptions );
            $label = str_replace("<p>","",str_replace("</p>","",$parserOutput->mText));
            $label = str_replace("\r","",$label);
            $label = str_replace("\n","",$label);
            array_push($labels, $label);
            array_push($values, $value);
          }
        }
        $graph->values = $values;
        $graph->labels = $labels;
        $res=$graph->create();
        $res=str_replace("<table","\n<table",$res);
        $res=str_replace("<td","\n<td",$res);
        $res=str_replace("<tr","\n<tr",$res);
        return $res;
    }

    function myexec($cmd) {
      if ($this->isWindows())
      {
          $shell = new COM('WScript.Shell');
          $str="cmd /c {$cmd}";
          $shell->CurrentDirectory="{$this->BaseDir}";
          $shell->Run($str, 0, TRUE);
      }
      else
      {
        $str=$cmd;
        $str=str_replace("/cygdrive/d","d:",$str);
        return exec($str);
      }
      return 0;
    }
}

?>
