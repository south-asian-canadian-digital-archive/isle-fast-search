<script>
  // One facet block, mounted where Drupal placed it in the filters region.
  // The search response decides what it shows; until the first one arrives it
  // renders nothing.
  import { search } from '../lib/search.svelte.js';
  import FacetBlock from './FacetBlock.svelte';

  let { alias } = $props();
  let facet = $derived(search.data?.facets.find((f) => f.alias === alias));
</script>

{#if facet}
  <FacetBlock {facet} />
{/if}
