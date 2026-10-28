<?php

declare(strict_types=1);

namespace Drupal\aculta_studio\Experience;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;

/**
 * Studio entry point for Mercado Pago plan administration. */
final class MercadoPagoPlansExperienceProvider implements StudioExperienceProviderInterface {

  public function __construct(private readonly ModuleHandlerInterface $modules) {}

  /**
   * {@inheritdoc} */
  public function id(): string {
    return 'mercado_pago_plans';
  }

  /**
   * {@inheritdoc} */
  public function label(): string {
    return 'Planos Mercado Pago';
  }

  /**
   * {@inheritdoc} */
  public function purpose(): string {
    return 'account';
  }

  /**
   * {@inheritdoc} */
  public function routeName(): string {
    return 'aculta_pagamentos_mercadopago.studio_plans';
  }

  /**
   * {@inheritdoc} */
  public function available(): bool {
    return $this->modules->moduleExists('aculta_pagamentos_mercadopago');
  }

  /**
   * {@inheritdoc} */
  public function access(AccountInterface $account): AccessResultInterface {
    return AccessResult::allowedIf($account->isAuthenticated())
      ->andIf(AccessResult::allowedIfHasPermission($account, 'access aculta portal'))
      ->andIf(AccessResult::allowedIfHasPermission($account, 'administrar doacoes'))
      ->andIf(AccessResult::allowedIf($this->available()))
      ->cachePerUser();
  }

  /**
   * {@inheritdoc} */
  public function build(): array {
    return [
      '#type' => 'container',
      '#attributes' => ['data-portal-ui' => 'studio-mercadopago-plans'],
      'link' => [
        '#type' => 'link',
        '#title' => new TranslatableMarkup('Administrar planos de assinatura'),
        '#url' => Url::fromRoute($this->routeName()),
      ],
      '#cache' => ['contexts' => ['user', 'user.permissions', 'url.site']],
    ];
  }

}
