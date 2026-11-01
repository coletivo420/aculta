<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\PathProcessor;

use Drupal\aculta_portal\Checkout\CheckoutSlugMap;
use Drupal\Core\PathProcessor\InboundPathProcessorInterface;
use Drupal\Core\PathProcessor\OutboundPathProcessorInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Symfony\Component\HttpFoundation\Request;

/**
 * Slugs públicos do checkout: gera /finalizar-compra/... e reconhece esses caminhos (ver CheckoutSlugMap).
 */
final class CheckoutFriendlyPathProcessor implements InboundPathProcessorInterface, OutboundPathProcessorInterface {

  /** {@inheritdoc} */
  public function processInbound($path, Request $request) {
    return CheckoutSlugMap::toInternal($path) ?? $path;
  }

  /** {@inheritdoc} */
  public function processOutbound($path, &$options = [], ?Request $request = NULL, ?BubbleableMetadata $bubbleable_metadata = NULL) {
    return CheckoutSlugMap::toPublic($path) ?? $path;
  }

}
