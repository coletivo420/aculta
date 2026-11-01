<?php

declare(strict_types=1);

namespace Drupal\aculta_statistics\Hook;

use Drupal\aculta_statistics\Recorder\StatisticsRecorder;
use Drupal\aculta_statistics\Wiki\WikiProvider;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\node\NodeInterface;

/**
 * Registra a publicação de verbetes da Wiki nas métricas agregadas.
 *
 * A contagem é diária, por verbete, sem autoria: a dimensão é o ID do nó. O
 * que conta (primeira publicação, revisão publicada, conteúdo importado) é
 * decidido pelo WikiProvider.
 */
final class WikiPublicationHooks {

  public function __construct(
    private readonly WikiProvider $provider,
    private readonly StatisticsRecorder $recorder,
  ) {}

  /**
   * Conta a criação de um verbete publicado.
   */
  #[Hook('node_insert')]
  public function nodeInsert(NodeInterface $node): void {
    $this->record($node, $this->provider->metricForInsert($node));
  }

  /**
   * Conta a publicação de uma revisão nova de um verbete.
   */
  #[Hook('node_update')]
  public function nodeUpdate(NodeInterface $node): void {
    $this->record($node, $this->provider->metricForUpdate($node));
  }

  /**
   * Grava a métrica quando o provider indicou uma.
   */
  private function record(NodeInterface $node, ?string $metric): void {
    if ($metric === NULL) {
      return;
    }
    $this->recorder->record($metric, (string) $node->id());
  }

}
