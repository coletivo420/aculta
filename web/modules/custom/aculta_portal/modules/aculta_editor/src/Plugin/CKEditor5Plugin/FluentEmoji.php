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
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected readonly FluentEmojiCatalog $catalog,
    protected readonly ThemeExtensionList $themeExtensionList,
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

}
