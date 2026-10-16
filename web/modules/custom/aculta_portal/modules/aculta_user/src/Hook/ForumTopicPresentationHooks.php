<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Hook;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Template\Attribute;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Dados da página de um tópico do Fórum (ACULTA Fórum, F4): contagem de respostas publicadas e a chamada de login
 * para visitante. O tema só desenha; a contagem e o acesso são calculados aqui.
 */
final class ForumTopicPresentationHooks {

  use StringTranslationTrait;

  public function __construct(
    #[Autowire(service: 'entity_type.manager')]
    private readonly EntityTypeManagerInterface $entityTypeManager,
    #[Autowire(service: 'current_user')]
    private readonly AccountInterface $currentUser,
  ) {}

  /**
   * Implements hook_ENTITY_TYPE_view() for node entities: forum topics only, full view.
   */
  #[Hook('node_view')]
  public function nodeView(array &$build, EntityInterface $entity, mixed $display, string $view_mode): void {
    if (!$entity instanceof NodeInterface || $entity->bundle() !== 'forum' || $view_mode !== 'full') {
      return;
    }

    // Respostas publicadas do campo de comentários do tópico. Consulta direta: a estatística do core não devolve a linha deste nó.
    $total = (int) $this->entityTypeManager->getStorage('comment')->getQuery()
      ->accessCheck(FALSE)
      ->condition('entity_type', 'node')
      ->condition('entity_id', $entity->id())
      ->condition('field_name', 'comment_forum')
      ->condition('status', 1)
      ->count()
      ->execute();
    $build['aculta_respostas_total'] = [
      '#theme' => 'aculta_portal_element',
      '#tag' => 'span',
      '#value' => (string) $total,
      '#element_attributes' => new Attribute(['data-portal-ui' => 'forum-reply-total']),
    ];

    // Soma aos metadados que o render array já tem; não os substitui (contextos do nó e do tema).
    $cache = CacheableMetadata::createFromRenderArray($build)
      ->addCacheableDependency($entity)
      ->addCacheContexts(['user'])
      ->addCacheTags(['comment_list']);

    if ($this->currentUser->isAnonymous()) {
      // Visitante vê o que a conta faz, mas não age: a chamada leva ao login e volta a este tópico.
      $build['aculta_visitante'] = [
        '#type' => 'container',
        '#attributes' => ['data-portal-ui' => 'forum-topic-visitor'],
        'texto' => ['#plain_text' => (string) $this->t('Entre para seguir, salvar ou denunciar este tópico.')],
        'entrar' => [
          '#type' => 'link',
          '#title' => $this->t('Entrar'),
          '#url' => Url::fromRoute('user.login', [], ['query' => ['destination' => $entity->toUrl()->toString()]]),
        ],
      ];
    }

    $cache->applyTo($build);
  }

}
