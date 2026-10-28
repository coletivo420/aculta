<?php

declare(strict_types=1);

namespace Drupal\aculta_lms_agenda;

use Drupal\aculta_events\Entity\Event;
use Drupal\aculta_events\Occurrence\EventDateOccurrences;
use Drupal\aculta_lms\Deadline\ClassActivityDeadlineManager;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\group\Entity\GroupInterface;
use Drupal\group\Entity\GroupMembership;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Entradas da agenda de um usuário.
 *
 * Inclui prazos e eventos das turmas em que ele está matriculado.
 *
 * Regra de acesso (docs/INTEGRACAO.md): só entram dados de turma publicada
 * cuja conta seja membro e de curso publicado ao qual tenha acesso. Sem
 * membro, não há entrada.
 *
 * Horários saem em horário de Brasília e no formato dd/mm/aaaa HH:mm.
 * A apresentação fica com quem consome; o provedor entrega DateTimeImmutable no
 * fuso do site.
 */
final class AgendaEntriesProvider {

  /**
   * Fuso do site (D9).
   */
  private const FUSO = 'America/Sao_Paulo';

  /**
   * Construtor.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly ClassActivityDeadlineManager $prazos,
    private readonly EventDateOccurrences $ocorrencias,
    private readonly TimeInterface $tempo,
  ) {}

  /**
   * Entradas do usuário na janela [$de, $ate], em ordem de início.
   *
   * @return array<int, array{tipo: string, titulo: string, inicio: \DateTimeImmutable, fim: \DateTimeImmutable, cancelada: bool, curso: int|string, turma: int|string}>
   *   `tipo` é `prazo` ou `evento`.
   */
  public function forAccount(AccountInterface $account, \DateTimeImmutable $de, \DateTimeImmutable $ate): array {
    if (!$account->isAuthenticated()) {
      return [];
    }
    $fuso = new \DateTimeZone(self::FUSO);
    $de = $de->setTimezone($fuso);
    $ate = $ate->setTimezone($fuso);
    $agora = (new \DateTimeImmutable('@' . $this->tempo->getCurrentTime()))->setTimezone($fuso);

    $saida = [];
    foreach ($this->turmasDoUsuario($account) as $turma) {
      $curso = $this->prazos->courseForClass($turma);
      if ($curso === NULL || !$curso->isPublished() || !$curso->takeAccess($account)->isAllowed()) {
        continue;
      }

      foreach ($turma->getRelationships('lms_class_activity_due') as $prazo) {
        if ($prazo->get('field_activity_due_at')->isEmpty()) {
          continue;
        }
        $inicio = (new \DateTimeImmutable((string) $prazo->get('field_activity_due_at')->value, new \DateTimeZone('UTC')))
          ->setTimezone($fuso);
        if ($inicio < $de || $inicio > $ate) {
          continue;
        }
        $saida[] = [
          'tipo' => 'prazo',
          'titulo' => 'Prazo: ' . (string) $prazo->getEntity()?->label(),
          'inicio' => $inicio,
          'fim' => $inicio,
          'cancelada' => FALSE,
          'curso' => $curso->id(),
          'turma' => $turma->id(),
        ];
      }

      foreach ($this->eventosDaTurma($turma) as $evento) {
        $cancelado = $evento->get('field_status')->value === 'cancelado';
        foreach ($this->datasDoEvento($evento) as $data) {
          foreach ($this->ocorrencias->expandir($data, $de, $ate, $agora) as $ocorrencia) {
            $saida[] = [
              'tipo' => 'evento',
              'titulo' => (string) $evento->label(),
              'inicio' => $ocorrencia['inicio'],
              'fim' => $ocorrencia['fim'],
              'cancelada' => $cancelado || $ocorrencia['cancelada'],
              'curso' => $curso->id(),
              'turma' => $turma->id(),
            ];
          }
        }
      }
    }

    usort($saida, static fn (array $a, array $b): int => $a['inicio'] <=> $b['inicio']);
    return $saida;
  }

  /**
   * Turmas publicadas em que o usuário é membro.
   *
   * @return \Drupal\group\Entity\GroupInterface[]
   *   Turmas às quais a conta tem membership.
   */
  private function turmasDoUsuario(AccountInterface $account): array {
    $turmas = [];
    foreach (GroupMembership::loadByUser($account) as $membro) {
      $grupo = $membro->getGroup();
      if ($grupo instanceof GroupInterface && $grupo->bundle() === 'lms_class' && $grupo->isPublished()) {
        $turmas[$grupo->id()] = $grupo;
      }
    }
    return array_values($turmas);
  }

  /**
   * Eventos ligados à turma, publicados ou cancelados (rascunho não entra).
   *
   * @return \Drupal\aculta_events\Entity\Event[]
   *   Eventos publicados ou cancelados ligados à turma.
   */
  private function eventosDaTurma(GroupInterface $turma): array {
    $storage = $this->entityTypeManager->getStorage('aculta_event');
    $ids = $storage->getQuery()
      ->condition('field_turma.target_id', $turma->id())
      ->condition('field_status', ['publicado', 'cancelado'], 'IN')
      ->accessCheck(FALSE)
      ->execute();
    return array_values($storage->loadMultiple($ids));
  }

  /**
   * Datas (EventDate) de um evento.
   *
   * @return \Drupal\aculta_events\Entity\EventDate[]
   *   Datas vinculadas ao evento.
   */
  private function datasDoEvento(Event $evento): array {
    $storage = $this->entityTypeManager->getStorage('aculta_event_date');
    $ids = $storage->getQuery()
      ->condition('field_evento.target_id', $evento->id())
      ->accessCheck(FALSE)
      ->execute();
    return array_values($storage->loadMultiple($ids));
  }

}
