<?php

declare(strict_types=1);

namespace Drupal\aculta_events\Hook;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountInterface;

/**
 * Bloqueia a criação e uso do tipo de conteúdo legado activity.
 */
final class ActivityRetirementHooks {

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

}
