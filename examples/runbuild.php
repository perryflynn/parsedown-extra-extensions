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

//--> Cleanup
$buildfiles = glob(CFG_BUILD."*.html");
foreach($buildfiles as $buildfile)
{
   @unlink($buildfile);
}

//--> Get files
$mdfiles = array();

// One file from cmd argument
if(isset($argv[1]) && is_file(CFG_MD.$argv[1]))
{
   line("Render only given files");
   $mdfiles[] = CFG_MD.$argv[1];
}
// All files in markdown folder
else
{
   line("Scan for markdown files");
   $mdfiles = glob(CFG_MD."*.md");
   line("Found ".count($mdfiles)." files");
}

//--> Build markdown files
foreach($mdfiles as $mdfile)
{
   line("Render ".$mdfile);

   $html = $twig->render('layout.html.twig', array(
       "title" => basename($mdfile),
       "filename" => $mdfile,
       "content" => $md->parse(file_get_contents($mdfile)),
   ));

   $target = CFG_BUILD.basename($mdfile).".html";
   file_put_contents($target, $html);
   line("Saved as ".$target);

}
