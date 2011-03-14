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


// keep these 1 per line, it's updated automagically by updateversion.sh
var luminous_version = 'r657';
var luminous_date = '12/03/11';

var luminous_initialised = false,
    luminous_fixed_bar = false,
    metabar_buttons = '<span class="metabar_buttons">\
<a href=# class="link luminous_uncollapse_clicker" title="Expand">\
&nbsp;</a>\
<a href=# class="link luminous_search_clicker" title="Search">\
&nbsp;</a>\
<a href=# class="link zoomout luminous_zoomout_clicker" title="Font size--">\
&nbsp;</a>\
<a href=# class="link zoomin luminous_zoomin_clicker" title="Font size++">\
&nbsp;</a>\
<a href=# class="link unhighlight luminous_plain_clicker" title="Toggle \
Highlighting">&nbsp;</a>\
<a href=# class="link print luminous_print_clicker" title="Print">&nbsp;</a>\
<a href=# class="link info luminous_info_clicker" title="Highlighting info">\
&nbsp;</a>\
</span>';


function luminous_exp_collapse(luminous)
{
  var cc = jQuery('div.code_container', luminous),
      sh, 
      st;
  jQuery('.luminous_uncollapse_clicker', luminous).toggleClass('luminous_collapse_clicker');
  
  
  if (luminous.data('collapsed'))
  { 
    sh = jQuery(window).scrollTop();
    sh -= cc.offset().top;
    
    cc.css('max-height', luminous.data('max-height')).css('overflow', 'auto');    
    luminous.data('collapsed', false);
    jQuery('.luminous_uncollapse_clicker', luminous).attr('title', 'Expand');
    
    window.scrollTo(window.scrollLeft, cc.offset().top);
    cc.scrollTop(sh);
  }
  
  else
  {
    st = cc[0].scrollTop;
    st += cc.offset().top;
    
    cc[0].scrollTop = 0;    
    
    luminous.data('max-height', cc.css('max-height')).data('collapsed', true);
    
    cc.css('max-height', 'none').css('overflow', 'visible');    
    jQuery('.luminous_uncollapse_clicker', luminous).attr('title', 'Collapse');
    
    window.scrollTo(window.scrollLeft, st);
    
    
  }
}

function luminous_search_open(luminous)
{
  jQuery(luminous).data('search_index', 0);
  var search = jQuery('<input type="text" class="luminous_search"></input>'),
      search_container = jQuery('<div class="luminous_search_container">\
<a href=# class="link luminous_close_search_clicker luminous_clicker">&nbsp</a></div>'),
      pos;
  
  jQuery('.luminous_close_search_clicker', search_container).click(
    function() { luminous_close_search(luminous_root(this)); 
      return false;
    });
  
  search.keypress(function(e){
    if (e.keyCode == 13)
      luminous_search_submit(luminous_root(jQuery(this)), this.value)
    else if(e.keyCode == 27)
      luminous_close_search(luminous_root(jQuery(this)));
    return true;
  });
  pos = luminous_get_tr_position(luminous);
  pos.left -= 15;
  pos.top += 35;
  search_container.prepend(search);
  
  search.css('width', '98px'); //2 for border
  luminous.prepend(search_container);  
  
  search_container.css('left', (pos.left - search_container.width()) + 'px').css('top', pos.top + 'px');
  search.focus();
}

