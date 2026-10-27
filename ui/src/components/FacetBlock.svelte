<script>
  // block--facets.html.twig: a <details> per facet block.
  import CheckboxFacet from './CheckboxFacet.svelte';
  import YearRangeFacet from './YearRangeFacet.svelte';

  let { facet } = $props();

  // facets renders empty blocks with `hidden`; the year widget renders nothing
  // when there are no results to range over.
  let empty = $derived(facet.widget === 'checkbox' ? !facet.items.length : false);
  let blockClass = $derived(facet.block.replaceAll('_', '-'));
</script>

<details
  class="{facet.active ? 'facet-active' : 'facet-inactive'} block-facets-ajax js-facet-block-id-{facet.block} block-facet--{facet.widget === 'year_range' ? 'year-range' : 'checkbox'} contextual-region w-full border-t border-[#e5e5e5] first:border-t-0 px-4 py-4 group"
  class:hidden={empty}
  id="block-{blockClass}"
  data-block-id={facet.block}
  open
>
  <summary class="flex items-center justify-between cursor-pointer list-none [&::-webkit-details-marker]:hidden text-foreground">
    <h4 class="m-0 text-base font-bold">{facet.label}</h4>
    <svg class="w-4 h-4 transition-transform duration-200 group-open:rotate-180 text-muted-foreground" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
    </svg>
  </summary>
  <div class="**:list-none pt-4">
    {#if facet.widget === 'year_range'}
      {#key `${facet.min}:${facet.max}`}
        <YearRangeFacet {facet} />
      {/key}
    {:else}
      <CheckboxFacet {facet} />
    {/if}
  </div>
</details>
