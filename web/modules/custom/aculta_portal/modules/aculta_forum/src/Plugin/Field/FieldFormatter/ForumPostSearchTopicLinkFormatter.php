<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Plugin\Field\FieldFormatter;

use Drupal\aculta_forum\Entity\ForumPost;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Links a Forum post search result to its anchored position in the host topic.
 */
#[FieldFormatter(
  id: 'forum_post_search_topic_link',
  label: new TranslatableMarkup('ACULTA Forum post topic link'),
  field_types: ['entity_reference'],
)]
final class ForumPostSearchTopicLinkFormatter extends FormatterBase implements ContainerFactoryPluginInterface {

  public function __construct(
    $plugin_id,
    $plugin_definition,
    $field_definition,
    array $settings,
    $label,
    $view_mode,
    array $third_party_settings,
    private readonly AccountProxyInterface $currentUser,
    private readonly DomainPurposeManager $domainPurposeManager,
  ) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);
  }

  /**
   * {@inheritdoc}
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
      $container->get('aculta_portal.domain_purpose'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $post = $items->getEntity();
    if (!$post instanceof ForumPost || $this->domainPurposeManager->getCurrentPurpose() !== 'forum') {
      return [];
    }

    $elements = [];
    foreach ($items as $delta => $item) {
      $topic = $item->entity;
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
        ->addCacheContexts(['domain']);
      if (!$post->isPublished() || !$topic->isPublished() || !$post_access->isAllowed() || !$topic_access->isAllowed()) {
        $elements[$delta] = ['#access' => FALSE];
        $cacheability->applyTo($elements[$delta]);
        continue;
      }

      $elements[$delta] = [
        '#type' => 'link',
        '#title' => $this->t('Ver resposta'),
        '#url' => Url::fromRoute('entity.node.canonical', ['node' => $topic->id()], [
          'fragment' => 'forum-post-' . $post->id(),
        ]),
      ];
      $cacheability->applyTo($elements[$delta]);
    }
    return $elements;
  }

}