function _luminous_search()
{
  var element = jQuery('.luminous_active_search');
  if (!element.length)
    return;
  var luminousroot = luminous_root(element),
      search_data = element.data('search_data'),
      starti = search_data.i;
  while (search_data.i < search_data.html.length && search_data.i - starti < 1024*10)
  {
    var c = search_data.html.charAt(search_data.i),
        remaining = search_data.html.substr(search_data.i),
        x;
    if (c == '<')
    {
      x = remaining.search('>') + 1;
      if (!search_data.matching)
        search_data.html_new += remaining.substr(0, x);
      search_data.i+=x;
      continue;
    }
    else if (c == '&')
    {
      x = remaining.search(';') + 1;
      if (!search_data.matching)
        search_data.html_new += remaining.substr(0, x);
      search_data.i+=x;
      continue;
    }
    
    if (!search_data.matching && search_data.searchstr.charAt(0) != c)
    {
      var next = remaining.indexOf(search_data.searchstr.charAt(0));
      if (next == -1)
      {
        search_data.html_new += remaining;
        search_data.i = search_data.html.length;
        break;
      }
      var r2 = remaining.substr(next),
          close = r2.indexOf('>'),
          open = r2.indexOf('<'),
          next_i = next;
          
      if ((close != -1 && open == -1) ||  (close != -1 && open != -1 && close < open))      
        next_i = next + close;
        
      var abs_i = search_data.i + next_i,
          min = Math.max(0, abs_i-5),
          preceding = search_data.html.substr( min, abs_i-min);
      if (preceding.search(/&[^;]*$/) != -1)
        next_i = next + r2.search(';'); 
      
      search_data.i += next_i;
      search_data.html_new += remaining.substr(0, next_i);
      continue;
    }
    
    match_len = search_data.sub_match.length;
    var failed = false;
    if (!search_data.matching)
    {
      if (c == search_data.searchstr.charAt(0))
      {
        search_data.open = search_data.i;
        search_data.sub_match = c;
        search_data.matching = true;
      }
    }
    else if (search_data.matching)
    {
      if (c == search_data.searchstr.charAt(match_len))
      {
        search_data.sub_match += c;
        search_data.matching = true;
      }
      else
      {
        failed = true;
        search_data.matching = false;
      }
    }
    
    if (search_data.matching)
    {
      if (search_data.sub_match.length == search_data.searchstr.length)
      {
        var subtext, replace;
        subtext = search_data.html.substr(search_data.open, search_data.i+1-search_data.open);
        subtext = subtext.replace(/(<span.*?>)/gi, "</hl>$1<hl>");
        subtext = subtext.replace(/(<\/span.*?>)/gi, "</hl>$1<hl>");
        subtext = subtext.replace(/<hl>/g, "<span class='user_highlight'>");
        subtext = subtext.replace(/<\/hl>/g, "</span>");
        replace = "<span class='user_highlight'>" + subtext + "</span>";
        search_data.html_new += replace;
        search_data.sub_match = "";
        search_data.matching = false;
      }
    }
    else if (failed)
    {
      var subtext = search_data.html.substr(search_data.open, search_data.i-search_data.open);
      search_data.html_new += subtext;
      search_data.html_new += c;
      search_data.sub_match = "";
      search_data.matching = false;
    }
    else 
    {
      search_data.html_new += c;
    }
    
    search_data.i++;
  }
  if (search_data.i >= search_data.html.length)    
  {    
    // because IE is stupid
    var select = (search_data.plain)? 'plain' : 'highlighted';
    
    $new = jQuery('<pre class="code ' + select + '">' + search_data.html_new + '</pre>');
    jQuery('pre.' + select, luminousroot).replaceWith($new);
    
    jQuery('div.searching', luminousroot).remove();
    element.toggleClass('luminous_active_search');
  }
  else
  {
    jQuery('span.search_progress', luminousroot).html(
      Math.round(search_data.i/search_data.html.length * 100) + "%");
   
    jQuery('.luminous_search_container', luminousroot).data('search_data', search_data);
    setTimeout(function() { _luminous_search(); }, 100);
  }
}
  

function luminous_search_submit(luminous, searchstr)
{
  luminous_search_reset(luminous);
  searchstr = searchstr.replace(/&/g, '&amp;');
  searchstr = searchstr.replace(/</g, '&lt;');
  searchstr = searchstr.replace(/>/g, '&gt;');
  var plain = luminous.data('code_view') == 'plain';
//   alert(searchstr);
  if (!searchstr.length)
    return;
  var search_data = 
  {
    i:0,
    plain : plain,
    html:  (plain)? jQuery('pre.plain', luminous).html() : 
      jQuery('pre.highlighted', luminous).html(),
    html_new : "",
    sub_match : "",
    open : 0,
    matching : false,  
    searchstr : searchstr
  };
//   alert(search_data.plain);
  
  jQuery('.luminous_search_container', luminous).toggleClass('luminous_active_search').append(
    jQuery('<div class="searching"><span class="search_progress"></span> <a href="#" class="luminous_search_cancel">Cancel</a></div>'));
  jQuery('.luminous_search_container', luminous).data('search_data', search_data);
  
  jQuery('.luminous_search_cancel', luminous).click(function(){
    var root = luminous_root(this);
    jQuery('.luminous_active_search', root).toggleClass('luminous_active_search');
    jQuery('div.searching', root).remove();
    luminous_close_search(root);
    return false;
  });  
  
  _luminous_search();
}

