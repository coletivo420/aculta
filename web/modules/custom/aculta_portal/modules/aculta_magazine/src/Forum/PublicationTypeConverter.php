<?php

declare(strict_types=1);

namespace Drupal\aculta_magazine\Forum;

use Drupal\aculta_magazine\Revista\RevistaPublications;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Troca o tipo de uma publicação da Revista (Notícia ↔ blog) sem quebrar o tópico do Fórum.
 *
 * O Drupal não converte o tipo de um nó no lugar. A troca cria a publicação no tipo de destino, copia os campos que
 * o destino tem, move o vínculo `field_discussao` para a nova publicação, mantém o endereço e só então remove a
 * antiga. Assim o tópico nunca fica sem publicação (a exclusão da antiga despublicaria o tópico se o vínculo
 * ainda estivesse nela). Tudo dentro de uma transação: se algo falhar, nada muda.
 *
 * Regras que valem aqui (docs/portal/ACULTA-USER-PLAN.md, E3; aculta_magazine/docs/README.md):
 * - a pasta do Fórum segue a sessão editorial (ForumTopicSync), então a troca não move a discussão de pasta;
 * - o título, o autor, o estado e os comentários nativos da publicação são preservados;
 * - campos que existem só no tipo de origem são listados no plano e não são copiados.
 */
final class PublicationTypeConverter {

  /**
   * Campos de sistema e de revisão: nunca copiados para a publicação nova.
   *
   * @var string[]
   */
  private const SKIP = [
    'nid', 'vid', 'uuid', 'type', 'changed', 'path', 'revision_timestamp', 'revision_uid', 'revision_log',
    'revision_default', 'revision_translation_affected', 'default_langcode', 'content_translation_source',
  ];

  public function __construct(
    #[Autowire(service: 'entity_type.manager')]
    private readonly EntityTypeManagerInterface $entityTypeManager,
    #[Autowire(service: 'aculta_magazine.forum_topic_sync')]
    private readonly ForumTopicSync $sync,
    #[Autowire(service: 'database')]
    private readonly Connection $database,
  ) {}

  /**
   * Plano da troca, sem gravar: tópico vinculado, campos copiados e campos que não existem no destino.
   *
   * @return array{topic: int|null, copy: string[], dropped: string[]}
   *   Linked topic, copied field names and non-empty fields omitted by the target.
   */
  public function plan(NodeInterface $post, string $target): array {
    $this->assertConvertible($post, $target);
    $fresh = $this->entityTypeManager->getStorage('node')->create(['type' => $target]);
    $copy = [];
    $dropped = [];
    foreach ($post->getFields(FALSE) as $name => $item) {
      if (in_array($name, self::SKIP, TRUE) || str_starts_with($name, 'revision_') || $name === 'field_discussao') {
        continue;
      }
      if ($fresh->hasField($name)) {
        $copy[] = $name;
      }
      elseif (!$item->isEmpty()) {
        $dropped[] = $name;
      }
    }
    $topic = $this->sync->topicFor($post);
    return ['topic' => $topic?->id(), 'copy' => $copy, 'dropped' => $dropped];
  }

  /**
   * Executa a troca e retorna a publicação nova. Dentro de transação.
   */
  public function convert(NodeInterface $post, string $target): NodeInterface {
    $plan = $this->plan($post, $target);
    $oldId = (int) $post->id();
    $topic = $this->sync->topicFor($post);

    $transaction = $this->database->startTransaction();
    try {
      $nodes = $this->entityTypeManager->getStorage('node');
      $new = $nodes->create(['type' => $target, 'uid' => $post->getOwnerId()]);
      foreach ($plan['copy'] as $name) {
        $new->set($name, $post->get($name)->getValue());
      }
      $new->set('status', $post->isPublished() ? 1 : 0);
      $new->setCreatedTime($post->getCreatedTime());
      if ($topic !== NULL) {
        $new->set('field_discussao', ['target_id' => $topic->id()]);
      }

      // O alias da antiga sai antes da gravação: a nova recebe o mesmo endereço pelo padrão do Pathauto.
      $aliases = $this->entityTypeManager->getStorage('path_alias')->loadByProperties(['path' => '/node/' . $oldId]);
      foreach ($aliases as $alias) {
        $alias->delete();
      }
      $new->save();
      $this->moveDiscussionComments($oldId, (int) $new->id());

      // A antiga perde o vínculo e o alias sem disparar a sincronização; depois sai.
      $this->sync->silently($post, function () use ($post): void {
        $post->set('field_discussao', NULL);
        $post->set('path', ['alias' => NULL, 'pathauto' => 0]);
        $post->save();
      });
      $post->delete();

      return $new;
    }
    catch (\Throwable $e) {
      $transaction->rollBack();
      throw $e;
    }
  }

  /**
   * Reassocia os comentários nativos à nova publicação antes da exclusão da antiga.
   *
   * Comentários são entidades de conteúdo e mantêm suas próprias autorias e revisões;
   * alterar apenas CommentItem faria a thread permanecer hospedada no Node antigo.
   */
  private function moveDiscussionComments(int $oldId, int $newId): void {
    $storage = $this->entityTypeManager->getStorage('comment');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('entity_type', 'node')
      ->condition('entity_id', $oldId)
      ->condition('field_name', 'field_aculta_discussion')
      ->sort('thread')
      ->execute();

    foreach ($storage->loadMultiple($ids) as $comment) {
      $comment->set('entity_id', $newId);
      $comment->save();
    }
  }

  /**
   * Validates the source publication and requested target bundle.
   */
  private function assertConvertible(NodeInterface $post, string $target): void {
    if (!RevistaPublications::isPublication($post->bundle()) || !RevistaPublications::isPublication($target)) {
      throw new \InvalidArgumentException('Só troca entre tipos da Revista (article e blog).');
    }
    if ($post->bundle() === $target) {
      throw new \InvalidArgumentException("A publicação já é do tipo $target.");
    }
  }

}
