<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Email;

use Drupal\aculta_mailer\Template\MailTemplateRenderer;
use Drupal\aculta_mailer\Type\MailTypeRegistry;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Url;
use Drupal\user\OneTimeAuthentication;
use Drupal\user\UserInterface;

/**
 * Leva os três textos de conta do módulo user para a camada do aculta_mailer (decisão de 2026-10-20).
 *
 * O Core monta a mensagem com o texto do user.mail; este serviço troca assunto e corpo pelo tipo da camada, com o link
 * de uso único gerado pela mesma API do Core. Mensagens de outras chaves seguem como o Core as montou.
 */
final class AccountMailRouter {

  /** Chave do Core => ID do tipo na camada. */
  public const TYPES = [
    'password_reset' => AccountPasswordResetMailType::ID,
    'register_no_approval_required' => AccountRegisterMailType::ID,
    'register_admin_created' => AccountRegisterAdminMailType::ID,
  ];

  public function __construct(
    private readonly MailTypeRegistry $types,
    private readonly MailTemplateRenderer $renderer,
    private readonly OneTimeAuthentication $oneTime,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly DomainPurposeManager $purposes,
  ) {}

  /**
   * Troca assunto e corpo de uma mensagem do módulo user, quando a chave é uma das três de conta.
   *
   * @param array $message
   *   Mensagem do hook_mail_alter, com `module`, `key` e `params`.
   */
  public function apply(array &$message): void {
    if (($message['module'] ?? '') !== 'user' || !isset(self::TYPES[$message['key'] ?? ''])) {
      return;
    }
    $account = $message['params']['account'] ?? NULL;
    if (!$account instanceof UserInterface) {
      return;
    }
    $type = self::TYPES[$message['key']];
    if (!$this->types->has($type)) {
      return;
    }
    $rendered = $this->renderer->render($this->types->get($type), [
      'name' => $account->getDisplayName(),
      'username' => $account->getAccountName(),
      'site' => (string) $this->configFactory->get('system.site')->get('name'),
      'login_url' => $this->loginUrl(),
      'one_time_url' => $this->oneTime->generateOneTimeLoginUrl($account)->setAbsolute()->toString(),
    ]);
    $message['subject'] = $rendered['subject'];
    $message['body'] = [$rendered['body']];
  }

  /**
   * Entrada pública: a rota de login no domínio de Conta (`/entrar`). Sem esse domínio, a rota do próprio site.
   */
  private function loginUrl(): string {
    $url = $this->purposes->routeUrl('account', 'user.login');
    return ($url ?? Url::fromRoute('user.login', [], ['absolute' => TRUE]))->setAbsolute()->toString();
  }

}