function luminous_search_reset(luminous)
{
  jQuery('.luminous_active_search').toggleClass('luminous_active_search');
  jQuery('.user_highlight', luminous).each( function(i, e){
    jQuery(e).replaceWith(jQuery(e).html())
  });
}

function luminous_close_search(luminous)
{
  luminous_search_reset(luminous);
  jQuery('.luminous_search_container', luminous).fadeOut(250,
    function (){ jQuery(this).remove() }
  );
}


function luminous_show_info(luminous)
{  
  
  var l = jQuery('.luminous_info', jQuery(luminous));
  
  if (!l.length)
    luminous_attach_infopane(luminous);
  jQuery('.luminous_info', jQuery(luminous)).fadeIn(500);   
  return false; 
}

function luminous_close_info(luminous)
{

  
  if (jQuery.browser.msie)
    jQuery('.luminous_info', jQuery(luminous)).hide();
  else
    jQuery('.luminous_info', jQuery(luminous)).fadeOut(500);
  luminous_show_buttons(luminous);
  return false; 
}


function luminous_get_tr_position(luminous)
{
  var luminous_pos = luminous.offset(),
      parents = jQuery(luminous).parents(),
      i,
      st = jQuery(window).scrollTop(),
      offset,
      p;  
  for (i=0; i<parents.length; i++)
  {
    p = jQuery(parents[i]);
    if (p.is('body'))
      break;
    luminous_pos.top += p.scrollTop();
  }
  
  luminous_pos.top -= st;
  
  offset = luminous_pos;
  
  offset.left = luminous_pos.left += luminous.width();;
  offset.left -= jQuery(window).scrollLeft();
  
  offset.top = Math.max(offset.top, 0);  
  return offset;
}

function luminous_show_buttons(luminous)
{
  if (luminous_fixed_bar)
    return;
  // IE as usual doesn't work
  if (jQuery.browser.msie && jQuery.browser.version <= 7)
  {
    jQuery('.metabar_buttons', luminous).css('position', 'absolute').css('right', '5px');
  }
  else
  {

    var metabar_width = jQuery('.metabar_buttons', luminous).width();
    var offset = luminous_get_tr_position(luminous);
    
    offset.top += 10;
    offset.left -= metabar_width + 20;
    
    jQuery('.metabar_buttons', luminous).css('top', offset.top + 'px').css('left', offset.left + 'px');
  }
  jQuery('.metabar_buttons', luminous).show();
  
}
function luminous_hide_buttons(luminous)
{
  if (luminous_fixed_bar)
    return;
  jQuery('.metabar_buttons', luminous).fadeOut(100);
}

