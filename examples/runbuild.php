<?php

require_once __DIR__.'/../vendor/autoload.php';

define("CFG_DIR", dirname(__FILE__)."/");
define("CFG_BUILD", CFG_DIR."build/");
define("CFG_MD", CFG_DIR."markdown/");

/**
 * CMD helper to echo one line of text
 */
function line($s)
{
   echo "[".date("Y-m-d H:i:s")."] ".$s."\n";
}



//--> Check build directory
if(!is_dir(CFG_BUILD))
{
   mkdir(CFG_BUILD, 0775);
}

if(!is_dir(CFG_BUILD))
{
   throw new \Exception("build dir not found.");
}

//--> Init Markdown
$md = new \PerryFlynn\ParsedownExtraExtensions();

$md->setBreaksEnabled(false)
   ->setMarkupEscaped(true)
   ->setUrlsLinked(true);

//--> Init Twig
$loader = new Twig_Loader_Filesystem(__DIR__);
$twig = new Twig_Environment($loader);

//--> Clean
$buildfiles = glob(CFG_BUILD."*.html");
foreach($buildfiles as $buildfile)
{
   @unlink($buildfile);
}

//--> Build markdown files
$mdfiles = glob(CFG_MD."*.md");
foreach($mdfiles as $mdfile)
{
   line("File: ".$mdfile);

   $html = $twig->render('layout.html.twig', array(
       "title" => basename($mdfile),
       "filename" => $mdfile,
       "content" => $md->parse(file_get_contents($mdfile)),
   ));

   file_put_contents(CFG_BUILD.basename($mdfile).".html", $html);

}

