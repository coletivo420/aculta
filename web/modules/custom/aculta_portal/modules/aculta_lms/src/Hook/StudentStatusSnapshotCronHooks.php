<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Hook;

use Drupal\aculta_lms\Event\StudentStatusSnapshotEvent;
use Drupal\aculta_lms\LearningDashboardManager;
use Drupal\aculta_user\Consent\AcultaUserAgreementLedger;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\group\Entity\GroupInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Emite a situação atual de cada aluno por curso, no cron, para a série por aluno.
 *
 * Inclui só quem aceitou o aceite único (Termos e Política), que é a base da função descrita na política. Sem o
 * ledger instalado, não emite nada.
 */
final class StudentStatusSnapshotCronHooks {

  public function __construct(
    private readonly LearningDashboardManager $dashboards,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly EventDispatcherInterface $dispatcher,
    private readonly ?AcultaUserAgreementLedger $ledger,
  ) {}

  /**
   * Emite uma linha por aluno e curso, com aceite único vigente.
   */
  #[Hook('cron')]
  public function cron(): void {
    if ($this->ledger === NULL) {
      return;
    }
    $rows = [];
    foreach ($this->entityTypeManager->getStorage('group')->loadByProperties(['type' => 'lms_class']) as $class) {
      if (!$class instanceof GroupInterface) {
        continue;
      }
      $course = $this->dashboards->courseForClass($class);
      if ($course === NULL) {
        continue;
      }
      $counts = $this->dashboards->countClassStatuses($class, $course, TRUE);
      foreach ($counts['by_student'] ?? [] as $uid => $situation) {
        if (!$this->ledger->hasAcceptedAcultaUserAgreement((int) $uid, AcultaUserAgreementLedger::AGREEMENT_TYPE)) {
          continue;
        }
        // Mesmo aluno em duas turmas do mesmo curso conta uma vez.
        $rows[(int) $course->id() . '/' . (int) $uid] = [
          'course' => (int) $course->id(),
          'uid' => (int) $uid,
          'situation' => $situation,
        ];
      }
    }
    $this->dispatcher->dispatch(new StudentStatusSnapshotEvent(array_values($rows)), StudentStatusSnapshotEvent::NAME);
  }

}
