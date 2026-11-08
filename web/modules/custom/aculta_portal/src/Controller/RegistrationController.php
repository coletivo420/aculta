<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;

/**
 * Provides the public registration status while account creation is closed. */
final class RegistrationController extends ControllerBase {

  /**
   * Returns an informational page without exposing account creation. */
  public function closed(): array {
    return [
      '#type' => 'container',
      '#attributes' => ['data-portal-ui' => 'registration-status'],
      'message' => [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $this->t('O cadastro de novas contas está temporariamente fechado. Ele será disponibilizado quando a confirmação de e-mail estiver pronta.'),
      ],
      'login' => [
        '#type' => 'component',
        '#component' => 'aculta420_v2:button',
        '#props' => [
          'text' => (string) $this->t('Já tenho uma conta — entrar'),
          'url' => Url::fromRoute('user.login')->toString(),
          'variant' => 'primary',
        ],
      ],
      '#cache' => [
        'contexts' => ['url.site'],
        'tags' => ['config:user.settings'],
      ],
    ];
  }

}
