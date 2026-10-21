<?php

declare(strict_types=1);

namespace Drupal\aculta_statistics\EventSubscriber;

use Drupal\aculta_statistics\Recorder\StatisticsRecorder;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Conta tópicos e comentários criados no Fórum a partir dos eventos do `aculta_forum`.
 *
 * Os nomes dos eventos são textos: este módulo não depende de `aculta_forum`. Só a contagem
 * é gravada, sem o identificador da entidade, autor ou texto.
 */
final class ForumActivitySubscriber implements EventSubscriberInterface {

  /**
   * Nome do evento de tópico criado no aculta_forum (ver ForumActivityEvent::TOPIC_CREATED).
   */
  private const TOPIC_CREATED = 'aculta_forum.topic_created';

  /**
   * Nome do evento de comentário criado no aculta_forum (ver ForumActivityEvent::COMMENT_CREATED).
   */
  private const COMMENT_CREATED = 'aculta_forum.comment_created';

  public function __construct(
    private readonly StatisticsRecorder $recorder,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      self::TOPIC_CREATED => 'onTopicCreated',
      self::COMMENT_CREATED => 'onCommentCreated',
    ];
  }

  /**
   * Soma um tópico criado.
   */
  public function onTopicCreated(): void {
    $this->recorder->record('forum.topics.created');
  }

  /**
   * Soma um comentário criado.
   */
  public function onCommentCreated(): void {
    $this->recorder->record('forum.comments.created');
  }

}
