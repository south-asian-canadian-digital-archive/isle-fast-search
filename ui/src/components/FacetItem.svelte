<script>
  // facets-result-item.html.twig, recursive for hierarchical facets.
  import { search } from '../lib/search.svelte.js';
  import FacetItem from './FacetItem.svelte';

  let { facet, item, hidden = false } = $props();

  let id = $derived(`${facet.alias}-${item.value}`.toLowerCase().replace(/[^a-z0-9_]+/g, '-'));
  let hasChildren = $derived(item.children.length > 0);
</script>

<li class="facet-item" style:display={hidden ? 'none' : null} class:facet-item--expanded={hasChildren} class:facet-item--active-trail={item.active && hasChildren}>
  <input type="checkbox" class="facets-checkbox" {id} checked={item.active} onchange={() => search.toggleFacet(facet.alias, item.value)} />
  <label for={id}>
    <span class="facet-item__value">{item.label}</span>
    {#if facet.showCount}
      <span class="facet-item__count">({item.count})</span>
    {/if}
  </label>
  {#if hasChildren}
    <div class="facets-widget-">
      <ul>
        {#each item.children as child (child.value)}
          <FacetItem {facet} item={child} />
        {/each}
      </ul>
    </div>
  {/if}
</li>
