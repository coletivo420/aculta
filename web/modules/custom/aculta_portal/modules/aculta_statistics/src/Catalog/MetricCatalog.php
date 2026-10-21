<?php

declare(strict_types=1);

namespace Drupal\aculta_statistics\Catalog;

/**
 * Catálogo central de métricas do ACULTA Statistics.
 *
 * Cada métrica registrada aqui é o único caminho aceito para gravar um
 * agregado. Um identificador fora do catálogo é recusado. A lista segue
 * docs/METRIC-CATALOG.md; métricas sem fonte ainda não entram aqui.
 */
final class MetricCatalog {

  /**
   * Definições do catálogo, indexadas pelo identificador da métrica.
   *
   * @return array<string, \Drupal\aculta_statistics\Catalog\MetricDefinition>
   *   Métricas disponíveis.
   */
  public function all(): array {
    $definitions = [
      new MetricDefinition('links.link.clicks', 'Cliques em links curtos', 'cliques', MetricDefinition::SENSITIVITY_PUBLIC, 90),
      new MetricDefinition('wiki.entries.read', 'Leituras de verbetes da Wiki', 'leituras', MetricDefinition::SENSITIVITY_PUBLIC, 90),
      new MetricDefinition('forum.comments.created', 'Comentários criados no Fórum', 'comentários', MetricDefinition::SENSITIVITY_PUBLIC, 90),
      new MetricDefinition('email.messages.accepted', 'E-mails aceitos pelo sistema de envio', 'mensagens', MetricDefinition::SENSITIVITY_INTERNAL, 90),
      new MetricDefinition('email.messages.failed', 'E-mails com falha de envio', 'mensagens', MetricDefinition::SENSITIVITY_INTERNAL, 90),
      new MetricDefinition('commerce.orders.paid', 'Pedidos pagos', 'pedidos', MetricDefinition::SENSITIVITY_INTERNAL, 90),
      new MetricDefinition('commerce.revenue.gross', 'Receita bruta paga, por moeda', 'centavos', MetricDefinition::SENSITIVITY_INTERNAL, 90),
      new MetricDefinition('lms.courses.completed', 'Conclusões de curso', 'conclusões', MetricDefinition::SENSITIVITY_INTERNAL, 90),
    ];

    $indexed = [];
    foreach ($definitions as $definition) {
      $indexed[$definition->id] = $definition;
    }
    return $indexed;
  }

  /**
   * Retorna a definição de uma métrica.
   *
   * @throws \InvalidArgumentException
   *   Quando o identificador não está no catálogo.
   */
  public function get(string $id): MetricDefinition {
    $all = $this->all();
    if (!isset($all[$id])) {
      throw new \InvalidArgumentException(sprintf('Métrica fora do catálogo: %s.', $id));
    }
    return $all[$id];
  }

  /**
   * Indica se o identificador existe no catálogo.
   */
  public function has(string $id): bool {
    return isset($this->all()[$id]);
  }

}
