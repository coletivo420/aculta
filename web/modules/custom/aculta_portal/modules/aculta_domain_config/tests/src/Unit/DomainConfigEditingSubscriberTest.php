<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_domain_config\Unit;

use Drupal\aculta_domain_config\EventSubscriber\DomainConfigEditingSubscriber;
use Drupal\Core\Config\StorageInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\RouteProviderInterface;
use Drupal\domain\DomainInterface;
use Drupal\domain_config_switcher\DomainConfigSwitcherInterface;
use Drupal\domain_config_ui\DomainConfigEditContextInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Routing\Route;

/**
 * Testa a seleção do domínio de edição antes do carregamento do bloco.
 */
#[CoversClass(DomainConfigEditingSubscriber::class)]
#[Group('aculta_domain_config')]
final class DomainConfigEditingSubscriberTest extends UnitTestCase {

  /**
   * Aplica o domínio escolhido em qualquer rota da administração de blocos.
   */
  public function testBlockPathSetsEditingDomain(): void {
    $context = $this->createMock(DomainConfigEditContextInterface::class);
    $context->expects(self::once())
      ->method('setEditingDomain')
      ->with('conta_aculta_org', ['block.block.a', 'block.block.b']);

    $subscriber = $this->subscriber('conta_aculta_org', $context, FALSE);
    $subscriber->onRequest($this->event('/painel-administrativo/estrutura/block/manage/a'));
  }

  /**
   * Ignora rotas fora da administração de blocos.
   */
  public function testOtherPathIsIgnored(): void {
    $context = $this->createMock(DomainConfigEditContextInterface::class);
    $context->expects(self::never())->method('setEditingDomain');

    $subscriber = $this->subscriber('conta_aculta_org', $context, FALSE);
    $subscriber->onRequest($this->event('/painel-administrativo/configuracoes/domain'));
  }

  /**
   * O domínio padrão representa o global e não recebe override.
   */
  public function testDefaultDomainIsIgnored(): void {
    $context = $this->createMock(DomainConfigEditContextInterface::class);
    $context->expects(self::never())->method('setEditingDomain');

    $subscriber = $this->subscriber('aculta_org', $context, TRUE);
    $subscriber->onRequest($this->event('/painel-administrativo/estrutura/block'));
  }

  /**
   * Sem seleção na URL, a negociação normal continua valendo.
   */
  public function testNoSelectionIsIgnored(): void {
    $context = $this->createMock(DomainConfigEditContextInterface::class);
    $context->expects(self::never())->method('setEditingDomain');

    $subscriber = $this->subscriber(NULL, $context, FALSE);
    $subscriber->onRequest($this->event('/painel-administrativo/estrutura/block'));
  }

  /**
   * Monta o assinante com um domínio e uma lista de blocos fixos.
   */
  private function subscriber(?string $domainId, DomainConfigEditContextInterface $context, bool $isDefault): DomainConfigEditingSubscriber {
    $switcher = $this->createMock(DomainConfigSwitcherInterface::class);
    $switcher->method('getSelectedDomainId')->willReturn($domainId);

    $domain = $this->createMock(DomainInterface::class);
    $domain->method('status')->willReturn(TRUE);
    $domain->method('isDefault')->willReturn($isDefault);

    $domainStorage = $this->createMock(EntityStorageInterface::class);
    $domainStorage->method('load')->willReturn($domain);

    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')->with('domain')->willReturn($domainStorage);

    $configStorage = $this->createMock(StorageInterface::class);
    $configStorage->method('listAll')->with('block.block.')
      ->willReturn(['block.block.a', 'block.block.b']);

    $routeProvider = $this->createMock(RouteProviderInterface::class);
    $routeProvider->method('getRouteByName')->with('block.admin_display')
      ->willReturn(new Route('/painel-administrativo/estrutura/block'));

    return new DomainConfigEditingSubscriber($switcher, $context, $configStorage, $routeProvider, $entityTypeManager);
  }

  /**
   * Cria um RequestEvent de requisição principal para o caminho informado.
   */
  private function event(string $path): RequestEvent {
    $kernel = $this->createMock(HttpKernelInterface::class);
    return new RequestEvent($kernel, Request::create($path), HttpKernelInterface::MAIN_REQUEST);
  }

}
