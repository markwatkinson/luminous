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
    
    if (typeof $ === 'undefined') { return; }
    
    /****************************************************************
     * UTILITY FUNCTIONS *
     ****************************************************************/
    
    // determines if the given element is a line element of luminous
    function isLine($line) {
        return $line.is('pre > span') && $line.parents('.luminous').length > 0;
    }
    
    function highlightLine($line) {
        // FIXME this 'highlighted_line' class needs to be renamed    
        $line.toggleClass('highlighted_line');
    }
    
    function highlightLineByIndex($luminous, index) {
        var $line = $luminous.find('pre > span').eq(index);
        highlightLine($line);
    }
    
    function highlightLineByNumber($luminous, number) {
        // the line's index must take into account the initial line number
        var offset = parseInt($luminous.find('>pre').data('startline'), 10);
        if (isNaN(offset)) offset = 0;
        highlightLineByIndex($luminous, number - offset);
    }
    
    // binds the event handlers to a luminous element
    function bindLuminousExtras($element) {
        if (!$element.is('.luminous')) { return false; }
        else if ($element.is('.bound')) { return true; }
        
        $element.click(function(ev) {
            var $t = $(ev.target);
            var $lines = $t.parents().add($t).
                    filter(function() { return isLine($(this)); }),
                 $line;
            if ($lines.length > 0) {
                $line = $lines.eq(0);
                highlightLine($line);
            }
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
            
        });
    };

    $(document).ready(function() {
        $('.luminous').luminous();
    });
  
}(jQuery));