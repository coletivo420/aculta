<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Hook;

use Drupal\comment\CommentInterface;
use Drupal\Core\Extension\ThemeExtensionList;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Hook\Order\Order;
use Drupal\Core\Render\Markup;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Troca o texto das ações de comentário (permalink, editar, apagar, citar, responder)
 * pelos ícones Phosphor do tema. O texto vai para aria-label; a cor e o hover vêm do tema.
 */
final class DiscussionCommentActionIconHooks {

  /**
   * Chave do link => ícone Phosphor (regular) do tema.
   */
  private const ICONS = [
    'comment-permalink' => 'link',
    'comment-edit' => 'pencil-simple',
    'comment-delete' => 'trash-simple',
    'comment-quote' => 'quotes',
    'comment-reply' => 'arrow-bend-up-left',
  ];

  public function __construct(
    #[Autowire(service: 'extension.list.theme')]
    private readonly ThemeExtensionList $themes,
  ) {}

  /**
   * Implements hook_comment_links_alter().
   */
  #[Hook('comment_links_alter', order: Order::Last)]
  public function commentLinksAlter(array &$links, CommentInterface $comment, array &$context): void {
    if (isset($links['#links'])) {
      $this->decorate($links['#links']);
    }
    if (isset($links['comment']['#links'])) {
      $this->decorate($links['comment']['#links']);
    }
  }

  /**
   * Aplica o ícone em cada ação conhecida.
   */
  private function decorate(array &$list): void {
    foreach (self::ICONS as $key => $icon) {
      if (!isset($list[$key])) {
        continue;
      }
      $label = (string) ($list[$key]['title'] ?? '');
      $svg = $this->svg($icon);
      if ($svg === '') {
        continue;
      }
      $attributes = $list[$key]['attributes'] ?? [];
      $attributes['class'] = array_merge((array) ($attributes['class'] ?? []), [
        'aculta-comment-action',
        'aculta-comment-action--' . substr($key, strlen('comment-')),
      ]);
      $attributes['aria-label'] = $label;
      $attributes['title'] = $label;
      $list[$key]['attributes'] = $attributes;
      $list[$key]['title'] = Markup::create($svg);
    }
  }

  /**
   * Conteúdo do SVG Phosphor do tema, pronto para ser inline.
   */
  private function svg(string $name): string {
    $path = $this->themes->getPath('aculta420_v2') . '/assets/icons/phosphor/regular/' . $name . '.svg';
    if (!is_file($path)) {
      return '';
    }
    $svg = (string) file_get_contents($path);
    $svg = preg_replace('/<\?xml[^>]*\?>\s*/', '', $svg) ?? $svg;
    return '<span class="aculta-comment-action__icon" aria-hidden="true">' . trim($svg) . '</span>';
  }

}
