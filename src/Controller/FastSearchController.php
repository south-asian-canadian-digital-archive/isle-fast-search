<?php

declare(strict_types=1);

namespace Drupal\sacda_fast_search\Controller;

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
  protected const PER_PAGE = [15, 60, 120];
  // The view's exposed sorts.
  protected const ORDERS = ['search_api_relevance', 'title', 'edtf_year'];
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
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('database'),
      $container->get('dgi_image_discovery.service'),
      $container->get('path_alias.manager'),
      $container->get('file_url_generator'),
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
        'drupalSettings' => ['sacdaFastSearch' => ['endpoint' => '/api/search']],
      ],
    ];
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

    $per_page = (int) $request->query->get('items_per_page', static::PER_PAGE[0]);
    $per_page = in_array($per_page, static::PER_PAGE, TRUE) ? $per_page : static::PER_PAGE[0];
    $page = max(0, (int) $request->query->get('page', 0));

    $query = $index->query(['limit' => $per_page, 'offset' => $page * $per_page]);
    $query->setSearchId('sacda_fast_search');
    // The index has excerpt_always on, so Highlight would load every result
    // to build excerpts this UI never shows: ~30% of the request.
    $query->addTag('search_api_skip_processor_highlight');
    $query->addCondition('type', static::BUNDLES, 'IN');
    $query->addCondition('status', TRUE);

    // The view's sorts are all exposed, so exactly one applies (relevance by
    // default). Adding a tie-breaker here would reorder unkeyed searches.
    [$sort_field, $sort_direction] = $this->order((string) $request->query->get('sort_by', ''), (string) $request->query->get('sort_order', ''));
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
      'facets' => $this->facetsOut($blocks, $results->getExtraData('search_api_facets', []), $active),
    ]))->addCacheableDependency($cacheability);
  }

  /**
   * Sort field and direction from the view's exposed-sort parameters.
   *
   * The theme's `order` select (e.g. title_desc) drives sort_by/sort_order;
   * /search ignores `order` itself in the URL.
   */
  protected function order(string $field, string $direction): array {
    $direction = strtoupper($direction);
    if (in_array($field, static::ORDERS, TRUE)) {
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
   * Mirrors the processors configured on these facets: active first, then
   * count, then display value; hierarchy (resource types) with
   * hide_inactive_siblings; labels truncated the way advanced_search does.
   */
  protected function facetsOut(array $blocks, array $raw, array $active): array {
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
          'count' => (int) $bucket['count'],
          'active' => in_array($value, $selected, TRUE),
          'parent' => $is_term && $facet->getUseHierarchy() ? (string) ($terms[$value]->parent ?? '0') : '0',
        ];
      }

      $out[] = [
        'alias' => $alias,
        'block' => $block,
        'label' => $label,
        'widget' => 'checkbox',
        'active' => (bool) $selected,
        'softLimit' => (int) ($widget['config']['soft_limit'] ?? 0),
        'showMore' => $widget['config']['soft_limit_settings']['show_more_label'] ?? 'Show more',
        'showLess' => $widget['config']['soft_limit_settings']['show_less_label'] ?? 'Show less',
        'showCount' => !empty($widget['config']['show_numbers']),
        'items' => $this->tree($items, '0', isset($facet->getProcessorConfigs()['hide_inactive_siblings_processor'])),
      ];
    }
    return $out;
  }

  /**
   * Nests items under their parents and orders each level.
   */
  protected function tree(array $items, string $parent, bool $hide_inactive_siblings): array {
    $level = array_values(array_filter($items, fn($i) => $i['parent'] === $parent
      // Children whose parent is not in the result set surface at the top.
      || ($parent === '0' && !isset($items[$i['parent']]))));
    usort($level, fn($a, $b) => [$b['active'], $b['count'], $a['label']] <=> [$a['active'], $a['count'], $b['label']]);
    if ($hide_inactive_siblings && array_filter($level, fn($i) => $i['active'])) {
      $level = array_values(array_filter($level, fn($i) => $i['active']));
    }
    foreach ($level as &$item) {
      $item['children'] = $item['value'] === $parent ? [] : $this->tree($items, $item['value'], $hide_inactive_siblings);
      unset($item['parent']);
    }
    return $level;
  }

}
