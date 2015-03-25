<?php

namespace PerryFlynn;

class ParsedownExtraExtensions extends \ParsedownExtra
{

   function __construct()
   {
      parent::__construct();

      $this->setMarkupEscaped(true);
      $this->setBreaksEnabled(false);
      $this->setUrlsLinked(true);

      $this->InlineTypes['{'][] = 'ColoredText';
      $this->inlineMarkerList .= '{';

      $this->InlineTypes['@'] = array('FontAwesome');
      $this->inlineMarkerList .= "@";

      $this->InlineTypes['\\'][] = 'ManualLineBreak';
      array_unshift($this->InlineTypes['\\'], 'ManualLineBreak');
   }


   protected function inlineFontAwesome($Excerpt)
   {
      $matches = array();
      if (preg_match('/@([a-z\-]+)@/', $Excerpt['text'], $matches))
      {
         return array(
            'extent' => strlen($matches[0]),
            'element' => array(
               'name' => 'i',
               'handler' => 'line',
               'text' => '',
               'attributes' => array(
                   'class' => 'fa fa-'.$matches[1],
               ),
            ),
         );
      }
   }


   protected function inlineManualLineBreak($Excerpt)
   {
      $matches = array();
      if (preg_match("/\\\\n/", $Excerpt['text'], $matches))
      {
         return array(
            'extent' => strlen($matches[0]),
            'element' => array(
               'name' => 'br',
               'handler' => 'line',
            ),
         );
      }
   }


   protected function inlineColoredText($Excerpt)
   {
      if (preg_match('/^{c:([#\w]\w+)}([^{]+){\/c}/', $Excerpt['text'], $matches))
      {
         return array(
            'extent' => strlen($matches[0]),
            'element' => array(
               'name' => 'span',
               'text' => $matches[2],
               'handler' => 'line',
               'attributes' => array(
                  'style' => 'color: '.$matches[1],
               ),
            ),
         );
      }
   }


   protected function inlineUrl($Excerpt)
   {
      if ($this->urlsLinked !== true or ! isset($Excerpt['text'][2]) or $Excerpt['text'][2] !== '/')
      {
          return;
      }

      $link = parent::inlineUrl($Excerpt);
      $link['element']['attributes']['target'] = "_blank";
      return $link;
   }


   protected function inlineUrlTag($Excerpt)
   {
      if (strpos($Excerpt['text'], '>') !== false and preg_match('/^<(\w+:\/{2}[^ >]+)>/i', $Excerpt['text'], $matches))
      {
         return;
      }

      $link = parent::inlineUrlTag($Excerpt);
      $link['element']['attributes']['target'] = "_blank";
      return $link;
   }

