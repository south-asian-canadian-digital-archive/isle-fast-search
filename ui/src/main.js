// The page is Drupal's /search layout (see sacda_fast_search.module). Each
// part of the app mounts where Drupal placed the block it replaces, so block
// order, regions and any other blocks there stay as configured. All parts
// share one search state.
import { mount } from 'svelte';
import { configure, search } from './lib/search.svelte.js';
import { syncLayout } from './lib/layout.svelte.js';
import AdvancedSearch from './components/AdvancedSearch.svelte';
import FacetSlot from './components/FacetSlot.svelte';
import Results from './components/Results.svelte';
import ResultPager from './components/ResultPager.svelte';

const SLOTS = {
  'advanced-search': () => [AdvancedSearch, {}],
  'result-pager': () => [ResultPager, {}],
  facet: (slot) => [FacetSlot, { alias: slot.dataset.alias }],
};

const results = document.getElementById('sacda-fast-search');
if (results) {
  const settings = window.drupalSettings?.sacdaFastSearch ?? {};
  configure(settings);

  // In place of the slot, not inside it: the theme's CSS depends on these
  // blocks being direct siblings (e.g. `first:border-t-0` on facet blocks).
  for (const slot of document.querySelectorAll('[data-fast-search-slot]')) {
    const make = SLOTS[slot.dataset.fastSearchSlot];
    if (!make) continue;
    const [Component, props] = make(slot);
    mount(Component, { target: slot.parentNode, anchor: slot, props });
    slot.remove();
  }
  mount(Results, { target: results });

  search.init(settings.endpoint ?? '/api/search');
  syncLayout();
}
