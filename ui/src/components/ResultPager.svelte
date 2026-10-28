<script>
  // block--advanced-search-result-pager.html.twig: per-page, display mode and
  // sort. Its pager is display:none on /search, so it is not rendered here.
  import { search, ORDERS, PER_PAGE, DISPLAYS } from '../lib/search.svelte.js';
  import ResultSummary from './ResultSummary.svelte';

  function nav(event, overrides) {
    event.preventDefault();
    search.go(overrides);
  }
</script>

<div class="advanced_search_result_pager" data-drupal-pager-id="solr_search_content__page_1" id="block-sacda-solrsearchcontentsearchresultspagerforsearchpage">
  <ResultSummary />
  <div class="pager__group">
    <h3>Results per page</h3>
    <ul class="fast-search-per-page">
      {#each PER_PAGE as n}
        <li class="pager__item" class:is-active={search.perPage === n}>
          <a href={search.href({ perPage: n, page: 0 })} class="pager__link pager__itemsperpage" class:pager__link--is-active={search.perPage === n} aria-label="{n} items per page" onclick={(e) => nav(e, { perPage: n, page: 0 })}>{n}</a>
        </li>
      {/each}
    </ul>
    <ul class="fast-search-display">
      {#each [['list', 'List', 'fa-list'], ['grid', 'Grid', 'fa-th']].filter(([mode]) => DISPLAYS.includes(mode)) as [mode, label, icon]}
        <li class="pager__item" class:is-active={search.display === mode}>
          <a href={search.href({ display: mode })} class="pager__link pager__display" class:pager__link--is-active={search.display === mode} aria-label="Display as  {label}" onclick={(e) => nav(e, { display: mode })}>
            <i class="fa {icon}" aria-hidden="true">&nbsp;</i><span class="display-mode">{label}</span>
          </a>
        </li>
      {/each}
    </ul>
    <div class="pager__sort container js-form-item form-item form-type-select js-form-type-select form-item-order js-form-item-order form-no-label">
      <label class="visually-hidden" for="fast-search-order">Sort</label>
      <select id="fast-search-order" autocomplete="off" aria-label="Sort By" name="order" class="form-select" value={search.order} onchange={(e) => search.setOrder(e.currentTarget.value)}>
        {#each ORDERS as [value, label]}
          <option {value}>{label}</option>
        {/each}
      </select>
    </div>
  </div>
</div>

<style>
  /* Phones: /search lets this row wrap wherever it falls, which leaves the
     sort select hanging under the List/Grid buttons. Two deliberate rows
     instead: per-page with its label, then display mode with the sort. */
  @media (max-width: 47.99rem) {
    .pager__group {
      display: grid;
      grid-template-columns: auto 1fr;
      align-items: center;
      row-gap: 0.75rem;
      column-gap: 1rem;
    }
    .pager__group h3 {
      margin: 0;
      white-space: nowrap;
    }
    .fast-search-per-page {
      justify-self: end;
      padding-right: 0 !important;
    }
    .fast-search-display {
      padding-right: 0 !important;
    }
    .pager__sort {
      justify-self: stretch;
      min-width: 0;
      margin: 0;
    }
    .pager__sort select {
      width: 100%;
    }
  }
</style>
