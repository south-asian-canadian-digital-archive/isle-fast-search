// Bits of /search's layout that its server render decides from the results,
// kept in step with the search here: the filters sidebar (hidden when no facet
// has values) and its "Clear all" link (only while facets are selected).
import { search } from './search.svelte.js';

export function syncLayout() {
  const aside = document.querySelector('aside.filters-sidebar');
  const content = aside?.nextElementSibling;
  const filters = aside?.querySelector('.filters');
  const heading = filters?.querySelector('.filters-heading');
  let clear = filters?.querySelector('.filters-clear') ?? null;

  clear?.addEventListener('click', onClear);

  $effect.root(() => {
    $effect(() => {
      if (!aside || !search.data) return;
      // The year range renders nothing when there are no results to range over.
      const any = search.data.facets.some((f) => (f.widget === 'checkbox' ? f.items.length : search.data.total > 0));
      aside.hidden = !any;
      content?.classList.toggle('content-children', any);
      content?.classList.toggle('w-full', !any);
    });
    $effect(() => {
      if (!heading) return;
      const href = search.href({ facets: [], page: 0 });
      if (search.facets.length) {
        if (!clear) {
          clear = Object.assign(document.createElement('a'), { className: 'filters-clear', textContent: 'Clear all' });
          clear.addEventListener('click', onClear);
          heading.after(clear);
        }
        clear.href = href;
      } else if (clear) {
        clear.remove();
        clear = null;
      }
    });
  });
}

function onClear(event) {
  event.preventDefault();
  search.go({ facets: [], page: 0 });
}