function luminous_toggle_plain(luminous)
{
  var code = jQuery('pre.highlighted', luminous).filter(function(x){ return !jQuery(this).hasClass('plain')} );
  
  if (!jQuery('pre.plain', luminous).length)
  {
    var content = code.html();
    var plain = content.replace(/<.*?>/ig, "");
    // Creating the pre element with full contents works around an IE bug
    // where white space isn't re-evaluated for changing content.
    jQuery('pre.code', luminous).parent().prepend(jQuery('<pre class="code plain">'
      + plain + '</pre>').hide());
  }
  // filter to just the code object
  plain = jQuery('pre.plain', luminous);
  jQuery('.luminous_plain_clicker', luminous).toggleClass('highlight unhighlight');
  
  /* The transition looks better faded, but text fading has some caveats with
   * IE so for IE we go with the simpler option */
  if (1 || jQuery.browser.msie)
  {    
    if (jQuery(code).css('display') == 'none')
      luminous.data('code_view', 'highlighted');
    else
      luminous.data('code_view', 'plain');
    code.add(plain).toggle();
    return;
  }
  
  var cb = function(){jQuery(this).css('position', 'relative')};
  plain.css('position', 'absolute');
  code.css('position', 'absolute');
  if (jQuery(code).css('display') == 'none')
  {
    code.fadeIn(500, cb);
    plain.fadeOut(500);
    luminous.data('code_view', 'highlighted');
  }
  else
  {
    code.fadeOut(500);
    plain.fadeIn(500, cb);
    luminous.data('code_view', 'plain');
  }
}

function luminous_root(element)
{
  while (element != [] && !jQuery(element).hasClass('luminous'))
    element = jQuery(element).parent();
  return element;
}


function luminous_print_arrange_numbered(element)
{
  var $linenos = jQuery('span.line_number', jQuery(element)),
      line_nos_text = '',      
      i,
      $lines = jQuery('span.line', jQuery(element)),
      table,
      line_nos,
      len_lines,
      lines_text = '';
  
  for (i=0; i<$linenos.length; i++)
    line_nos_text += jQuery($linenos[i]).html().replace(/<.*?>/g, "") + "\n";
  
  for (var i=0; i<$lines.length; i++)
    lines_text += jQuery($lines[i]).html() + "\n";
  
  table = "<ol>";
  lines = lines_text.split("\n");
  line_nos = line_nos_text.split("\n");
  
  len_lines = lines.length;
  
  for (i=0; i<len_lines; i++)
  {
    table += "<li style='white-space:pre-wrap;'>" + lines[i];
    var j = i+1,
        x;
    while (j<len_lines && (x = line_nos[j].match(/\d+/)) == null)
    {
      table += lines[j++];
      i++;
    }
    table += "</li>";
  }
  table += "</ol>";
  return table;
}

function luminous_print_arrange_non_numbered(element)
{
  return jQuery('pre.code', jQuery(element)).html();
}

function luminous_print(element)
{
  var html = '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">\
<html>\
<head>\
' + (jQuery('title')? ('<title>' + jQuery('title').html() + '</title>') : '') + '\
',
      stylesheets = jQuery('link'),
      href_to_theme = '',
      i,
      href,
      lines,
      $el,
      w;
      
  // we want the URI to the print stylesheet, which we guess by grepping for the 
  // main layout stylesheet and using its path
  for(i=0; i<stylesheets.length; i++)
  {
    href = stylesheets[i].href;
    if (href.match(/\/luminous(?:\.min)?\.css$/i))
      href_to_theme = href.replace(/\/luminous.css$/, "/luminous_light.css");
    // not interested in other styles
    else 
      continue;
    html += "<link rel='stylesheet' type='text/css' href='" 
            + stylesheets[i].href + "'>\n";
  }
  html += "<link rel='stylesheet' type='text/css' href='" + href_to_theme + "'>\
<link rel='stylesheet' type='text/css' href='" + href_to_theme.replace(/_light.css$/, '_print.css') + "'>\
<style type='text/css'>div.luminous { font-size: " + jQuery(element).css('font-size') + " !important;}\
</style>\
</head>\n<body onload='window.print();'>\
<div class='luminous'>";
  
  lines = jQuery('td.line_number_bar', jQuery(element)).length? 
    luminous_print_arrange_numbered(element) : 
    luminous_print_arrange_non_numbered(element);
    
  $el = jQuery(element).clone();
  $cc_contents = jQuery('<pre class=code>' + lines + '</pre>');
  jQuery('div.code_container', $el).html($cc_contents);
  jQuery('div.code_container', $el).css('max-height', 'none');
    
  html += $el.html();
  html += "</div></body>\n</html>\n";
  
  w = window.open();
  w.top.document.open();
  w.top.document.write(html);
  w.document.close();
}

