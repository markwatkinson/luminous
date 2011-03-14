<?php

/**
 * Vimscript
 * 
 * Credit for this goes to the 'nelstrom', the author of the following
 * SyntaxHighlighter brush for Vim. The keyword tokens anyway are a direct
 * copy.
 * 
 * https://github.com/nelstrom/SyntaxHighlighter/blob/master/scripts/shBrushVimscript.js
 * 
 * 
 * NOTE: :edit $VIMRUNTIME/syntax/vim.vim   
 * 
 */


function luminous_callback_vim_comments($str)
{
  // It pays to run the strpos checks first.
  if (strpos(substr($str, 1), '"') !== false)
    $str = preg_replace('/(?<!^)"(?>[^"]*)"/', "<STRING>$0</STRING>", $str);
  
  if (strpos($str, ':') !== false)
    $str = preg_replace('/(?<=^")((?>\W*))((?>[A-Z]\w+(?>(?>\s+\w+)*)))(:\s*)(.*)/',
      '$1<DOCTAG>$2</DOCTAG>$3<DOCSTR>$4</DOCSTR>', $str);
  return $str;
}



class LuminousGrammarVim extends LuminousGrammar
{
  // This would probably get a significant speed boost if the regex common prefixes were factored.
  
  public $keywords = array('Next',
'Print',
'XML(ent|ns)',
'abc(lear)?|abo(veleft)?|acd|ai|akm|al(eph)?|all(owrevins)|altkeymap|ambiwidth|ambw|anti(alias)?|ar(ab(ic)?(shape)?)?|arga(dd)?|argd(elete|o)|arge(dit)?|argg(lobal)?|argl(ocal)?args|argu(ment)|ari|arshape|as|ascii|au(group)?|auto(chdir|cmd|indent|read|write(all)?)?|awa?',
'bN|bNext|ba|background|backspace|backup|backupcopy|backupdir|backupext|backupskip|bad|badd|ball|balloondelay|ballooneval|balloonexpr|bd|bdelete|bdir|bdlay|bel|belowright|beval|bex|bexpr|bf|bfirst|bg|bh|bin|binary|biosk|bioskey|bk|bkc|bl|blast|bm|bmodified|bn|bnext|bo|bomb|botright|bp|bprevious|br|brea|break|breaka|breakadd|breakat|breakd|breakdel|breakl|breaklist|brewind|brk|bro|browse|browsedir|bs|bsdir|bsk|bt|bufdo|buffer|buffers|bufhidden|buflisted|buftype|bun|bunload|bw|bwipeout',
'cN|cNext|cNf|cNfile|cabc|cabclear|cad|caddb|caddbuffer|caddexpr|caddf|caddfile|cal|call|casemap|cat|catch|cb|cbuffer|cc|ccl|cclose|ccv|cd|cdpath|ce|cedit|center|cex|cexpr|cf|cfile|cfir|cfirst|cfu|cg|cgetb|cgetbuffer|cgete|cgetexpr|cgetfile|ch|change|changes|charconvert|chd|chdir|che|checkpath|checkt|checktime|ci|cin|cindent|cink|cinkeys|cino|cinoptions|cinw|cinwords|cl|cla|clast|clipboard|clist|clo|close|cm|cmap|cmapc|cmapclear|cmdheight|cmdwinheight|cmp|cms|cn|cnew|cnewer|cnext|cnf|cnfile|cno|cnoremap|co|col|colder|colo|colorscheme|columns|com|comc|comclear|command|comments|commentstring|comp|compatible|compiler|complete|completefunc|completeopt|con|conf|confirm|consk|conskey|continue|cope|copen|copy|copyindent|cot|cp|cpf|cpfile|cpo|cpoptions|cprevious|cpt|cq|cquit|cr|crewind|cscopepathcomp|cscopeprg|cscopequickfix|cscopetag|cscopetagorder|cscopeverbose|cspc|csprg|csqf|cst|csto|csverb|cuc|cul|cuna|cunabbrev|cursorcolumn|cursorline|cw|cwh|cwindow',
'debug|debugg|debuggreedy|deco|def|define|delc|delcombine|delcommand|delete|delf|delfunction|delm|delmarks|dex|dg|di|dict|dictionary|diff|diffexpr|diffg|diffget|diffoff|diffopt|diffpatch|diffpu|diffput|diffsplit|diffthis|diffu|diffupdate|dig|digraph|digraphs|dip|dir|directory|display|dj|djump|dl|dlist|do|doautoa|doautoall|doautocmd|dr|drop|ds|dsearch|dsp|dsplit|dy',
'ea|ead|eadirection|earlier|eb|echoe|echoerr|echohl|echom|echomsg|echon|ed|edcompatible|edit|ef|efm|ei|ek|el|else|elsei|elseif|em|emenu|en|enc|encoding|endf|endfo|endfor|endfunction|endif|endofline|endt|endtry|endw|endwhile|ene|enew|environment|eol|ep|equalalways|equalprg|errorbells|errorfile|errorformat|esckeys|et|event|eventignore|ex|exi|exit|expandtab|expression|exrc|exu|exusage',
'fcl|fcs|fdc|fde|fdi|fdl|fdls|fdm|fdn|fdo|fdt|fen|fenc|fencs|fex|ff|ffs|file|fileencoding|fileencodings|fileformat|fileformats|files|filetype|fillchars|fin|fina|finally|find|fini|finish|fir|first|fix|fixdel|fk|fkmap|flp|fml|fmr|fo|fold|foldc|foldclose|foldcolumn|foldd|folddoc|folddoclosed|folddoopen|foldenable|foldexpr|foldignore|foldlevel|foldlevelstart|foldmarker|foldmethod|foldminlines|foldnestmax|foldo|foldopen|foldtext|for|formatexpr|formatlistpat|formatoptions|formatprg|fp|fs|fsync|ft|fu|function',
'gcr|gd|gdefault|gfm|gfn|gfs|gfw|ghr|go|goto|gp|gr|grep|grepa|grepadd|grepformat|grepprg|gtl|gtt|guicursor|guifont|guifontset|guifontwide|guiheadroom|guioptions|guipty|guitablabel|guitabtooltip',
'ha|hardcopy|help|helpf|helpfile|helpfind|helpg|helpgrep|helpheight|helplang|helpt|helptags|hf|hh|hi|hid|hidden|hide|highlight|his|history|hk|hkmap|hkmapp|hkp|hl|hlg|hls|hlsearch',
'iabc|iabclear|ic|icon|iconstring|if|ignorecase|ij|ijump|il|ilist|im|imactivatekey|imak|imap|imapc|imapclear|imc|imcmdline|imd|imdisable|imi|iminsert|ims|imsearch|inc|include|includeexpr|incsearch|inde|indentexpr|indentkeys|indk|inex|inf|infercase|ino|inoremap|insertmode|is|isearch|isf|isfname|isi|isident|isk|iskeyword|isp|isplit|isprint|iuna|iunabbrev',
'join|joinspaces|js|ju|jumps',
'kee|keepalt|keepj|keepjumps|keepmarks|key|keymap|keymodel|keywordprg|km|kmp|kp',
'lN|lNext|lNf|lNfile|la|lad|laddb|laddbuffer|laddexpr|laddf|laddfile|lan|langmap|langmenu|language|last|laststatus|later|lazyredraw|lb|lbr|lbuffer|lc|lcd|lch|lchdir|lcl|lclose|lcs|le|left|lefta|leftabove|let|lex|lexpr|lf|lfile|lfir|lfirst|lg|lgetb|lgetbuffer|lgete|lgetexpr|lgetfile|lgr|lgrep|lgrepa|lgrepadd|lh|lhelpgrep|linebreak|lines|linespace|lisp|lispwords|list|listchars|ll|lla|llast|lli|llist|lm|lmak|lmake|lmap|lmapc|lmapclear|ln|lne|lnew|lnewer|lnext|lnf|lnfile|lnoremap|lo|loadplugins|loadview|loc|lockmarks|lockv|lockvar|lol|lolder|lop|lopen|lp|lpf|lpfile|lpl|lprevious|lr|lrewind|ls|lsp|ltag|lv|lvimgrep|lvimgrepa|lvimgrepadd|lw|lwindow|lz',
'ma|maca|macaction|macatsui|macm|macmenu|magic|mak|make|makeef|makeprg|map|mapping|mark|marks|mat|match|matchpairs|matchtime|maxcombine|maxfuncdepth|maxmapdepth|maxmem|maxmempattern|maxmemtot|mco|mef|menu|menuitems|menut|menutranslate|mfd|mh|mis|mk|mkexrc|mks|mksession|mksp|mkspell|mkspellmem|mkv|mkvie|mkview|mkvimrc|ml|mls|mm|mmd|mmp|mmt|mod|mode|modeline|modelines|modifiable|modified|more|mouse|mousef|mousefocus|mousehide|mousem|mousemodel|mouses|mouseshape|mouset|mousetime|move|mp|mps|msm|mz|mzf|mzfile|mzq|mzquantum|mzscheme',
'nbkey|new|next|nf|nm|nmap|nmapc|nmapclear|nn|nnoremap|no|noexpandtab|noh|nohlsearch|noremap|nrformats|nu|number|numberwidth|nuw',
'odev|oft|ofu|om|omap|omapc|omapclear|omnifunc|on|only|ono|onoremap|open|opendevice|operatorfunc|opfunc|opt|option|options|osfiletype',
'pa|para|paragraphs|paste|pastetoggle|patchexpr|patchmode|path|pc|pclose|pdev|pe|ped|pedit|penc|perl|perld|perldo|pex|pexpr|pfn|ph|pheader|pi|pm|pmbcs|pmbfn|po|pop|popt|popu|popup|pp|ppop|pre|preserve|preserveindent|prev|previewheight|previewwindow|previous|print|printdevice|printencoding|printexpr|printfont|printheader|printmbcharset|printmbfont|printoptions|prof|profd|profdel|profile|prompt|promptf|promptfind|promptr|promptrepl|ps|psearch|pt|ptN|ptNext|pta|ptag|ptf|ptfirst|ptj|ptjump|ptl|ptlast|ptn|ptnext|ptp|ptprevious|ptr|ptrewind|pts|ptselect|pu|pumheight|put|pvh|pvw|pw|pwd|py|pyf|pyfile|python',
'qa|qall|qe|quit|quita|quitall|quoteescape',
'rdt|read|readonly|rec|recover|red|redi|redir|redo|redr|redraw|redraws|redrawstatus|redrawtime|reg|registers|remap|report|res|resize|restorescreen|ret|retab|retu|return|revins|rew|rewind|ri|right|rightb|rightbelow|rightleft|rightleftcmd|rl|rlc|ro|rs|rtp|ru|rub|ruby|rubyd|rubydo|rubyf|rubyfile|ruf|ruler|rulerformat|runtime|runtimepath|rv|rviminfo',
'sN|sNext|sa|sal|sall|san|sandbox|sargument|sav|saveas|sb|sbN|sbNext|sba|sball|sbf|sbfirst|sbl|sblast|sbm|sbmodified|sbn|sbnext|sbo|sbp|sbprevious|sbr|sbrewind|sbuffer|sc|scb|scr|scrip|scripte|scriptencoding|scriptnames|scroll|scrollbind|scrolljump|scrolloff|scrollopt|scs|se|sect|sections|secure|sel|selection|selectmode|sessionoptions|set|setf|setfiletype|setg|setglobal|setl|setlocal|sf|sfind|sfir|sfirst|sft|sh|shcf|shell|shellcmdflag|shellpipe|shellquote|shellredir|shellslash|shelltemp|shelltype|shellxquote|shiftround|shiftwidth|shm|shortmess|shortname|showbreak|showcmd|showfulltag|showmatch|showmode|showtabline|shq|si|sidescroll|sidescrolloff|sign|sil|silent|sim|simalt|siso|sj|sl|sla|slast|sleep|slm|sm|smagic|smap|smapc|smapclear|smartcase|smartindent|smarttab|smc|smd|sme|smenu|sn|snext|sni|sniff|sno|snomagic|snor|snoremap|snoreme|snoremenu|so|softtabstop|sol|something|sor|sort|source|sp|spc|spe|spell|spellcapcheck|spelld|spelldump|spellfile|spellgood|spelli|spellinfo|spelllang|spellr|spellrepall|spellsuggest|spellu|spellundo|spellw|spellwrong|spf|spl|split|splitbelow|splitright|spr|sprevious|sps|sr|sre|srewind|srr|ss|ssl|ssop|st|sta|stag|stal|star|startg|startgreplace|startinsert|startofline|startr|startreplace|statusline|stj|stjump|stl|stmp|stop|stopi|stopinsert|sts|stselect|su|sua|suffixes|suffixesadd|sun|sunhide|sunme|sunmenu|sus|suspend|sv|sview|sw|swapfile|swapsync|swb|swf|switchbuf|sws|sxq|syn|syncbind|synmaxcol|syntax',
'tN|tNext|ta|tab|tabN|tabNext|tabc|tabclose|tabd|tabdo|tabe|tabedit|tabf|tabfind|tabfir|tabfirst|tabl|tablast|tabline|tabm|tabmove|tabn|tabnew|tabnext|tabo|tabonly|tabp|tabpagemax|tabprevious|tabr|tabrewind|tabs|tabstop|tag|tag_listfiles|tagbsearch|taglength|tagrelative|tags|tagstack|tal|tb|tbi|tbidi|tbis|tbs|tc|tcl|tcld|tcldo|tclf|tclfile|te|tearoff|tenc|term|termbidi|termencoding|terse|textauto|textmode|textwidth|tf|tfirst|tgst|th|thesaurus|throw|tildeop|timeout|timeoutlen|title|titlelen|titleold|titlestring|tj|tjump|tl|tlast|tm|tmenu|tn|tnext|to|toolbar|toolbariconsize|top|topleft|tp|tpm|tprevious|tr|trewind|try|ts|tselect|tsl|tsr|ttimeout|ttimeoutlen|ttm|tty|ttybuiltin|ttyfast|ttym|ttymouse|ttyscroll|ttytype|tu|tunmenu|tw|tx',
'uc|ul|una|unabbreviate|undo|undoj|undojoin|undol|undolevels|undolist|unh|unhide|unl|unlet|unlo|unlockvar|up|update|updatecount|updatetime|ut',
'var|vb|vbs|vdir|ve|verb|verbose|verbosefile|version|vert|vertical|vfile|vi|vie|view|viewdir|viewoptions|vim|vimgrep|vimgrepa|vimgrepadd|viminfo|virtualedit|visual|visualbell|viu|viusage|vm|vmap|vmapc|vmapclear|vn|vne|vnew|vnoremap|vop|vs|vsplit',
'wN|wNext|wa|wak|wall|warn|wb|wc|wcm|wd|weirdinvert|wfh|wfw|wh|whichwrap|while|wi|wig|wildchar|wildcharm|wildignore|wildmenu|wildmode|wildoptions|wim|win|winaltkeys|winc|wincmd|windo|window|winfixheight|winfixwidth|winheight|winminheight|winminwidth|winp|winpos|winsize|winwidth|wiv|wiw|wm|wmh|wmnu|wmw|wn|wnext|wop|wp|wprevious|wq|wqa|wqall|wrap|wrapmargin|wrapscan|write|writeany|writebackup|writedelay|ws|wsverb|wv|wviminfo|ww',

'xa(ll)?|xit|xm(ap(c(lear)?)?|enu)|xn|xnremap|xnoreme(nu)?|xunme(nu)?',
'yank'

    );
    
  
  // http://vimdoc.sourceforge.net/htmldoc/eval.html#functions
    
