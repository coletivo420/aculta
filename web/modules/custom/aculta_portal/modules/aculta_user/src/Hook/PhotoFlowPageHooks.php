<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Hook;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Anexa o fluxo do popup de foto (aculta_user/photo-flow) na página do painel e informa a foto salva ao script.
 *
 * O script precisa da foto salva para distinguir um envio novo de um valor que já estava no campo.
 */
final class PhotoFlowPageHooks {

  public function __construct(
    #[Autowire(service: 'current_route_match')]
    private readonly RouteMatchInterface $routeMatch,
    #[Autowire(service: 'current_user')]
    private readonly AccountInterface $currentUser,
    #[Autowire(service: 'entity_type.manager')]
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  #[Hook('page_attachments')]
  public function pageAttachments(array &$attachments): void {
    if ($this->routeMatch->getRouteName() !== 'aculta_portal.dashboard' || (int) $this->currentUser->id() <= 0) {
      return;
    }
    $account = $this->entityTypeManager->getStorage('user')->load($this->currentUser->id());
    $saved = $account instanceof UserInterface ? (int) ($account->get('user_picture')->target_id ?? 0) : 0;
    $attachments['#attached']['library'][] = 'aculta_user/photo-flow';
    $attachments['#attached']['drupalSettings']['acultaUser']['savedFid'] = $saved;
  }

}
