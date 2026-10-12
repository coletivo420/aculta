<?php

declare(strict_types=1);

namespace Drupal\aculta420\Hook;

use Drupal\Core\Extension\ExtensionList;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Logos de marcas de terceiros usadas pelo Portal (apresentação: o asset é do tema).
 */
final class PortalBrandHooks {

  public function __construct(
    #[Autowire(service: 'extension.list.theme')]
    private readonly ExtensionList $themes,
  ) {}

  /**
   * Logo do Mercado Pago na caixa de pagamentos de Meus pedidos.
   */
  #[Hook('preprocess_aculta_portal_payment_notice')]
  public function preprocessPaymentNotice(array &$variables): void {
    $path = $this->themes->getPath('aculta420') . '/assets/brands/mercado-pago/mercado-pago.svg';
    $variables['logo'] = Url::fromUri('base:' . $path)->toString();
  }

}
