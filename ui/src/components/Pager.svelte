<script>
  // navigation/pager.html.twig (full pager; quantity from the view).
  import { search, PAGER_QUANTITY } from '../lib/search.svelte.js';
  import { pagerWindow } from '../lib/pager.js';

  let total = $derived(Math.ceil(search.data.total / search.data.perPage));
  let current = $derived(search.data.page);
  let win = $derived(pagerWindow(current, total, PAGER_QUANTITY));

  function go(event, page) {
    if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
    event.preventDefault();
    search.go({ page });
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }
</script>

{#if total > 1}
  <nav class="pager my-8! md:my-12!" role="navigation" aria-labelledby="pagination-heading">
    <h4 id="pagination-heading" class="visually-hidden">Pagination</h4>
    <ul class="pager gap-1!">
      {#if current > 0}
        <li class="pager__item pager__item--first">
          <a href={search.href({ page: 0 })} title="Go to first page" onclick={(e) => go(e, 0)}>
            <span class="visually-hidden">First page</span>
            <svg viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 19l-7-7 7-7m8 14l-7-7 7-7" /></svg>
          </a>
        </li>
        <li class="pager__item pager__item--previous">
          <a href={search.href({ page: current - 1 })} title="Go to previous page" rel="prev" onclick={(e) => go(e, current - 1)}>
            <span class="visually-hidden">Previous page</span>
            <svg viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
          </a>
        </li>
      {/if}
      {#if win.before}
        <li class="pager__item pager__item--ellipsis" role="presentation"><span>&hellip;</span></li>
      {/if}
      {#each win.pages as key (key)}
        <li class="pager__item" class:is-active={key === current + 1} class:pager__item--current={key === current + 1}>
          {#if key === current + 1}
            <span title="Current page" aria-current="page">
              <span class="visually-hidden">Current page</span>
              {key}
            </span>
          {:else}
            <a href={search.href({ page: key - 1 })} title="Go to page {key}" onclick={(e) => go(e, key - 1)}>{key}</a>
          {/if}
        </li>
      {/each}
      {#if win.after}
        <li class="pager__item pager__item--ellipsis" role="presentation"><span>&hellip;</span></li>
      {/if}
      {#if current < total - 1}
        <li class="pager__item pager__item--next">
          <a href={search.href({ page: current + 1 })} title="Go to next page" rel="next" onclick={(e) => go(e, current + 1)}>
            <span class="visually-hidden">Next page</span>
            <svg viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
          </a>
        </li>
        <li class="pager__item pager__item--last">
          <a href={search.href({ page: total - 1 })} title="Go to last page" onclick={(e) => go(e, total - 1)}>
            <span class="visually-hidden">Last page</span>
            <svg viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 5l7 7-7 7M5 5l7 7-7 7" /></svg>
          </a>
        </li>
      {/if}
    </ul>
  </nav>
{/if}
