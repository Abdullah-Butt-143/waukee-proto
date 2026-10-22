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

  /**
   * Staggered scroll-reveal for homepage/section blocks and cards.
   * Skips entirely when the visitor prefers reduced motion.
   */
  Drupal.behaviors.waukeeReveal = {
    attach: function (context) {
      if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        return;
      }
      var targets = once('wk-reveal', '.wk-main .layout > .layout__region > .block, .wk-schools__grid > li, .wk-schools__grid .field--name-field-cards > .field__item, .view-news-feed .views-row, .view-events .views-row', context);
      if (!targets.length) {
        return;
      }
      targets.forEach(function (el, i) {
        el.classList.add('wk-reveal');
        el.style.transitionDelay = Math.min(i % 6, 5) * 80 + 'ms';
      });

      if (!('IntersectionObserver' in window)) {
        targets.forEach(function (el) { el.classList.add('is-visible'); });
        return;
      }

      var observer = new IntersectionObserver(function (entries, obs) {
        entries.forEach(function (entry) {
          if (entry.isIntersecting) {
            entry.target.classList.add('is-visible');
            obs.unobserve(entry.target);
          }
        });
      }, {threshold: 0.15, rootMargin: '0px 0px -40px 0px'});

      targets.forEach(function (el) { observer.observe(el); });
    }
  };

})(Drupal, once);
