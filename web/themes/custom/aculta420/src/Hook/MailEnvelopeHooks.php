<?php

declare(strict_types=1);

namespace Drupal\aculta420\Hook;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Apresentação do envelope HTML dos e-mails (aculta_mailer_wrap): o logo é o que o tema configura.
 *
 * O módulo de e-mail só fornece a base absoluta do site (public_base) e o logo da marca (logo_url); o logo do tema vem
 * da configuração do próprio tema (logo.path), sem caminho fixo no template.
 */
final class MailEnvelopeHooks {

  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * Implements hook_preprocess_HOOK() para o envelope de e-mail.
   */
  #[Hook('preprocess_aculta_mailer_wrap')]
  public function preprocess(array &$variables): void {
    $path = ltrim((string) $this->configFactory->get('aculta420.settings')->get('logo.path'), '/');
    $base = (string) ($variables['public_base'] ?? '');
    $variables['theme_logo'] = $base !== '' ? $base . $path : '/' . $path;
  }

}
