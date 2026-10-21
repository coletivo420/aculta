<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Aviso;

use Drupal\aculta_portal\Account\EmailConfirmationPolicy;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Aviso permanente de e-mail não confirmado, no sistema de avisos do site.
 *
 * Existe um aviso (message, modelo aculta_msg_email_pendente) para cada conta com e-mail não confirmado. Ele aparece
 * na lista de avisos do aculta_messenger como item persistente (sem fechar) e some quando o e-mail é confirmado.
 * Não contém dados pessoais.
 */
final class EmailPendingAviso {

  /** Modelo de aviso do e-mail não confirmado. */
  public const TEMPLATE = 'aculta_msg_email_pendente';

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly EmailConfirmationPolicy $policy,
  ) {}

  /** TRUE quando a conta está logada e ainda não confirmou o e-mail. */
  public function isPending(AccountInterface $account): bool {
    return $account->isAuthenticated() && !$this->policy->isConfirmed($account);
  }

  /** Garante o aviso da conta (cria se faltar) e devolve-o. */
  public function ensure(int $uid): ?object {
    $storage = $this->entityTypeManager->getStorage('message');
    $ids = $this->ids($uid);
    if ($ids === []) {
      $message = $storage->create(['template' => self::TEMPLATE, 'uid' => $uid]);
      $message->save();
      return $message;
    }
    return $storage->load(reset($ids));
  }

  /** Título do aviso (delta 0 do modelo). Null se o modelo não existir. */
  public function title(?object $message): ?string {
    return $this->text($message, 0);
  }

  /** Corpo do aviso (delta 1 do modelo). Única fonte do texto que a caixa de Minha conta também mostra. */
  public function body(?object $message): ?string {
    return $this->text($message, 1);
  }

  /** Texto de um delta do modelo do aviso. */
  private function text(?object $message, int $delta): ?string {
    if ($message === NULL || !method_exists($message, 'getText')) {
      return NULL;
    }
    $items = $message->getText(NULL, $delta);
    // O modelo guarda HTML (<p>) e devolve Markup: o texto de aviso é sempre texto puro.
    $item = is_array($items) ? reset($items) : $items;
    $plain = trim(html_entity_decode(strip_tags((string) $item), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    return $plain !== '' ? $plain : NULL;
  }

  /** Remove os avisos de e-mail pendente da conta. */
  public function remove(int $uid): void {
    $storage = $this->entityTypeManager->getStorage('message');
    $ids = $this->ids($uid);
    if ($ids !== []) {
      $storage->delete($storage->loadMultiple($ids));
    }
  }

  /** @return int[] */
  private function ids(int $uid): array {
    return array_values(array_map('intval', $this->entityTypeManager->getStorage('message')->getQuery()
      ->accessCheck(FALSE)
      ->condition('template', self::TEMPLATE)
      ->condition('uid', $uid)
      ->execute()));
  }

}
