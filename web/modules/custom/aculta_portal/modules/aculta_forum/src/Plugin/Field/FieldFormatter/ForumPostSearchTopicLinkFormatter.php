<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Plugin\Field\FieldFormatter;

use Drupal\aculta_forum\Entity\ForumPost;
use Drupal\aculta_forum\Service\ForumPostThreadBuilder;
use Drupal\aculta_portal\Domain\DomainSiteManager;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Links a Forum post search result to its anchored position in the host topic.
 *
 * @extends FormatterBase<\Drupal\Core\Field\FieldItemListInterface<\Drupal\Core\Field\FieldItemInterface>>
 */
#[FieldFormatter(
  id: 'forum_post_search_topic_link',
  label: new TranslatableMarkup('ACULTA Forum post topic link'),
  field_types: ['entity_reference'],
)]
final class ForumPostSearchTopicLinkFormatter extends FormatterBase implements ContainerFactoryPluginInterface {

  /**
   * Constructs a ForumPostSearchTopicLinkFormatter object.
   *
   * @param string $plugin_id
   *   The plugin ID for the formatter.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the formatter is associated.
   * @param array<string, mixed> $settings
   *   The formatter settings.
   * @param string $label
   *   The formatter label display setting.
   * @param string $view_mode
   *   The view mode.
   * @param array<string, mixed> $third_party_settings
   *   Any third party settings.
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The current user.
   * @param \Drupal\aculta_portal\Domain\DomainSiteManager $siteManager
   *   The domain site manager.
   * @param \Drupal\aculta_forum\Service\ForumPostThreadBuilder $threadBuilder
   *   The forum post thread builder.
   */
  public function __construct(
    $plugin_id,
    $plugin_definition,
    $field_definition,
    array $settings,
    $label,
    $view_mode,
    array $third_party_settings,
    protected readonly AccountProxyInterface $currentUser,
    protected readonly DomainSiteManager $siteManager,
    protected readonly ForumPostThreadBuilder $threadBuilder,
  ) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);
  }

  /**
   * {@inheritdoc}
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The service container.
   * @param array<string, mixed> $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   *
   * @phpstan-param array{field_definition: \Drupal\Core\Field\FieldDefinitionInterface, settings: array<string, mixed>, label: string, view_mode: string, third_party_settings: array<string, mixed>, ...} $configuration
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('current_user'),
      $container->get('aculta_portal.domain_site'),
      $container->get('aculta_forum.post_thread_builder'),
    );
  }

  /**
   * {@inheritdoc}
   *
   * @param \Drupal\Core\Field\FieldItemListInterface<\Drupal\Core\Field\FieldItemInterface> $items
   * @param string $langcode
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $post = $items->getEntity();
    if (!$post instanceof ForumPost
      || !$items instanceof EntityReferenceFieldItemListInterface
      || $this->siteManager->getCurrentSiteKey()?->value !== 'forum') {
      return [];
    }

    $elements = [];
    foreach ($items->referencedEntities() as $delta => $topic) {
      if (!$topic instanceof NodeInterface || $topic->bundle() !== 'aculta_forum_topic') {
        continue;
      }

      $post_access = $post->access('view', $this->currentUser, TRUE);
      $topic_access = $topic->access('view', $this->currentUser, TRUE);
      $cacheability = (new CacheableMetadata())
        ->addCacheableDependency($post)
        ->addCacheableDependency($topic)
        ->addCacheableDependency($post_access)
        ->addCacheableDependency($topic_access)
        ->addCacheContexts(['domain'])
        ->addCacheTags(['aculta_forum_post_list']);
      if (!$post->isPublished() || !$topic->isPublished() || !$post_access->isAllowed() || !$topic_access->isAllowed()) {
        $elements[$delta] = ['#access' => FALSE];
        $cacheability->applyTo($elements[$delta]);
        continue;
      }

      $page = $this->threadBuilder->getPageForPost($topic, $post);
      if ($page === NULL) {
        $elements[$delta] = ['#access' => FALSE];
        $cacheability->applyTo($elements[$delta]);
        continue;
      }

      $elements[$delta] = [
        '#type' => 'link',
        '#title' => $this->t('Ver resposta'),
        '#url' => Url::fromRoute('entity.node.canonical', ['node' => $topic->id()], [
          'query' => $this->threadBuilder->getTopicPagerQuery($page),
          'fragment' => 'forum-post-' . $post->id(),
        ]),
      ];
      $cacheability->applyTo($elements[$delta]);
    }
    return $elements;
  }

}
