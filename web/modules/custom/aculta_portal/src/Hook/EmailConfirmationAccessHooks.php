<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Hook;

use Drupal\aculta_portal\Account\EmailConfirmationPolicy;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Bloqueia escrita de quem não confirmou o e-mail (política de confirmação de e-mail): wiki e comentários.
 *
 * Leitura continua livre. Cursos, loja e apoio são bloqueados nas suas próprias rotas (ver docs/portal).
 */
final class EmailConfirmationAccessHooks {

  /** Entidades de progresso de curso, respostas, pedidos e itens (loja e apoio). */
  private const BLOCKED_CREATE = ['lms_course_status', 'lms_answer', 'commerce_order', 'commerce_order_item'];

  /** Bundles de wiki: verbetes (node) e categorias (taxonomy). */
  private const WIKI = [
    'node' => ['wiki_entry'],
    'taxonomy_term' => ['wiki_category'],
  ];

  public function __construct(
    #[Autowire(service: 'aculta_portal.email_confirmation_policy')]
    private readonly EmailConfirmationPolicy $policy,
  ) {}

  #[Hook('entity_create_access')]
  public function createAccess(AccountInterface $account, array $context, ?string $entity_bundle): AccessResultInterface {
    if (!$this->isRestricted($context['entity_type_id'] ?? '', $entity_bundle, $account)) {
      return AccessResult::neutral();
    }
    return AccessResult::forbidden('E-mail não confirmado.')->addCacheContexts(['user'])->cachePerUser();
  }

  /** Verdadeiro para escrita de wiki ou comentário por usuário autenticado sem e-mail confirmado. */
  private function isRestricted(string $entityTypeId, ?string $bundle, AccountInterface $account): bool {
    if ($account->isAnonymous() || $this->policy->isConfirmed($account)) {
      return FALSE;
    }
    if ($entityTypeId === 'comment' || in_array($entityTypeId, self::BLOCKED_CREATE, TRUE)) {
      return TRUE;
    }
    return $bundle !== NULL && in_array($bundle, self::WIKI[$entityTypeId] ?? [], TRUE);
  }

}
