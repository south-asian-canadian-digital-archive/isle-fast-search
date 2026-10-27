<script>
  // region--filters.html.twig plus the theme's filters-panel.ts behaviour:
  // collapsed by default below `lg`, the user's choice kept in localStorage.
  import { onMount } from 'svelte';
  import { search } from '../lib/search.svelte.js';
  import FacetBlock from './FacetBlock.svelte';

  const STORAGE_KEY = 'sacdaFiltersCollapsed';
  const DESKTOP_QUERY = '(min-width: 64rem)';

  let collapsed = $state(false);

  function stored() {
    try {
      const v = localStorage.getItem(STORAGE_KEY);
      return v === 'true' ? true : v === 'false' ? false : null;
    } catch {
      return null;
    }
  }

  function toggle() {
    collapsed = !collapsed;
    try {
      localStorage.setItem(STORAGE_KEY, String(collapsed));
    } catch {
      /* private mode */
    }
  }

  onMount(() => {
    const desktop = matchMedia(DESKTOP_QUERY);
    collapsed = stored() ?? !desktop.matches;
    const follow = (event) => {
      if (stored() === null) collapsed = !event.matches;
    };
    desktop.addEventListener('change', follow);
    return () => desktop.removeEventListener('change', follow);
  });

  function clear(event) {
    event.preventDefault();
    search.go({ facets: [], page: 0 });
  }
</script>

<div class="filters result-facets min-h-[10vh]" class:filters--collapsed={collapsed}>
  <h4 class="filters-heading border-y border-b-[#e5e5e5] border-t-transparent m-0">
    <button type="button" class="filters-toggle" aria-expanded={!collapsed} aria-controls="filters-panel-content" onclick={toggle}>
      <span class="flex items-center text-lg! h-10">Filter Results</span>
    </button>
  </h4>
  {#if search.facets.length}
    <a href={search.href({ facets: [], page: 0 })} class="filters-clear" onclick={clear}>Clear all</a>
  {/if}
  <div id="filters-panel-content" class="filters-content">
    {#each search.data.facets as facet (facet.alias)}
      <FacetBlock {facet} />
    {/each}
  </div>
</div>
