<?php

// as far as I know, actionscript and javascript are both derivatives of
// ECMA script, and therefore we can subclass JavaScript's scanner and just
// and override the identifier names.
// but we also override init so as to prevent any embedding

class LuminousActionScriptScanner extends LuminousJSScanner {


  function init() {
    $this->embedded_server = false;
    $this->embedded_script = false;
    parent::init();
    
    // add preprocessor support
    $this->add_pattern('PREPROCESSOR', '/\^\s*#.*/m');
    

    // clear the identifier map for JS and insert our own.
//     $this->ident_map = array();
    $this->add_identifier_mapping('', array());
    


    $this->add_identifier_mapping('FUNCTION', array('add', 'chr',
    'clearInterval', 'escape', 'eval',
    'evaluate', 'fscommand', 'getProperty', 'getTimer', 'getVersion',
    'globalStyleFormat', 'gotoAndPlay', 'gotoAndStop', 'ifFrameLoaded',
    'instanceOf', 'isFinite', 'isNaN', 'loadMovie', 'loadMovieNum',
    'loadVariables', 'mbchr', 'mblength', 'mbord', 'mbsubstring', 'nextFrame',
    'nextScene', 'onClipEvent',
    'ord', 'parseFloat', 'parseInt', 'play', 'prevFrame', 'prevScene', 'print',
     'printAsBitMap', 'printNum', 'printNum', 'random', 'scroll', 'setInterval',
    'setProperty', 'stop', 'stopDrag', 'substring', 'super', 'targetPath',
    'tellTarget', 'toString', 'toggleHighQuality', 'trace', 'unescape'));

    $this->add_identifier_mapping('TYPE', array('Accessibility',
    'Array', 'Arguments', 'Boolean',
    'Button', 'ByteArray', 'Camera', 'Color', 'Date', 'Event', 'FScrollPane',
    'FStyleFormat',
    'Function', 'int', 'Key', 'LoadVars', 'LocalConnection', 'Math',
    'Microphone', 'Mouse', 'Movieclip', 'Number', 'Object', 'Selection',
    'Sound', 'Sprite', 'String', 'System', 'TextField', 'TextFormat',
    'Timer', 'TimerEvent', 'uint', 'var',  'void', 'XML'));

    $this->add_identifier_mapping('KEYWORD', array('as', 'break',
    'case', 'catch', 'class', 'const', 'continue', 'default', 'delete',
    'do', 'else', 'extends', 'false', 'finally', 'for', 'function',
    'if', 'implements', 'import', 'in', 'instanceof', 'interface', 'internal',
    'is', 'native', 'new', 'null', 'package', 'private', 'protected', 'public',
    'return', 'super', 'switch', 'static', 'this', 'throw', 'to', 'true', 'try',
    'typeof', 'use', 'void', 'while', 'with'));
  }
}
