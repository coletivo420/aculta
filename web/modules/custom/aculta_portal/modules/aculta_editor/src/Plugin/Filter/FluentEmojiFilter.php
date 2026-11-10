<?php

declare(strict_types=1);

namespace Drupal\aculta_editor\Plugin\Filter;

use Drupal\aculta_editor\FluentEmojiCatalog;
use Drupal\Component\Utility\Html;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\filter\Attribute\Filter;
use Drupal\filter\FilterProcessResult;
use Drupal\filter\Plugin\FilterBase;
use Drupal\filter\Plugin\FilterInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Renderiza os emojis Fluent do catálogo como SVGs coloridos.
 *
 * O editor e o banco guardam o caractere Unicode nativo. Este filtro troca,
 * apenas na saída, cada glifo conhecido por uma marcação com o SVG colorido da
 * biblioteca Fluent do tema e nome acessível em português.
 *
 * É do tipo reversível: o texto armazenado continua em Unicode puro, leve,
 * pesquisável e compatível com feeds, leitores de tela e cópia em texto plano.
 */
#[Filter(
  id: 'fluent_emoji',
  title: new TranslatableMarkup('ACULTA: emojis Fluent'),
  type: FilterInterface::TYPE_TRANSFORM_REVERSIBLE,
)]
final class FluentEmojiFilter extends FilterBase implements ContainerFactoryPluginInterface {

  /**
   * Lado do SVG na saída, em pixels.
   */
  private const SIZE = 20;

  /**
   * Constrói o filtro.
   *
   * @param array $configuration
   *   Configuração do plugin.
   * @param string $plugin_id
   *   ID do plugin.
   * @param array $plugin_definition
   *   Definição do plugin.
   * @param \Drupal\aculta_editor\FluentEmojiCatalog $catalog
   *   Catálogo curado de emojis.
   * @param string $assetsUrl
   *   URL base dos SVGs coloridos do tema.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected readonly FluentEmojiCatalog $catalog,
    protected readonly string $assetsUrl,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * Cria o filtro com suas dependências.
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    try {
      $themePath = $container->get('extension.list.theme')->getPath('aculta420_v2');
    }
    catch (\Throwable) {
      // O tema pode não estar instalado em ambientes de teste ou em Olivero.
      $themePath = 'themes/custom/aculta420_v2';
    }
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('aculta_editor.fluent_emoji_catalog'),
      base_path() . $themePath . '/assets/icons/fluent-emoji/color',
    );
  }

  /**
   * {@inheritdoc}
   */
  public function process($text, $langcode): FilterProcessResult {
    return new FilterProcessResult($this->transform((string) $text));
  }

  /**
   * Troca os glifos do catálogo pela marcação do SVG colorido.
   *
   * Ignora o conteúdo de atributos HTML e de blocos <pre> e <code>, onde o
   * glifo deve permanecer literal.
   */
  public function transform(string $text): string {
    if ($text === '' || !preg_match('/[\x{2600}-\x{27BF}\x{1F000}-\x{1FAFF}]/u', $text)) {
      return $text;
    }

    $replacements = $this->replacements();
    if ($replacements === []) {
      return $text;
    }

    $parts = preg_split('/(<[^>]+>)/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
    if ($parts === FALSE) {
      return $text;
    }

    $output = '';
    $preOrCode = 0;
    foreach ($parts as $index => $part) {
      // Índices ímpares são as tags capturadas pelo preg_split.
      if ($index % 2 === 1) {
        if (preg_match('/^<\s*(pre|code)\b/i', $part) === 1) {
          $preOrCode++;
        }
        elseif (preg_match('/^<\s*\/\s*(pre|code)\b/i', $part) === 1 && $preOrCode > 0) {
          $preOrCode--;
        }
        $output .= $part;
        continue;
      }

      if ($part === '') {
        continue;
      }
      $output .= $preOrCode > 0 ? $part : strtr($part, $replacements);
    }

    return $output;
  }

  /**
   * Monta o mapa glifo => marcação do SVG.
   *
   * @return array<string, string>
   *   Substituições prontas para strtr().
   */
  private function replacements(): array {
    $replacements = [];
    foreach ($this->catalog->byGlyph() as $glyph => $entry) {
      $name = Html::escape((string) ($entry['name'] ?? $entry['id']));
      $file = Html::escape((string) ($entry['svg'] ?? $entry['id'] . '.svg'));
      $url = Html::escape($this->assetsUrl . '/' . $file);
      $replacements[$glyph] = '<span class="reaction-emoji reaction-emoji--color reaction-emoji--sm" role="img" aria-label="' . $name . '">'
        . '<img src="' . $url . '" alt="" width="' . self::SIZE . '" height="' . self::SIZE . '" loading="lazy" decoding="async">'
        . '</span>';
    }
    return $replacements;
  }

}
