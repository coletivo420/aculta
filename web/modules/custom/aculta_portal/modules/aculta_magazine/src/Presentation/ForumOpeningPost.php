<?php

declare(strict_types=1);

namespace Drupal\aculta_magazine\Presentation;

use Drupal\aculta_image\ImageRenderer;
use Drupal\aculta_magazine\Forum\LegacyPublicationTopicLookup;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Abertura legada: resumo, imagem e link para a publicação da Revista.
 *
 * Nada é copiado. Resumo e imagem são lidos da publicação de origem; a imagem
 * continua sendo o arquivo de mídia (`field_media_image`) renderizado no
 * momento da leitura. Só aparece no purpose FORUM.
 */
final class ForumOpeningPost {

  use StringTranslationTrait;

  public function __construct(
    #[Autowire(service: 'aculta_portal.domain_purpose')]
    private readonly DomainPurposeManager $purposeManager,
    #[Autowire(service: 'aculta_magazine.legacy_publication_topic_lookup')]
    private readonly LegacyPublicationTopicLookup $topicLookup,
    #[Autowire(service: 'aculta_image.render')]
    private readonly ImageRenderer $imageRenderer,
  ) {}

  /**
   * Adiciona a abertura ao tópico do Fórum, antes dos comentários.
   */
  public function build(array &$build, NodeInterface $topic): void {
    if ($this->purposeManager->getCurrentPurpose() !== 'forum') {
      return;
    }
    $post = $this->topicLookup->openingPostFor($topic);
    if ($post === NULL) {
      return;
    }
    $url = $this->purposeManager->routeUrl(
      'magazine',
      'entity.node.canonical',
      ['node' => $post->id()],
    );
    if ($url === NULL) {
      return;
    }

    $cache = (new CacheableMetadata())->addCacheableDependency($post)->addCacheableDependency($topic);
    // O título não se repete: a página do tópico já o apresenta.
    $children = [];

    $image = $this->image($post, $cache);
    if ($image !== NULL) {
      $children['imagem'] = [
        '#type' => 'container',
        '#attributes' => ['data-portal-ui' => 'forum-opening-post-image'],
        'image' => $image,
      ];
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
      '#title' => $this->t('Ler a publicação na Revista'),
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
    if ($media === NULL
      || !$media->hasField('field_media_image')
      || $media->get('field_media_image')->isEmpty()) {
      return NULL;
    }
    $item = $media->get('field_media_image');
    $file = $item->entity;
    if ($file === NULL) {
      return NULL;
    }
    $cache->addCacheableDependency($media)->addCacheableDependency($file);
    return $this->imageRenderer->build('destaque', $file->getFileUri(), (string) $item->alt);
  }

}
