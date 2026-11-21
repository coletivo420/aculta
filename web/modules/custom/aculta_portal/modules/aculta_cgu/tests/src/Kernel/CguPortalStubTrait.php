<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_cgu\Kernel;

use Drupal\Core\DependencyInjection\ContainerBuilder;

/**
 * Stub sintético de aculta_portal.domain_site para testes de Kernel do CGU.
 *
 * O controller Marley do CGU referencia esse serviço, mas os testes não habilitam o
 * aculta_portal inteiro (dependências de contrib). O serviço satisfaz a referência do
 * container; as decisões de denúncia testadas não o utilizam.
 */
trait CguPortalStubTrait {

  /**
   * Registra o stub do domínio do portal no container do teste.
   */
  protected function registerDomainSiteStub(ContainerBuilder $container): void {
    $container->register('aculta_portal.domain_site', \Drupal\aculta_portal\Domain\DomainSiteManager::class)
      ->setFactory([CguDomainSiteStub::class, 'create'])
      ->setPublic(TRUE);
  }

  /**
   * Valor atual de um campo da denúncia, lido do banco.
   *
   * O ReportService trabalha sobre uma cópia recarregada; o objeto segurado pelo teste
   * fica desatualizado após a decisão, por isso a leitura não usa o objeto em memória.
   */
  protected function reportValue(\Drupal\aculta_cgu\Entity\CguReport $report, string $field): mixed {
    $fresh = $this->container->get('entity_type.manager')->getStorage('aculta_cgu_report')->loadUnchanged((int) $report->id());
    return $fresh instanceof \Drupal\aculta_cgu\Entity\CguReport ? $fresh->get($field)->value : NULL;
  }

}
