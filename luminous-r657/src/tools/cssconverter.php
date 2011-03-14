<?php

/*
 * Copyright 2010 Mark Watkinson
 * 
 * This file is part of Luminous.
 * 
 * Luminous is free software: you can redistribute it and/or
 * modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * 
 * Luminous is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with Luminous.  If not, see <http://www.gnu.org/licenses/>.
 */



/*
 * This file implements a CSS converter. It will read a CSS string and
 * represent its rules internally in memory.
 * 
 * Only a limited subset of CSS is supported, enough to make Luminous themes
 * portable.
 */


require_once dirname(__FILE__) . '/../luminous.php';


// source: http://www.w3schools.com/css/css_colornames.asp
global $col2hex;
$col2hex = array(
  'aliceblue' => '#f0f8ff',
  'antiquewhite' => '#faebd7',
  'aqua' => '#00ffff',
  'aquamarine' => '#7fffd4',
  'azure' => '#f0ffff',
  'beige' => '#f5f5dc',
  'bisque' => '#ffe4c4',
  'black' => '#000000',
  'blanchedalmond' => '#ffebcd',
  'blue' => '#0000ff',
  'blueviolet' => '#8a2be2',
  'brown' => '#a52a2a',
  'burlywood' => '#deb887',
  'cadetblue' => '#5f9ea0',
  'chartreuse' => '#7fff00',
  'chocolate' => '#d2691e',
  'coral' => '#ff7f50',
  'cornflowerblue' => '#6495ed',
  'cornsilk' => '#fff8dc',
  'crimson' => '#dc143c',
  'cyan' => '#00ffff',
  'darkblue' => '#00008b',
  'darkcyan' => '#008b8b',
  'darkgoldenrod' => '#b8860b',
  'darkgray' => '#a9a9a9',
  'darkgrey' => '#a9a9a9',
  'darkgreen' => '#006400',
  'darkkhaki' => '#bdb76b',
  'darkmagenta' => '#8b008b',
  'darkolivegreen' => '#556b2f',
  'darkorange' => '#ff8c00',
  'darkorchid' => '#9932cc',
  'darkred' => '#8b0000',
  'darksalmon' => '#e9967a',
  'darkseagreen' => '#8fbc8f',
  'darkslateblue' => '#483d8b',
  'darkslategray' => '#2f4f4f',
  'darkslategrey' => '#2f4f4f',
  'darkturquoise' => '#00ced1',
  'darkviolet' => '#9400d3',
  'deeppink' => '#ff1493',
  'deepskyblue' => '#00bfff',
  'dimgray' => '#696969',
  'dimgrey' => '#696969',
  'dodgerblue' => '#1e90ff',
  'firebrick' => '#b22222',
  'floralwhite' => '#fffaf0',
  'forestgreen' => '#228b22',
  'fuchsia' => '#ff00ff',
  'gainsboro' => '#dcdcdc',
  'ghostwhite' => '#f8f8ff',
  'gold' => '#ffd700',
  'goldenrod' => '#daa520',
  'gray' => '#808080',
  'grey' => '#808080',
  'green' => '#008000',
  'greenyellow' => '#adff2f',
  'honeydew' => '#f0fff0',
  'hotpink' => '#ff69b4',
  'indianred' => '#cd5c5c',
  'indigo' => '#4b0082',
  'ivory' => '#fffff0',
  'khaki' => '#f0e68c',
  'lavender' => '#e6e6fa',
  'lavenderblush' => '#fff0f5',
  'lawngreen' => '#7cfc00',
  'lemonchiffon' => '#fffacd',
  'lightblue' => '#add8e6',
  'lightcoral' => '#f08080',
  'lightcyan' => '#e0ffff',
  'lightgoldenrodyellow' => '#fafad2',
  'lightgray' => '#d3d3d3',
  'lightgrey' => '#d3d3d3',
  'lightgreen' => '#90ee90',
  'lightpink' => '#ffb6c1',
  'lightsalmon' => '#ffa07a',
  'lightseagreen' => '#20b2aa',
  'lightskyblue' => '#87cefa',
  'lightslategray' => '#778899',
  'lightslategrey' => '#778899',
  'lightsteelblue' => '#b0c4de',
  'lightyellow' => '#ffffe0',
  'lime' => '#00ff00',
  'limegreen' => '#32cd32',
  'linen' => '#faf0e6',
  'magenta' => '#ff00ff',
  'maroon' => '#800000',
  'mediumaquamarine' => '#66cdaa',
  'mediumblue' => '#0000cd',
  'mediumorchid' => '#ba55d3',
  'mediumpurple' => '#9370d8',
  'mediumseagreen' => '#3cb371',
  'mediumslateblue' => '#7b68ee',
  'mediumspringgreen' => '#00fa9a',
  'mediumturquoise' => '#48d1cc',
  'mediumvioletred' => '#c71585',
  'midnightblue' => '#191970',
  'mintcream' => '#f5fffa',
  'mistyrose' => '#ffe4e1',
  'moccasin' => '#ffe4b5',
  'navajowhite' => '#ffdead',
  'navy' => '#000080',
  'oldlace' => '#fdf5e6',
  'olive' => '#808000',
  'olivedrab' => '#6b8e23',
  'orange' => '#ffa500',
  'orangered' => '#ff4500',
  'orchid' => '#da70d6',
  'palegoldenrod' => '#eee8aa',
  'palegreen' => '#98fb98',
  'paleturquoise' => '#afeeee',
  'palevioletred' => '#d87093',
  'papayawhip' => '#ffefd5',
  'peachpuff' => '#ffdab9',
  'peru' => '#cd853f',
  'pink' => '#ffc0cb',
  'plum' => '#dda0dd',
  'powderblue' => '#b0e0e6',
  'purple' => '#800080',
  'red' => '#ff0000',
  'rosybrown' => '#bc8f8f',
  'royalblue' => '#4169e1',
  'saddlebrown' => '#8b4513',
  'salmon' => '#fa8072',
  'sandybrown' => '#f4a460',
  'seagreen' => '#2e8b57',
  'seashell' => '#fff5ee',
  'sienna' => '#a0522d',
  'silver' => '#c0c0c0',
  'skyblue' => '#87ceeb',
  'slateblue' => '#6a5acd',
  'slategray' => '#708090',
  'slategrey' => '#708090',
  'snow' => '#fffafa',
  'springgreen' => '#00ff7f',
  'steelblue' => '#4682b4',
  'tan' => '#d2b48c',
  'teal' => '#008080',
  'thistle' => '#d8bfd8',
  'tomato' => '#ff6347',
  'turquoise' => '#40e0d0',
  'violet' => '#ee82ee',
  'wheat' => '#f5deb3',
  'white' => '#ffffff',
  'whitesmoke' => '#f5f5f5',
  'yellow' => '#ffff00',
  'yellowgreen' => '#9acd32'
);



