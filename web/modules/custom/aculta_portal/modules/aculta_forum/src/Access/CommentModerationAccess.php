<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Access;

use Drupal\comment\CommentInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controles administrativos do comentário: só administrador e só por HTMX (sem página própria).
 */
final class CommentModerationAccess {

  public function access(AccountInterface $account, Request $request, CommentInterface $comment): AccessResultInterface {
    $isHtmx = $request->headers->has('HX-Request');
    return AccessResult::allowedIf($isHtmx && $account->hasPermission('administer comments'))
      ->addCacheContexts(['user.permissions', 'headers:HX-Request'])
      ->addCacheableDependency($comment);
  }

}
