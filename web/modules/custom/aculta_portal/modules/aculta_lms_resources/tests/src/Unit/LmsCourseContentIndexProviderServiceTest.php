<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms_resources\Unit;

use Drupal\aculta_index\Provider\ContentIndexProviderRegistry;
use Drupal\aculta_lms_resources\ContentIndex\LmsCourseContentIndexProvider;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\domain\DomainNegotiatorInterface;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Yaml\Yaml;

/**
 * Tests the LMS provider's service-tag contract using its real service YAML.
 *
 * @group aculta_lms_resources
 */
final class LmsCourseContentIndexProviderServiceTest extends UnitTestCase {

  /**
   * The owner service is discoverable through the Content Index provider tag.
   */
  public function testProviderServiceIsCollectedByTaggedRegistry(): void {
    $moduleDirectory = dirname(__DIR__, 3);
    $services = Yaml::parseFile($moduleDirectory . '/aculta_lms_resources.services.yml');
    $providerId = 'aculta_lms_resources.course_content_index_provider';
    self::assertArrayHasKey($providerId, $services['services']);
    $providerDefinition = $services['services'][$providerId];

    $container = new ContainerBuilder();
    $purposeServiceId = 'aculta_portal.domain_purpose';
    $purposeDefinition = (new Definition(DomainPurposeManager::class))
      ->setSynthetic(TRUE)
      ->setPublic(TRUE);
    $container->setDefinition($purposeServiceId, $purposeDefinition);
    $providerService = (new Definition($providerDefinition['class']))
      ->setArguments(array_map(
        static fn (string $argument): Reference => new Reference(substr($argument, 1)),
        $providerDefinition['arguments'],
      ))
      ->setPublic(TRUE);
    foreach ($providerDefinition['tags'] as $tag) {
      $providerService->addTag($tag['name']);
    }
    $container->setDefinition($providerId, $providerService);
    $registryDefinition = (new Definition(ContentIndexProviderRegistry::class, [new TaggedIteratorArgument('aculta_index.provider')]))
      ->setPublic(TRUE);
    $container->setDefinition('aculta_index.provider_registry', $registryDefinition);
    $container->compile();
    $container->set($purposeServiceId, new DomainPurposeManager(
      $this->createMock(EntityTypeManagerInterface::class),
      $this->createMock(DomainNegotiatorInterface::class),
      $this->createMock(ConfigFactoryInterface::class),
      new RequestStack(),
    ));

    $registry = $container->get('aculta_index.provider_registry');
    self::assertInstanceOf(ContentIndexProviderRegistry::class, $registry);
    self::assertInstanceOf(LmsCourseContentIndexProvider::class, $registry->get('lms_course'));
    self::assertSame('lms_course', $registry->findProviderIdForBundle('group', 'lms_course'));
  }

}
