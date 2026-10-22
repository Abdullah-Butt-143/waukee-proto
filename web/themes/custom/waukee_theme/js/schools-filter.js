/**
 * @file
 * Schools directory: live search, grade-tier pills and feeder-zone filter.
 */
(function (Drupal, once) {
  'use strict';

  var ACTIVE = ['bg-primary', 'text-on-primary', 'shadow-sm'];
  var INACTIVE = ['bg-surface-container', 'hover:bg-surface-variant', 'text-on-surface-variant'];

  Drupal.behaviors.waukeeSchoolsFilter = {
    attach: function (context) {
      once('wk-schools-filter', '#schoolSearchInput', context).forEach(function (input) {
        var doc = input.ownerDocument;
        var cards = Array.prototype.slice.call(doc.querySelectorAll('.school-card'));
        var sections = doc.querySelectorAll('.school-category-section');
        var pills = doc.querySelectorAll('.tier-pill');
        var feeder = doc.getElementById('feederSelect');
        var clearBtn = doc.getElementById('clearSearchBtn');
        var resetBtn = doc.getElementById('resetAllFiltersBtn');
        var emptyState = doc.getElementById('noResultsState');
        var label = doc.getElementById('filterResultLabel');
        var tier = 'all';

        // Search covers everything printed on the card plus data-name (mascots).
        cards.forEach(function (card) {
          card.dataset.search = (card.dataset.name + ' ' + card.textContent).toLowerCase().replace(/\s+/g, ' ');
        });

        function apply() {
          var query = input.value.trim().toLowerCase();
          var zone = feeder.value;
          var visible = 0;
          var counted = 0;
          // "Learning communities" are the 19 schools; specialized centers are counted only in their own tab.
          var pool = cards.filter(function (card) {
            return tier === 'specialized' ? card.dataset.tier === 'specialized' : card.dataset.tier !== 'specialized';
          });

          cards.forEach(function (card) {
            var show = (tier === 'all' || card.dataset.tier === tier)
              && (zone === 'all' || card.dataset.feeder === zone)
              && (!query || card.dataset.search.indexOf(query) !== -1);
            card.classList.toggle('hidden', !show);
            if (show) {
              visible++;
              if (pool.indexOf(card) !== -1) {
                counted++;
              }
            }
          });

          sections.forEach(function (section) {
            section.classList.toggle('hidden', !section.querySelector('.school-card:not(.hidden)'));
          });

          var filtered = query || zone !== 'all' || tier !== 'all';
          emptyState.classList.toggle('hidden', visible > 0);
          emptyState.classList.toggle('flex', visible === 0);
          clearBtn.classList.toggle('hidden', !query);
          resetBtn.classList.toggle('hidden', !filtered);
          label.innerHTML = Drupal.t('Showing <strong class="text-on-surface font-semibold">@count of @total</strong> @what', {
            '@count': counted,
            '@total': pool.length,
            '@what': tier === 'specialized' ? Drupal.t('specialized centers') : Drupal.t('learning communities')
          });
        }

        function setTier(value) {
          tier = value;
          pills.forEach(function (pill) {
            var on = pill.dataset.filter === value;
            pill.classList.toggle('active', on);
            pill.setAttribute('aria-pressed', on ? 'true' : 'false');
            ACTIVE.forEach(function (c) { pill.classList.toggle(c, on); });
            INACTIVE.forEach(function (c) { pill.classList.toggle(c, !on); });
          });
          apply();
        }

        function resetAll() {
          input.value = '';
          feeder.value = 'all';
          setTier('all');
          input.focus();
        }

        pills.forEach(function (pill) {
          pill.addEventListener('click', function () { setTier(pill.dataset.filter); });
        });
        input.addEventListener('input', apply);
        feeder.addEventListener('change', apply);
        clearBtn.addEventListener('click', function () {
          input.value = '';
          apply();
          input.focus();
        });
        resetBtn.addEventListener('click', resetAll);
        doc.getElementById('clearSearchFiltersAction').addEventListener('click', resetAll);

        setTier('all');
      });
    }
  };

})(Drupal, once);
