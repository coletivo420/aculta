<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Hook;

use Drupal\aculta_forum\Service\DiscussionCommentPermalinkBuilderInterface;
use Drupal\comment\CommentInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Hook\Order\Order;
use Drupal\Core\Extension\ThemeExtensionList;
use Drupal\Core\Link;
use Drupal\Core\Render\Markup;
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
    #[Autowire(service: 'extension.list.theme')]
    private readonly ThemeExtensionList $themes,
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
      // Mesmo padrão das demais ações do comentário: ícone Phosphor e rótulo em aria-label/title.
      $label = (string) $this->t('Permalink');
      $attributes = $url->getOption('attributes') ?: [];
      $attributes += [
        'class' => ['permalink', 'aculta-resposta-acao', 'aculta-resposta-acao--link'],
        'rel' => 'bookmark',
        'aria-label' => $label,
        'title' => $label,
      ];
      $url->setOption('attributes', $attributes);

      $variables['permalink'] = Link::fromTextAndUrl(Markup::create($this->icon('link')), $url)->toString();
      $variables['permalink_url'] = $url->toString();
    }
  }

  /**
   * Ícone Phosphor (regular) do tema, em span decorativo.
   */
  private function icon(string $name): string {
    $path = $this->themes->getPath('aculta420_v2') . '/assets/icons/phosphor/regular/' . $name . '.svg';
    if (!is_file($path)) {
      return '';
    }
    $svg = (string) file_get_contents($path);
    $svg = preg_replace('/<\?xml[^>]*\?>\s*/', '', $svg) ?? $svg;
    return '<span class="aculta-resposta-acao__icon" aria-hidden="true">' . trim($svg) . '</span>';
  }

  /**
   * Id do elemento do comentário: resposta-CID, igual à âncora do permalink.
   *
   * Roda depois do core, que define comment-CID no mesmo atributo.
   */
  #[Hook('preprocess_comment', order: Order::Last)]
  public function preprocessCommentDomId(array &$variables): void {
    $comment = $variables['elements']['#comment'] ?? NULL;
    if (!$comment instanceof CommentInterface
      || $comment->bundle() !== 'aculta_discussion_post'
      || $comment->getFieldName() !== 'field_aculta_discussion') {
      return;
    }
    $variables['attributes']->setAttribute('id', 'resposta-' . $comment->id());
  }

}
