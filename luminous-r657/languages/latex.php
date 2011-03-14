<?php
class LuminousGrammarLatex extends LuminousGrammar
{
  
  public $type_regex = "/\\\(%TYPE)/";
  public $types = array('alpha', 'theta', 'tau', 
  'beta', 'vartheta', 'pi', 'upsilon', 
  'gamma', 'gamma', 'varpi', 'phi', 
  'delta', 'kappa', 'rho', 'varphi', 
  'epsilon', 'lambda', 'varrho', 'chi', 
  'varepsilon', 'mu', 'sigma', 'psi',
  'zeta', 'nu', 'varsigma', 'omega', 
  'eta', 'xi', 
  'Gamma', 'Lambda', 'Sigma', 'Psi',
  'Delta', 'Xi', 'Upsilon', 'Omega', 
  'Theta', 'Pi', 'Phi');
  

  
  public $operators = array();
  
  public $numeric_regex = null;
  
  public function __construct()
  {
    $this->SetInfoLanguage('latex');
    $this->SetInfoAuthor( 
    array('name'=>'Mark Watkinson', 'email'=>'markwatkinson@gmail.com',
    'website'=>'http://www.asgaard.co.uk'));
    $this->SetInfoVersion('r657');
    
    $this->delimited_types = array(
      new LuminousDelimiterRule(0, 'COMMENT', LUMINOUS_REGEX|LUMINOUS_COMPLETE, 
        '/(?<!\\\)%.*?$/m'),
        
      // this definitely shouldn't be variable, it's the range of a 
      // square bracket, but not nested ones
      new LuminousDelimiterRule(3, 'VARIABLE', LUMINOUS_REGEX|LUMINOUS_COMPLETE,
        '/(?<=[^\\\]\[)(?:[^\[\\\]+?)(?=(\]))/', null, null),
      
      // Curley brace
      new LuminousDelimiterRule(3, 'VALUE', LUMINOUS_REGEX|LUMINOUS_COMPLETE,
        '/(?<=[^\\\]\{)(?:[^\{\\\]+?)(?=(\}))/', null, null)
     );
     


    $this->SetSimpleTypeRules();
    
    $keyword_regex = "/\\\(%KEYWORD)/";
    
    // I'm trying to keep this more oriented towards common layout and 
    // formatting commands, not all the maths stuff, will be caught by the 
    // general rule.
    // But I haven't used LaTeX much, and when I did it was always for maths
    // (the irony).
    $keywords = array('@', 'begin', 'DisableLigatures', 'end', 'emph', 
      'fbox', 'footnotesize', 'frenchspacing', '[hH]uge', 'hyphonation', 
      '[lL]arge', 'LARGE', 'ldots', 'mathrm', 'mbox', 'newcommand', 'normalem', 
      'normalsize', 'oldstylenums', 'scriptsize', 'small', 'sout', 'tiny', 
      'text(normal|rm|sf|tt|up|it|sl|sc|bf|md|(super|sub)script|color)', 
      'ulem', 'underline', 'usepackage', 'uwave');
  
    $this->simple_types[] = new LuminousSimpleRuleList(2, 'LATEX_FUNCTION',
      LUMINOUS_REGEX, $keywords, $keyword_regex, '%KEYWORD');
    

    
    
    // kind of a generic thing to catch everything else.
    $this->simple_types[] = 
      new LuminousSimpleRule(3, 'FUNCTION', LUMINOUS_REGEX, 
        '/
          (?:\\\)
          (?:
            (?:[[:alnum:]]+)
            | [\-\\\\{\}%]
            
            )
            
        /x');
        
    $this->simple_types[] = new LuminousSimpleRuleList(4, 'LATEX_OPERATOR', 
      LUMINOUS_REGEX, array('\[', '\]', '\{', '\}'), '/(%OP)+/', '%OP');
  }
  
}