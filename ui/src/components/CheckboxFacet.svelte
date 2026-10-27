<script>
  // facets-item-list.html.twig for the checkbox widget, as it looks after
  // facets' checkbox-widget.js and soft-limit.js have run.
  import FacetItem from './FacetItem.svelte';

  let { facet } = $props();
  let open = $state(false);

  // Hidden, not removed, past the soft limit: that is what soft-limit.js does,
  // and the theme's `space-y-2` spacing depends on the hidden siblings.
  const hidden = (n) => !open && facet.softLimit && n >= facet.softLimit;
</script>

<div class="facets-widget-checkbox">
  <ul data-drupal-facet-id={facet.alias} data-drupal-facet-alias={facet.alias} class="{facet.active ? 'facet-active' : 'facet-inactive'} js-facets-checkbox-links item-list__checkbox js-facets-widget">
    {#each facet.items as item, n (item.value)}
      <FacetItem {facet} {item} hidden={hidden(n)} />
    {/each}
  </ul>
  {#if facet.softLimit && facet.items.length > facet.softLimit}
    <!-- svelte-ignore a11y_invalid_attribute -->
    <a href="#" class="facets-soft-limit-link" class:open onclick={(e) => { e.preventDefault(); open = !open; }}>
      {open ? facet.showLess : facet.showMore}
    </a>
  {/if}
</div>