   protected function blockTable($Line, array $Block = null)
   {
      if ( ! isset($Block) or isset($Block['type']) or isset($Block['interrupted']))
      {
         return;
      }

      if (strpos($Block['element']['text'], '|') !== false && /*chop($Line['text'], ' -:|') === ''*/ preg_match('/^[\s\-:0-9\|]+$/', $Line['text'])===1)
      {
         $alignments = array();

         $usewidth=false;
         $totalwidth="100";
         $widths = array();

         $divider = $Line['text'];
         $divider = trim($divider);
         $divider = trim($divider, '|');

         $widthmatch = array();
         if(preg_match("/\|\|([0-9]+)$/", $divider, $widthmatch)===1)
         {
            $totalwidth = $widthmatch[1];
            $divider = substr($divider, 0, strlen($divider)-strlen($widthmatch[0]));
         }

         $dividerCells = explode('|', $divider);

         foreach ($dividerCells as $dividerCell)
         {
            $dividerCell = trim($dividerCell);

            if ($dividerCell === '')
            {
               continue;
            }

            $alignment = null;

            if ($dividerCell[0] === ':')
            {
               $alignment = 'left';
            }

            if (substr($dividerCell, - 1) === ':')
            {
               $alignment = $alignment === 'left' ? 'center' : 'right';
            }

            $alignments []= $alignment;

            $widthmatch = array();
            if(preg_match("/([0-9\.]+)/", $dividerCell, $widthmatch)===1)
            {
               $usewidth=true;
               $widths[] = (int)$widthmatch[1];
            }
            else
            {
               $widths[] = null;
            }

         }

         $HeaderElements = array();

         $header = $Block['element']['text'];

         $header = trim($header);
         $header = trim($header, '|');

         $headerCells = explode('|', $header);

         foreach ($headerCells as $index => $headerCell)
         {
            $headerCell = trim($headerCell);

            $HeaderElement = array(
               'name' => 'th',
               'text' => $headerCell,
               'handler' => 'line',
            );

            if (isset($alignments[$index]))
            {
               $alignment = $alignments[$index];

               $HeaderElement['attributes'] = array(
                  'style' => 'text-align: '.$alignment.';',
               );
            }

            if(isset($widths[$index]) && !is_null($widths[$index]))
            {
               if(!isset($HeaderElement['attributes']['style']))
               {
                  $HeaderElement['attributes']['style']="";
               }
               $HeaderElement['attributes']['style'] .= 'width:'.$widths[$index].'%;';
            }

            $HeaderElements []= $HeaderElement;
         }

         $Block = array(
            'alignments' => $alignments,
            'identified' => true,
            'element' => array(
               'name' => 'table',
               'handler' => 'elements',
                'attributes'=>array('class'=>'lines'),
            ),

         );

         if($usewidth===true)
         {
            $Block['element']['attributes']['style'] = 'width:'.$totalwidth.'%;';
         }

         $Block['element']['text'] []= array(
            'name' => 'thead',
            'handler' => 'elements',
         );

         $Block['element']['text'] []= array(
            'name' => 'tbody',
            'handler' => 'elements',
            'text' => array(),
         );

         $Block['element']['text'][0]['text'] []= array(
            'name' => 'tr',
            'handler' => 'elements',
            'text' => $HeaderElements,
         );

         return $Block;
      }
   }

    protected function inlineLink($Excerpt)
    {
        $Element = array(
            'name' => 'a',
            'handler' => 'line',
            'text' => null,
            'attributes' => array(
                'href' => null,
                'title' => null,
            ),
        );

        $extent = 0;

        $remainder = $Excerpt['text'];

        // Get Link text
        if (preg_match('/\[((?:[^][]|(?R))*)\]/', $remainder, $matches))
        {
            $Element['text'] = $matches[1];

            $extent += strlen($matches[0]);

            $remainder = substr($remainder, $extent);
        }
        else
        {
            return;
        }

        // Get Link URL
        if (strpos($remainder, "(")===0 && strpos($remainder, ")")!==false && strpos($remainder, ")")>1)
        {

            $linkcontent = substr($remainder, 1, strpos($remainder, ")")-1);
            $extent += strlen($linkcontent)+2;

            // Check for title attribute
            if (strpos($linkcontent, '"')!==false && strpos($linkcontent, '"', strpos($linkcontent, '"')+1)!==false)
            {
                $start = strpos($linkcontent, '"');
                $end = strpos($linkcontent, '"', $start+1);
                $Element['attributes']['title'] = substr($linkcontent, $start+1, $end-$start-1);
                $linkcontent = substr($linkcontent, 0, $start);
            }

            $Element['attributes']['href'] = trim($linkcontent);
        }

        // Definition
        else
        {
            if (preg_match('/^\s*\[(.*?)\]/', $remainder, $matches))
            {
                $definition = $matches[1] ? $matches[1] : $Element['text'];
                $definition = strtolower($definition);

                $extent += strlen($matches[0]);
            }
            else
            {
                $definition = strtolower($Element['text']);
            }

            if ( ! isset($this->DefinitionData['Reference'][$definition]))
            {
                return;
            }

            $Definition = $this->DefinitionData['Reference'][$definition];

            $Element['attributes']['href'] = $Definition['url'];
            $Element['attributes']['title'] = $Definition['title'];
        }

        $Element['attributes']['href'] = str_replace(array('&', '<'), array('&amp;', '&lt;'), $Element['attributes']['href']);

        return array(
            'extent' => $extent,
            'element' => $Element,
        );
    }


}

