/**
 * @file
 * Waukee theme behaviours: mobile navigation toggle and header shadow.
 */
(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.waukeeHeader = {
    attach: function (context) {
      once('wk-header', '[data-wk-header]', context).forEach(function (header) {
        var toggle = header.querySelector('.wk-header__toggle');
        var nav = header.querySelector('.wk-header__nav');
        if (!toggle || !nav) {
          return;
        }

        function setOpen(open) {
          toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
          header.classList.toggle('is-open', open);
        }

        toggle.addEventListener('click', function () {
          setOpen(toggle.getAttribute('aria-expanded') !== 'true');
        });

        // Escape closes the menu and returns focus to the button.
        header.addEventListener('keydown', function (event) {
          if (event.key === 'Escape' && toggle.getAttribute('aria-expanded') === 'true') {
            setOpen(false);
            toggle.focus();
          }
        });

        // Reset when the window becomes wide again.
        window.matchMedia('(min-width: 75rem)').addEventListener('change', function (e) {
          if (e.matches) {
            setOpen(false);
          }
        });

        // Soft shadow once the page scrolls.
        function onScroll() {
          header.classList.toggle('is-scrolled', window.scrollY > 4);
        }
        window.addEventListener('scroll', onScroll, {passive: true});
        onScroll();
      });
    }
  };

})(Drupal, once);