function luminous_get_info_element(condensed)
{
  var element;
  if (condensed === true)
  {
    element = jQuery("<span class='code luminous_info'>\
    Highlighting peformed by Luminous.\
    <a class=link href='http://www.asgaard.co.uk/p/Luminous' \
    target=_blank>More info</a> | <a href=# class='luminous_info_close link'>\
    Close</a></span>");
  }
  else
  {
    element = jQuery("<span class='luminous_info code' style='padding-top:1em'>\
    <h1 style='text-align:center; margin-top:0;' class='keyword'>\
    Luminous " + luminous_version + 
  " " + luminous_date + " </h1>\
  <p>This source was highlighted with <span class='keyword'>Luminous</span>. Visit \
  <a class=link href='http://code.google.com/p/luminous/' target=_blank>\
  http://code.google.com/p/luminous/</a> for the latest \
  stable and development versions, and \
  <a class=link \
  href=http://www.asgaard.co.uk/p/Luminous \
  target=_blank>\
  http://www.asgaard.co.uk/p/Luminous</a>\
  for more information</p>\
    <p><span class='keyword'>Luminous</span> is free software (GPL)</p>\
    <p><span class='comment'>&copy; 2010 Mark Watkinson\
    <em>&lt;markwatkinson@gmail.com&gt;</em></span></p>\
    <p><a href=# class='luminous_info_close link'\
    >Close</a></p>\
    </span>"
    );
  }
  jQuery('.luminous_info_close', element).click( function(e){
    luminous_close_info((luminous_root((e.target))));
    return false;
  });    
  return element;
}


function luminous_attach_infopane(luminous)
{
  jQuery(luminous).prepend(
    luminous_get_info_element(jQuery(luminous).height() < 100)
  );
 

}


function luminous_determine_id(target)
{
  while (target && !jQuery(target).hasClass('luminous'))
    target = target.parentNode;
  if (!target)
    return -1;
  return parseInt(target.id.match(/\d+$/));
}






