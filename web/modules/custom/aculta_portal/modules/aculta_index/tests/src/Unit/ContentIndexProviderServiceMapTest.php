<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_index\Unit;

use Drupal\aculta_index\Provider\ContentIndexProviderRegistry;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\domain\DomainNegotiatorInterface;
use Drupal\entity_usage\EntityUsageInterface;
use Drupal\group\Plugin\Group\Relation\GroupRelationTypeManagerInterface;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Yaml\Yaml;
use PHPUnit\Framework\Attributes\Group;

/**
 * Compiles the real service tags for every ACULTA Content Index adapter.
 *
 * The service files are discovered from the module tree instead of a manual
 * list: a provider contributed by any module, including the Events entities
 * and the index-owned file adapter, is compiled through the same
 * `aculta_index.provider` tagged iterator that production uses.
 */
#[Group('aculta_index')]
final class ContentIndexProviderServiceMapTest extends UnitTestCase {

  /**
   * The provider IDs registered by the enabled source modules.
   *
   * A new provider must be an intentional addition, so this list is a
   * contract checkpoint rather than a derived count.
   */
  private const EXPECTED_PROVIDER_IDS = [
    'activity',
    'course_document',
    'document',
    'event',
    'event_location',
    'event_organization',
    'file',
    'forum',
    'forum_attachment',
    'gallery',
    'image',
    'lms_course',
    'magazine',
    'remote_video',
    'wiki',
    'wiki_category',
  ];

  /**
   * Service files under modules that declare a provider tag.
   */
  public function testEnabledAdapterServiceFilesCompileIntoOneRegistry(): void {
    // web/modules/custom: the Portal kernel service file sits above modules/,
    // so scanning only the sibling modules would miss its remote-video provider.
    $customDirectory = dirname(__DIR__, 6);
    $serviceFiles = $this->providerServiceFiles($customDirectory);
    $this->assertNotEmpty($serviceFiles, 'Nenhum arquivo de serviço declara aculta_index.provider.');

    $container = new ContainerBuilder();
    $purposeServiceId = 'aculta_portal.domain_purpose';
    $container->setDefinition($purposeServiceId, (new Definition(DomainPurposeManager::class))
      ->setSynthetic(TRUE)
      ->setPublic(TRUE));
    $container->setDefinition('entity_type.manager', (new Definition(EntityTypeManagerInterface::class))
      ->setSynthetic(TRUE)
      ->setPublic(TRUE));
    $container->setDefinition('entity_usage.usage', (new Definition(EntityUsageInterface::class))
      ->setSynthetic(TRUE)
      ->setPublic(TRUE));
    $relationTypeManagerServiceId = 'group_relation_type.manager';
    $container->setDefinition($relationTypeManagerServiceId, (new Definition(GroupRelationTypeManagerInterface::class))
      ->setSynthetic(TRUE)
      ->setPublic(TRUE));
    $stringTranslationServiceId = 'string_translation';
    $container->setDefinition($stringTranslationServiceId, (new Definition(\Drupal\Core\StringTranslation\TranslationInterface::class))
      ->setSynthetic(TRUE)
      ->setPublic(TRUE));

    $contributed = 0;
    foreach ($serviceFiles as $serviceFile) {
      $services = Yaml::parseFile($serviceFile, Yaml::PARSE_CUSTOM_TAGS)['services'] ?? [];
      foreach ($services as $serviceId => $service) {
        $tags = array_filter(
          $service['tags'] ?? [],
          static fn (array $tag): bool => ($tag['name'] ?? NULL) === 'aculta_index.provider',
        );
        if ($tags === []) {
          continue;
        }
        $contributed++;
        $arguments = array_map(static function (mixed $argument): mixed {
          return is_string($argument) && str_starts_with($argument, '@')
            ? new Reference(substr($argument, 1))
            : $argument;
        }, $service['arguments'] ?? []);
        $definition = (new Definition($service['class'], $arguments))->setPublic(TRUE);
        foreach ($tags as $tag) {
          $definition->addTag($tag['name']);
        }
        $container->setDefinition($serviceId, $definition);
      }
    }
    $this->assertGreaterThanOrEqual(count(self::EXPECTED_PROVIDER_IDS), $contributed, 'Há menos definições de provider que o contrato.');

    $container->setDefinition(
      'aculta_index.provider_registry',
      (new Definition(ContentIndexProviderRegistry::class, [new TaggedIteratorArgument('aculta_index.provider')]))
        ->setPublic(TRUE),
    );
    $container->compile();
    $container->set($purposeServiceId, new DomainPurposeManager(
      $this->createMock(EntityTypeManagerInterface::class),
      $this->createMock(DomainNegotiatorInterface::class),
      $this->createMock(ConfigFactoryInterface::class),
      new RequestStack(),
    ));
    $container->set('entity_type.manager', $this->createMock(EntityTypeManagerInterface::class));
    $container->set('entity_usage.usage', $this->createMock(EntityUsageInterface::class));
    $relationTypeManager = $this->createMock(GroupRelationTypeManagerInterface::class);
    $relationTypeManager->method('hasDefinition')->willReturn(FALSE);
    $container->set($relationTypeManagerServiceId, $relationTypeManager);
    $container->set($stringTranslationServiceId, $this->createMock(\Drupal\Core\StringTranslation\TranslationInterface::class));

    $registry = $container->get('aculta_index.provider_registry');
    self::assertInstanceOf(ContentIndexProviderRegistry::class, $registry);

    $providerIds = array_keys($registry->all());
    sort($providerIds);
    self::assertSame(self::EXPECTED_PROVIDER_IDS, $providerIds);

    // Events project a dedicated entity type each, including location and
    // organization. Missing these would silently drop Events from the index.
    self::assertSame(['aculta_event' => ['aculta_event']], $registry->get('event')->getSupportedEntityTypes());
    self::assertSame(['aculta_event_location' => ['aculta_event_location']], $registry->get('event_location')->getSupportedEntityTypes());
    self::assertSame(['aculta_event_organization' => ['aculta_event_organization']], $registry->get('event_organization')->getSupportedEntityTypes());
    self::assertSame(['file' => ['file']], $registry->get('file')->getSupportedEntityTypes());

    // Each bundle has exactly one owner; the registry throws on duplicates.
    self::assertSame('event', $registry->findProviderIdForBundle('aculta_event', 'aculta_event'));
    self::assertSame('forum', $registry->findProviderIdForBundle('node', 'aculta_forum_topic'));
    self::assertSame('wiki', $registry->findProviderIdForBundle('node', 'wiki_entry'));

    // The LMS projection stays version 3 without the optional PDF Group plugin.
    self::assertSame(3, $registry->get('lms_course')->getProjectionVersion());
    self::assertNotSame('', $registry->fingerprint());
  }

  /**
   * Finds every module service file that declares an index provider tag.
   *
   * @return list<string>
   *   Absolute service file paths, excluding test support modules.
   */
  private function providerServiceFiles(string $moduleDirectory): array {
    $files = [];
    $iterator = new \RecursiveIteratorIterator(
      new \RecursiveDirectoryIterator($moduleDirectory, \FilesystemIterator::SKIP_DOTS),
    );
    foreach ($iterator as $file) {
      if (!$file->isFile() || !str_ends_with($file->getFilename(), '.services.yml')) {
        continue;
      }
      $path = $file->getPathname();
      if (str_contains($path, '/tests/')) {
        continue;
      }
      if (!str_contains((string) file_get_contents($path), 'aculta_index.provider')) {
        continue;
      }
      $files[] = $path;
    }
    sort($files);
    return $files;
  }

}
