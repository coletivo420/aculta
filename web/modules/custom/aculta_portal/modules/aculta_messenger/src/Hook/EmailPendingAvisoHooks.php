<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Hook;

use Drupal\aculta_portal\Account\EmailConfirmationPolicy;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Aviso persistente de e-mail não confirmado, no sistema de avisos do site.
 *
 * Quem não confirmou o e-mail tem um aviso (message, modelo aculta_msg_email_pendente) enquanto a confirmação estiver
 * pendente. O aviso aparece no título do aviso de atenção em "Minha conta" (ponto aculta_portal_email_notice) e é
 * removido quando o e-mail é confirmado. Não contém dados pessoais.
 */
final class EmailPendingAvisoHooks {

  /** Modelo de aviso do e-mail não confirmado. */
  public const TEMPLATE = 'aculta_msg_email_pendente';

  public function __construct(
    #[Autowire(service: 'entity_type.manager')]
    private readonly EntityTypeManagerInterface $entityTypeManager,
    #[Autowire(service: 'aculta_portal.email_confirmation_policy')]
    private readonly EmailConfirmationPolicy $policy,
  ) {}

  /** Garante o aviso de quem não confirmou e usa o texto do modelo como título do aviso de atenção. */
  #[Hook('aculta_portal_email_notice_alter')]
  public function emailNoticeAlter(array &$context, AccountInterface $account): void {
    if ($account->isAnonymous() || $this->policy->isConfirmed($account)) {
      return;
    }
    $storage = $this->entityTypeManager->getStorage('message');
    $ids = $this->avisoIds((int) $account->id());
    if ($ids === []) {
      $message = $storage->create(['template' => self::TEMPLATE, 'uid' => $account->id()]);
      $message->save();
    }
    else {
      $message = $storage->load(reset($ids));
    }
    $text = $message?->getText(NULL, 0);
    if (is_array($text)) {
      $text = reset($text);
    }
    if (is_string($text) && $text !== '') {
      $context['title'] = $text;
    }
  }

  /**
   * Remove o aviso quando o e-mail é confirmado. Vem do ponto aculta_portal_email_confirmed, e não de user_update:
   * a confirmação pode não salvar a conta (o papel já existe), e o aviso não pode sobrar.
   */
  #[Hook('aculta_portal_email_confirmed')]
  public function emailConfirmed(int $uid): void {
    $storage = $this->entityTypeManager->getStorage('message');
    $ids = $this->avisoIds($uid);
    if ($ids !== []) {
      $storage->delete($storage->loadMultiple($ids));
    }
  }

  /** IDs dos avisos de e-mail pendente da conta. */
  private function avisoIds(int $uid): array {
    return array_values($this->entityTypeManager->getStorage('message')->getQuery()
      ->accessCheck(FALSE)
      ->condition('template', self::TEMPLATE)
      ->condition('uid', $uid)
      ->execute());
  }

}
