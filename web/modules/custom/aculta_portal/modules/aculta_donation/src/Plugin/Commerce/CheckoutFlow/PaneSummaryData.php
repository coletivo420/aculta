<?php

namespace Drupal\aculta_donation\Plugin\Commerce\CheckoutFlow;

use Drupal\commerce_checkout\Plugin\Commerce\CheckoutPane\CheckoutPaneInterface;
use Drupal\Core\Url;

/**
 * A value object for performance and type safety.
 */
class PaneSummaryData {

  /**
   * Array of panes providing summaries.
   *
   * @var \Drupal\commerce_checkout\Plugin\Commerce\CheckoutPane\CheckoutPaneInterface[]
   */
  public readonly array $panes;

  /**
   * Instantiate readonly values for PaneSummaryData.
   *
   * @param int $index
   *   The step index.
   * @param string $label
   *   The step label.
   * @param \Drupal\Core\Url $url
   *   The step url.
   * @param array $panes
   *   Panes with summaries.
   */
  public function __construct(
    public readonly int $index,
    public readonly string $label,
    public readonly Url $url,
    array $panes,
  ) {
    foreach ($panes as $pane) {
      if (!($pane instanceof CheckoutPaneInterface)) {
        throw new \TypeError('All $pane items passed must be instances of CheckoutPaneInterface');
      }
    }
    $this->panes = $panes;
  }

  /**
   * Helper function to check if panes are present in this data.
   *
   * @return bool
   *   True if there are panes set.
   */
  public function hasPanes(): bool {
    return !empty($this->panes);
  }

}