/**
 * The LuminousGrammar used to parse the CSS
 * \internal
 */
class CSSParserRules extends LuminousGrammar
{
  function __construct()
  {
    $this->SetInfoLanguage('css');
  }
}


/**
 * \internal
 *
 * \brief Converts CSS themes into an in-memory format
 * 
 * The CSSConverter class is responsible for parsing CSS into 'portable' data
 * structures. This is intended to be used for applying themes to non-HTML
 * output formats.
 * 
 * The data recorded here will be a subset of CSS. The properties currently
 * supported are 'color', 'bgcolor', 'bold', 'underline', 'strikethrough',
 * 'italics'.
 * 
 * Colors are always stored as hexadecimal RGB with a leading hash, e.g:
 * #0369BF
 * 
 * 
 * Some verbatim CSS may be recorded also, but don't rely on it.
 * 
 * 
 * 
 * The function you are interested in is CSSConverter::Convert(), followed
 * by CSSConverter::GetRules() or CSSConverter::GetProperties()
 */
class CSSConverter
{
  
  public $rules = array();
  
  private $active_rules = array();
  private $active_property = '';
  
  private $grammar = null;
  
  public $verbose = true;
  
  
  public function __construct()
  {
    $this->grammar = new CSSParserRules();
    
    $this->grammar->delimited_types = array(
      new LuminousDelimiterRule(0, 'COMMENT', 0, '/*', '*/'),
      new LuminousDelimiterRule(0, 'STRING', 0, '"', '"'),
      new LuminousDelimiterRule(0, 'STRING', 0, '\'', '\''),
      
      new LuminousDelimiterRule(0, 'RULE', LUMINOUS_REGEX|LUMINOUS_COMPLETE,
        '/[\w\.#:\s]+/', null, array($this, 'Rule')),
      new LuminousDelimiterRule(0, 'BLOCK', 0, '{', '}', 
        array($this, 'Block')),

      new LuminousDelimiterRule(0, 'VALUE', LUMINOUS_COMPLETE|LUMINOUS_REGEX,
        '/(?<=:)[^;].*?(?=;)/s', null, array($this, 'Value')),
      new LuminousDelimiterRule(0, 'PROPERTY', LUMINOUS_COMPLETE|LUMINOUS_REGEX,
        '/[\w\-\s]+?(?=:)/s', null, array($this, 'Property')),
    );
    
    $this->grammar->state_transitions = array(
      'GLOBAL' => array('COMMENT', 'RULE', 'BLOCK'),
      'BLOCK' => array('COMMENT', 'VALUE', 'PROPERTY'),
      'VALUE' => array('COMMENT', 'STRING')
    );
    
    
  }
  
  
  /**
   * \return the rules as defined in the CSS.
   * The rules are in the form:
   \verbatim 
[ 
  rule_name => [ property1 => [val1, ...], property2 => [val1, ...] ]

  rule_name1 => [ property1 => [val1, ...], property2 => [val1, ...] ]

]
  \endverbatim
   * Note that a property may be defined multiple times for a particular rule,
   * in which case the first one takes precedence, unless it is unsupported by
   * the output medium.
   */
                     
