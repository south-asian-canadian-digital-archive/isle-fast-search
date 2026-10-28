<script>
  // facets-item-list.html.twig for the checkbox widget, as it looks after
  // facets' checkbox-widget.js and soft-limit.js have run.
  import { search } from '../lib/search.svelte.js';
  import FacetItem from './FacetItem.svelte';

  let { facet } = $props();
  let open = $state(false);
  // The response carries only the first soft-limit values; `more` counts the
  // rest, fetched the first time "Show more" is clicked.
  let full = $state(null);
  let loading = $state(false);

  let items = $derived(open && full ? full : facet.items);
  // soft-limit.js hides top-level values past the limit but shows the link
  // when the values at every level outnumber it, so nested values (Map under
  // Cartographic) can bring it up with nothing hidden. Kept, to match /search.
  const count = (items) => items.reduce((n, i) => n + 1 + count(i.children), 0);
  let expandable = $derived(facet.softLimit && (facet.more > 0 || count(facet.items) > facet.softLimit));

  // A new search brings new counts, so a list fetched for the old one is stale.
  $effect.pre(() => {
    facet;
    full = null;
    open = false;
  });

  // Hidden, not removed, past the soft limit: that is what soft-limit.js does,
  // and the theme's `space-y-2` spacing depends on the hidden siblings.
  const hidden = (n) => !open && facet.softLimit && n >= facet.softLimit;

  async function toggle(event) {
    event.preventDefault();
    if (!open && facet.more > 0 && !full) {
      loading = true;
      try {
        full = await search.facetItems(facet.alias);
      } catch {
        return;
      } finally {
        loading = false;
      }
    }
    open = !open;
  }
</script>

<div class="facets-widget-checkbox">
  <ul data-drupal-facet-id={facet.alias} data-drupal-facet-alias={facet.alias} class="{facet.active ? 'facet-active' : 'facet-inactive'} js-facets-checkbox-links item-list__checkbox js-facets-widget">
    {#each items as item, n (item.value)}
      <FacetItem {facet} {item} hidden={hidden(n)} />
    {/each}
  </ul>
  {#if expandable}
    <!-- svelte-ignore a11y_invalid_attribute -->
    <a href="#" class="facets-soft-limit-link" class:open aria-busy={loading} onclick={toggle}>
      {open ? facet.showLess : facet.showMore}
    </a>
  {/if}
</div>
