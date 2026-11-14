<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_messenger\Unit;

use Drupal\Core\Config\ConfigManagerInterface;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Extension\ExtensionPathResolver;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use PHPUnit\Framework\Attributes\Group;

/**
 * Atualização de notificação de Events para sites já instalados.
 *

 */
#[Group('aculta_messenger')]
final class EventsContributionNoticeUpdateTest extends UnitTestCase {

  /**
   * Em site que já tem a configuração, a atualização não grava nada.
   */
  public function testUpdateSkipsExistingNoticeConfiguration(): void {
    require_once DRUPAL_ROOT . '/modules/custom/aculta_portal/modules/aculta_messenger/aculta_messenger.install';

    $modulePath = DRUPAL_ROOT . '/modules/custom/aculta_portal/modules/aculta_messenger';
    $pathResolver = $this->createStub(ExtensionPathResolver::class);
    $pathResolver->method('getPath')->with('module', 'aculta_messenger')->willReturn($modulePath);

    $configManager = $this->createMock(ConfigManagerInterface::class);
    $configManager->method('loadConfigEntityByName')
      ->willReturn($this->createStub(ConfigEntityInterface::class));
    // Nada é salvo quando a configuração já existe.
    $configManager->expects(self::never())->method('getEntityTypeIdByName');

    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->expects(self::never())->method('getStorage');

    $container = new ContainerBuilder();
    $container->set('extension.path.resolver', $pathResolver);
    $container->set('config.manager', $configManager);
    $container->set('entity_type.manager', $entityTypeManager);

    $hadContainer = \Drupal::hasContainer();
    $previousContainer = $hadContainer ? \Drupal::getContainer() : NULL;
    \Drupal::setContainer($container);
    try {
      \aculta_messenger_update_10015();
    }
    finally {
      if ($previousContainer !== NULL) {
        \Drupal::setContainer($previousContainer);
      }
      else {
        \Drupal::unsetContainer();
      }
    }
    $this->addToAssertionCount(1);
  }

}
