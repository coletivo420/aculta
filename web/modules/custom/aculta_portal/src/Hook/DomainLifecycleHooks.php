<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Hook;

use Drupal\aculta_portal\Domain\ActiveSiteKeysState;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Responds to Domain entity changes and refreshes active site keys.
 */
final class DomainLifecycleHooks {

  public function __construct(
    #[Autowire(service: 'aculta_portal.active_site_keys_state')]
    private readonly ActiveSiteKeysState $activeSiteKeysState,
  ) {}

  /**
   * Reconstrói o estado dos site keys ativos após inserir um domínio.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   Domínio inserido.
   */
  #[Hook('domain_insert')]
  public function onDomainInsert(EntityInterface $entity): void {
    $this->activeSiteKeysState->rebuild();
  }

  /**
   * Reconstrói o estado dos site keys ativos após atualizar um domínio.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   Domínio atualizado.
   */
  #[Hook('domain_update')]
  public function onDomainUpdate(EntityInterface $entity): void {
    $this->activeSiteKeysState->rebuild();
  }

  /**
   * Reconstrói o estado dos site keys ativos após excluir um domínio.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   Domínio excluído.
   */
  #[Hook('domain_delete')]
  public function onDomainDelete(EntityInterface $entity): void {
    $this->activeSiteKeysState->rebuild();
  }

}