  function GetRules()
  {
    return $this->rules;
  }
  
  /**
   * \return the properties known for a rule, as defined in the CSS.
   * The properties are in the format:
   * \verbatim
[ property1 => [val1, ...], property2 => [val1, ...] ]
   \endverbatim
   *
   * Note that a property may be defined multiple times for a particular rule,
   * in which case the first one takes precedence, unless it is unsupported by
   * the output medium.
   */
  function GetProperties($rule)
  {
    return (isset($this->rules[$rule]))?$this->rules[$rule] : array();
  }
  
  function GetValue($rule, $property)
  {
    if (!isset($this->rules[$rule]))
      return false;
    if (!isset($this->rules[$rule][$property]) || !count($this->rules[$rule][$property]))
      return false;
    
    return $this->rules[$rule][$property][0];
  }
  
  
  
  
  /// \internal
  private function FormatColor($col)
  {
    $col = trim($col);
    if (preg_match('/^#[a-f0-9]{6}$/i', $col))
      return $col;
    elseif(preg_match('/^#[a-f0-9]{6}$/i', $col))
      return "#{$col[1]}{$col[1]}{$col[2]}{$col[2]}{$col[3]}{$col[3]}";
      
    // TODO: don't drop transparency
    elseif(preg_match('/^rgba?\s*\(/', $col))
    {
      $col = preg_replace('/^rgba?\s*\(|\).*$/', '', $col);
      $rgb = explode(',', $col);
      if (count($rgb)<3)
        return false;
      if (count($rgb) == 4)
        $this::Warning('Dropped alpha in colour declaration for `' . implode(', ', $this->active_rules) . '` in property ' . $this->active_property);
      $c = '#';
      for ($i=0; $i<3; $i++)
      {
        $x = trim($rgb[$i]);
        $percentage = ($x[strlen($x)-1] === '%');
        $n = max(intval($x), 0);
        if ($percentage)
        {
          $n = min($n, 100);
          $n = $n/100 * 255;
        }
        else
          $n = min($n, 255);
        
        $h = dechex($n);
        if (strlen($h) == 1)
          $h = '0' . $h;
        $c .= $h;
      }
      return $c;
    }
  }
  
