<script>
  // macros/media_placeholder.twig: an icon keyed to the Islandora model.
  let { model = '' } = $props();
  let name = $derived(model.trim());
</script>

<div class="w-full h-full flex items-center justify-center bg-muted text-muted-foreground" aria-label={name || 'Item'}>
  <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    {#if name === 'Audio'}
      <path d="M9 18V5l12-2v13" /><circle cx="6" cy="18" r="3" /><circle cx="18" cy="16" r="3" />
    {:else if name === 'Video'}
      <rect x="2" y="4" width="20" height="16" rx="2" /><path d="m10 9 5 3-5 3z" fill="currentColor" stroke="none" />
    {:else if name === 'Image'}
      <rect x="3" y="3" width="18" height="18" rx="2" /><circle cx="9" cy="9" r="2" /><path d="m21 15-4.5-4.5L3 21" />
    {:else if name === 'Collection'}
      <path d="M20 20a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.9a2 2 0 0 1-1.69-.9L9.6 3.9A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2Z" />
    {:else if name === 'Digital Document'}
      <path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z" /><path d="M14 2v5h5" /><path d="M8 13h8" /><path d="M8 17h5" />
    {:else if name === 'Newspaper'}
      <path d="M4 22h16a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2h-2" /><path d="M18 22V4a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2Z" /><path d="M6 7h8" /><path d="M6 11h8" /><path d="M6 15h5" />
    {:else if name === 'Publication Issue'}
      <path d="M12 7v14" /><path d="M3 18a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1h5a4 4 0 0 1 4 4 4 4 0 0 1 4-4h5a1 1 0 0 1 1 1v13a1 1 0 0 1-1 1h-6a3 3 0 0 0-3 3 3 3 0 0 0-3-3Z" />
    {:else if name === 'Paged Content' || name === 'Compound Object'}
      <path d="M15 2H9a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h9a2 2 0 0 0 2-2V7Z" /><path d="M14 2v5h5" /><path d="M4 8v12a2 2 0 0 0 2 2h9" />
    {:else if name === 'Binary'}
      <path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z" /><path d="M14 2v5h5" /><path d="M8 13h2v4H8z" /><path d="M14 13h2v4h-2z" />
    {:else}
      <path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z" /><path d="M14 2v5h5" />
    {/if}
  </svg>
</div>
