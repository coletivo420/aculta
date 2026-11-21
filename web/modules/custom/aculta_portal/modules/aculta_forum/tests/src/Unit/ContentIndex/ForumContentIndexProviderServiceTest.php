<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum\Unit\ContentIndex;

use Drupal\aculta_forum\Origin\DiscussionOriginResolver;
use Drupal\aculta_index\Provider\ContentIndexProviderRegistry;
use Drupal\aculta_portal\ContentIndex\MediaContentIndexProvider;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Yaml\Yaml;

/**
 * Verifies Forum-owned Content Index providers are service-tagged.
 */
#[Group('aculta_forum')]
final class ForumContentIndexProviderServiceTest extends UnitTestCase {

  /**
   * The real Forum service file contributes discussion and attachment owners.
   */
  public function testForumServicesAreCollectedByTaggedRegistry(): void {
    $moduleDirectory = dirname(__DIR__, 4);
    $parsed = Yaml::parseFile(
      $moduleDirectory . '/aculta_forum.services.yml',
      Yaml::PARSE_CUSTOM_TAGS,
    );
    /** @var array<string, mixed> $services */
    $services = is_array($parsed) && isset($parsed['services']) && is_array($parsed['services'])
      ? $parsed['services']
      : [];
    $container = new ContainerBuilder();
    foreach ($services as $serviceId => $definition) {
      if (!is_array($definition)) {
        continue;
      }
      $rawTags = $definition['tags'] ?? [];
      $tags = is_array($rawTags) ? $rawTags : [];
      $providerTags = array_filter(
        $tags,
        static fn (mixed $tag): bool => is_array($tag) && ($tag['name'] ?? NULL) === 'aculta_index.provider',
      );
      if ($providerTags === []) {
        continue;
      }
      $rawArguments = $definition['arguments'] ?? [];
      $args = is_array($rawArguments) ? $rawArguments : [];
      $arguments = array_map(static function (mixed $argument): mixed {
        if (is_string($argument) && str_starts_with($argument, '@')) {
          return new Reference(substr($argument, 1));
        }
        return $argument;
      }, $args);
      $class = is_string($definition['class'] ?? NULL) ? (string) $definition['class'] : NULL;
      $providerDefinition = (new Definition($class, $arguments))->setPublic(TRUE);
      foreach ($providerTags as $tag) {
        $providerDefinition->addTag((string) $tag['name']);
      }
      $container->setDefinition((string) $serviceId, $providerDefinition);
    }
    $registryDefinition = (new Definition(ContentIndexProviderRegistry::class, [new TaggedIteratorArgument('aculta_index.provider')]))
      ->setPublic(TRUE);
    $container->setDefinition(
      'aculta_forum.discussion_origin_resolver',
      (new Definition(DiscussionOriginResolver::class, [[]]))->setPublic(TRUE),
    );
    $container->setDefinition('aculta_index.provider_registry', $registryDefinition);
    $container->compile();

    $registry = $container->get('aculta_index.provider_registry');
    self::assertSame('forum', $registry->findProviderIdForBundle('node', 'aculta_forum_topic'));
    self::assertSame('forum', $registry->findProviderIdForBundle('aculta_forum_post', 'aculta_forum_post'));
    self::assertSame('forum', $registry->findProviderIdForBundle('comment', 'aculta_discussion_post'));
    self::assertSame('forum_attachment', $registry->findProviderIdForBundle('media', 'forum_attachment'));
    self::assertInstanceOf(MediaContentIndexProvider::class, $registry->get('forum_attachment'));
  }

}
