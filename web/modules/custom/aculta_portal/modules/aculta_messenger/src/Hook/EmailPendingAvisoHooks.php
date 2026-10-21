<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Hook;

use Drupal\aculta_messenger\Aviso\EmailPendingAviso;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Ligações do aviso de e-mail pendente com o aviso de atenção de "Minha conta" e com a confirmação.
 */
final class EmailPendingAvisoHooks {

  public function __construct(
    #[Autowire(service: 'aculta_messenger.email_pending_aviso')]
    private readonly EmailPendingAviso $pending,
  ) {}

  /** Título e texto do aviso de atenção em "Minha conta" vêm do mesmo aviso persistente. */
  #[Hook('aculta_portal_email_notice_alter')]
  public function emailNoticeAlter(array &$context, AccountInterface $account): void {
    if (!$this->pending->isPending($account)) {
      return;
    }
    $aviso = $this->pending->ensure((int) $account->id());
    $context['title'] = $this->pending->title($aviso) ?? '';
    $context['detail'] = $this->pending->body($aviso) ?? '';
  }

  /**
   * Remove o aviso quando o e-mail é confirmado. Vem do ponto aculta_portal_email_confirmed, e não de user_update:
   * a confirmação pode não salvar a conta (o papel já existe), e o aviso não pode sobrar.
   */
  #[Hook('aculta_portal_email_confirmed')]
  public function emailConfirmed(int $uid): void {
    $this->pending->remove($uid);
  }

}
