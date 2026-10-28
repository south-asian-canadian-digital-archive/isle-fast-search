<?php

declare(strict_types=1);

namespace Drupal\sacda_fast_search\Plugin\Block;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;
use Drupal\facets\Plugin\Block\FacetBlock;
use Drupal\sacda_fast_search\Controller\FastSearchController;

/**
 * Facets' block, with /search-beta counted as showing /search's facet source.
 *
 * A facet set to "only visible when the facet source is visible" checks the
 * current path against /search, so on /search-beta it would hide. Swapped in
 * by sacda_fast_search_block_alter(); everywhere else it behaves as before.
 */
class FastSearchFacetBlock extends FacetBlock {

  /**
   * {@inheritdoc}
   */
  public function blockAccess(AccountInterface $account) {
    if (\Drupal::routeMatch()->getRouteName() === 'sacda_fast_search.page'
      && $this->getFacet()->getFacetSourceId() === FastSearchController::FACET_SOURCE) {
      return AccessResult::allowed()->addCacheableDependency($this->getFacet())->addCacheContexts(['route']);
    }
    return parent::blockAccess($account)->addCacheContexts(['route']);
  }

}
