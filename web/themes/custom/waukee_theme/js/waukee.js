/**
 * @file
 * Waukee theme behaviours: mobile navigation toggle and scroll reveal.
 */
(function (Drupal, once) {
  'use strict';

  /**
   * Mobile menu of the shared header (templates/includes/tw-header.html.twig):
   * the Menu button shows/hides the panel; Escape closes it.
   */
  Drupal.behaviors.waukeeMobileMenu = {
    attach: function (context) {
      once('wk-mobile-menu', '[data-wk-mobile-toggle]', context).forEach(function (toggle) {
        var panel = document.getElementById(toggle.getAttribute('aria-controls'));
        if (!panel) {
          return;
        }
        function setOpen(open) {
          toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
          panel.classList.toggle('hidden', !open);
        }
        toggle.addEventListener('click', function () {
          setOpen(toggle.getAttribute('aria-expanded') !== 'true');
        });
        document.addEventListener('keydown', function (event) {
          if (event.key === 'Escape' && toggle.getAttribute('aria-expanded') === 'true') {
            setOpen(false);
            toggle.focus();
          }
        });
        window.matchMedia('(min-width: 80rem)').addEventListener('change', function (e) {
          if (e.matches) {
            setOpen(false);
          }
        });
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
