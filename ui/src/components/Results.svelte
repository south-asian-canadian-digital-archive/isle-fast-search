<script>
  // The main content of /search: the view, inside Drupal's main-content block.
  import { search } from '../lib/search.svelte.js';
  import ResultsView from './ResultsView.svelte';
</script>

{#if search.error}
  <div class="messages messages--error" role="alert">{search.error}</div>
{:else if search.data}
  <ResultsView />
{/if}
