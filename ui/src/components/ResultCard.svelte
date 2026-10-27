<script>
  // views/views-view-fields.html.twig: a grid card and a list card, both
  // rendered; the view's wrapper class decides which one shows.
  import MediaPlaceholder from './MediaPlaceholder.svelte';

  let { row } = $props();
  let isCollection = $derived(row.model.includes('Collection'));
  const imageBox = 'bg-muted overflow-hidden [&_a]:block [&_a]:w-full [&_a]:h-full [&_img]:w-full [&_img]:h-full [&_img]:object-cover [&_img]:object-center [&_img]:transition-transform [&_img]:duration-300 group-hover:[&_img]:scale-105';
  const titleBox = 'text-sm leading-tight mb-1 [&_a]:text-foreground [&_a]:inline-block group-hover:[&_a]:underline group-hover:[&_a]:decoration-primary [&_a]:transition-colors';
</script>

{#snippet badge()}
  {#if isCollection}
    <div class="absolute top-3 left-3 z-10 flex items-center justify-center bg-background/90 backdrop-blur-sm text-foreground p-1.5 rounded shadow-sm" aria-label="Collection" title="Collection">
      <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="M20 20a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.9a2 2 0 0 1-1.69-.9L9.6 3.9A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2Z" />
      </svg>
    </div>
  {/if}
{/snippet}

{#snippet media()}
  {#if row.image}
    <a href={row.url} hreflang="en"><img loading="lazy" src={row.image.src} width={row.image.width} height={row.image.height} alt={row.image.alt} typeof="foaf:Image" /></a>
  {:else}
    <a href={row.url} tabindex="-1" aria-hidden="true"><MediaPlaceholder model={row.model} /></a>
  {/if}
{/snippet}

{#snippet title()}
  <div class={titleBox}><a href={row.url} hreflang="en">{row.title}</a></div>
  {#if row.memberOf}
    <div class="text-xs text-muted-foreground">{row.memberOf}</div>
  {/if}
{/snippet}

<div class="group cursor-pointer">
  <div class="relative aspect-square {imageBox}">
    {@render badge()}
    {@render media()}
  </div>
  <div class="py-2.5">
    {@render title()}
  </div>
</div>

<div class="group cursor-pointer">
  <div class="flex! gap-4 items-start">
    <div class="relative w-1/2 shrink-0 aspect-square {imageBox}">
      {@render badge()}
      {@render media()}
    </div>
    <div class="py-1 w-1/2 min-w-0 h-full break-words">
      {@render title()}
      {#if row.description}
        <div class="text-xs text-muted-foreground mt-1">
          {row.description}
          {#if row.descriptionTrimmed}<a href={row.url} class="views-more-link">more</a>{/if}
        </div>
      {/if}
    </div>
  </div>
</div>
