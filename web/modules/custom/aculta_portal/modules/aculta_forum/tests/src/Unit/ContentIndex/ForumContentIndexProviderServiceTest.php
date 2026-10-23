<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum\Unit\ContentIndex;

use Drupal\aculta_index\Provider\ContentIndexProviderRegistry;
use Drupal\aculta_portal\ContentIndex\MediaContentIndexProvider;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Yaml\Yaml;

/**
 * Verifies Forum-owned Content Index providers are service-tagged.
 *
 * @group aculta_forum
 */
final class ForumContentIndexProviderServiceTest extends UnitTestCase {

  /**
   * The real Forum service file contributes discussion and attachment owners.
   */
  public function testForumServicesAreCollectedByTaggedRegistry(): void {
    $moduleDirectory = dirname(__DIR__, 4);
    $services = Yaml::parseFile(
      $moduleDirectory . '/aculta_forum.services.yml',
      Yaml::PARSE_CUSTOM_TAGS,
    )['services'];
    $container = new ContainerBuilder();
    foreach ($services as $serviceId => $definition) {
      $providerTags = array_filter(
        $definition['tags'] ?? [],
        static fn (array $tag): bool => ($tag['name'] ?? NULL) === 'aculta_index.provider',
      );
      if ($providerTags === []) {
        continue;
      }
      $arguments = array_map(static function (mixed $argument): mixed {
        if (is_string($argument) && str_starts_with($argument, '@')) {
          return new Reference(substr($argument, 1));
        }
        return $argument;
      }, $definition['arguments'] ?? []);
      $providerDefinition = (new Definition($definition['class'], $arguments))->setPublic(TRUE);
      foreach ($providerTags as $tag) {
        $providerDefinition->addTag($tag['name']);
      }
      $container->setDefinition($serviceId, $providerDefinition);
    }
    $registryDefinition = (new Definition(ContentIndexProviderRegistry::class, [new TaggedIteratorArgument('aculta_index.provider')]))
      ->setPublic(TRUE);
    $container->setDefinition('aculta_index.provider_registry', $registryDefinition);
    $container->compile();

    $registry = $container->get('aculta_index.provider_registry');
    self::assertInstanceOf(ContentIndexProviderRegistry::class, $registry);
    self::assertSame('forum', $registry->findProviderIdForBundle('node', 'aculta_forum_topic'));
    self::assertSame('forum', $registry->findProviderIdForBundle('aculta_forum_post', 'aculta_forum_post'));
    self::assertSame('forum', $registry->findProviderIdForBundle('comment', 'aculta_discussion_post'));
    self::assertSame('forum_attachment', $registry->findProviderIdForBundle('media', 'forum_attachment'));
    self::assertInstanceOf(MediaContentIndexProvider::class, $registry->get('forum_attachment'));
  }

}
