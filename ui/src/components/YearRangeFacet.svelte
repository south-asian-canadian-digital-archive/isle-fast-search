<script>
  // sacda_modules' YearRangeWidget: From / To, Apply on its own row.
  import { search } from '../lib/search.svelte.js';

  let { facet } = $props();
  // Seeded once; FacetBlock re-creates this component (#key) whenever the
  // applied range changes, rather than syncing state in an effect.
  let min = $state(facet.min ?? '');
  let max = $state(facet.max ?? '');

  const apply = () => search.setYear(facet.alias, String(min ?? ''), String(max ?? ''));
  const onKey = (event) => event.key === 'Enter' && (event.preventDefault(), apply());
</script>

<div data-drupal-facet-id={facet.alias}>
  <div class="facets-widget-year_range">
    <ul data-drupal-facet-id={facet.alias} data-drupal-facet-alias={facet.alias} class="{facet.active ? 'facet-active' : 'facet-inactive'} item-list__year_range">
      <li>
        <div class="js-form-item form-item form-type-number js-form-type-number form-no-label">
          <label class="visually-hidden" for="year_min">{facet.labels.min}</label>
          <input class="facet-year-range form-number" id="year_min" data-type="year-range-min" inputmode="numeric" step="1" placeholder={facet.labels.min} aria-label={facet.labels.min} type="number" bind:value={min} onkeydown={onKey} />
        </div>
      </li>
      <li>
        <div class="js-form-item form-item form-type-number js-form-type-number form-no-label">
          <label class="visually-hidden" for="year_max">{facet.labels.max}</label>
          <input class="facet-year-range form-number" id="year_max" data-type="year-range-max" inputmode="numeric" step="1" placeholder={facet.labels.max} aria-label={facet.labels.max} type="number" bind:value={max} onkeydown={onKey} />
        </div>
      </li>
      <li><button type="button" class="facet-year-range__apply" data-facet-id={facet.alias} onclick={apply}>{facet.labels.apply}</button></li>
    </ul>
  </div>
</div>