  /**
   * \internal
   * maps a property and value to a pair to be stored interally
   * E.g. 'text-decoration', 'underline' is converted to 
   * array('underline', true)
   * 
   * If no translation is known for the given properties, they are stored 
   * verbatim.
   */
  function PropertyValueMap($property, $value)
  {
    $out = array();
    switch($property)
    {
      case 'color':
        $out[0] = 'color';
        $out[1] = $this::FormatColor($value);
        break;
      case 'background-color':
        $out[0] = 'bgcolor';
        $out[1] = $this::FormatColor($value);
        break;
      case 'font-weight':
        $out[0] = 'bold';
        $out[1] = (in_array($value, array('bold', 'bolder', '700', '800', '900')));
        break;
      case 'font-style':
        $out[0] = 'italic';
        $out[1] = (in_array($value, array('italic', 'oblique')));
        break;
      case 'text-decoration':
        if ($value === 'line-through')
          $out = array(0=>'strikethrough', 1=>true);
        elseif($value === 'underline')
          $out = array(0=>'underline', 1=>true);
        else
          $out = array($property, $value);
        break;
      default:
        $out = array(0=>$property, 1=>$value);
    }
    return $out;
  }
  
  
  
  
  /**
   * Initiates the conversion process. 
   * 
   * \param str  The CSS string to be parsed.
   * 
   * After usage, use 
   */
  function Convert($str)
  {
    $l = new Luminous();
    $l->pre_escaped = true;
    $l->separate_lines = false;
    $l->tag_input = false;
    $l->Easy_Parse($str, $this->grammar);
  }
  
  
  /// \internal
  private function Warning($str)
  {
    if ($this->verbose)
      echo "Warning: $str<br>\n";
  }
  
  /// \internal
  function Block($str)
  {
    $this->active_rules = array();
    return $str;
  }
  /// \internal
  function Property($str)
  {
    $this->active_property = trim($str);
    return $str;
  }
  /// \internal
  function Value($str)
  {
    global $col2hex;
    $val = trim(str_replace('!important', '', $str));
    if (isset($col2hex[strtolower($val)]))
      $val = $col2hex[strtolower($val)];
    foreach($this->active_rules as $r)
    {
      $p_v_m = $this->PropertyValueMap($this->active_property, $val);
      $p = $p_v_m[0];
      $v = $p_v_m[1];
      
      if (!isset($this->rules[$p][$v]))
        $this->rules[$r][$p] = array();
      $this->rules[$r][$p][] = $v;
    }
    return $str;
  }
  /// \internal
  function Rule($str)
  {

    $str_ = preg_replace("/[\n\r]+/", ' ', $str);    
    $rules = explode(',', $str_);

    foreach($rules as $r)
    {      
      $r_ = $r;
      $r = preg_replace('/(?<=\W)\.luminous(?=\W)/', '', $r);
      $r = trim($r);
      
      if (!strlen($r))
        continue;

      $elements = array();
      $classes = array();
      preg_match_all('/\S+(?=\.)/', $r, $elements, PREG_SET_ORDER);
      preg_match_all('/(?<=:)\S+/', $r, $classes, PREG_SET_ORDER);
      if (count($elements))
        $this::warning("dropped elements: `" . implode(', ', $elements[0]) . "` on rule $str");
        
      $class = array();
      preg_match('/(?<=\.).+$/s', $r, $class);
      if (!count($class))
        $this::warning("No class found on line: $r_");
      elseif(in_array($class[0], $this->active_rules))
        continue;
      else
        $this->active_rules[] = trim(strtolower($class[0]));
    }
    foreach($this->active_rules as $r)
    {
      if (!isset($this->rules[$r]))
        $this->rules[$r] = array();
    }
    return $str;
  }
}