  public $functions = array('abs|add|append|argc|argidx|argv|atan',
    'browse|browsedir|bufexists|buflisted|bufloaded|bufname|bufnr|bufwinnr|byte2line|byteidx',
    'call|ceil|changenr|char2nr|cindent|clearmatches|col|complete|complete_add|complete_check|confirm|copy|cos|count|cscope_connection|cursor',
    'deepcopy|delete|did_filetype|diff_filler|diff_hlID',
    'empty|escape|eval|eventhandler|executable|exists|expand|expr8|extend',
    'feedkeys|filereadable|filewritable|filter|finddir|findfile|float2nr|floor|fnameescape|fnamemodify|foldclosed|foldclosedend|foldlevel|foldtext|foldtextresult|foreground|function',
    'garbagecollect|get|getbufline|getbufvar|getchar|getcharmod|getcmdline|getcmdpos|getcmdtype|getcwd|getfontname|getfperm|getfsize|getftime|getftype|getline|getloclist|getmatches|getpid|getpos|getqflist|getreg|getregtype|gettabwinvar|getwinposx|getwinposy|getwinvar|glob|globpath',
    'has|has_key|haslocaldir|hasmapto|histadd|histdel|histget|histnr|hlID|hlexists|hostname',
    'iconv|indent|index|input|inputdialog|inputlist|inputrestore|inputsave|inputsecret|insert|isdirectory|islocked|items',
    'join',
    'keys',
    'len|libcall|libcallnr|line|line2byte|lispindent|localtime|log10',
    'map|maparg|mapcheck|match|matchadd|matcharg|matchdelete|matchend|matchlist|matchstr|max|min|mkdir|mode',
    'nextnonblank|nr2char',
    'pathshorten|pow|prevnonblank|printf|pumvisible',
    'range|readfile|reltime|reltimestr|remote_expr|remote_foreground|remote_peek|remote_read|remote_send|remove|rename|repeat|resolve|reverse|round',
    'search|searchdecl|searchpair|searchpairpos|searchpos|server2client|serverlist|setbufvar|setcmdpos|setline|setloclist|setmatches|setpos|setqflist|setreg|settabwinvar|setwinvar|shellescape|simplify|sin|sort|soundfold|spellbadword|spellsuggest|split|sqrt|str2float|str2nr|strftime|stridx|string|strlen|strpart|strridx|strtrans|submatch|substitute|synID|synIDattr|synIDtrans|synstack|system',
    'tabpagebuflist|tabpagenr|tabpagewinnr|tagfiles|taglist|tempname|tolower|toupper|tr|trunc|type',
    'values|virtcol|visualmode',
    'winbufnr|wincol|winheight|winline|winnr|winrestcmd|winrestview|winsaveview|winwidth|writefile'
    );
  
