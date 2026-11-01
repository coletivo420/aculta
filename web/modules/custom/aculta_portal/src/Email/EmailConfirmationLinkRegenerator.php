<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Email;

use Drupal\aculta_mailer\Api\SingleUseLinkRegenerator;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Gera o pedido novo de confirmação de e-mail para o reenvio manual de um link de uso único do aculta_mailer.
 *
 * O token é novo: o pedido passa pelo solicitante de confirmação, que cria a confirmação, envia a mensagem nova e
 * cancela as anteriores. O conteúdo antigo do aculta_mailer é cancelado pelo envio da mensagem nova.
 */
final class EmailConfirmationLinkRegenerator implements SingleUseLinkRegenerator {

  public function __construct(
    private readonly EmailConfirmationRequester $requester,
    private readonly EntityTypeManagerInterface $entityTypes,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function supports(string $type): bool {
    return $type === EmailConfirmationRequestMailType::ID;
  }

  /**
   * {@inheritdoc}
   */
  public function regenerate(string $recipient): bool {
    $storage = $this->entityTypes->getStorage('user');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('mail', $recipient)
      ->range(0, 1)
      ->execute();
    $account = $ids === [] ? NULL : $storage->load(reset($ids));
    if ($account === NULL) {
      return FALSE;
    }
    return $this->requester->request($account) === EmailConfirmationRequestResult::ACCEPTED;
  }

}
