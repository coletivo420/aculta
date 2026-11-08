<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Hook;

use Drupal\aculta_forum\Service\DiscussionCommentPermalinkBuilderInterface;
use Drupal\comment\CommentInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Link;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Preprocesses shared discussion comments to provide direct host permalinks.
 */
final class DiscussionCommentPermalinkHooks {

  use StringTranslationTrait;

  public function __construct(
    #[Autowire(service: 'aculta_forum.discussion_comment_permalink_builder')]
    private readonly DiscussionCommentPermalinkBuilderInterface $permalinkBuilder,
  ) {}

  /**
   * Overrides comment permalink with canonical host link and anchor.
   */
  #[Hook('preprocess_comment')]
  public function preprocessComment(array &$variables): void {
    $comment = $variables['elements']['#comment'] ?? NULL;
    if (!$comment instanceof CommentInterface
      || $comment->bundle() !== 'aculta_discussion_post'
      || $comment->getFieldName() !== 'field_aculta_discussion') {
      return;
    }

    $variables['commented_entity_type'] = $comment->getCommentedEntityTypeId();
    $variables['commented_entity_id'] = $comment->getCommentedEntityId();

    $url = $this->permalinkBuilder->build($comment);
    if ($url !== NULL) {
      $attributes = $url->getOption('attributes') ?: [];
      $attributes += ['class' => ['permalink'], 'rel' => 'bookmark'];
      $url->setOption('attributes', $attributes);

      $variables['permalink'] = Link::fromTextAndUrl($this->t('Permalink'), $url)->toString();
      $variables['permalink_url'] = $url->toString();
    }
  }

}
