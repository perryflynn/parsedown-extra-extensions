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
      /**
       * Inline font awesome icons
       * Usage: @star-o@ = <i class="fa fa-star-o"></i>
       */

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
      /**
       * Manual line break
       * Usage \n = <br>; \\n = \n
       */

      $matches = array();
      // Find \n but not \\n
      if (preg_match('/((?:([\\\]))?\\\n)/', $Excerpt['text'], $matches)===1)
      {
         // If \\n abort here
         if(isset($matches[2]) && $matches[2]=="\\")
         {
            return;
         }

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
      /**
       * Colored text (grabbed from parsedown wiki)
       */

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
      /**
       * Better tables
       * - Change table and column width
       * see examples/markdown/tables.md
       */

       if(isset($Block['interrupted']) && $Block['interrupted'])
       {
         return;
       }

      $alignments = array();
      $usewidth=false;
      $totalwidth="100";
      $widths = array();

      //--> Parse dividers
      if (preg_match('/^[\s\-:0-9\|]+$/', $Line['text'])===1)
      {

         $divider = $Line['text'];
         $divider = trim($divider);
         $divider = trim($divider, '|');

         // Table width
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

            // Alignment
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

            // Column width
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

      }
      else
      {
         return;
      }

      // Create new Block element
      $NewBlock = array(
         'alignments' => $alignments,
         'usewidth' => $usewidth,
         'widths' => $widths,
         'identified' => true,
         'element' => array(
            'name' => 'table',
            'handler' => 'elements',
            'attributes'=>array('class'=>'lines'),
         ),
      );

      // Return old block element, if no table begin
      $headerarraypos = 0;
      if ( ! isset($Block) or isset($Block['type']) or isset($Block['interrupted']))
      {
         $headerarraypos++;
         $NewBlock['element']['text'] []= $Block['element'];
      }

      // Table header
      $NewBlock['element']['text'] []= array(
         'name' => 'thead',
         'handler' => 'elements',
      );

      // Table body
      $NewBlock['element']['text'] []= array(
         'name' => 'tbody',
         'handler' => 'elements',
         'text' => array(),
      );

      // Table width
      if($usewidth===true)
      {
         $NewBlock['element']['attributes']['style'] = 'width:'.$totalwidth.'%;';
      }

      // Header line
      if (strpos($Block['element']['text'], '|') !== false)
      {
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

            // Header cell alignment
            if (isset($alignments[$index]))
            {
               $alignment = $alignments[$index];

               $HeaderElement['attributes'] = array(
                  'style' => 'text-align: '.$alignment.';',
               );
            }

            // Header cell width
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

         // Add header to new block array
         $NewBlock['element']['text'][$headerarraypos]['text'] []= array(
            'name' => 'tr',
            'handler' => 'elements',
            'text' => $HeaderElements,
         );


      }

      return $NewBlock;
   }



    protected function blockTableContinue($Line, array $Block)
    {
      /**
       * Better tables
       * - Tables without header
       * - Change table and column width
       * see examples/markdown/tables.md
       */

        if (isset($Block['interrupted']))
        {
            return;
        }

        if ($Line['text'][0] === '|' or strpos($Line['text'], '|'))
        {
            $Elements = array();

            $row = $Line['text'];

            $row = trim($row);
            $row = trim($row, '|');

            preg_match_all('/(?:(\\\\[|])|[^|`]|`[^`]+`|`)+/', $row, $matches);

            foreach ($matches[0] as $index => $cell)
            {
                $cell = trim($cell);

                $Element = array(
                    'name' => 'td',
                    'handler' => 'line',
                    'text' => $cell,
                );

                if (isset($Block['alignments'][$index]))
                {
                    $Element['attributes'] = array(
                        'style' => 'text-align: '.$Block['alignments'][$index].';',
                    );
                }

               if($Block['usewidth']==true && isset($Block['widths'][$index]) && !is_null($Block['widths']))
               {
                  if(!isset($Element['attributes']['style']))
                  {
                     $Element['attributes']['style']="";
                  }
                  $Element['attributes']['style'] .= 'width:'.$Block['widths'][$index].'%;';
               }

                $Elements []= $Element;
            }

            $Element = array(
                'name' => 'tr',
                'handler' => 'elements',
                'text' => $Elements,
            );

            $Block['element']['text'][1]['text'] []= $Element;

            return $Block;
        }
    }


    protected function inlineLink($Excerpt)
    {
      /**
       * Removed regex and use string functions to
       * support very long urls such as data uris
       */

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

