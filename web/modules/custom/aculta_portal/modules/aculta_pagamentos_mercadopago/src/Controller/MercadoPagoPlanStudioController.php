<?php

declare(strict_types=1);

namespace Drupal\aculta_pagamentos_mercadopago\Controller;

use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\aculta_pagamentos_mercadopago\Form\MercadoPagoPlanManagementForm;

/**
 * Builds the ACULTA Studio presentation for plan management.
 */
final class MercadoPagoPlanStudioController {

  public function __construct(private readonly FormBuilderInterface $formBuilder) {}

  /**
   * Renders the shared plan form inside the ACCOUNT-purpose Studio shell.
   *
   * @return array<string, mixed>
   *   Render array using the ACULTA420 site theme.
   */
  public function page(): array {
    return [
      '#type' => 'container',
      '#attributes' => ['data-portal-ui' => 'studio-mercadopago-plans'],
      'description' => [
        '#plain_text' => new TranslatableMarkup('Crie e atualize planos recorrentes da conta sandbox do Mercado Pago. As assinaturas e cobranças permanecem sob gestão dos fluxos proprietários de doação.'),
      ],
      'form' => $this->formBuilder->getForm(MercadoPagoPlanManagementForm::class),
      '#cache' => ['contexts' => ['user', 'user.permissions', 'url.site'], 'max-age' => 0],
    ];
  }

}
