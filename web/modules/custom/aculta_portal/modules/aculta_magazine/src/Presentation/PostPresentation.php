<?php

declare(strict_types=1);

namespace Drupal\aculta_magazine\Presentation;

use Drupal\aculta_portal\Render\PortalLinkElement;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Template\Attribute;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Cabeçalho de apresentação das publicações da Revista: autor, data, hora e compartilhamento.
 *
 * Função apenas: monta os dados e os links com tokens data-portal-ui. A aparência é do ACULTA420.
 * Serviço (não é hook): o Drupal aceita uma implementação de hook por módulo, então node_view fica em ArticleAuthorsHooks.
 */
final class PostPresentation {

  /**
   * Redes de compartilhamento: rótulo, ícone (Bootstrap Icons) e montagem do link.
   */
  private const SHARE = [
    'whatsapp' => ['label' => 'WhatsApp', 'icon' => 'whatsapp'],
    'telegram' => ['label' => 'Telegram', 'icon' => 'telegram'],
    'facebook' => ['label' => 'Facebook', 'icon' => 'facebook'],
    'reddit' => ['label' => 'Reddit', 'icon' => 'reddit'],
    'email' => ['label' => 'E-mail', 'icon' => 'envelope'],
  ];

  public function __construct(
    #[Autowire(service: 'date.formatter')]
    private readonly DateFormatterInterface $dateFormatter,
  ) {}

  /**
   * Adiciona metainformação e compartilhamento à publicação (chamado pelo hook node_view de aculta_magazine).
   */
  public function build(array &$build, NodeInterface $entity): void {
    $cache = (new CacheableMetadata())->addCacheableDependency($entity)->addCacheContexts(['url.path', 'url.site']);
    $ts = $entity->hasField('field_published_at') && !$entity->get('field_published_at')->isEmpty()
      ? strtotime((string) $entity->get('field_published_at')->value . ' UTC') ?: $entity->getCreatedTime()
      : $entity->getCreatedTime();
    $owner = $entity->getOwner();
    $author = $owner ? $owner->getDisplayName() : '';

    $build['post_meta'] = [
      '#type' => 'container',
      '#weight' => -30,
      '#attributes' => ['data-portal-ui' => 'post-meta'],
      'author' => $this->item('post-meta-author', $author),
      'date' => $this->item('post-meta-date', mb_strtolower($this->dateFormatter->format($ts, 'custom', 'j \d\e F \d\e Y'))),
      'time' => $this->item('post-meta-time', $this->dateFormatter->format($ts, 'custom', 'H:i')),
    ];

    $url = $entity->toUrl('canonical', ['absolute' => TRUE])->toString();
    $title = $entity->label();
    $links = [];
    foreach (self::SHARE as $key => $network) {
      $links[] = PortalLinkElement::build('', Url::fromUri($this->shareUrl($key, $url, $title)), [
        'data-portal-ui' => 'post-share-' . $key,
        'rel' => 'noopener nofollow',
        'target' => $key === 'email' ? '_self' : '_blank',
        'aria-label' => $network['label'],
        'title' => $network['label'],
      ]);
    }
    // Compartilhamento dentro da mesma faixa da metainformação (à direita).
    $build['post_meta']['share'] = [
      '#type' => 'container',
      '#attributes' => ['data-portal-ui' => 'post-share'],
      'label' => ['#theme' => 'aculta_portal_element', '#tag' => 'span', '#value' => (string) t('Compartilhe:'), '#element_attributes' => new Attribute(['data-portal-ui' => 'post-share-label'])],
      'links' => $links,
    ];
    $cache->applyTo($build);
  }

  /**
   * Um item de metainformação (texto com token).
   */
  private function item(string $token, string $value): array {
    return ['#theme' => 'aculta_portal_element', '#tag' => 'span', '#value' => $value, '#element_attributes' => new Attribute(['data-portal-ui' => $token])];
  }

  /**
   * Link de compartilhamento para cada rede.
   */
  private function shareUrl(string $network, string $url, string $title): string {
    $u = rawurlencode($url);
    $t = rawurlencode($title);
    return match ($network) {
      'whatsapp' => "https://wa.me/?text={$t}%20{$u}",
      'telegram' => "https://t.me/share/url?url={$u}&text={$t}",
      'facebook' => "https://www.facebook.com/sharer/sharer.php?u={$u}",
      'reddit' => "https://www.reddit.com/submit?url={$u}&title={$t}",
      default => "mailto:?subject={$t}&body={$u}",
    };
  }

}
