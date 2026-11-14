<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Controller;

use Drupal\comment\CommentInterface;
use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Exclusão de respostas da discussão.
 *
 * O autor exclui a própria resposta: ela permanece como "Comentário excluído", sem edição.
 * O administrador exclui a caixa (a resposta) de vez.
 */
final class DiscussionCommentDeleteController extends ControllerBase {

  public const TOMBSTONES = 'aculta_forum_comment_tombstones';

  public function __construct(
    private readonly KeyValueFactoryInterface $tombstoneFactory,
    private readonly CsrfTokenGenerator $csrfGenerator,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('keyvalue'),
      $container->get('csrf_token'),
    );
  }

  /**
   * Token CSRF de exclusão de uma resposta.
   */
  public static function tokenFor(CsrfTokenGenerator $csrf, int $cid): string {
    return $csrf->get('aculta_forum.comment.delete.' . $cid);
  }

  public function delete(CommentInterface $comment, Request $request): RedirectResponse {
    $cid = (int) $comment->id();
    if (!$this->csrfGenerator->validate((string) $request->request->get('token'), 'aculta_forum.comment.delete.' . $cid)) {
      throw new AccessDeniedHttpException('Token inválido.');
    }

    $account = $this->currentUser();
    $tombstones = $this->tombstoneFactory->get(self::TOMBSTONES);
    $host = $comment->getCommentedEntity();
    $redirect = $host ? $host->toUrl()->toString() : Url::fromRoute('<front>')->toString();

    if ($account->hasPermission('administer comments')) {
      // Moderação pela barra: o conteúdo some e fica "Conteúdo removido pela moderação".
      // A exclusão de vez (que remove o bloco) fica no modal de controles.
      $tombstones->set((string) $cid, 'moderacao');
      return new RedirectResponse($redirect);
    }

    if ((int) $comment->getOwnerId() !== (int) $account->id() || $tombstones->get((string) $cid)) {
      throw new AccessDeniedHttpException('Somente o autor pode excluir esta resposta.');
    }

    // Autor: mantém a caixa como "Comentário excluído", sem edição.
    $tombstones->set((string) $cid, TRUE);
    return new RedirectResponse($redirect);
  }

}
