<?php

declare(strict_types=1);

namespace Drupal\aculta420_v2\Story;

use Drupal\Core\Extension\ThemeExtensionList;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\Markup;
use Drupal\ui_patterns_library\StoryDefault;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Story do Comentário de discussão com os mesmos botões de ação das respostas reais.
 *
 * Os ícones são os SVGs Phosphor do tema (mesmos usados em forum-opening-post).
 */
final class DiscussionCommentStory extends StoryDefault implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    private readonly ThemeExtensionList $themes,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('extension.list.theme'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function props(): array {
    return [
      'has_author' => TRUE,
      'has_submitted' => TRUE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function slots(): array {
    return [
      'author' => ['markup' => 'Autoria pública'],
      'submitted' => ['markup' => '<time datetime="2026-11-04">4 de novembro de 2026</time>'],
      'body' => ['markup' => 'Contribuição na discussão.'],
      'footer' => ['markup' => Markup::create($this->footer())],
    ];
  }

  /**
   * Rodapé com permalink e ações de resposta, como no comentário real.
   */
  private function footer(): string {
    $actions = [
      ['link', 'Link permanente', '#comentario', 'aculta-resposta-acao--link'],
      ['trash-simple', 'Excluir comentário', '#', 'aculta-resposta-acao--apagar'],
      ['pencil-simple', 'Editar', '#', 'aculta-resposta-acao--editar'],
      ['quotes', 'Citar publicação', '#', 'aculta-resposta-acao--citar'],
      ['arrow-bend-up-left', 'Responder ao comentário', '#', 'aculta-resposta-acao--responder'],
    ];
    $html = '<div class="forum-post-card__actions">';
    foreach ($actions as [$icon, $label, $href, $class]) {
      $html .= '<a href="' . $href . '" class="aculta-resposta-acao ' . $class . '" aria-label="' . $label . '" title="' . $label . '">' . $this->icon($icon) . '</a>';
    }
    return $html . '</div>';
  }

  /**
   * Lê o SVG Phosphor do tema, em span decorativo.
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

}
