<?php

declare(strict_types=1);

namespace Drupal\aculta_magazine\Presentation;

use Drupal\aculta_magazine\Forum\ForumTopicSync;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Abertura do tópico: resumo, imagem e link para a publicação da Revista.
 *
 * Nada é copiado. O resumo e a imagem são lidos da publicação de origem, e a imagem é o mesmo arquivo da
 * biblioteca de mídia (`field_media_image`), renderizado de novo. Assim uma troca de imagem ou de resumo na
 * Revista aparece no tópico sem reedição. Só aparece no propósito FORUM.
 */
final class ForumOpeningPost {

  /** Estilo de imagem da abertura. */
  private const IMAGE_STYLE = 'large';

  public function __construct(
    #[Autowire(service: 'aculta_portal.domain_purpose')]
    private readonly DomainPurposeManager $purposeManager,
    #[Autowire(service: 'aculta_magazine.forum_topic_sync')]
    private readonly ForumTopicSync $sync,
  ) {}

  /**
   * Adiciona a abertura ao tópico do Fórum, antes dos comentários.
   */
  public function build(array &$build, NodeInterface $topic): void {
    if ($this->purposeManager->getCurrentPurpose() !== 'forum') {
      return;
    }
    $post = $this->sync->openingPostFor($topic);
    if ($post === NULL) {
      return;
    }
    $url = $this->purposeManager->routeUrl('magazine', 'entity.node.canonical', ['node' => $post->id()]);
    if ($url === NULL) {
      return;
    }

    $cache = (new CacheableMetadata())->addCacheableDependency($post)->addCacheableDependency($topic);
    $children = [
      'titulo' => [
        '#type' => 'html_tag',
        '#tag' => 'h2',
        '#attributes' => ['data-portal-ui' => 'forum-opening-post-title'],
        'link' => ['#type' => 'link', '#title' => $post->label(), '#url' => $url],
      ],
    ];

    $image = $this->image($post, $cache);
    if ($image !== NULL) {
      $children['imagem'] = ['#type' => 'container', '#attributes' => ['data-portal-ui' => 'forum-opening-post-image'], 'image' => $image];
    }
    if ($post->hasField('field_summary') && !$post->get('field_summary')->isEmpty()) {
      $children['resumo'] = [
        '#type' => 'container',
        '#attributes' => ['data-portal-ui' => 'forum-opening-post-body'],
        'texto' => $post->get('field_summary')->view(['label' => 'hidden']),
      ];
    }
    $children['link'] = [
      '#type' => 'link',
      '#title' => t('Ler a publicação na Revista'),
      '#url' => $url,
      '#attributes' => ['data-portal-ui' => 'forum-opening-post-link'],
    ];

    $build['abertura'] = [
      '#type' => 'container',
      '#weight' => -100,
      '#attributes' => ['data-portal-ui' => 'forum-opening-post'],
    ] + $children;
    $cache->applyTo($build['abertura']);
  }

  /**
   * Imagem da publicação, do próprio arquivo da mídia. NULL se não houver.
   */
  private function image(NodeInterface $post, CacheableMetadata $cache): ?array {
    if ($post->get('field_media_image')->isEmpty()) {
      return NULL;
    }
    $media = $post->get('field_media_image')->entity;
    if ($media === NULL || !$media->hasField('field_media_image') || $media->get('field_media_image')->isEmpty()) {
      return NULL;
    }
    $item = $media->get('field_media_image');
    $file = $item->entity;
    if ($file === NULL) {
      return NULL;
    }
    $cache->addCacheableDependency($media)->addCacheableDependency($file);
    return [
      '#theme' => 'image_style',
      '#style_name' => self::IMAGE_STYLE,
      '#uri' => $file->getFileUri(),
      '#alt' => (string) $item->alt,
    ];
  }

}
