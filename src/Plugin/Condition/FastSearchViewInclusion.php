<?php

declare(strict_types=1);

namespace Drupal\sacda_fast_search\Plugin\Condition;

use Drupal\context\Plugin\Condition\ViewInclusion;
use Drupal\sacda_fast_search\Controller\FastSearchController;

/**
 * Context's "view inclusion" condition, with /search-beta counted as /search.
 *
 * Blocks placed for /search (advanced search, result pager, anything added
 * later) are limited to it with this condition. Swapped in for the original
 * by sacda_fast_search_condition_info_alter(), so those blocks appear on
 * /search-beta under the rest of their configured visibility.
 */
class FastSearchViewInclusion extends ViewInclusion {

  /**
   * {@inheritdoc}
   */
  public function evaluate() {
    if (!empty($this->configuration['view_inclusion'])
      // The parent keeps its route match private.
      && \Drupal::routeMatch()->getRouteName() === 'sacda_fast_search.page') {
      $view_route = str_replace('.', '-', FastSearchController::viewRoute());
      return in_array($view_route, $this->configuration['view_inclusion'], TRUE);
    }
    return parent::evaluate();
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    return array_merge(parent::getCacheContexts(), ['route']);
  }

}
