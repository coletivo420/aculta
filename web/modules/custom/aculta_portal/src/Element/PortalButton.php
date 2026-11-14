<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Element;

use Drupal\Core\Render\Attribute\RenderElement;
use Drupal\Core\Render\Element\RenderElementBase;

/**
 * Elemento neutro de botão usado pelos módulos do Portal.
 *
 * O módulo entrega props sem conhecer o tema. O fallback do módulo mantém o
 * elemento acessível; o ACULTA420 sobrescreve o template e apresenta o SDC.
 */
#[RenderElement('aculta_portal_button')]
final class PortalButton extends RenderElementBase {

  /**
   * {@inheritdoc}
   */
  public function getInfo(): array {
    return [
      '#theme' => 'aculta_portal_button',
      '#props' => [],
      '#attributes' => [],
    ];
  }

}
