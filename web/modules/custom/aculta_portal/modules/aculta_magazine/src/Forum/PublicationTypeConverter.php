<?php

declare(strict_types=1);

namespace Drupal\aculta_magazine\Forum;

use Drupal\aculta_magazine\Revista\RevistaPublications;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Troca o tipo da publicação (Notícia ↔ blog) e mantém referências antigas.
 *
 * O Drupal não converte o tipo de um nó no lugar. A troca cria a publicação no
 * tipo de destino, copia os campos compatíveis, mantém o endereço e remove a
 * antiga. Comments
 * diretos também são re-hospedados. Tudo dentro de uma transação.
 *
 * Título, autor, estado e Comments nativos permanecem. Campos que existem só
 * no tipo de origem aparecem no plano, mas não são copiados.
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
    #[Autowire(service: 'database')]
    private readonly Connection $database,
  ) {}

  /**
   * Plano sem gravação: campos copiados ou descartados.
   *
   * @return array{copy: string[], dropped: string[]}
   *   Field names copied or omitted by the target.
   */
  public function plan(NodeInterface $post, string $target): array {
    $this->assertConvertible($post, $target);
    $fresh = $this->entityTypeManager->getStorage('node')->create(['type' => $target]);
    $copy = [];
    $dropped = [];
    foreach ($post->getFields(FALSE) as $name => $item) {
      if (in_array($name, self::SKIP, TRUE)
        || str_starts_with($name, 'revision_')) {
        continue;
      }
      if ($fresh->hasField($name)) {
        $copy[] = $name;
      }
      elseif (!$item->isEmpty()) {
        $dropped[] = $name;
      }
    }
    return ['copy' => $copy, 'dropped' => $dropped];
  }

  /**
   * Executa a troca e retorna a publicação nova. Dentro de transação.
   */
  public function convert(NodeInterface $post, string $target): NodeInterface {
    $plan = $this->plan($post, $target);
    $oldId = (int) $post->id();
    $transaction = $this->database->startTransaction();
    try {
      $nodes = $this->entityTypeManager->getStorage('node');
      $new = $nodes->create(['type' => $target, 'uid' => $post->getOwnerId()]);
      foreach ($plan['copy'] as $name) {
        $new->set($name, $post->get($name)->getValue());
      }
      $new->set('status', $post->isPublished() ? 1 : 0);
      $new->setCreatedTime($post->getCreatedTime());
      // Remova o alias antigo para que Pathauto possa gerar o mesmo endereço.
      $aliases = $this->entityTypeManager->getStorage('path_alias')->loadByProperties(['path' => '/node/' . $oldId]);
      foreach ($aliases as $alias) {
        $alias->delete();
      }
      $new->save();
      $this->moveDiscussionComments($oldId, (int) $new->id());

      // A antiga perde o alias antes de ser excluída.
      $post->set('path', ['alias' => NULL, 'pathauto' => 0]);
      $post->save();
      $post->delete();

      return $new;
    }
    catch (\Throwable $e) {
      $transaction->rollBack();
      throw $e;
    }
  }

  /**
   * Reassocia os Comments à nova publicação antes da exclusão da antiga.
   *
   * Comments são entidades de conteúdo e mantêm autoria e revisões. Alterar
   * apenas CommentItem manteria a thread hospedada no Node antigo.
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
