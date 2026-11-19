<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms\Unit\Resources;

use Drupal\aculta_index\Provider\ContentIndexProviderRegistry;
use Drupal\aculta_lms\Resources\ContentIndex\LmsCourseContentIndexProvider;
use Drupal\aculta_portal\Domain\DomainSiteManager;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\domain\DomainNegotiatorInterface;
use Drupal\group\Plugin\Group\Relation\GroupRelationTypeManagerInterface;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Yaml\Yaml;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the LMS provider's service-tag contract using its real service YAML.
 */
#[Group('aculta_lms')]
final class LmsCourseContentIndexProviderServiceTest extends UnitTestCase {

  /**
   * The owner service is discoverable through the Content Index provider tag.
   */
  public function testProviderServiceIsCollectedByTaggedRegistry(): void {
    $moduleDirectory = dirname(__DIR__, 4);
    $services = Yaml::parseFile($moduleDirectory . '/aculta_lms.services.yml');
    self::assertIsArray($services);
    self::assertIsArray($services['services'] ?? NULL);
    $serviceDefinitions = $services['services'];
    $providerId = 'aculta_lms.course_content_index_provider';
    self::assertArrayHasKey($providerId, $serviceDefinitions);
    self::assertIsArray($serviceDefinitions[$providerId]);
    $providerDefinition = $serviceDefinitions[$providerId];
    self::assertIsString($providerDefinition['class'] ?? NULL);
    self::assertIsArray($providerDefinition['arguments'] ?? NULL);
    self::assertIsArray($providerDefinition['tags'] ?? NULL);

    $container = new ContainerBuilder();
    $purposeServiceId = 'aculta_portal.domain_site';
    $purposeDefinition = (new Definition(DomainSiteManager::class))
      ->setSynthetic(TRUE)
      ->setPublic(TRUE);
    $container->setDefinition($purposeServiceId, $purposeDefinition);
    $relationManagerId = 'group_relation_type.manager';
    $container->setDefinition($relationManagerId, (new Definition())->setSynthetic(TRUE)->setPublic(TRUE));
    $arguments = [];
    foreach ($providerDefinition['arguments'] as $argument) {
      self::assertIsString($argument);
      $arguments[] = new Reference(substr($argument, 1));
    }
    $providerService = (new Definition($providerDefinition['class']))
      ->setArguments($arguments)
      ->setPublic(TRUE);
    foreach ($providerDefinition['tags'] as $tag) {
      self::assertIsArray($tag);
      self::assertIsString($tag['name'] ?? NULL);
      $providerService->addTag($tag['name']);
    }
    $container->setDefinition($providerId, $providerService);
    $registryDefinition = (new Definition(ContentIndexProviderRegistry::class, [new TaggedIteratorArgument('aculta_index.provider')]))
      ->setPublic(TRUE);
    $container->setDefinition('aculta_index.provider_registry', $registryDefinition);
    $container->compile();
    $container->set($purposeServiceId, new DomainSiteManager(
      $this->createMock(EntityTypeManagerInterface::class),
      $this->createMock(DomainNegotiatorInterface::class),
      $this->createMock(ConfigFactoryInterface::class),
      new RequestStack(),
    ));
    $container->set($relationManagerId, $this->createMock(GroupRelationTypeManagerInterface::class));

    $registry = $container->get('aculta_index.provider_registry');
    self::assertInstanceOf(LmsCourseContentIndexProvider::class, $registry->get('lms_course'));
    self::assertSame('lms_course', $registry->findProviderIdForBundle('group', 'lms_course'));
  }

}
