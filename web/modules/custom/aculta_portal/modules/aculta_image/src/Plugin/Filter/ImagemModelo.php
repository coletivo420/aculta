<?php

declare(strict_types=1);

namespace Drupal\aculta_image\Plugin\Filter;

use Drupal\aculta_image\ImageCatalog;
use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\filter\FilterProcessResult;
use Drupal\filter\Attribute\Filter;
use Drupal\filter\Plugin\FilterBase;
use Drupal\filter\Plugin\FilterInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Modelo de imagem do texto (aculta_image, docs/MODELOS-DE-IMAGEM.md).
 *
 * Transforma `<img data-aculta-modelo="…">` gravada pelo editor em `<figure>` com srcset explícito (estilos do modelo),
 * legenda e crédito. Roda depois do filtro de HTML, então o marcador precisa estar permitido no formato. Sem marcador,
 * ou se a imagem não existe em public://, o HTML original segue sem mudança.
 */
#[Filter(
  id: 'aculta_imagem_modelo',
  title: new TranslatableMarkup('ACULTA: modelo de imagem'),
  type: FilterInterface::TYPE_TRANSFORM_IRREVERSIBLE,
)]
final class ImagemModelo extends FilterBase implements ContainerFactoryPluginInterface {

  /** Largura de exibição de cada modelo (atributo sizes). */
  private const TAMANHOS = [
    'pequena' => '(min-width: 600px) 290px, 100vw',
    'padrao' => '(min-width: 600px) 580px, 100vw',
    'larga' => '(min-width: 1000px) 900px, 100vw',
    'destaque' => '100vw',
  ];

  /** Alinhamentos aceitos; qualquer outro valor vira centro. */
  private const ALINHAMENTOS = ['esquerda', 'direita', 'centro'];

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    private readonly ImageCatalog $catalog,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly FileSystemInterface $fileSystem,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('aculta_image.catalog'),
      $container->get('entity_type.manager'),
      $container->get('file_system'),
    );
  }

  /** {@inheritdoc} */
  public function process($text, $langcode): FilterProcessResult {
    if (!str_contains($text, 'data-aculta-modelo')) {
      return new FilterProcessResult($text);
    }
    $novo = preg_replace_callback('/<img\b[^>]*\bdata-aculta-modelo="[^"]*"[^>]*>/i', fn(array $m): string => $this->figura($m[0]) ?? $m[0], $text);
    return new FilterProcessResult($novo ?? $text);
  }

  /** Figura do modelo, ou NULL se o modelo ou o arquivo não existem. */
  private function figura(string $tag): ?string {
    $atributos = $this->atributos($tag);
    $modelo = $atributos['data-aculta-modelo'] ?? '';
    $estilos = $this->catalog->estilosDoModelo($modelo);
    $uri = $this->uriDoSrc($atributos['src'] ?? '');
    if ($estilos === NULL || $uri === NULL) {
      return NULL;
    }
    $urls = [];
    foreach ($estilos as $nome) {
      $estilo = $this->entityTypeManager->getStorage('image_style')->load($nome);
      if ($estilo === NULL) {
        return NULL;
      }
      $largura = (int) preg_replace('/\D/', '', $nome);
      $urls[] = ['url' => $estilo->buildUrl($uri), 'largura' => $largura];
    }
    $alinhamento = in_array($atributos['data-aculta-alinhamento'] ?? '', self::ALINHAMENTOS, TRUE) ? $atributos['data-aculta-alinhamento'] : 'centro';
    $srcset = implode(', ', array_map(fn(array $u): string => Html::escape($u['url']) . ' ' . $u['largura'] . 'w', $urls));
    $img = '<img src="' . Html::escape($urls[0]['url']) . '" srcset="' . $srcset . '" sizes="' . self::TAMANHOS[$modelo] . '"'
      . ' alt="' . Html::escape($atributos['alt'] ?? '') . '" loading="lazy" decoding="async">';
    $legenda = Html::escape($atributos['data-aculta-legenda'] ?? '');
    $credito = Html::escape($atributos['data-aculta-credito'] ?? '');
    $rodape = '';
    if ($legenda !== '' || $credito !== '') {
      $rodape = '<figcaption class="aculta-imagem__legenda">' . $legenda
        . ($credito !== '' ? '<span class="aculta-imagem__credito">' . $credito . '</span>' : '') . '</figcaption>';
    }
    return '<figure class="aculta-imagem aculta-imagem--' . $modelo . ' aculta-imagem--' . $alinhamento . '">' . $img . $rodape . '</figure>';
  }

  /** URI public:// do src de um arquivo de imagem do site, ou NULL. */
  private function uriDoSrc(string $src): ?string {
    if (!preg_match('~/sites/[^/]+/files/([^?#]+)~', html_entity_decode($src), $m)) {
      return NULL;
    }
    $uri = 'public://' . rawurldecode($m[1]);
    $caminho = $this->fileSystem->realpath($uri);
    return $caminho !== FALSE && is_file($caminho) ? $uri : NULL;
  }

  /** Atributos de uma tag, com valores já escapados pelo editor. @return array<string, string> */
  private function atributos(string $tag): array {
    preg_match_all('/([\w-]+)="([^"]*)"/', $tag, $pares, PREG_SET_ORDER);
    $saida = [];
    foreach ($pares as $par) {
      $saida[strtolower($par[1])] = html_entity_decode($par[2], ENT_QUOTES | ENT_HTML5);
    }
    return $saida;
  }

}
