<?php

declare(strict_types=1);

namespace Drupal\aculta_magazine\Forum;

use Drupal\aculta_magazine\Revista\RevistaPublications;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Tópico do Fórum de cada publicação da Revista.
 *
 * Regras (docs/portal/ACULTA-USER-PLAN.md, seção E3, decisão do responsável em 2026-10-13):
 * - cada publicação da Revista tem um tópico do Fórum, criado automaticamente, com o mesmo autor;
 * - o tópico fica na pasta da categoria editorial (ForumSectionResolver);
 * - o vínculo é `field_discussao` na publicação (campo já existente em article e blog);
 * - o tópico não copia resumo, imagem nem corpo: a abertura é lida da publicação (ForumOpeningPost);
 * - título, autor, estado e pasta seguem a publicação; excluir a publicação despublica o tópico, sem apagar a discussão.
 */
final class ForumTopicSync {

  /** Publicações que guardam o tópico (mesmos tipos da Revista). */
  private const TOPIC_FIELD = 'field_discussao';

  /** IDs de publicações em sincronização, para o save da publicação não reentrar. */
  private array $sincronizando = [];

  public function __construct(
    #[Autowire(service: 'entity_type.manager')]
    private readonly EntityTypeManagerInterface $entityTypeManager,
    #[Autowire(service: 'aculta_portal.domain_purpose')]
    private readonly DomainPurposeManager $purposeManager,
    #[Autowire(service: 'aculta_magazine.forum_section_resolver')]
    private readonly ForumSectionResolver $sections,
  ) {}

  /**
   * Cria ou atualiza o tópico da publicação. Sem categoria editorial, não cria tópico.
   */
  public function sync(NodeInterface $post): void {
    if (!RevistaPublications::isPublication($post->bundle()) || isset($this->sincronizando[$post->id()])) {
      return;
    }
    $category = $post->hasField('field_editorial_category') ? $post->get('field_editorial_category')->entity : NULL;
    if ($category === NULL) {
      return;
    }

    $this->sincronizando[$post->id()] = TRUE;
    try {
      $topic = $this->topicFor($post);
      $section = $this->sections->sectionFor($category);
      $domainId = $this->purposeManager->getDomain('forum')?->id();

      if ($topic === NULL) {
        $topic = $this->entityTypeManager->getStorage('node')->create([
          'type' => 'forum',
          'uid' => $post->getOwnerId(),
        ]);
      }
      $changed = $topic->isNew()
        || $topic->label() !== $post->label()
        || $topic->isPublished() !== $post->isPublished()
        || (int) $topic->getOwnerId() !== (int) $post->getOwnerId()
        || (int) $topic->get('taxonomy_forums')->target_id !== (int) $section->id()
        || (string) $topic->get('field_domain_source')->target_id !== (string) $domainId;

      if ($changed) {
        $topic->setTitle($post->label());
        // EntityPublishedTrait::setPublished() não recebe argumento no Core 11: despublicar exige setUnpublished().
        $post->isPublished() ? $topic->setPublished() : $topic->setUnpublished();
        $topic->setOwnerId((int) $post->getOwnerId());
        $topic->set('taxonomy_forums', ['target_id' => $section->id()]);
        if ($domainId !== NULL) {
          $topic->set('field_domain_source', ['target_id' => $domainId]);
        }
        $topic->save();
      }

      // Primeiro tópico da publicação: grava o vínculo. Save com a publicação em sincronização não reentra aqui.
      if ($post->get(self::TOPIC_FIELD)->isEmpty() || (int) $post->get(self::TOPIC_FIELD)->target_id !== (int) $topic->id()) {
        $post->set(self::TOPIC_FIELD, ['target_id' => $topic->id()]);
        $post->setNewRevision(FALSE);
        $post->save();
      }
    }
    finally {
      unset($this->sincronizando[$post->id()]);
    }
  }

  /**
   * Executa uma gravação da publicação sem disparar a sincronização do tópico (uso da troca de tipo).
   */
  public function silently(NodeInterface $post, callable $work): void {
    $this->sincronizando[$post->id()] = TRUE;
    try {
      $work();
    }
    finally {
      unset($this->sincronizando[$post->id()]);
    }
  }

  /**
   * Despublica o tópico quando a publicação é excluída. A discussão e os comentários são preservados.
   */
  public function unpublish(NodeInterface $post): void {
    $topic = $this->topicFor($post);
    if ($topic !== NULL && $topic->isPublished()) {
      $topic->setUnpublished();
      $topic->save();
    }
  }

  /**
   * Tópico vinculado à publicação, ou NULL.
   */
  public function topicFor(NodeInterface $post): ?NodeInterface {
    if (!$post->hasField(self::TOPIC_FIELD) || $post->get(self::TOPIC_FIELD)->isEmpty()) {
      return NULL;
    }
    $topic = $post->get(self::TOPIC_FIELD)->entity;
    return $topic instanceof NodeInterface && $topic->bundle() === 'forum' ? $topic : NULL;
  }

  /**
   * Publicação de abertura do tópico (primeira postagem da discussão), ou NULL se não houver publicação publicada.
   */
  public function openingPostFor(NodeInterface $topic): ?NodeInterface {
    $ids = $this->entityTypeManager->getStorage('node')->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', RevistaPublications::BUNDLES, 'IN')
      ->condition(self::TOPIC_FIELD . '.target_id', $topic->id())
      ->condition('status', 1)
      ->sort('nid')
      ->range(0, 1)
      ->execute();
    if (!$ids) {
      return NULL;
    }
    $post = $this->entityTypeManager->getStorage('node')->load(reset($ids));
    return $post instanceof NodeInterface ? $post : NULL;
  }

  /**
   * Cria o tópico de todas as publicações que ainda não têm um. Retorna quantas foram tratadas.
   */
  public function backfill(): int {
    $ids = $this->entityTypeManager->getStorage('node')->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', RevistaPublications::BUNDLES, 'IN')
      ->sort('nid')
      ->execute();
    $total = 0;
    foreach ($this->entityTypeManager->getStorage('node')->loadMultiple($ids) as $post) {
      $this->sync($post);
      $total++;
    }
    return $total;
  }

}
