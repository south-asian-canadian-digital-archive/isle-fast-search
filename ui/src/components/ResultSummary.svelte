<script>
  import { search } from '../lib/search.svelte.js';

  let { id = undefined } = $props();
  let from = $derived(search.data.total ? search.data.page * search.data.perPage + 1 : 0);
  let to = $derived(Math.min(search.data.total, (search.data.page + 1) * search.data.perPage));
</script>

<div {id} class="pager__summary">Displaying {from} - {to} of {search.data.total}</div>
