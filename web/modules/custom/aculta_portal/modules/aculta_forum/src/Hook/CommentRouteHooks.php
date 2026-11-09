<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Symfony\Component\Routing\RouteCollection;

/**
 * Caminhos públicos dos comentários em português.
 *
 * Mantém os nomes de rota do core (links e formulários continuam funcionando) e troca só o
 * caminho. O comentário avulso (entity.comment.canonical) vira /resposta/{comment} e redireciona
 * para a página do tópico, na paginação e âncora certas.
 */
final class CommentRouteHooks {

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
   * Implements hook_route_alter().
   */
  #[Hook('route_alter')]
  public function routeAlter(RouteCollection $routes): void {
    foreach (self::PATHS as $name => $path) {
      $route = $routes->get($name);
      if ($route !== NULL) {
        $route->setPath($path);
      }
    }

    // O comentário avulso não é mais uma página: redireciona para o tópico.
    $canonical = $routes->get('entity.comment.canonical');
    if ($canonical !== NULL) {
      $canonical->setDefaults([
        '_controller' => 'aculta_forum.comment_reply_permalink_controller:redirectToComment',
      ]);
    }
  }

}
