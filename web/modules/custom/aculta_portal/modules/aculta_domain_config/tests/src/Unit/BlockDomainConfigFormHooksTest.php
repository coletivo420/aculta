<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_domain_config\Unit;

use Drupal\aculta_domain_config\Hook\BlockDomainConfigFormHooks;
use Drupal\Core\Config\StorageInterface;
use Drupal\Core\Form\FormState;
use Drupal\domain_config_switcher\DomainConfigSwitcherInterface;
use Drupal\domain_config_ui\DomainConfigEditContextInterface;
use Drupal\domain_config_ui\DomainConfigUIManagerInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Tests the block domain switch submit callback.
 */
#[CoversClass(BlockDomainConfigFormHooks::class)]
#[Group('aculta_domain_config')]
final class BlockDomainConfigFormHooksTest extends UnitTestCase {

  /**
   * The selected domain changes while unrelated query parameters are preserved.
   */
  public function testSwitchSubmitPreservesQueryAndSetsDomain(): void {
    $requestStack = new RequestStack();
    $requestStack->push(Request::create('/admin/structure/block?destination=%2Fadmin&domain=old'));
    $hooks = $this->createHooks($requestStack);
    $formState = new FormState();
    $formState->setValue(['aculta_domain_config', 'domain'], 'new');
    $form = [];

    $hooks->switchSubmit($form, $formState);

    $redirect = $formState->getRedirect();
    self::assertSame('<current>', $redirect->getRouteName());
    self::assertSame('/admin', $redirect->getOptions()['query']['destination']);
    self::assertSame('new', $redirect->getOptions()['query'][DomainConfigSwitcherInterface::QUERY_ARG]);
  }

  /**
   * Clearing the selection removes the override query parameter.
   */
  public function testSwitchSubmitClearsDomainQuery(): void {
    $requestStack = new RequestStack();
    $requestStack->push(Request::create('/admin/structure/block?domain=old&destination=blocks'));
    $hooks = $this->createHooks($requestStack);
    $formState = new FormState();
    $formState->setValue(['aculta_domain_config', 'domain'], '');
    $form = [];

    $hooks->switchSubmit($form, $formState);

    $query = $formState->getRedirect()->getOptions()['query'];
    self::assertSame('blocks', $query['destination']);
    self::assertArrayNotHasKey(DomainConfigSwitcherInterface::QUERY_ARG, $query);
  }

  private function createHooks(RequestStack $requestStack): BlockDomainConfigFormHooks {
    return new BlockDomainConfigFormHooks(
      $this->createMock(DomainConfigSwitcherInterface::class),
      $this->createMock(DomainConfigEditContextInterface::class),
      $this->createMock(DomainConfigUIManagerInterface::class),
      $this->createMock(StorageInterface::class),
      $requestStack,
    );
  }

}
