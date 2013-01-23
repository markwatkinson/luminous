/**
 * Tabify script for jQuery
 * 
 * CSS classes:
 * 'tab_title'  - A title element
 * 'tab_hover' - a hovered (mouseover) title element
 * 'tab_selected' - the title element corresponding to the currently displayed 
 *                  tab.
 * 
 * 
 * Usage: Build your content, and wrap your tabs in a an element and your menu
 * in another element. Your menu may contain either <a> elements, or, if not, 
 * then the child elements of your menu wrapper are used as the 'clickers'.
 * The elements in the menu (direct children or <a>) should correspond 1-1 
 * with the first level children in the tab wrapper element. Call tabify on 
 * your tabs wrapper and give the first argument as the menu wrapper. e.g.:
 * 
 * 
 * <div id='menu'> <span>go to tab 1</span> <span>go to tab 2</span> 
 *      <span>go to tab 3</span> </div>
 * 
 * <div id='tabs'>
 *      <div> tab 1 </div>
 *      <div> tab 2 </div>
 *      <div> tab 3 </div>
 * </div>
 * 
 * 
 * <script>
 * $('#tabs').tabify($('#menu'), true);
 * </script>
 * 
 * The last argument controls whether or not to try to emulate back/forward 
 * buttons. This must be false if you have multiple tabified elements per 
 * page.
 * 
 * 
 * You may apply your own CSS theming to #tabs and #menu to arrange it as you 
 * like
 */


(function($){
  $.fn.tabify = function(menu, hashwatch){
    var element = this;
    var tabs = this.children();
    var clickers = $('a', menu);
    var titles = menu.children();
    if (!clickers.length) clickers = titles;
    element.data('active', -1);
    
    $(tabs).hide();

    clickers.each(function(i){
      $(this).click(function(e){ 
        $(tabs[i]).fadeIn(250);
        var active = element.data('active');
        if (active != -1 && active != i && active < tabs.length)
          $(element.children().get(element.data('active'))).hide();

        $('.tab_selected', menu).removeClass('tab_selected'); 
        element.data('active', i);
        if (hashwatch === true)
          parent.location.hash = i;
        $el = (($(this).parent()[0] == $(menu)[0])? $(this) : $(titles[i]));
        $el.addClass('tab_selected');
        
        e.preventDefault();
      });
    });
    // toggleClass seems unreliable with hover.
    clickers.hover(function(e){$(this).addClass('tab_hover')},
                  function(e){$(this).removeClass('tab_hover')}).addClass('tab_title');
    tabs.addClass('tab');
    
    var trigger = true;
    if (hashwatch === true)
    {
      if (parent.location.hash.replace(/^#|\s*$/g, '').match(/^\d+$/))
        trigger = false;
        
      setInterval(function(){
        var hash = parent.location.hash.replace(/^#|\s*$/g, '');
        if (hash.match(/^\d+$/))
          hash = parseInt(hash);
        else 
          return;                    
        if (hash != element.data('active') && hash < clickers.length)
          $(clickers.get(hash)).trigger('click');
      }, 200);
    }
    
    if (trigger)
      clickers.first().trigger('click');

    
    return this;
  };
})(jQuery);
