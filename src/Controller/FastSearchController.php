<?php

declare(strict_types=1);

namespace Drupal\sacda_fast_search\Controller;

use Drupal\Component\Transliteration\TransliterationInterface;
use Drupal\Component\Utility\Unicode;
use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\dgi_image_discovery\ImageDiscoveryInterface;
use Drupal\facets\FacetInterface;
use Drupal\image\Entity\ImageStyle;
use Drupal\path_alias\AliasManagerInterface;
use Drupal\sacda_fast_search\EventSubscriber\OrNotTermsSubscriber;
use Drupal\sacda_modules\ImageDiscovery\StoredImageDiscovery;
use Drupal\search_api\Entity\Index;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Search as JSON, rendered in the browser.
 *
 * The stock /search page spends ~130-200 ms of PHP per uncached request
 * building entities, fields, facet blocks and Twig, while the Solr query itself
 * takes 2-4 ms. This answers the same query from Solr plus a handful of flat
 * SQL lookups — no entity loads, no render arrays — and leaves presentation to
 * the Svelte app in ui/, which reproduces the sacda theme's markup.
 *
 * Parity with /search comes from reading the same config, not copying it:
 * - facets: the enabled facet blocks in the theme's `filters` region, in block
 *   order with block labels, so the sidebar matches;
 * - keyword search: the `a[n][c|f|i|v]` parameters are applied by
 *   advanced_search's own PostConvertedQuery subscriber, which acts on every
 *   Search API query, so semantics and relevance are identical (plus "or …
 *   is not" rows, which /search 500s on; see OrNotTermsSubscriber);
 * - URLs: f[n]=alias:value, sort_by=, sort_order=, items_per_page=,
 *   display=, page=.
 * Access filtering is Search API's content_access processor, as on /search.
 */
class FastSearchController extends ControllerBase {

  protected const INDEX = 'default_solr_index';
  protected const FACET_SOURCE = 'search_api:views_page__solr_search_content__page_1';
  protected const THEME = 'sacda';
  protected const BUNDLES = ['archival_component', 'collection', 'islandora_object'];
  // The view behind /search; pager, sorts and keyword fields come from it and
  // from the blocks placed on it.
  protected const VIEW = 'solr_search_content';
  protected const DISPLAY = 'page_1';
  // facets' sort processors, by the result property each compares.
  protected const SORTS = [
    'active_widget_order' => 'active',
    'count_widget_order' => 'count',
    'display_value_widget_order' => 'sortLabel',
    'raw_value_widget_order' => 'value',
  ];
  // Facets on these fields hold taxonomy term IDs, not names.
  protected const TERM_ID_FIELDS = ['field_resource_type', 'field_subjects_name', 'child_resource_type'];
  // The view's did_image formatter and its field_description trim.
  protected const THUMBNAIL_STYLE = 'thumbnail';
  protected const DESCRIPTION_LENGTH = 400;

  public function __construct(
    protected Connection $database,
    protected ImageDiscoveryInterface $imageDiscovery,
    protected AliasManagerInterface $aliasManager,
    protected FileUrlGeneratorInterface $fileUrlGenerator,
    protected TransliterationInterface $transliteration,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('database'),
      $container->get('dgi_image_discovery.service'),
      $container->get('path_alias.manager'),
      $container->get('file_url_generator'),
      $container->get('transliteration'),
    );
  }

  /**
   * Page shell for the Svelte app.
   */
  public function page(): array {
    return [
      '#markup' => '<div id="sacda-fast-search"></div>',
      '#attached' => [
        'library' => ['sacda_fast_search/app'],
        'drupalSettings' => ['sacdaFastSearch' => ['endpoint' => '/api/search'] + $this->settings()],
      ],
      '#cache' => ['tags' => ['config:views.view.' . static::VIEW, 'config:block_list', 'config:advanced_search.settings']],
    ];
  }

  /**
   * Page settings read from the /search configuration.
   *
   * Everything the UI would otherwise hardcode: the view's pager and exposed
   * sorts, the advanced search block's fields and advanced_search's labels
   * and display modes. Editing any of them in Drupal changes this page too.
   */
  protected function settings(): array {
    $view = $this->config('views.view.' . static::VIEW);
    $option = fn(string $key) => $view->get('display.' . static::DISPLAY . '.display_options.' . $key)
      ?? $view->get('display.default.display_options.' . $key);

    $pager = $option('pager')['options'] ?? [];
    $default = (int) ($pager['items_per_page'] ?? 15);
    $per_page = !empty($pager['expose']['items_per_page'])
      ? array_values(array_filter(array_map('intval', explode(',', (string) ($pager['expose']['items_per_page_options'] ?? '')))))
      : [];

    // advanced_search's sort select: "<label> ↓" for ASC, "↑" for DESC.
    $orders = [];
    foreach ($option('sorts') ?? [] as $id => $sort) {
      if (!empty($sort['exposed'])) {
        $orders[$id] = (string) ($sort['expose']['label'] ?? $id);
      }
    }

    $advanced = $this->config('advanced_search.settings');
    $fields = [];
    if ($advanced->get('lucene_on_off') && $advanced->get('all_fields_on_off')) {
      $fields['all'] = (string) ($advanced->get('lucene_label') ?: 'All');
    }
    $index = Index::load(static::INDEX);
    foreach ($this->searchBlockFields() as $field) {
      $fields[$field] = $index->getField($field)?->getLabel() ?? $field;
    }

    $displays = array_keys(array_filter([
      'grid' => $advanced->get('grid_on_off'),
      'list' => $advanced->get('list_on_off'),
    ]));

    return [
      'perPage' => $per_page ?: [$default],
      'perPageDefault' => $default,
      'pagerQuantity' => (int) ($pager['quantity'] ?? 9),
      'orders' => $orders,
      'fields' => $fields,
      'displays' => $displays,
      'defaultDisplay' => $advanced->get('default-display-mode') ?: 'grid',
    ];
  }

  /**
   * Fields of the advanced search block placed on /search in the theme.
   */
  protected function searchBlockFields(): array {
    $blocks = $this->entityTypeManager()->getStorage('block')->loadByProperties([
      'theme' => static::THEME,
      'plugin' => 'advanced_search_block:' . static::VIEW . '__' . static::DISPLAY,
      'status' => TRUE,
    ]);
    uasort($blocks, fn($a, $b) => $a->getWeight() <=> $b->getWeight());
    $block = reset($blocks);
    return $block ? array_values($block->getPlugin()->getConfiguration()['fields'] ?? []) : [];
  }

  /**
   * GET /api/search with the same query string as /search.
   */
  public function api(Request $request): CacheableJsonResponse {
    $index = Index::load(static::INDEX);
    $blocks = $this->facetBlocks();
    $cacheability = (new CacheableMetadata())
      ->addCacheContexts(['url.query_args', 'user.permissions', 'languages:language_content'])
      ->addCacheTags(['search_api_list:' . static::INDEX, 'config:block_list', 'config:facets_facet_list']);

    $settings = $this->settings();
    $cacheability->addCacheTags(['config:views.view.' . static::VIEW, 'config:advanced_search.settings']);
    $per_page = (int) $request->query->get('items_per_page', $settings['perPageDefault']);
    $per_page = in_array($per_page, $settings['perPage'], TRUE) ? $per_page : $settings['perPageDefault'];
    $page = max(0, (int) $request->query->get('page', 0));

    // ?facet=<alias> asks for one facet's full list ("Show more"), no results.
    $only = (string) $request->query->get('facet', '');
    $only = isset($blocks[$only]) ? $only : '';

    $query = $index->query(['limit' => $only ? 0 : $per_page, 'offset' => $only ? 0 : $page * $per_page]);
    $query->setSearchId('sacda_fast_search');
    // The index has excerpt_always on, so Highlight would load every result
    // to build excerpts this UI never shows: ~30% of the request.
    $query->addTag('search_api_skip_processor_highlight');
    $query->addCondition('type', static::BUNDLES, 'IN');
    $query->addCondition('status', TRUE);

    // The view's sorts are all exposed, so exactly one applies (relevance by
    // default). Adding a tie-breaker here would reorder unkeyed searches.
    [$sort_field, $sort_direction] = $this->order(array_keys($settings['orders']), (string) $request->query->get('sort_by', ''), (string) $request->query->get('sort_order', ''));
    $query->sort($sort_field, $sort_direction);

    // Selected values, grouped by facet alias.
    $active = [];
    foreach ($request->query->all('f') as $raw) {
      [$alias, $value] = array_pad(explode(':', (string) $raw, 2), 2, '');
      if (isset($blocks[$alias]) && $value !== '') {
        $active[$alias][] = $value;
      }
    }

    $solr_facets = [];
    foreach ($blocks as $alias => ['facet' => $facet]) {
      $field = $facet->getFieldIdentifier();
      if ($facet->getWidget()['type'] === 'year_range') {
        if ($range = $this->yearRange($active[$alias][0] ?? '')) {
          // edtf_year stores year N as the integer N; see YearRangeWidget.
          $query->addCondition($field, $range, 'BETWEEN');
        }
        continue;
      }
      if (!empty($active[$alias])) {
        // Tagged so OR facets keep counts for their unselected siblings.
        $group = $query->createConditionGroup(strtoupper($facet->getQueryOperator()), ['facet:' . $field]);
        foreach ($active[$alias] as $value) {
          $group->addCondition($field, $value);
        }
        $query->addConditionGroup($group);
      }
      if ($only && $only !== $alias) {
        continue;
      }
      $solr_facets[$field] = [
        'field' => $field,
        'limit' => $facet->getHardLimit() ?: -1,
        'operator' => $facet->getQueryOperator(),
        'min_count' => $facet->getMinCount(),
        'missing' => FALSE,
      ];
    }
    $query->setOption('search_api_facets', $solr_facets);

    // advanced_search reads its terms from the request and refuses "or … is
    // not" rows. Show it those rows as "is" and let OrNotTermsSubscriber
    // negate them afterwards; the request is restored straight after.
    $terms = $request->query->all('a');
    $or_not = array_filter($terms, fn($t) => is_array($t) && OrNotTermsSubscriber::isOrNot($t));
    if ($or_not) {
      $query->setOption(OrNotTermsSubscriber::OPTION, $terms);
      $request->query->set('a', array_map(fn($t) => is_array($t) && OrNotTermsSubscriber::isOrNot($t) ? ['i' => 'IS'] + $t : $t, $terms));
    }
    try {
      $results = $query->execute();
    }
    catch (\InvalidArgumentException $e) {
      // Anything else advanced_search rejects (e.g. an unknown conjunction).
      return (new CacheableJsonResponse(['error' => $e->getMessage()], 400))
        ->addCacheableDependency($cacheability);
    }
    finally {
      if ($or_not) {
        $request->query->set('a', $terms);
      }
    }

    $facets = $this->facetsOut($only ? [$only => $blocks[$only]] : $blocks, $results->getExtraData('search_api_facets', []), $active, (bool) $only);
    if ($only) {
      return (new CacheableJsonResponse(['facets' => $facets]))->addCacheableDependency($cacheability);
    }

    $nids = [];
    foreach ($results->getResultItems() as $item) {
      if (preg_match('/^entity:node\/(\d+):/', $item->getId(), $m)) {
        $nids[] = (int) $m[1];
      }
    }

    return (new CacheableJsonResponse([
      'total' => $results->getResultCount(),
      'page' => $page,
      'perPage' => $per_page,
      'results' => $this->rows($nids, $cacheability),
      'facets' => $facets,
    ]))->addCacheableDependency($cacheability);
  }

  /**
   * Sort field and direction from the view's exposed-sort parameters.
   *
   * The theme's `order` select (e.g. title_desc) drives sort_by/sort_order;
   * /search ignores `order` itself in the URL.
   */
  protected function order(array $exposed, string $field, string $direction): array {
    $direction = strtoupper($direction);
    if (in_array($field, $exposed, TRUE)) {
      return [$field, $direction === 'ASC' ? 'ASC' : 'DESC'];
    }
    return ['search_api_relevance', 'DESC'];
  }

  /**
   * Parses the year facet's `(min:1980,max:1990)` value.
   */
  protected function yearRange(string $value): ?array {
    if (!preg_match('/^\(min:(-?\d*),max:(-?\d*)\)$/', $value, $m) || ($m[1] === '' && $m[2] === '')) {
      return NULL;
    }
    return [$m[1] === '' ? -9999 : (int) $m[1], $m[2] === '' ? 9999 : (int) $m[2]];
  }

  /**
   * The facet blocks of the theme's filters region, keyed by URL alias.
   *
   * Only facets of the /search source: the `_swc` blocks in the same region
   * belong to collection pages and render empty (and hidden) on /search.
   */
  protected function facetBlocks(): array {
    $blocks = $this->entityTypeManager()->getStorage('block')->loadByProperties([
      'theme' => static::THEME,
      'region' => 'filters',
      'status' => TRUE,
    ]);
    uasort($blocks, fn($a, $b) => $a->getWeight() <=> $b->getWeight());
    $facet_storage = $this->entityTypeManager()->getStorage('facets_facet');
    $out = [];
    foreach ($blocks as $block) {
      if (!str_starts_with($block->getPluginId(), 'facet_block:')) {
        continue;
      }
      $facet = $facet_storage->load(substr($block->getPluginId(), 12));
      if ($facet instanceof FacetInterface && $facet->getFacetSourceId() === static::FACET_SOURCE) {
        $out[$facet->getUrlAlias()] = [
          'facet' => $facet,
          'block' => $block->id(),
          'label' => $block->getPlugin()->getConfiguration()['label'] ?? $facet->label(),
        ];
      }
    }
    return $out;
  }

  /**
   * Result cards from flat lookups.
   */
  protected function rows(array $nids, CacheableMetadata $cacheability): array {
    if (!$nids) {
      return [];
    }
    $nodes = $this->database->query(
      'SELECT n.nid, n.title, t.nid AS stored, t.mid, d.field_description_value AS description, mt.name AS model
       FROM {node_field_data} n
       LEFT JOIN {sacda_thumbnail} t ON t.nid = n.nid
       LEFT JOIN {node__field_description} d ON d.entity_id = n.nid AND d.delta = 0
       LEFT JOIN {node__field_model} m ON m.entity_id = n.nid AND m.delta = 0
       LEFT JOIN {taxonomy_term_field_data} mt ON mt.tid = m.field_model_target_id AND mt.default_langcode = 1
       WHERE n.nid IN (:nids[]) AND n.default_langcode = 1',
      [':nids[]' => $nids],
    )->fetchAllAssoc('nid');

    $parents = [];
    foreach ($this->database->query(
      'SELECT m.entity_id, p.title FROM {node__field_member_of} m JOIN {node_field_data} p ON p.nid = m.field_member_of_target_id AND p.default_langcode = 1 WHERE m.entity_id IN (:nids[]) ORDER BY m.entity_id, m.delta',
      [':nids[]' => $nids],
    ) as $row) {
      $parents[$row->entity_id][] = $row->title;
    }

    // Nodes nobody has viewed since their last change have no stored answer
    // yet; compute those once (this also stores them).
    $missing = array_keys(array_filter($nodes, fn($n) => $n->stored === NULL));
    if ($missing && $this->imageDiscovery instanceof StoredImageDiscovery) {
      foreach ($this->entityTypeManager()->getStorage('node')->loadMultiple($missing) as $node) {
        $nodes[$node->id()]->mid = $this->imageDiscovery->getImage($node)->getMedia()?->id();
      }
    }

    $mids = array_filter(array_column($nodes, 'mid'));
    $images = $mids ? $this->database->query(
      'SELECT m.mid, f.uri, m.thumbnail__alt AS alt, m.thumbnail__width AS width, m.thumbnail__height AS height
       FROM {media_field_data} m JOIN {file_managed} f ON f.fid = m.thumbnail__target_id
       WHERE m.mid IN (:mids[]) AND m.status = 1',
      [':mids[]' => $mids],
    )->fetchAllAssoc('mid') : [];
    $style = ImageStyle::load(static::THUMBNAIL_STYLE);

    $rows = [];
    foreach ($nids as $nid) {
      if (!$node = $nodes[$nid] ?? NULL) {
        continue;
      }
      $image = $images[$node->mid] ?? NULL;
      $description = trim((string) $node->description);
      $trimmed = Unicode::truncate($description, static::DESCRIPTION_LENGTH, TRUE, TRUE);
      $rows[] = [
        'nid' => $nid,
        'title' => $node->title,
        'url' => $this->aliasManager->getAliasByPath('/node/' . $nid),
        'model' => $node->model ?? '',
        'memberOf' => implode(', ', $parents[$nid] ?? []),
        'description' => $trimmed,
        'descriptionTrimmed' => $trimmed !== $description,
        'image' => $image && $style ? [
          'src' => $this->fileUrlGenerator->transformRelative($style->buildUrl($image->uri)),
          'alt' => (string) $image->alt,
          'width' => (int) $image->width,
          'height' => (int) $image->height,
        ] : NULL,
      ];
      $cacheability->addCacheTags(['node:' . $nid, StoredImageDiscovery::cacheTag($nid)]);
    }
    return $rows;
  }

  /**
   * Facet blocks with ordered, labelled, nested items.
   *
   * Mirrors each facet's own settings: its sort processors in their order and
   * direction, hierarchy, hide_inactive_siblings, soft limit and labels, and
   * advanced_search's label truncation.
   *
   * Only the soft limit's worth of top-level values is sent (plus any that are
   * selected); `more` says how many were left out, and the UI fetches them
   * with ?facet=<alias> when "Show more" is clicked. Large archives have
   * thousands of values per facet, and sending them all made each response
   * ~800 KB and spent most of its time here.
   */
  protected function facetsOut(array $blocks, array $raw, array $active, bool $full = FALSE): array {
    $tids = [];
    foreach ($blocks as ['facet' => $facet]) {
      if (in_array($facet->getFieldIdentifier(), static::TERM_ID_FIELDS, TRUE)) {
        foreach ($raw[$facet->getFieldIdentifier()] ?? [] as $bucket) {
          $tids[] = (int) trim($bucket['filter'], '"');
        }
      }
    }
    $terms = $tids ? $this->database->query(
      'SELECT t.tid, t.name, p.parent_target_id AS parent FROM {taxonomy_term_field_data} t LEFT JOIN {taxonomy_term__parent} p ON p.entity_id = t.tid WHERE t.tid IN (:tids[]) AND t.default_langcode = 1',
      [':tids[]' => $tids],
    )->fetchAllAssoc('tid') : [];
    $truncate = $this->config('advanced_search.settings')->get('facet_truncate');

    $out = [];
    foreach ($blocks as $alias => ['facet' => $facet, 'block' => $block, 'label' => $label]) {
      $field = $facet->getFieldIdentifier();
      $widget = $facet->getWidget();
      if ($widget['type'] === 'year_range') {
        $range = $this->yearRange($active[$alias][0] ?? '');
        $out[] = [
          'alias' => $alias,
          'block' => $block,
          'label' => $label,
          'widget' => 'year_range',
          'active' => $range !== NULL,
          'min' => $range && $range[0] !== -9999 ? $range[0] : NULL,
          'max' => $range && $range[1] !== 9999 ? $range[1] : NULL,
          'labels' => [
            'min' => $widget['config']['min_label'] ?? 'From',
            'max' => $widget['config']['max_label'] ?? 'To',
            'apply' => $widget['config']['apply_label'] ?? 'Apply',
          ],
        ];
        continue;
      }

      $is_term = in_array($field, static::TERM_ID_FIELDS, TRUE);
      $selected = $active[$alias] ?? [];
      $items = [];
      foreach ($raw[$field] ?? [] as $bucket) {
        $value = trim($bucket['filter'], '"');
        $name = $is_term ? ($terms[$value]->name ?? NULL) : $value;
        if ($name === NULL || $name === '') {
          continue;
        }
        $items[$value] = [
          'value' => $value,
          'label' => is_numeric($truncate) ? Unicode::truncate($name, (int) $truncate, TRUE, TRUE) : $name,
          'sortLabel' => $this->transliteration->removeDiacritics($name),
          'count' => (int) $bucket['count'],
          'active' => in_array($value, $selected, TRUE),
          'parent' => $is_term && $facet->getUseHierarchy() ? (string) ($terms[$value]->parent ?? '0') : '0',
        ];
      }

      $soft_limit = (int) ($widget['config']['soft_limit'] ?? 0);
      $tree = $this->tree($items, $this->comparator($facet), isset($facet->getProcessorConfigs()['hide_inactive_siblings_processor']));
      $more = 0;
      if (!$full && $soft_limit && count($tree) > $soft_limit) {
        // Selected values stay listed even past the limit, so they can be
        // unticked.
        $shown = array_merge(
          array_slice($tree, 0, $soft_limit),
          array_values(array_filter(array_slice($tree, $soft_limit), fn($i) => $i['active'])),
        );
        $more = count($tree) - count($shown);
        $tree = $shown;
      }

      $out[] = [
        'alias' => $alias,
        'block' => $block,
        'label' => $label,
        'widget' => 'checkbox',
        'active' => (bool) $selected,
        'softLimit' => $soft_limit,
        'more' => $more,
        'showMore' => $widget['config']['soft_limit_settings']['show_more_label'] ?? 'Show more',
        'showLess' => $widget['config']['soft_limit_settings']['show_less_label'] ?? 'Show less',
        'showCount' => !empty($widget['config']['show_numbers']),
        'items' => $tree,
      ];
    }
    return $out;
  }

  /**
   * facets' sort for one facet: its sort processors by weight, first non-tie
   * wins, DESC reverses (DefaultFacetManager::sortFacetResults()).
   */
  protected function comparator(FacetInterface $facet): \Closure {
    $sorts = [];
    foreach ($facet->getProcessorConfigs() as $id => $config) {
      if (isset(static::SORTS[$id])) {
        $sorts[] = [(int) ($config['weights']['sort'] ?? 0), static::SORTS[$id], ($config['settings']['sort'] ?? 'ASC') === 'DESC' ? -1 : 1];
      }
    }
    usort($sorts, fn($a, $b) => $a[0] <=> $b[0]);
    return function (array $a, array $b) use ($sorts): int {
      foreach ($sorts as [, $key, $direction]) {
        $result = match ($key) {
          // ActiveWidgetOrderProcessor: ascending puts active values first.
          'active' => $b['active'] <=> $a['active'],
          'sortLabel', 'value' => strnatcasecmp((string) $a[$key], (string) $b[$key]),
          default => $a[$key] <=> $b[$key],
        };
        if ($result) {
          return $result * $direction;
        }
      }
      return 0;
    };
  }

  /**
   * Nests items under their parents and sorts each level.
   *
   * Grouped once by parent: a facet can hold thousands of values, and
   * scanning the whole list per item was quadratic.
   */
  protected function tree(array $items, \Closure $compare, bool $hide_inactive_siblings): array {
    $children = [];
    foreach ($items as $value => $item) {
      // Children whose parent is not in the result set surface at the top.
      $parent = $item['parent'] !== (string) $value && isset($items[$item['parent']]) ? $item['parent'] : '0';
      $children[$parent][] = $item;
    }
    $build = function (string $parent) use (&$build, $children, $compare, $hide_inactive_siblings): array {
      $level = $children[$parent] ?? [];
      usort($level, $compare);
      if ($hide_inactive_siblings && array_filter($level, fn($i) => $i['active'])) {
        $level = array_values(array_filter($level, fn($i) => $i['active']));
      }
      foreach ($level as &$item) {
        $item['children'] = $build((string) $item['value']);
        unset($item['parent'], $item['sortLabel']);
      }
      return $level;
    };
    return $build('0');
  }

}
