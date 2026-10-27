<script>
  // page--search.html.twig: content_above (advanced search), then the
  // results layout with the filters sidebar. Markup and classes are the
  // theme's, so its stylesheet styles this page unchanged.
  import { onMount } from 'svelte';
  import { search } from './lib/search.svelte.js';
  import AdvancedSearch from './components/AdvancedSearch.svelte';
  import FiltersRegion from './components/FiltersRegion.svelte';
  import ResultPager from './components/ResultPager.svelte';
  import ResultsView from './components/ResultsView.svelte';

  let { endpoint } = $props();

  // onMount, not $effect: init reads and writes the same state, which an
  // effect would track and re-run forever.
  onMount(() => search.init(endpoint));
</script>

<!-- Mounted inside the content region, which already applies the page
     container (max-w-5xl, px-16), so no container classes here. -->
<div class="region region-content-above flex flex-col gap-8">
  <AdvancedSearch />
</div>

<!-- 2rem: on /search this gap is <main class="my-8"> between content_above and
     the results; mt-8 is not in the theme's compiled CSS. -->
<section class="content-children-layout w-full" style="margin-top: 2rem">
  {#if search.data?.facets.length}
    <aside class="filters-sidebar">
      <FiltersRegion />
    </aside>
  {/if}
  <div class={search.data?.facets.length ? 'content-children' : 'w-full'}>
    <div class="region region-content">
      {#if search.error}
        <div class="messages messages--error" role="alert">{search.error}</div>
      {:else if search.data}
        <ResultPager />
        <ResultsView />
      {/if}
    </div>
  </div>
</section>
