/* Round Robin brand (inbox.roundrobin.in): dropdowns animate closed as well as open.
   Bootstrap 3 hides menus instantly, so the hide is held for the closing animation (opacity + transform,
   brand.css), then finished exactly as Bootstrap would. Reduced motion keeps Bootstrap's instant close. */
(function ($) {
    if (!$ || !$.fn || !$.fn.dropdown) { return; }
    if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) { return; }
    $(document).on('hide.bs.dropdown', function (e) {
        var $parent = $(e.target);
        var $menu = $parent.children('.dropdown-menu');
        if (!$menu.length || !$parent.hasClass('open') || $parent.data('rrClosing')) { return; }
        e.preventDefault();
        $parent.data('rrClosing', true);
        $menu.addClass('rr-closing');
        var finished = false;
        var done = function () {
            if (finished) { return; }
            finished = true;
            $menu.off('animationend.rrbrand').removeClass('rr-closing');
            $parent.removeClass('open').data('rrClosing', false);
            $parent.find('[data-toggle="dropdown"]').attr('aria-expanded', 'false');
            $parent.trigger($.Event('hidden.bs.dropdown', { relatedTarget: e.relatedTarget }));
        };
        $menu.one('animationend.rrbrand', done);
        setTimeout(done, 200); // in case the animation never runs (hidden tab, display change)
    });
})(window.jQuery);
