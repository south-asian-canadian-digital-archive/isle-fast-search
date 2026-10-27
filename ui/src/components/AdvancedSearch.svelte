<script>
  // block--advanced-search.html.twig + advanced_search's form. Rows chain with
  // and/or, each "is" or "is not" — including "or … is not", which the API
  // supports (OrNotTermsSubscriber) although advanced_search itself does not.
  // Buttons are submit inputs because that is what the theme styles.
  import { search, FIELDS } from '../lib/search.svelte.js';

  function submit(event) {
    event.preventDefault();
    search.go({ page: 0 });
  }
</script>

<div class="advanced-search-block">
  <h2 class="block-title"></h2>
  <form id="advanced_search_form" class="advanced-search-form" onsubmit={submit}>
    <div id="advanced-search-ajax" class="js-form-wrapper form-wrapper">
      <div class="advanced-search-grid js-form-wrapper form-wrapper" id="edit-terms">
        {#each search.terms as term, n}
          {#if n > 0}
            <div class="grid-col-conjunction">
              <select aria-label="Select search condition" id="edit-terms-{n}-conjunction" class="form-select" bind:value={term.c}>
                <option value="AND">and</option>
                <option value="OR">or</option>
              </select>
            </div>
          {/if}
          <div class="grid-col-search">
            <select aria-label="Select search field" id="edit-terms-{n}-search" class="form-select" bind:value={term.f}>
              {#each FIELDS as [value, label]}
                <option {value}>{label}</option>
              {/each}
            </select>
          </div>
          <div class="grid-col-include">
            <select aria-label="Select search operator" id="edit-terms-{n}-include" class="form-select" bind:value={term.i}>
              <option value="IS">is</option>
              <option value="NOT">is not</option>
            </select>
          </div>
          <div class="grid-col-value">
            <input aria-label="Enter a search term" type="text" id="edit-terms-{n}-value" size="60" maxlength="128" class="form-text" bind:value={term.v} />
          </div>
          <div class="grid-col-actions">
            <div id="edit-terms-{n}-actions" class="js-form-wrapper form-wrapper">
              <input class="advanced-search-form__add fa button js-form-submit form-submit" type="submit" value="+" onclick={(e) => { e.preventDefault(); search.addTerm(n); }} />
              {#if search.terms.length > 1}
                <input class="advanced-search-form__remove fa button js-form-submit form-submit" aria-label="Remove" type="submit" value="-" onclick={(e) => { e.preventDefault(); search.removeTerm(n); }} />
              {/if}
            </div>
          </div>
        {/each}
      </div>
    </div>
    <div class="advanced-search-actions js-form-wrapper form-wrapper" id="edit-actions-wrapper">
      <input class="advanced-search-form__search button js-form-submit form-submit" type="submit" id="edit-submit" value="Search" />
      <input class="advanced-search-form__reset button js-form-submit form-submit" type="submit" id="edit-reset" value="Reset" onclick={(e) => { e.preventDefault(); search.reset(); }} />
    </div>
  </form>
</div>
