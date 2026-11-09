<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Hook;

use Drupal\Core\Extension\ThemeExtensionList;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Dados da postagem de abertura do tópico: link de edição (só com acesso) e ícones de ação.
 *
 * Os ícones saem como SVG do PHP, igual aos ícones das ações dos comentários.
 */
final class TopicOpeningPostHooks {

  public function __construct(
    private readonly AccountProxyInterface $currentUser,
    #[Autowire(service: 'extension.list.theme')]
    private readonly ThemeExtensionList $themes,
  ) {}

  /**
   * Implements hook_preprocess_node().
   */
  #[Hook('preprocess_node')]
  public function preprocessNode(array &$variables): void {
    $node = $variables['node'] ?? NULL;
    if (!$node instanceof NodeInterface || $node->bundle() !== 'aculta_forum_topic' || ($variables['view_mode'] ?? '') !== 'full') {
      return;
    }
    $variables['opening_edit_url'] = NULL;
    if ($node->access('update', $this->currentUser)) {
      $variables['opening_edit_url'] = Url::fromRoute('entity.node.edit_form', ['node' => $node->id()])->toString();
    }
    $variables['opening_icons'] = [
      'link' => $this->icon('link'),
      'edit' => $this->icon('pencil-simple'),
      'reply' => $this->icon('arrow-bend-up-left'),
    ];
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

}
