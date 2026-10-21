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
      new MetricDefinition('forum.topics.created', 'Tópicos criados no Fórum', 'tópicos', MetricDefinition::SENSITIVITY_PUBLIC, 90),
      new MetricDefinition('forum.comments.created', 'Comentários criados no Fórum', 'comentários', MetricDefinition::SENSITIVITY_PUBLIC, 90),
      new MetricDefinition('email.messages.queued', 'E-mails solicitados, por tipo (dia da solicitação)', 'mensagens', MetricDefinition::SENSITIVITY_INTERNAL, 90),
      new MetricDefinition('email.messages.accepted', 'E-mails aceitos pelo sistema de envio (dia da solicitação)', 'mensagens', MetricDefinition::SENSITIVITY_INTERNAL, 90),
      new MetricDefinition('email.messages.delivered', 'E-mails confirmados como entregues pelo provedor', 'mensagens', MetricDefinition::SENSITIVITY_INTERNAL, 90),
      new MetricDefinition('email.messages.bounced', 'E-mails devolvidos pelo destino', 'mensagens', MetricDefinition::SENSITIVITY_INTERNAL, 90),
      new MetricDefinition('email.messages.status', 'E-mails por estado atual (dia da solicitação)', 'mensagens', MetricDefinition::SENSITIVITY_INTERNAL, 90),
      new MetricDefinition('email.messages.failed', 'E-mails com falha de envio (dia da solicitação)', 'mensagens', MetricDefinition::SENSITIVITY_INTERNAL, 90),
      new MetricDefinition('commerce.orders.paid', 'Pedidos pagos', 'pedidos', MetricDefinition::SENSITIVITY_INTERNAL, 90),
      new MetricDefinition('commerce.revenue.gross', 'Receita bruta paga, por moeda', 'centavos', MetricDefinition::SENSITIVITY_INTERNAL, 90),
      new MetricDefinition('commerce.carts.abandoned', 'Carrinhos abandonados (24 horas sem finalizar)', 'carrinhos', MetricDefinition::SENSITIVITY_INTERNAL, 90),
      new MetricDefinition('commerce.orders.by_domain', 'Pedidos pagos, por purpose e moeda', 'pedidos', MetricDefinition::SENSITIVITY_INTERNAL, 90),
      new MetricDefinition('commerce.revenue.by_domain', 'Receita bruta paga, por purpose e moeda', 'centavos', MetricDefinition::SENSITIVITY_INTERNAL, 90),
      new MetricDefinition('commerce.coupons.orders', 'Pedidos pagos com cupom, por moeda', 'pedidos', MetricDefinition::SENSITIVITY_INTERNAL, 90),
      new MetricDefinition('commerce.discounts.total', 'Desconto de promoção concedido, por moeda', 'centavos', MetricDefinition::SENSITIVITY_INTERNAL, 90),
      new MetricDefinition('commerce.items.sold', 'Unidades vendidas, por variação de produto', 'unidades', MetricDefinition::SENSITIVITY_INTERNAL, 90),
      new MetricDefinition('commerce.refunds.total', 'Reembolsos, por moeda', 'centavos', MetricDefinition::SENSITIVITY_INTERNAL, 90),
      new MetricDefinition('lms.enrollments.created', 'Matrículas criadas em turma, por curso e turma', 'matrículas', MetricDefinition::SENSITIVITY_INTERNAL, 90),
      new MetricDefinition('lms.courses.completed', 'Conclusões de curso, por curso e turma', 'conclusões', MetricDefinition::SENSITIVITY_INTERNAL, 90),
      new MetricDefinition('media.files.requests', 'Aberturas de arquivo privado de mídia, por arquivo', 'aberturas', MetricDefinition::SENSITIVITY_INTERNAL, 90),
      new MetricDefinition('lms.student.status', 'Situação de cada aluno por curso (dado pessoal, aceite único)', 'alunos', MetricDefinition::SENSITIVITY_RESTRICTED, 90),
      new MetricDefinition('lms.students.by_status', 'Alunos por situação, por curso e turma (instantâneo diário)', 'alunos', MetricDefinition::SENSITIVITY_INTERNAL, 90),
      new MetricDefinition('lms.certificates.issued', 'Certificados emitidos (total)', 'certificados', MetricDefinition::SENSITIVITY_INTERNAL, 90),
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
