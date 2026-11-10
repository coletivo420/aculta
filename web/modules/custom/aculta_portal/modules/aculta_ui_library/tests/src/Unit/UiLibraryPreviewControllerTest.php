<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_ui_library\Unit;

use Drupal\aculta_ui_library\Controller\UiLibraryPreviewController;
use Drupal\block\BlockInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\BlockPluginInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\Context\ContextRepositoryInterface;
use Drupal\Core\Routing\RouteProviderInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Valida a execução de preview sob decisões de acesso (allowed, forbidden, neutral).
 *
 * Garante SEC-01:
 * - BlockPluginInterface::access() com forbidden ou neutral NUNCA chama build().
 * - Apenas quando isAllowed() for TRUE é que build() é executado.
 */
final class UiLibraryPreviewControllerTest extends UnitTestCase {

  private ContextRepositoryInterface $contextRepository;
  private RouteProviderInterface $routeProvider;
  private AccountProxyInterface $currentUser;
  private AccountInterface $account;
  private UiLibraryPreviewController $controller;

  protected function setUp(): void {
    parent::setUp();

    $this->contextRepository = $this->createMock(ContextRepositoryInterface::class);
    $this->routeProvider = $this->createMock(RouteProviderInterface::class);
    $this->currentUser = $this->createMock(AccountProxyInterface::class);
    $this->account = $this->createMock(AccountInterface::class);

    $this->currentUser->method('getAccount')->willReturn($this->account);

    $container = new ContainerBuilder();
    $container->set('current_user', $this->currentUser);
    $container->set('string_translation', $this->getStringTranslationStub());
    \Drupal::setContainer($container);

    $this->controller = new UiLibraryPreviewController(
      $this->contextRepository,
      $this->routeProvider
    );
  }

  /**
   * Prova que quando o plugin retorna AccessResult::forbidden(), build() NUNCA é chamado.
   */
  public function testPluginAccessForbiddenPreventsBuild(): void {
    $block = $this->createMock(BlockInterface::class);
    $block->method('id')->willReturn('test_block');
    $block->method('label')->willReturn('Test Block');

    $plugin = $this->createMock(BlockPluginInterface::class);
    $plugin->expects($this->once())
      ->method('access')
      ->with($this->isInstanceOf(AccountInterface::class), TRUE)
      ->willReturn(AccessResult::forbidden('Proibido para este usuário'));

    $plugin->expects($this->never())
      ->method('build');

    $block->method('getPlugin')->willReturn($plugin);

    $result = $this->controller->preview($block);

    self::assertArrayHasKey('unavailable', $result);
    self::assertArrayNotHasKey('content', $result);
    self::assertSame(0, $result['#cache']['max-age']);
  }

  /**
   * Prova que quando o plugin retorna AccessResult::neutral(), build() NUNCA é chamado.
   */
  public function testPluginAccessNeutralPreventsBuild(): void {
    $block = $this->createMock(BlockInterface::class);
    $block->method('id')->willReturn('test_block');
    $block->method('label')->willReturn('Test Block');

    $plugin = $this->createMock(BlockPluginInterface::class);
    $plugin->expects($this->once())
      ->method('access')
      ->with($this->isInstanceOf(AccountInterface::class), TRUE)
      ->willReturn(AccessResult::neutral());

    $plugin->expects($this->never())
      ->method('build');

    $block->method('getPlugin')->willReturn($plugin);

    $result = $this->controller->preview($block);

    self::assertArrayHasKey('unavailable', $result);
    self::assertArrayNotHasKey('content', $result);
    self::assertSame(0, $result['#cache']['max-age']);
  }

  /**
   * Prova que quando o plugin retorna AccessResult::allowed(), build() é executado.
   */
  public function testPluginAccessAllowedExecutesBuild(): void {
    $block = $this->createMock(BlockInterface::class);
    $block->method('id')->willReturn('test_block');
    $block->method('label')->willReturn('Test Block');

    $plugin = $this->createMock(BlockPluginInterface::class);
    $plugin->expects($this->once())
      ->method('access')
      ->with($this->isInstanceOf(AccountInterface::class), TRUE)
      ->willReturn(AccessResult::allowed());

    $plugin->expects($this->once())
      ->method('build')
      ->willReturn(['#markup' => '<div>Conteúdo seguro de teste</div>']);

    $block->method('getPlugin')->willReturn($plugin);

    $result = $this->controller->preview($block);

    self::assertArrayNotHasKey('unavailable', $result);
    self::assertArrayHasKey('content', $result);
    self::assertSame(0, $result['#cache']['max-age']);
  }

}
