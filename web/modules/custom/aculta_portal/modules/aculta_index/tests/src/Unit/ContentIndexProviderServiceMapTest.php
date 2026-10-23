<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_index\Unit;

use Drupal\aculta_index\Provider\ContentIndexProviderRegistry;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\domain\DomainNegotiatorInterface;
use Drupal\group\Plugin\Group\Relation\GroupRelationTypeManagerInterface;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Yaml\Yaml;

/**
 * Compiles the service tags for all implemented ACULTA Content Index adapters.
 *
 * @group aculta_index
 */
final class ContentIndexProviderServiceMapTest extends UnitTestCase {

  /**
   * Current provider service files have unique owner/type/bundle declarations.
   */
  public function testEnabledAdapterServiceFilesCompileIntoOneRegistry(): void {
    $moduleDirectory = dirname(__DIR__, 4);
    $serviceFiles = [
      'aculta_activity/aculta_activity.services.yml',
      'aculta_document_viewer/aculta_document_viewer.services.yml',
      'aculta_forum/aculta_forum.services.yml',
      'aculta_gallery/aculta_gallery.services.yml',
      'aculta_image/aculta_image.services.yml',
      'aculta_lms_resources/aculta_lms_resources.services.yml',
      'aculta_magazine/aculta_magazine.services.yml',
      'aculta_pdf_viewer/modules/aculta_pdf_viewer_group/aculta_pdf_viewer_group.services.yml',
      'aculta_wiki/aculta_wiki.services.yml',
    ];
    $container = new ContainerBuilder();
    $purposeServiceId = 'aculta_portal.domain_purpose';
    $container->setDefinition($purposeServiceId, (new Definition(DomainPurposeManager::class))
      ->setSynthetic(TRUE)
      ->setPublic(TRUE));
    $relationTypeManagerServiceId = 'group_relation_type.manager';
    $container->setDefinition($relationTypeManagerServiceId, (new Definition(GroupRelationTypeManagerInterface::class))
      ->setSynthetic(TRUE)
      ->setPublic(TRUE));

    foreach ($serviceFiles as $serviceFile) {
      $services = Yaml::parseFile($moduleDirectory . '/' . $serviceFile, Yaml::PARSE_CUSTOM_TAGS)['services'];
      foreach ($services as $serviceId => $service) {
        $tags = array_filter(
          $service['tags'] ?? [],
          static fn (array $tag): bool => ($tag['name'] ?? NULL) === 'aculta_index.provider',
        );
        if ($tags === []) {
          continue;
        }
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
    $relationTypeManager = $this->createMock(GroupRelationTypeManagerInterface::class);
    $relationTypeManager->method('hasDefinition')->willReturn(FALSE);
    $container->set($relationTypeManagerServiceId, $relationTypeManager);

    $registry = $container->get('aculta_index.provider_registry');
    self::assertInstanceOf(ContentIndexProviderRegistry::class, $registry);
    self::assertSame([
      'activity' => ['node' => ['activity']],
      'course_document' => ['media' => ['course_document']],
      'document' => ['media' => ['document']],
      'forum' => [
        'node' => ['aculta_forum_topic'],
        'aculta_forum_post' => ['aculta_forum_post'],
        'comment' => ['aculta_discussion_post'],
      ],
      'forum_attachment' => ['media' => ['forum_attachment']],
      'gallery' => ['media_gallery' => ['media_gallery']],
      'image' => ['media' => ['image']],
      'lms_course' => ['group' => ['lms_course']],
      'magazine' => ['node' => ['revista']],
      'wiki' => ['node' => ['wiki_entry']],
      'wiki_category' => ['taxonomy_term' => ['wiki_category']],
    ], $this->providerMap($registry));
    self::assertSame(2, $registry->get('lms_course')->getProjectionVersion(), 'The service map compiles without the optional PDF Group plugin and includes the owner projection version.');
  }

  /**
   * Returns the service registry as provider ID => supported entity bundles.
   */
  private function providerMap(ContentIndexProviderRegistry $registry): array {
    $map = [];
    foreach ($registry->all() as $providerId => $provider) {
      $map[$providerId] = $provider->getSupportedEntityTypes();
    }
    ksort($map);
    return $map;
  }

}
