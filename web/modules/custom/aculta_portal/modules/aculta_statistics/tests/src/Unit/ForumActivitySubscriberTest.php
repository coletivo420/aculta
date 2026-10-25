<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_statistics\Unit;

use Drupal\aculta_statistics\EventSubscriber\ForumActivitySubscriber;
use Drupal\aculta_statistics\Catalog\MetricCatalog;
use Drupal\aculta_statistics\Recorder\StatisticsRecorder;
use Drupal\aculta_statistics\Storage\AggregateStorageInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Eventos de atividade do Fórum viram contagens no catálogo, sem dimensão pessoal.
 */
#[CoversClass(ForumActivitySubscriber::class)]
#[Group('aculta_statistics')]
final class ForumActivitySubscriberTest extends TestCase {

  /**
   * Tópico grava a métrica de tópicos, sem dimensão.
   */
  public function testTopicIsRecordedAsTopicMetric(): void {
    $storage = $this->createMock(AggregateStorageInterface::class);
    $storage->expects($this->once())->method('record')->with('forum.topics.created', '', 1);

    (new ForumActivitySubscriber($this->recorder($storage)))->onTopicCreated();
  }

  /**
   * Comentário grava a métrica de comentários, sem dimensão.
   */
  public function testCommentIsRecordedAsCommentMetric(): void {
    $storage = $this->createMock(AggregateStorageInterface::class);
    $storage->expects($this->once())->method('record')->with('forum.comments.created', '', 1);

    (new ForumActivitySubscriber($this->recorder($storage)))->onCommentCreated();
  }

  /**
   * Post revisionável grava a métrica de posts, sem dimensão.
   */
  public function testPostIsRecordedAsPostMetric(): void {
    $storage = $this->createMock(AggregateStorageInterface::class);
    $storage->expects($this->once())->method('record')->with('forum.posts.created', '', 1);

    (new ForumActivitySubscriber($this->recorder($storage)))->onPostCreated();
  }

  /**
   * Recorder real sobre o catálogo real e o storage informado.
   */
  private function recorder(AggregateStorageInterface $storage): StatisticsRecorder {
    return new StatisticsRecorder(new MetricCatalog(), $storage);
  }

  /**
   * Os nomes de evento assinados são os publicados pelo aculta_forum.
   */
  public function testSubscribedEventNames(): void {
    $this->assertSame(
      [
        'aculta_forum.topic_created' => 'onTopicCreated',
        'aculta_forum.comment_created' => 'onCommentCreated',
        'aculta_forum.post_created' => 'onPostCreated',
      ],
      ForumActivitySubscriber::getSubscribedEvents(),
    );
  }

}
