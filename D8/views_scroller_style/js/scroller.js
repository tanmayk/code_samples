(function ($, Drupal, drupalSettings) {

  'use strict';

  Drupal.behaviors.viewsScrollerStyleScroller = {
    attach: function () {
      var settingsArr = $.makeArray(drupalSettings.viewsScrollerStyleScroller);
      var scrollerSettings = settingsArr.pop();
      for ( var id in scrollerSettings ) {
        if (scrollerSettings[id]['url'] !== undefined) {
          $("section#scroller-" + id + " header").css('background-image', 'url("' + scrollerSettings[id]['url'] + '")');
        }
        $("section#scroller-" + id + " header").css('height', scrollerSettings[id]['height']);
        $("section#scroller-" + id + " .scroller-color").css('background-color', scrollerSettings[id]['color']);
        $("section#scroller-" + id + " .scroller-color").css('opacity', scrollerSettings[id]['opacity']);
      }
    }
  };

})(jQuery, Drupal, drupalSettings);
