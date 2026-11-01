<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_commerce_melhorenvio\Unit;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\aculta_commerce_melhorenvio\Address\ShipmentAddressBinder;
use Drupal\aculta_portal_cep\Lookup\CepLookupInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Yaml\Yaml;

/**
 * Tests dependency injection for the shipment address binder service.
 */
#[CoversClass(ShipmentAddressBinder::class)]
#[Group('aculta_commerce_melhorenvio')]
final class ShipmentAddressBinderServiceDefinitionTest extends TestCase {

  /**
   * The service YAML injects its mapper alias and config factory explicitly.
   */
  public function testShipmentAddressBinderUsesExplicitMapperAndConfigFactory(): void {
    $modulePath = dirname(__DIR__, 3);
    $container = new ContainerBuilder();
    $services = Yaml::parseFile($modulePath . '/aculta_commerce_melhorenvio.services.yml')['services'];
    foreach ($services as $serviceId => $service) {
      if (isset($service['alias'])) {
        $container->setAlias($serviceId, $service['alias']);
        continue;
      }
      $definition = new Definition($service['class']);
      $definition->setAutowired($service['autowire'] ?? FALSE);
      if (isset($service['arguments'])) {
        $definition->setArguments(array_map(
          static fn(mixed $argument): mixed => is_string($argument) && str_starts_with($argument, '@')
            ? new Reference(substr($argument, 1))
            : $argument,
          $service['arguments'],
        ));
      }
      if ($serviceId === 'aculta_commerce_melhorenvio.shipment_address_binder') {
        $definition->setPublic(TRUE);
      }
      $container->setDefinition($serviceId, $definition);
    }

    $container->register('config.factory', ConfigFactoryInterface::class)
      ->setSynthetic(TRUE)
      ->setPublic(TRUE);
    $container->register(CepLookupInterface::class, CepLookupInterface::class)
      ->setSynthetic(TRUE)
      ->setPublic(TRUE);
    $container->compile();
    $container->set('config.factory', $this->createMock(ConfigFactoryInterface::class));
    $container->set(CepLookupInterface::class, $this->createMock(CepLookupInterface::class));

    $this->assertInstanceOf(
      ShipmentAddressBinder::class,
      $container->get('aculta_commerce_melhorenvio.shipment_address_binder'),
    );
  }

}
