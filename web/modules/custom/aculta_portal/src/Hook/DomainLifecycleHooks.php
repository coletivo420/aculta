<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Hook;

use Drupal\aculta_portal\Domain\ActivePurposesState;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Responds to Domain entity lifecycle changes to keep active purposes state fresh.
 */
final class DomainLifecycleHooks {

  public function __construct(
    #[Autowire(service: 'aculta_portal.active_purposes_state')]
    private readonly ActivePurposesState $activePurposesState,
  ) {}

  /**
   * Reconstrói o estado dos propósitos ativos após inserir um domínio.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   Domínio inserido.
   */
  #[Hook('domain_insert')]
  public function onDomainInsert(EntityInterface $entity): void {
    $this->activePurposesState->rebuild();
  }

  /**
   * Reconstrói o estado dos propósitos ativos após atualizar um domínio.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   Domínio atualizado.
   */
  #[Hook('domain_update')]
  public function onDomainUpdate(EntityInterface $entity): void {
    $this->activePurposesState->rebuild();
  }

  /**
   * Reconstrói o estado dos propósitos ativos após excluir um domínio.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   Domínio excluído.
   */
  #[Hook('domain_delete')]
  public function onDomainDelete(EntityInterface $entity): void {
    $this->activePurposesState->rebuild();
  }

}
