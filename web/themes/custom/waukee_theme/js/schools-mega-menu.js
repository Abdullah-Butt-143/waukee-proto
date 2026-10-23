/**
 * @file
 * Header "Schools" mega menu: live search and tier-chip filtering.
 */
(function (Drupal, once) {
  'use strict';

  var ACTIVE = ['bg-primary', 'text-on-primary', 'shadow-sm'];
  var INACTIVE = ['bg-surface-container', 'hover:bg-surface-container-high', 'text-on-surface-variant'];

  Drupal.behaviors.waukeeSchoolsMegaMenu = {
    attach: function (context) {
      once('wk-mega-menu', '[data-wk-mega="schools"]', context).forEach(function (panel) {
        var input = panel.querySelector('[data-wk-mega-search]');
        var chips = panel.querySelectorAll('.wk-mega-chip');
        var groups = panel.querySelectorAll('.wk-mega-group');
        var links = Array.prototype.slice.call(panel.querySelectorAll('.wk-mega-link'));
        var empty = panel.querySelector('[data-wk-mega-empty]');
        var notice = panel.querySelector('[data-wk-mega-notice]');
        var tier = 'all';

        function apply() {
          var query = input.value.trim().toLowerCase();
          var visible = 0;

          links.forEach(function (link) {
            var group = link.closest('.wk-mega-group');
            var matchesTier = tier === 'all' || (group && group.dataset.tier === tier);
            var matchesQuery = !query || (link.dataset.search || '').indexOf(query) !== -1;
            var show = matchesTier && matchesQuery;
            link.classList.toggle('hidden', !show);
            if (show) {
              visible++;
            }
          });

          groups.forEach(function (group) {
            group.classList.toggle('hidden', !group.querySelector('.wk-mega-link:not(.hidden)'));
          });

          if (empty) {
            empty.classList.toggle('hidden', visible > 0);
          }
          if (notice) {
            notice.classList.toggle('hidden', visible === 0);
          }
        }

        function setTier(value) {
          tier = value;
          chips.forEach(function (chip) {
            var on = chip.dataset.filter === value;
            chip.classList.toggle('active', on);
            ACTIVE.forEach(function (c) { chip.classList.toggle(c, on); });
            INACTIVE.forEach(function (c) { chip.classList.toggle(c, !on); });
          });
          apply();
        }

        chips.forEach(function (chip) {
          chip.addEventListener('click', function () { setTier(chip.dataset.filter); });
        });
        if (input) {
          input.addEventListener('input', apply);
        }
      });
    }
  };

})(Drupal, once);
