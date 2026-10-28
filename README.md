# SACDA Fast Search

A faster search page for the [South Asian Canadian Digital Archive](https://github.com/south-asian-canadian-digital-archive)
Islandora site: a JSON search endpoint over the existing Solr index, plus a
Svelte front end that reproduces the site's `/search` page.

The stock `/search` page (a Search API view with Facets and
advanced_search) spends ~0.13 CPU-seconds of PHP per uncached request
building entities, fields, facet blocks and Twig, while the Solr query
itself takes a few milliseconds. This module answers the same query with
Solr plus a few flat SQL lookups and renders in the browser.

Measured on a 2-CPU test box with 240 unique two/three-facet URLs:

| | `/search` | `/api/search` |
| --- | --- | --- |
| PHP CPU per request | 0.13 s | 0.024 s |
| CPU in use at 10 req/s | 1.25 of 2 cores | 0.22 of 2 cores |
| Max throughput | 11 req/s | 47 req/s |

## Routes

- `/search-beta` — the Svelte page (same URL format as `/search`).
- `/api/search` — JSON: results, facets with counts, totals.

Query parameters match `/search`: `f[n]=alias:value` facets,
`a[n][c|f|i|v]` advanced-search rows, `sort_by`/`sort_order`,
`items_per_page`, `display`, `page`.

## How it stays identical to `/search`

It reads the same configuration rather than copying it:

- **Facets**: the enabled facet blocks in the theme's `filters` region, in
  block order with block labels; facet processors (ordering, hierarchy,
  hide inactive siblings, label truncation) are mirrored.
- **Settings**: per facet, the soft limit, "Show more"/"Show less"
  labels, count display, hard limit, minimum count and sort processors (in
  their weight order and direction); from the view, the items-per-page
  options, default and pager size, and the exposed sorts with their labels;
  from the advanced search block and `advanced_search.settings`, the
  keyword fields, the "all fields" label and the display modes. The page
  reads them when it is built, so changes in Drupal apply here too.
- **Facet size**: each response carries only a facet's first soft-limit
  values (plus selected ones) and a `more` count. "Show more" fetches the
  rest with `/api/search?…&facet=<alias>`. With ~20k items PROD has
  ~8,000 facet values, which made the full response ~800 KB.
- **Keyword search**: `a[...]` is applied by advanced_search's own Solr
  subscriber, so semantics and relevance order are the same.
- **Markup**: one Svelte component per theme template, emitting the
  theme's markup and classes, so the theme's stylesheet styles it.

Deliberate differences: "or … is not" rows work (`OrNotTermsSubscriber`;
advanced_search refuses them), a two-row results header on phones, and
the index's Highlight processor is skipped (excerpts are never shown).

## Requirements

Built for the `sacda` theme and site configuration. Depends on
`search_api`, `facets`, `advanced_search`, `dgi_image_discovery` and
`sacda_modules` (whose `StoredImageDiscovery` provides precomputed
collection thumbnails). It is not a general-purpose module.

## Building the UI

```sh
cd ui
bun install
bun run build   # writes dist/
```

CI rebuilds `dist/` before building the image, and the image ships only
`dist/`. Svelte 5, Vite 8.
