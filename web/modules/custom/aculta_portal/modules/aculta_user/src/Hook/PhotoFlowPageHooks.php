<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\RouteMatchInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Anexa o fluxo do popup de foto (aculta_user/photo-flow) na página do painel da conta.
 *
 * A biblioteca vai para a página, e não só para o formulário, porque o popup é renderizado por um template do Portal.
 * O script só age quando os elementos do popup existem.
 */
final class PhotoFlowPageHooks {

  public function __construct(
    #[Autowire(service: 'current_route_match')]
    private readonly RouteMatchInterface $routeMatch,
  ) {}

  #[Hook('page_attachments')]
  public function pageAttachments(array &$attachments): void {
    if ($this->routeMatch->getRouteName() === 'aculta_portal.dashboard') {
      $attachments['#attached']['library'][] = 'aculta_user/photo-flow';
    }
  }

}
