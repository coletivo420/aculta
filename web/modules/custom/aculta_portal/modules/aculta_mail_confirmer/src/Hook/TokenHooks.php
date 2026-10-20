<?php

declare(strict_types=1);

namespace Drupal\aculta_mail_confirmer\Hook;

use Drupal\aculta_mail_confirmer\AcultaMailConfirmerManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;

/**
 * Tokens do pedido de confirmação de e-mail.
 */
final class TokenHooks {

  /**
   * Creates the token hooks.
   *
   * @param \Drupal\aculta_mail_confirmer\AcultaMailConfirmerManagerInterface $manager
   *   The email confirmation service.
   */
  public function __construct(protected AcultaMailConfirmerManagerInterface $manager) {
  }

  /**
   * Declara o tipo de token e seus tokens.
   */
  #[Hook('token_info')]
  public function tokenInfo(): array {
    return [
      'types' => [
        'aculta-mail-confirmer' => [
          'name' => new TranslatableMarkup('Email confirmer'),
          'description' => new TranslatableMarkup('Tokens related to the email confirmation process'),
          'needs-data' => 'aculta_mail_confirmer',
        ],
      ],
      'tokens' => [
        'aculta-mail-confirmer' => [
          'confirmation-url' => [
            'name' => new TranslatableMarkup('Confirmation URL'),
            'description' => new TranslatableMarkup('The URL of the page where users can confirm their email address.'),
          ],
          'confirmation-mail' => [
            'name' => new TranslatableMarkup('Confirmation email'),
            'description' => new TranslatableMarkup('The email address to confirm.'),
          ],
        ],
      ],
    ];
  }

  /**
   * Substitui os tokens do pedido pelo link de resposta e pelo e-mail a confirmar.
   */
  #[Hook('tokens')]
  public function tokens(string $type, array $tokens, array $data, array $options, BubbleableMetadata $bubbleable_metadata): array {
    if ($type !== 'aculta-mail-confirmer' || !isset($data['aculta_mail_confirmer'])) {
      return [];
    }
    $confirmation = $data['aculta_mail_confirmer'];
    $replacements = [];
    foreach ($tokens as $name => $original) {
      switch ($name) {
        case 'confirmation-url':
          $replacements[$original] = Url::fromRoute(
            'entity.aculta_mail_confirmer.response_form',
            ['aculta_mail_confirmer' => $confirmation->uuid(), 'hash' => $this->manager->getHash($confirmation)],
          )->setAbsolute()->toString();
          $bubbleable_metadata->setCacheMaxAge(0);
          break;

        case 'confirmation-mail':
          $replacements[$original] = $confirmation->getEmail();
          break;
      }
    }
    return $replacements;
  }

}
