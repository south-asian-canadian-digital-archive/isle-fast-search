<script>
  // views-view.html.twig + views-view-unformatted.html.twig for
  // solr_search_content. Grid/list is purely the wrapper class; the theme CSS
  // shows the matching half of each card.
  import { search } from '../lib/search.svelte.js';
  import Pager from './Pager.svelte';
  import ResultCard from './ResultCard.svelte';
  import ResultSummary from './ResultSummary.svelte';
</script>

<!-- Inside Drupal's own main-content block wrapper. -->
<div class="views-element-container">
    <div class="view-{search.display}" class:opacity-60={search.loading} aria-busy={search.loading}>
      <header>
        <ResultSummary id="ajax-page-summary" />
      </header>
      <div class="search-content-container">
        {#each search.data.results as row (row.nid)}
          <div class="clearfix bg-background overflow-hidden">
            <ResultCard {row} />
          </div>
        {/each}
      </div>
      <Pager />
    </div>
</div>