function luminous_bind(luminous)
{
  
  
  var $l = jQuery(luminous),
      bar,
      floating;
  
  if ($l.data('luminous') == true)
    return;
  
  $l.data('luminous', true);    
  
  jQuery('pre.code', $l).addClass('highlighted')
  
  bar = jQuery(metabar_buttons);
  
  jQuery(bar).bind('mousedown', function(e){ return false;} );  
  
  floating = !(jQuery.browser.msie && jQuery.browser.version <= 7);
  if (floating)
  {
    jQuery(bar).hide();
    jQuery(bar).addClass('metabar_buttons_floating');
    
    jQuery('div.luminous').bind('mouseenter', function(e) {
      luminous_show_buttons(luminous_root(e.target));
      
    });
    jQuery('div.luminous').bind('mouseleave', function(e) {  
      luminous_hide_buttons(luminous_root(e.target));
    });
  }
  else
  {    
    bar.addClass('metabar_buttons_fixed');
    bar = jQuery('<div class="metabar"></div>').append(bar);
  }
  
  
  $l.prepend(bar);
  
  if (!floating)
  {
   
    var len = jQuery('pre.highlighted', $l).html().replace(/<.*?>/g, "").length,
        nos = jQuery('pre.line_numbers', $l).children(),
        num_lines = null,
        h,
        parents,
        width,
        i,
        diff;
    if (nos.length)
    {
      for (i=nos.length-1; i >= 0; i--)
      {
        num_lines = parseInt(jQuery(nos[i]).html().replace(/&.*?;|\s/g, ''));
        if (!isNaN(num_lines))
          break;
      }
    }
    if (isNaN(num_lines) || num_lines == null)
      num_lines = jQuery('pre.highlighted', $l).html().match(/\n/g).length;
    
    len /= 1024.0;
    len = "" + len.toFixed(2);
    
    h = $l.find('.metabar').html();
    
    
    $l.find('.metabar').prepend(
      jQuery("<span>Source code | " + num_lines + " lines | " + len + " KiB</span>").css('margin-left', '2px').css('margin-top', '2px').css('display', 'inline-block')
                        );
    parents = $l.parents();
    
    width = jQuery(parents[0]).width(); 
    for(i=1; i<parents.length; i++)
      width = Math.min(width, jQuery(parents[i]).width());
    diff = $l.width() - width;
    if (diff > 0)
      $l.find('.metabar_buttons').css('right', diff + 'px');
  }
  
  
  
  
  var $cc = jQuery('div.code_container', $l).get(0);
  if (typeof $cc == 'undefined')
    return;
    
  var sh =  $cc.scrollHeight,
      h =  $cc.clientHeight,
      sw =  $cc.scrollWidth,
      w =  $cc.clientWidth;
  if (sh <= h && sw <= w)
    jQuery('.luminous_uncollapse_clicker', $l).hide();
  
    
    
  jQuery('.luminous_uncollapse_clicker', $l).click(function(e) {
    luminous_exp_collapse(luminous_root((e.target)));
    return false;
  });
  
  
  
  jQuery('.luminous_search_clicker', $l).click(function(e) {
    luminous_search_open(luminous_root((e.target)));
    return false;
  });
  
  jQuery('.luminous_info_clicker', $l).click(function(e) {
    luminous_show_info(luminous_root((e.target)));
    luminous_hide_buttons(luminous_root((e.target)));    
    return false;
  });
  jQuery('.luminous_print_clicker', $l).click(function(e) {
    luminous_print(luminous_root((e.target)));
    luminous_hide_buttons(luminous_root((e.target)));
    return false;
  });

  jQuery('.luminous_plain_clicker', $l).click(function(e) {
    luminous_toggle_plain(luminous_root((e.target)));
    return false;
  });
  
  var resize_func = function(up){
    $l.fadeTo(200, 0, function(){
      var size = jQuery(this).css('font-size'),
          num = size.replace(/^(\d+(\.\d+)?).*/, '$1'),
          units = size.replace(/^(\d+(\.\d+)?)/, '');
      num = (up)? ++num : --num;
      if (num > 1)
        jQuery(this).css('font-size', num + units);
      jQuery(this).fadeTo(200, 1);
    });
  }
  jQuery('.luminous_zoomin_clicker', $l).click(function(e){
    resize_func(true);    
    return false;
  });
  jQuery('.luminous_zoomout_clicker', $l).click(function(e){
    resize_func(false);
    return false;
  });
  
  jQuery('a.line_number', $l).each(function(i, e) {
    var $e = jQuery(e);
    $e.click(function(e) {
      var $el = jQuery(this),
          name = $el.attr('id').replace(/^lineno/, 'line'),
          target = '#' + name;
      jQuery(target).toggleClass('highlighted_line');
      return false;
    });
  }); 
  
  luminous_opera_hack($l);
}

/* On some doctypes Opera will collapse blank lines into height:0,
 * which skews everything.
 * This function prepends any such lines with a space character to prevent
 * this from happening, although it's still not ideal.
 */ 
function luminous_opera_hack(luminous)
{
  if (!jQuery.browser.opera)
    return;
  var $l = jQuery(luminous),  
      bad_doctype = true,
      doctype = document.doctype,
      d;
    
  if (doctype != undefined)
  {
    var text = captureDoctypeType = doctype.publicId,
        uri = captureDoctypePath = doctype.systemId;
    // Strict doctype - okay
    if (uri.match(/strict\.dtd/))
      bad_doctype = false;
    // HTML 5 doctype - okay
    else if( text.replace(/(^[\s]+)|([\s]+$)/g).toLowerCase() == "html")
      bad_doctype = false;
  }
  
  if (!bad_doctype)
    return;
  
  d = jQuery(document).html();
  
  
  
  jQuery('span.line', $l).each( function (i, e) {
    var html = jQuery(e).html();
    if (html.replace(/<.*?>/g, '').replace(/(^[\s]+)|([\s]+$)/g, '') == '')
      jQuery(e).html("&nbsp;" + html);
  });

}

function luminous_init()
{
  
  jQuery('div.luminous').each(function(){
    luminous_bind(this);
  });
}

jQuery(document).ready(function(){
  luminous_init();
});

