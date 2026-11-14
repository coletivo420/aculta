<?php

declare(strict_types=1);

namespace Drupal\aculta_editor\Plugin\CKEditor5Plugin;

use Drupal\aculta_editor\FluentEmojiCatalog;
use Drupal\ckeditor5\Plugin\CKEditor5PluginDefault;
use Drupal\Core\Extension\ThemeExtensionList;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\editor\EditorInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Seletor de emojis Fluent do CKEditor 5.
 *
 * Entrega ao JavaScript o catálogo curado e a URL base dos SVGs coloridos do
 * tema. O editor insere apenas o caractere Unicode nativo; a parte colorida é
 * aplicada na saída pelo filtro fluent_emoji.
 */
class FluentEmoji extends CKEditor5PluginDefault implements ContainerFactoryPluginInterface {

  use StringTranslationTrait;

  /**
   * Arquivo do emoji usado como ícone do botão na toolbar.
   */
  private const BUTTON_ICON_FILE = 'grinning-squinting-face.svg';

  /**
   * Ícone de reserva, caso o SVG colorido do tema não esteja disponível.
   */
  private const FALLBACK_ICON = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="none" aria-hidden="true">'
    . '<circle cx="10" cy="10" r="7.25" stroke="currentColor" stroke-width="1.5"/>'
    . '<circle cx="7.25" cy="8.25" r="1.1" fill="currentColor"/>'
    . '<circle cx="12.75" cy="8.25" r="1.1" fill="currentColor"/>'
    . '<path d="M6.6 11.4c.9 1.4 2 2.1 3.4 2.1s2.5-.7 3.4-2.1" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>'
    . '</svg>';

  /**
   * Constrói o plugin.
   *
   * @param array $configuration
   *   Configuração do plugin.
   * @param string $plugin_id
   *   ID do plugin.
   * @param array $plugin_definition
   *   Definição do plugin.
   * @param \Drupal\aculta_editor\FluentEmojiCatalog $catalog
   *   Catálogo curado de emojis.
   * @param \Drupal\Core\Extension\ThemeExtensionList $themeExtensionList
   *   Lista de extensões de tema, usada para localizar os SVGs.
   * @param string $appRoot
   *   Diretório raiz da aplicação Drupal.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected readonly FluentEmojiCatalog $catalog,
    protected readonly ThemeExtensionList $themeExtensionList,
    protected readonly string $appRoot,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * Cria o plugin com suas dependências.
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('aculta_editor.fluent_emoji_catalog'),
      $container->get('extension.list.theme'),
      $container->getParameter('app.root'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDynamicPluginConfig(array $static_plugin_config, EditorInterface $editor): array {
    try {
      $themePath = $this->themeExtensionList->getPath('aculta420_v2');
    }
    catch (\Throwable) {
      $themePath = 'themes/custom/aculta420_v2';
    }

    $catalog = [];
    foreach ($this->catalog->all() as $entry) {
      $catalog[] = [
        'id' => $entry['id'],
        'name' => $entry['name'],
        'glyph' => $entry['glyph'],
        'category' => $entry['category'],
        'keywords' => $entry['keywords'],
        'svg' => $entry['svg'] ?? $entry['id'] . '.svg',
      ];
    }

    $static_plugin_config['acultaFluentEmoji'] = [
      'catalog' => $catalog,
      'svgBaseUrl' => base_path() . $themePath . '/assets/icons/fluent-emoji/color',
      'buttonIcon' => $this->buttonIcon($themePath),
      'labels' => [
        'button' => (string) $this->t('Emoji'),
        'search' => (string) $this->t('Buscar emoji'),
        'list' => (string) $this->t('Emojis disponíveis'),
        'empty' => (string) $this->t('Nenhum emoji encontrado.'),
        'categories' => [
          'cultivo' => (string) $this->t('Cultivo e natureza'),
          'vibe' => (string) $this->t('Consumo e vibe 420'),
          'saude' => (string) $this->t('Medicinal e saúde'),
          'direitos' => (string) $this->t('Direitos e comunidade'),
        ],
      ],
    ];

    return $static_plugin_config;
  }

  /**
   * Devolve o SVG colorido do emoji usado como ícone do botão.
   *
   * Lê o asset do tema para o botão exibir o emoji Fluent real, com cor, em
   * vez de um ícone monocromático genérico.
   */
  private function buttonIcon(string $themePath): string {
    $path = $this->appRoot . '/' . $themePath . '/assets/icons/fluent-emoji/color/' . self::BUTTON_ICON_FILE;
    $icon = is_file($path) ? file_get_contents($path) : FALSE;
    return is_string($icon) && $icon !== '' ? $icon : self::FALLBACK_ICON;
  }

}
