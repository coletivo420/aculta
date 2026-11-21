<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Service;

use Drupal\aculta_forum\Entity\ForumPost;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Controle de acesso das ações de publicação do Fórum na Marley (frontend).
 *
 * Delega a regra ao ForumPostMover, que já valida perfil, domínio, tópico e visibilidade.
 * Não concede nada além do que o serviço de mover publicação concede.
 */
final class ForumMarleyPostActions {

  public function __construct(
    private readonly ForumPostMover $mover,
  ) {}

  /**
   * Autoriza a ação de mover uma publicação a partir da rota Marley.
   *
   * O nome do argumento corresponde ao parâmetro da rota ({aculta_forum_post}).
   */
  public function access(AccountInterface $account, ?ForumPost $aculta_forum_post = NULL): AccessResultInterface {
    if (!$account->isAuthenticated() || !$aculta_forum_post instanceof ForumPost) {
      return AccessResult::forbidden()->cachePerUser()->cachePerPermissions();
    }
    $moverAccess = $this->mover->access($aculta_forum_post, $account);
    return AccessResult::allowedIf($moverAccess->isAllowed())
      ->addCacheableDependency($moverAccess)
      ->cachePerUser()
      ->cachePerPermissions();
  }

}
