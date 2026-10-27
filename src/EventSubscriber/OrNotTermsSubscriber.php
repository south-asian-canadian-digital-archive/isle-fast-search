<?php

declare(strict_types=1);

namespace Drupal\sacda_fast_search\EventSubscriber;

use Drupal\advanced_search\AdvancedSearchQueryTerm;
use Drupal\search_api_solr\Event\PostConvertedQueryEvent;
use Drupal\search_api_solr\Event\SearchApiSolrEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Lets an "or" search row say "is not".
 *
 * advanced_search refuses OR + NOT in AdvancedSearchQueryTerm's constructor,
 * yet its form offers the choice (the #states rule meant to hide "is not" on
 * "or" rows never matches), so /search answers it with a 500. In Solr it is
 * simply `OR (*:* -clause)`.
 *
 * FastSearchController hands advanced_search those rows as "is", so the
 * module still does all of its own work (edismax, query fields, highlighting).
 * This runs after it and rebuilds the same main query with each such clause
 * negated. Rows the module supports produce the same string it would.
 */
class OrNotTermsSubscriber implements EventSubscriberInterface {

  /**
   * Query option holding the original `a` terms, set only when needed.
   */
  public const OPTION = 'sacda_fast_search.or_not_terms';

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    // After advanced_search's subscriber (default priority 0).
    return [SearchApiSolrEvents::POST_CONVERT_QUERY => ['alter', -10]];
  }

  /**
   * Rewrites the main query with the "or … is not" clauses negated.
   */
  public function alter(PostConvertedQueryEvent $event): void {
    $search_api_query = $event->getSearchApiQuery();
    $original = $search_api_query->getOption(self::OPTION);
    if (!$original) {
      return;
    }

    $index = $search_api_query->getIndex();
    $field_mapping = $index->getServerInstance()->getBackend()
      ->getSolrFieldNamesKeyedByLanguage($search_api_query->getLanguages(), $index);

    $q = [];
    foreach (array_values(static::normalize($original)) as $n => $params) {
      $negate = static::isOrNot($params);
      $params['i'] = $negate ? 'IS' : ($params['i'] ?? 'IS');
      $term = AdvancedSearchQueryTerm::fromQueryParams($params);
      if (!$term) {
        continue;
      }
      $clause = $term->toSolrQuery($field_mapping);
      if ($q) {
        $q[] = $term->getConjunction();
      }
      $q[] = $negate ? "(*:* -$clause)" : $clause;
    }
    if ($q) {
      $event->getSolariumQuery()->setQuery(implode(' ', $q));
    }
  }

  /**
   * Whether a row is "or" + "is not".
   */
  public static function isOrNot(array $params): bool {
    return strtoupper((string) ($params['c'] ?? '')) === 'OR'
      && strtoupper((string) ($params['i'] ?? '')) === 'NOT'
      && trim((string) ($params['v'] ?? '')) !== '';
  }

  /**
   * Rows in the order advanced_search reads them, empty ones dropped.
   */
  public static function normalize(array $terms): array {
    return array_filter($terms, fn($t) => is_array($t) && trim((string) ($t['v'] ?? '')) !== '' && !empty($t['f']));
  }

}
