<?php

declare(strict_types=1);

namespace Drupal\aculta_statistics\EventSubscriber;

use Drupal\aculta_statistics\Recorder\StatisticsRecorder;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Conta reações novas por tipo em conteúdo público do Fórum.
 *
 * O nome do evento é um texto: este módulo não depende de `aculta_forum`.
 * A remoção e a troca de emoji não incrementam `added`; o estoque atual é um
 * retrato calculado pelo provedor do Fórum.
 */
final class ForumReactionSubscriber implements EventSubscriberInterface {

  /**
   * Nome do evento emitido pelo aculta_forum.
   */
  private const REACTION_ADDED = 'aculta_forum.reaction_added';

  public function __construct(
    private readonly StatisticsRecorder $recorder,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [self::REACTION_ADDED => 'onReactionAdded'];
  }

  /**
   * Soma uma reação nova na dimensão do tipo de reação.
   *
   * O contrato é só o nome do evento e a propriedade `reaction`; não há
   * dependência de classe do módulo produtor.
   */
  public function onReactionAdded(object $event): void {
    $reaction = get_object_vars($event)['reaction'] ?? NULL;
    if (!is_string($reaction) || $reaction === '' || strlen($reaction) > 32) {
      return;
    }
    $this->recorder->record('forum.reactions.added', $reaction);
  }

}
