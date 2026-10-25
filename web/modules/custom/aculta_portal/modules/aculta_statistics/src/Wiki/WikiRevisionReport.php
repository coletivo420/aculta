<?php

declare(strict_types=1);

namespace Drupal\aculta_statistics\Wiki;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Statement\FetchAs;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;

/**
 * Relatório de edição e revisão por verbete, lido do histórico do Node.
 *
 * O relatório não grava nada: conta as revisões que o Node já guarda e mostra
 * quantas foram publicadas e quantas continuam em rascunho. Por isso ele é um
 * retrato do histórico atual, independente do período do painel, e não cria
 * métrica nova nem muda a retenção.
 *
 * O histórico de traduções pode ter mais de uma linha por revisão em
 * `node_field_revision`; as contagens usam `DISTINCT vid`, então cada revisão
 * conta uma vez.
 *
 * Este módulo não importa classes de `aculta_wiki`: o recorte depende só do
 * tipo de conteúdo `wiki_entry`.
 */
final class WikiRevisionReport {

  /**
   * Quantidade padrão de verbetes na tabela do painel.
   *
   * O CSV não corta: exporta todos os verbetes com histórico.
   */
  public const LIMIT = 50;

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly Connection $database,
  ) {}

  /**
   * Verbetes com histórico, rascunhos pendentes primeiro e edição recente antes.
   *
   * @param int|null $limit
   *   Quantidade de verbetes a devolver; NULL devolve todos.
   *
   * @return array{
   *   entries: list<array{node: \Drupal\node\NodeInterface, revisions: int, published: int, drafts: int, last_edit: int|null}>,
   *   total_entries: int,
   *   total_revisions: int,
   *   total_drafts: int,
   *   limit: int|null
   * }
   *   Verbete e contagens, além dos totais de todo o histórico (não só do
   *   recorte exibido).
   */
  public function report(?int $limit = self::LIMIT): array {
    $report = [
      'entries' => [],
      'total_entries' => 0,
      'total_revisions' => 0,
      'total_drafts' => 0,
      'limit' => $limit,
    ];
    if (!$this->entityTypeManager->hasDefinition('node')) {
      return $report;
    }

    $rows = $this->history();
    if ($rows === []) {
      return $report;
    }

    $report['total_entries'] = count($rows);
    foreach ($rows as $row) {
      $report['total_revisions'] += $row['revisions'];
      $report['total_drafts'] += $row['drafts'];
    }

    usort($rows, static function (array $a, array $b): int {
      // Rascunho pendente primeiro: é o que precisa de revisão.
      $pending = ($b['drafts'] > 0) <=> ($a['drafts'] > 0);
      if ($pending !== 0) {
        return $pending;
      }
      $recent = ($b['last_edit'] ?? 0) <=> ($a['last_edit'] ?? 0);
      if ($recent !== 0) {
        return $recent;
      }
      return $a['nid'] <=> $b['nid'];
    });

    if ($limit !== NULL) {
      $rows = array_slice($rows, 0, max(0, $limit));
    }

    // Só carrega os nós que serão exibidos; verbete excluído entre a consulta
    // e a carga simplesmente sai da lista.
    $nodes = $this->entityTypeManager->getStorage('node')->loadMultiple(array_column($rows, 'nid'));
    foreach ($rows as $row) {
      $node = $nodes[$row['nid']] ?? NULL;
      if (!$node instanceof NodeInterface) {
        continue;
      }
      $report['entries'][] = [
        'node' => $node,
        'revisions' => $row['revisions'],
        'published' => $row['published'],
        'drafts' => $row['drafts'],
        'last_edit' => $row['last_edit'],
      ];
    }

    return $report;
  }

  /**
   * Contagens por verbete, direto das tabelas de revisão do Node.
   *
   * @return list<array{nid: int, revisions: int, published: int, drafts: int, last_edit: int|null}>
   *   Uma linha por verbete com pelo menos uma revisão.
   */
  private function history(): array {
    $query = $this->database->select('node', 'n');
    $query->join('node_revision', 'nr', '[nr].[nid] = [n].[nid]');
    $query->join('node_field_revision', 'nfr', '[nfr].[vid] = [nr].[vid]');
    $query->addField('nr', 'nid');
    $query->addExpression('COUNT(DISTINCT nr.vid)', 'revisions');
    $query->addExpression('COUNT(DISTINCT CASE WHEN nfr.status = 1 THEN nr.vid END)', 'published');
    $query->addExpression('MAX(nr.revision_timestamp)', 'last_edit');
    $query->condition('n.type', WikiProvider::BUNDLE);
    $query->groupBy('nr.nid');

    $rows = [];
    foreach ($query->execute()->fetchAll(FetchAs::Associative) as $row) {
      $revisions = (int) $row['revisions'];
      $published = (int) $row['published'];
      $rows[] = [
        'nid' => (int) $row['nid'],
        'revisions' => $revisions,
        'published' => $published,
        'drafts' => $revisions - $published,
        'last_edit' => $row['last_edit'] !== NULL ? (int) $row['last_edit'] : NULL,
      ];
    }
    return $rows;
  }

}
