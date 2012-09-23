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
 
 
 
 /**
   * This simply adds some extras to Luminous elements via a jQUery
   * plugin. The extras are currently a toggleable line-highlighting
   * on click
   */
 
(function($) {
    "use strict";
    
    var LINE_SELECTOR = 'td .code > span ';
    
    if (typeof $ === 'undefined') { return; }
    
    /****************************************************************
     * UTILITY FUNCTIONS *
     ****************************************************************/
    
    // determines if the given element is a line element of luminous
    function isLine($line) {
        return $line.is(LINE_SELECTOR) && $line.parents('.luminous').length > 0;
    }
    
    function isLineNumber($element) {
        return $element.is('.luminous .line-numbers span');
    }
    
    function highlightLine($line) {
        $line.toggleClass('highlight');
    }
    
    function highlightLineByIndex($luminous, index) {
        var $line = $luminous.find(LINE_SELECTOR).eq(index);
        highlightLine($line);
    }
    
    function highlightLineByNumber($luminous, number) {
        // the line's index must take into account the initial line number
        var offset = parseInt($luminous.find('.code').data('startline'), 10);
        if (isNaN(offset)) offset = 0;
        highlightLineByIndex($luminous, number - offset);
    }
    
    function toggleHighlightAndPlain($luminous, forceState) {
        var data = $luminous.data('luminous'),
            state = data.code.active,
            $elem = $luminous.find('.code'),
            toSetCode, toSetState;
        
        if (forceState === 'plain') state = 'highlighted';
        else if (forceState === 'highlighted') state = 'plain';
        
        toSetCode = (state === 'plain')? data.code.highlighted : data.code.plain;
        toSetState = (state === 'plain')? 'highlighted' : 'plain';
        
        $elem.html(toSetCode);
    }
    
    
    function toggleLineNumbers($luminous, forceState) {
        var data = $luminous.data('luminous'),
            show = (typeof forceState !== 'undefined')? forceState : 
                !data.lineNumbers.visible;
        
        data.lineNumbers.visible = show;
        
        
        var $numberContainer = $luminous.find('.line-numbers'),
            $control = $luminous.find('.line-number-control');
        
        if (!show) {
            $numberContainer.addClass('collapsed');
            $control.addClass('show-line-numbers');
            $luminous.addClass('collapsed-line-numbers');
        } else {
            $numberContainer.removeClass('collapsed');
            $control.removeClass('show-line-numbers');
        }
        $luminous.data('luminous', data);
        
    }
    
    // binds the event handlers to a luminous element
    function bindLuminousExtras($element) {
        var highlightLinesData, highlightLines, data = {},
            hasLineNumbers = $element.find('td .line-numbers').length > 0,
            schedule = [];

        if (!$element.is('.luminous')) { return false; }
        else if ($element.is('.bound')) { return true; }
        
        $element.addClass('bound');
        
        // highlight lines on click
        $element.find('td .code').click(function(ev) {
            var $t = $(ev.target);
            var $lines = $t.parents().add($t).
                    filter(function() { return isLine($(this)); }),
                 $line
                 ;

            if ($lines.length > 0) {
                $line = $lines.eq(0);
                highlightLine($line);
            }
        });
        // highlight lines on clicking the line number        
        $element.find('td .line-numbers').click(function(ev) {
            var $t = $(ev.target),
                 index;
            if ($t.is('span')) {
                index = $t.prevAll().length;
                highlightLineByIndex($element, index);
            }
        });
        
        data.lineNumbers = {visible: false};
        
        if (hasLineNumbers) {
            var $control, controlHeight, controlWidth, gutterWidth, 
              controlIsVisible = false;
            
            data.lineNumbers.visible = true;
            data.lineNumbers.setControlPosition = function() {
                var scrollOffset = $element.scrollTop(),
                    scrollHeight = $element.height();
                $control.css('top', scrollOffset + (scrollHeight/2) - (controlHeight/2) + 'px');
            }
            
            $control = $('<a class="line-number-control"></a>');
            $control.click(function() {
                $element.luminous('showLineNumbers');
                if ($element.data('luminous').lineNumbers.visible) {
                    $control.css('left', gutterWidth - controlWidth + 'px')
                }
                else {
                    $control.css('left', '0px');
                }
            });
            
            $control.appendTo($element);
            $control.show();
            controlWidth = $control.outerWidth();
            controlHeight = $control.outerHeight();
            gutterWidth = $element.find('.line-numbers').outerWidth();
            $control.css('left', gutterWidth - controlWidth + 'px');
            
            $control.hide();
            $element.mousemove(function(ev) {
                if (ev.pageX < gutterWidth) {
                    if (!controlIsVisible) { 
                        $control.stop(true, true).fadeIn('fast');
                        controlIsVisible = true;
                    }
                } else {
                    if (controlIsVisible) { 
                        $control.stop(true, true).fadeOut('fast'); 
                        controlIsVisible = false;
                    } 
                }
            });
                       
            data.lineNumbers.setControlPosition();
            $element.scroll(data.lineNumbers.setControlPosition);
            schedule.push(function() { $element.luminous('showLineNumbers', true); });
        }
        
        // highlight all the initial lines
        highlightLinesData = $element.find('.code').data('highlightlines') || "";
        highlightLines = highlightLinesData.split(",");
        $.each(highlightLines, function(i, element) {
             var lineNo = parseInt(element, 10);
             if (!isNaN(lineNo)) {
                 highlightLineByNumber($element, lineNo);
            }
        });

        data.code = {};
        data.code.highlighted = $element.find('.code').html();
        
        data.code.plain = '';
        $element.find(LINE_SELECTOR).each(function(i, e) {
            var line = $(e).text();
            line = line
                    .replace(/&/g, '&amp')
                    .replace(/>/g, '&gt;')
                    .replace(/</g, '&lt;');
        
            data.code.plain += '<span>' + line + '</span>';
        });
        data.code.active = 'highlighted';
        
        $element.data('luminous', data);
        
        $.each(schedule, function(i, f) {
            f();
        });
        
    }
    
    
    
    /****************************************************************
     * JQUERY PLUGIN *
     ***************************************************************/


    $.fn.luminous = function(optionsOrCommand /* variadic */) {
    
        var args = Array.prototype.slice.call(arguments);
        
        return $(this).each(function() {
            var $luminous = $(this);
            
            // no instructions - bind everything 
            if (!optionsOrCommand) {
                bindLuminousExtras($luminous);
                return;
            }
            
            // $('.luminous').luminous('highlightLine', [2, 3]);
            if (optionsOrCommand === 'highlightLine') {
                var lineNumbers = args[1];
                if (!$.isArray(lineNumbers)) 
                    lineNumbers = [lineNumbers];
                
                $.each(lineNumbers, function(index, el) {
                    highlightLineByNumber($luminous, el);
                });
                
                return;
            }
            else if (optionsOrCommand === 'show') {
                // args[1] should be 'highlighted' or 'plain'
                toggleHighlightAndPlain($luminous, args[1]);
            }
            else if (optionsOrCommand === 'showLineNumbers') {
                toggleLineNumbers($luminous, args[1]);
            }
            
        });
    };

    $(document).ready(function() {
        $('.luminous').luminous();
    });
  
}(jQuery));