<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Hook;

use Drupal\aculta_lms\Event\ClassStatusSnapshotEvent;
use Drupal\aculta_lms\LearningDashboardManager;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\group\Entity\GroupInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Emite o instantâneo de situação por turma no cron, para as séries do aculta_statistics.
 *
 * Usa a mesma contagem do painel da turma, sem a checagem de acesso de quem vê o painel. Só agregados.
 */
final class ClassStatusSnapshotCronHooks {

  private const SITUATIONS = ['students', 'not_started', 'in_progress', 'pending_evaluation', 'passed', 'failed'];

  public function __construct(
    private readonly LearningDashboardManager $dashboards,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly EventDispatcherInterface $dispatcher,
  ) {}

  /**
   * Conta cada turma e emite uma linha por situação.
   */
  #[Hook('cron')]
  public function cron(): void {
    $rows = [];
    foreach ($this->entityTypeManager->getStorage('group')->loadByProperties(['type' => 'lms_class']) as $class) {
      if (!$class instanceof GroupInterface) {
        continue;
      }
      $course = $this->dashboards->courseForClass($class);
      if ($course === NULL) {
        continue;
      }
      $counts = $this->dashboards->countClassStatuses($class, $course);
      foreach (self::SITUATIONS as $situation) {
        $rows[] = [
          'course' => (int) $course->id(),
          'class' => (int) $class->id(),
          'situation' => $situation,
          'count' => (int) $counts[$situation],
        ];
      }
    }
    $this->dispatcher->dispatch(new ClassStatusSnapshotEvent($rows), ClassStatusSnapshotEvent::NAME);
  }

}
