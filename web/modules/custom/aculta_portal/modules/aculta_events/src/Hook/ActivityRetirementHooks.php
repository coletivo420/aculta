<?php

declare(strict_types=1);

namespace Drupal\aculta_events\Hook;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Hook\Order\Order;
use Drupal\Core\Menu\MenuLinkDefault;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Bloqueia a criação e uso do tipo de conteúdo legado activity.
 */
final class ActivityRetirementHooks {

  use StringTranslationTrait;

  /**
   * Bloqueia criação do tipo node activity em favor de Eventos.
   */
  #[Hook('entity_create_access')]
  public function entityCreateAccess(AccountInterface $account, array $context, ?string $entity_bundle): AccessResultInterface {
    if (($context['entity_type_id'] ?? '') === 'node' && $entity_bundle === 'activity') {
      return AccessResult::forbidden('Atividades foram descontinuadas em favor de Eventos.');
    }
    return AccessResult::neutral();
  }

  /**
   * Remove o link legado de criação de Activity da barra administrativa e adiciona Eventos e Locais.
   */
  #[Hook('menu_links_discovered_alter', order: Order::Last)]
  public function menuLinksDiscoveredAlter(array &$links): void {
    unset($links['navigation.content.node_type.activity']);

    if (isset($links['navigation.create'])) {
      $links['navigation.content.aculta_event.create'] = [
        'title' => $this->t('Evento'),
        'route_name' => 'aculta_events.event_add',
        'parent' => 'navigation.create',
        'weight' => 10,
        'class' => MenuLinkDefault::class,
        'provider' => 'aculta_events',
        'menu_name' => 'content',
        'id' => 'navigation.content.aculta_event.create',
      ];
      $links['navigation.content.aculta_event_location.create'] = [
        'title' => $this->t('Local de evento'),
        'route_name' => 'aculta_events.location_add',
        'parent' => 'navigation.create',
        'weight' => 11,
        'class' => MenuLinkDefault::class,
        'provider' => 'aculta_events',
        'menu_name' => 'content',
        'id' => 'navigation.content.aculta_event_location.create',
      ];
    }
  }

}