  public function __construct()
  {
    $this->SetInfoAuthor( 
      array('name'=>'Mark Watkinson', 
            'email'=>'markwatkinson@gmail.com',
            'website'=>'http://www.asgaard.co.uk')
    );
    $this->SetInfoLanguage('vim');
    $this->SetInfoVersion('r657');
    
    $this->delimited_types = array(
      
      new LuminousDelimiterRule(0, 'COMMENT', LUMINOUS_COMPLETE|LUMINOUS_REGEX,
        '/^\s*".*?$/m', null, 'luminous_callback_vim_comments'),
      
      new LuminousDelimiterRule(0, 'STRING', LUMINOUS_REGEX|LUMINOUS_COMPLETE,
      '/".*?(?<!\\\\)(?:\\\\\\\\)*"/'),
      
      new LuminousDelimiterRule(0, 'STRING', LUMINOUS_REGEX|LUMINOUS_COMPLETE,
      "/'.*?'/"),
      
      new LuminousDelimiterRule(0, 'COMMENT', LUMINOUS_REGEX|LUMINOUS_COMPLETE,
      "/(?<!\")\"[^\"\n]*$/mx", null, 'luminous_callback_vim_comments'),
      
      new LuminousDelimiterRule(0, 'TYPE', LUMINOUS_COMPLETE|LUMINOUS_REGEX,
        '/(&lt;)\w+[-\w\[\]]+(&gt;)/'),
      new LuminousDelimiterRule(0, 'PREPROCESSOR', 
        LUMINOUS_COMPLETE|LUMINOUS_REGEX, '/\s*#.*$/m')
    );
    $this->SetSimpleTypeRules();
    
    
  }
}