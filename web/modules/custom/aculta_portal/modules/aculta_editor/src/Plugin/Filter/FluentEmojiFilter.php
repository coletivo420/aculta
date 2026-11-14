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
    $resolver = $container->has('aculta_editor.fluent_emoji_asset_resolver')
      ? $container->get('aculta_editor.fluent_emoji_asset_resolver')
      : null;
    $assetsUrl = $resolver instanceof \Drupal\aculta_editor\Service\FluentEmojiAssetResolverInterface
      ? $resolver->getAssetsUrl()
      : base_path() . 'themes/custom/aculta420_v2/assets/icons/fluent-emoji/color';

    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('aculta_editor.fluent_emoji_catalog'),
      $assetsUrl,
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
   * Ignora o conteúdo de atributos HTML e de nós textuais dentro de <script>,
   * <style>, <pre>, <code>, <svg>, <textarea> e nós já convertidos.
   */
  public function transform(string $text): string {
    if ($text === '' || !preg_match('/[\x{2600}-\x{27BF}\x{1F000}-\x{1FAFF}]/u', $text)) {
      return $text;
    }

    $byGlyph = $this->catalog->byGlyph();
    if ($byGlyph === []) {
      return $text;
    }

    $keys = array_keys($byGlyph);
    usort($keys, static fn(string $a, string $b): int => mb_strlen($b, 'UTF-8') <=> mb_strlen($a, 'UTF-8'));
    $pattern = '/(' . implode('|', array_map('preg_quote', $keys)) . ')/u';

    $document = Html::load($text);
    $xpath = new \DOMXPath($document);

    $excluded = ['script', 'style', 'code', 'pre', 'svg', 'textarea', 'kbd', 'samp', 'var'];
    $tagExclusions = implode(' and ', array_map(static fn(string $tag): string => 'not(ancestor::' . $tag . ')', $excluded));
    $query = '//text()[' . $tagExclusions . ' and not(ancestor::*[contains(concat(" ", normalize-space(@class), " "), " reaction-emoji ")])]';

    /** @var \DOMText[] $nodes */
    $nodes = iterator_to_array($xpath->query($query));

    $modified = FALSE;
    foreach ($nodes as $node) {
      $val = $node->nodeValue;
      if ($val === NULL || !preg_match($pattern, $val)) {
        continue;
      }

      $parts = preg_split($pattern, $val, -1, PREG_SPLIT_DELIM_CAPTURE);
      if ($parts === FALSE || count($parts) <= 1) {
        continue;
      }

      $parent = $node->parentNode;
      if ($parent === NULL) {
        continue;
      }

      foreach ($parts as $part) {
        if ($part === '') {
          continue;
        }

        if (isset($byGlyph[$part])) {
          $entry = $byGlyph[$part];
          $name = (string) ($entry['name'] ?? $entry['id']);
          $file = (string) ($entry['svg'] ?? $entry['id'] . '.svg');
          $url = $this->assetsUrl . '/' . $file;

          $span = $document->createElement('span');
          $span->setAttribute('class', 'reaction-emoji reaction-emoji--color reaction-emoji--sm');
          $span->setAttribute('role', 'img');
          $span->setAttribute('aria-label', $name);

          $img = $document->createElement('img');
          $img->setAttribute('src', $url);
          $img->setAttribute('alt', '');
          $img->setAttribute('width', (string) self::SIZE);
          $img->setAttribute('height', (string) self::SIZE);
          $img->setAttribute('loading', 'lazy');
          $img->setAttribute('decoding', 'async');

          $span->appendChild($img);
          $parent->insertBefore($span, $node);
          $modified = TRUE;
        }
        else {
          $parent->insertBefore($document->createTextNode($part), $node);
        }
      }

      $parent->removeChild($node);
    }

    return $modified ? Html::serialize($document) : $text;
  }

}
