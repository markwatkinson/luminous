/*
 * Simple drag functionality for jQuery.
 * 
 * Usage:
 * 
 * $('#yourelement').dragify();
 * 
 * element will now respond to drag.
 * 
 * User may optionally specify the actual dragger (if it is different to the element
 * as the first argument of dragify, i.e.
 * 
 * $('#content_box').dragify('#dragger');
 * 
 * The second argument may be a callback which is fired when the element is dropped.
 * It receives the mouseup event and its 'this' context is the element.
 * 
 * I realise this is implemented in some form in jQuery UI but it seems like
 * a big dependency for the sake of 40 lines of code.
 */


/*
  Copyright (c) 2010, Mark Watkinson
All rights reserved.

Redistribution and use in source and binary forms, with or without
modification, are permitted provided that the following conditions are met:
    * Redistributions of source code must retain the above copyright
      notice, this list of conditions and the following disclaimer.
    * Redistributions in binary form must reproduce the above copyright
      notice, this list of conditions and the following disclaimer in the
      documentation and/or other materials provided with the distribution.
    * Neither the name of the <organization> nor the
      names of its contributors may be used to endorse or promote products
      derived from this software without specific prior written permission.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
DISCLAIMED. IN NO EVENT SHALL Mark Watkinson BE LIABLE FOR ANY
DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
(INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND
ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
(INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
*/

(function($){
  $.fn.dragify = function(dragger, drop_callback){
    var $el = $(this);
    dragger = (typeof dragger != 'undefined')? dragger : $el;
      
    var document_mouseup_cb = function(e){
      dragger.trigger('mouseup');
    };
      
    var drag_handler = function(e){
      var old_coords = $el.data('drag_coords');
      var new_coords = {x:e.pageX, y:e.pageY};
      var dx = new_coords.x - old_coords.x;
      var dy = new_coords.y - old_coords.y;
      
      var offset = $el.offset();
      
      offset.top+=dy;
      offset.left+=dx;
      $el.offset(offset);  
      $el.offset(offset);  /* workaround for chrome bug, if the element has a 
                              css position, then $el.offset(offset) != offset 
                              the first time it is set. (at least in chromium
                              8.0.552.215 (67652) x64 linux)
                              */
      $el.data('drag_coords', new_coords);
      return false;
    };
    
    dragger.mousedown(function(e){
      $el.data('drag_coords', {x: e.pageX, y: e.pageY});   
      $(document).mousemove(drag_handler).mouseup(document_mouseup_cb);   
    });
  
    dragger.mouseup(function(e){
      $(document).unbind('mousemove', drag_handler)
        .unbind('mouseup', document_mouseup_cb);
        
      if (typeof drop_callback != 'undefined')
        drop_callback.call($el, e);
    });    
    return this;
  };
})(jQuery);