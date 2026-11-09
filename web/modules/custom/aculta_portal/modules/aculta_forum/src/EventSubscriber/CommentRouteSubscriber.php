<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\EventSubscriber;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Caminhos públicos dos comentários em português.
 *
 * Mantém os nomes de rota do core (links e formulários continuam funcionando) e troca só o
 * caminho. O comentário avulso (entity.comment.canonical) vira /resposta/{comment} e redireciona
 * para a página do tópico, na paginação e âncora certas.
 */
final class CommentRouteSubscriber extends RouteSubscriberBase {

  /**
   * Nome da rota do core => caminho em português.
   */
  private const PATHS = [
    'entity.comment.canonical' => '/resposta/{comment}',
    'entity.comment.edit_form' => '/resposta/{comment}/editar',
    'entity.comment.delete_form' => '/resposta/{comment}/apagar',
    'comment.approve' => '/resposta/{comment}/aprovar',
    'comment.reply' => '/resposta/comentar/{entity_type}/{entity}/{field_name}/{pid}',
    'comment.node_redirect' => '/resposta/{node}/comentar',
  ];

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection): void {
    foreach (self::PATHS as $name => $path) {
      $route = $collection->get($name);
      if ($route !== NULL) {
        $route->setPath($path);
      }
    }

    // Páginas antigas do fórum só existem no popup: criação de tópico em /node/add, edição e
    // resposta de comentário em página própria ficam bloqueadas (ver ForumPopupOnlyAccess).
    foreach (['node.add', 'entity.comment.edit_form', 'comment.reply'] as $popupOnlyRoute) {
      $route = $collection->get($popupOnlyRoute);
      if ($route !== NULL && $route->getRequirement('_custom_access') === NULL) {
        $route->setRequirement('_custom_access', 'aculta_forum.popup_only_access:access');
      }
    }

    // O comentário avulso não é mais uma página: redireciona para o tópico.
    $canonical = $collection->get('entity.comment.canonical');
    if ($canonical !== NULL) {
      $canonical->setDefaults([
        '_controller' => 'aculta_forum.comment_reply_permalink_controller:redirectToComment',
      ]);
    }
  }

}
