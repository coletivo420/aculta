<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_events\Unit\Plugin\Field\FieldWidget;

use Drupal\aculta_events\Plugin\Field\FieldWidget\EventLocationModalWidget;
use Drupal\aculta_events\Plugin\Field\FieldWidget\EventOrganizationModalWidget;
use Drupal\aculta_events\Controller\EventLocationWidgetController;
use Drupal\Core\DependencyInjection\ClassResolver;
use Drupal\Core\Utility\CallableResolver;
use Drupal\aculta_events\Route\EventRouteContext;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Tests container autowiring for the event modal widgets.
 */
final class EventModalWidgetsDependencyInjectionTest extends UnitTestCase {

  /**
   * The location widget receives its entity manager and current account.
   */
  public function testLocationWidgetReceivesEntityManagerAndCurrentUser(): void {
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $currentUser = $this->createMock(AccountProxyInterface::class);
    $widget = EventLocationModalWidget::create($this->container($entityTypeManager, $currentUser), $this->configuration(), 'location', []);

    self::assertSame($entityTypeManager, (new \ReflectionProperty($widget, 'entityTypeManager'))->getValue($widget));
    self::assertSame($currentUser, (new \ReflectionProperty($widget, 'currentUser'))->getValue($widget));
  }

  /**
   * The organization widget receives its entity manager.
   */
  public function testOrganizationWidgetReceivesEntityManager(): void {
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $widget = EventOrganizationModalWidget::create($this->container($entityTypeManager), $this->configuration(), 'organization', []);

    self::assertSame($entityTypeManager, (new \ReflectionProperty($widget, 'entityTypeManager'))->getValue($widget));
  }

  /**
   * The Form API callback resolves the controller service notation.
   */
  public function testWidgetAjaxCallbackResolvesThroughCallableResolver(): void {
    $container = $this->container(
      $this->createMock(EntityTypeManagerInterface::class),
      $this->createMock(AccountProxyInterface::class),
    );
    $resolver = new CallableResolver(new ClassResolver($container));

    $callback = $resolver->getCallableFromDefinition(EventLocationWidgetController::class . ':clearLocationFormWidget');

    self::assertInstanceOf(EventLocationWidgetController::class, $callback[0]);
    self::assertSame('clearLocationFormWidget', $callback[1]);
  }

  /**
   * Returns the standard widget plugin configuration.
   */
  private function configuration(): array {
    return [
      'field_definition' => $this->createMock(FieldDefinitionInterface::class),
      'settings' => [],
      'third_party_settings' => [],
    ];
  }

  /**
   * Creates a container with the widget service dependencies.
   */
  private function container(EntityTypeManagerInterface $entityTypeManager, ?AccountProxyInterface $currentUser = NULL): ContainerBuilder {
    $container = new ContainerBuilder();
    $container->set(EntityTypeManagerInterface::class, $entityTypeManager);
    $container->set('aculta_events.route_context', new EventRouteContext());
    if ($currentUser !== NULL) {
      $container->set(AccountProxyInterface::class, $currentUser);
    }
    return $container;
  }

}
