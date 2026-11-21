<?php

declare(strict_types=1);

namespace Drupal\aculta_cgu\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\comment\CommentInterface;
use Drupal\node\NodeInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\aculta_cgu\Entity\CguReport;
use Drupal\aculta_cgu\Exception\ReportNotAllowedException;
use Drupal\aculta_cgu\Scope\ReportTargetScope;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Serviço unificado de denúncias de conteúdo gerado por usuários (ARQ-06).
 */
final class ReportService {

  /**
   * Limite diário provisório de denúncias por conta.
   *
   * ATENÇÃO: valor NÃO homologado. `docs/PLANO-IMPLANTACAO.md` registra os
   * limites numéricos como pendentes de decisão do responsável. O valor foi
   * mantido apenas porque remover o teto seria menos seguro. Não promover a
   * configuração nem tratar como decisão. Ver `docs/ARQ-06-AUDITORIA.md` §4.
   */
  public const DAILY_LIMIT = 20;

  public const STATUS_PENDING = 'pendente';

  public const STATUS_REVIEW = 'em_analise';

  public const STATUS_UPHELD = 'procedente';

  public const STATUS_REJECTED = 'improcedente';

  /**
   * Motivos aceitos de uma nova denúncia.
   */
  public const REASONS = [
    'spam',
    'ofensa',
    'conteudo_inadequado',
    'direitos_autorais',
    'desinformacao',
    'outro',
  ];

  /**
   * Transições válidas de status. Decisões finais não são reabertas.
   */
  public const TRANSITIONS = [
    self::STATUS_PENDING => [self::STATUS_REVIEW, self::STATUS_UPHELD, self::STATUS_REJECTED],
    self::STATUS_REVIEW => [self::STATUS_UPHELD, self::STATUS_REJECTED],
  ];

  /**
   * Tamanho máximo do parecer da moderação.
   */
  public const NOTES_MAX_LENGTH = 2000;

  /**
   * Maximum length of the confidential description supplied by the reporter.
   */
  public const DESCRIPTION_MAX_LENGTH = 2000;

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly TimeInterface $time,
    private readonly ModerationNotifier $notifier,
    private readonly LockBackendInterface $lock,
    private readonly LoggerInterface $logger,
    private readonly ReportTargetScope $targetScope,
  ) {}

  /**
   * Verifica se o usuário pode denunciar a entidade.
   */
  public function canReport(EntityInterface $entity, AccountInterface $account): bool {
    return $this->reportDenial($entity, $account) === NULL;
  }

  /**
   * Motivo interno da negação, ou NULL quando a denúncia é permitida.
   *
   * O código nunca é mostrado ao usuário: a mensagem pública é genérica para
   * não revelar a existência de conteúdo que a conta não pode ver.
   *
   * @return string|null
   *   Um de: anonimo, alvo_invalido, sem_acesso, duplicada, limite_diario.
   */
  public function reportDenial(EntityInterface $entity, AccountInterface $account): ?string {
    if ($account->isAnonymous()) {
      return 'anonimo';
    }
    if ($entity instanceof CguReport || $entity->isNew()) {
      return 'alvo_invalido';
    }
    // Unsupported discussion hosts must never be silently assigned to
    // the public Forum moderation queue.
    try {
      $this->resolvePurpose($entity);
    }
    catch (ReportNotAllowedException) {
      return 'alvo_invalido';
    }
    // Só denuncia o que a própria conta pode ler.
    if (!$entity->access('view', $account)) {
      return 'sem_acesso';
    }

    $uid = (int) $account->id();
    $storage = $this->entityTypeManager->getStorage('aculta_cgu_report');

    // Não permite denúncia duplicada em aberto para o mesmo objeto.
    $existing = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('target_type', $entity->getEntityTypeId())
      ->condition('target_id', (int) $entity->id())
      ->condition('uid', $uid)
      ->condition('status', [self::STATUS_PENDING, self::STATUS_REVIEW], 'IN')
      ->range(0, 1)
      ->execute();
    if (!empty($existing)) {
      return 'duplicada';
    }

    // Limite diário por conta contra assédio em massa (valor provisório).
    $countToday = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('uid', $uid)
      ->condition('created', $this->time->getRequestTime() - 86400, '>=')
      ->count()
      ->execute();
    if ((int) $countToday >= self::DAILY_LIMIT) {
      return 'limite_diario';
    }
    return NULL;
  }

  /**
   * Registra uma nova denúncia.
   *
   * O purpose é sempre resolvido no servidor. O parâmetro existe só para
   * conferência: se o chamador informar outro purpose, a denúncia é recusada.
   *
   * @throws \Drupal\aculta_cgu\Exception\ReportNotAllowedException
   */
  public function createReport(EntityInterface $entity, AccountInterface $account, string $motivo, ?string $purpose = NULL, string $description = ''): CguReport {
    $description = trim($description);
    if (mb_strlen($description) > self::DESCRIPTION_MAX_LENGTH) {
      throw new ReportNotAllowedException('Descrição da denúncia muito longa.');
    }
    $motivo = strtolower(trim($motivo));
    if (!in_array($motivo, self::REASONS, TRUE)) {
      throw new ReportNotAllowedException('Motivo de denúncia inválido.');
    }
    $resolvedPurpose = $this->resolvePurpose($entity);
    if ($purpose !== NULL && $purpose !== $resolvedPurpose) {
      throw new ReportNotAllowedException('Purpose da denúncia não corresponde ao conteúdo denunciado.');
    }

    // A checagem e a gravação ficam sob lock para evitar duplicata por corrida.
    $lockName = sprintf('aculta_cgu:report:%s:%d:%d', $entity->getEntityTypeId(), (int) $entity->id(), (int) $account->id());
    if (!$this->lock->acquire($lockName, 15.0)) {
      throw new ReportNotAllowedException('Denúncia não permitida neste momento.');
    }
    try {
      if ($this->reportDenial($entity, $account) !== NULL) {
        throw new ReportNotAllowedException('Denúncia não permitida.');
      }
      /** @var \Drupal\aculta_cgu\Entity\CguReport $report */
      $report = $this->entityTypeManager->getStorage('aculta_cgu_report')->create([
        'target_type' => $entity->getEntityTypeId(),
        'target_id' => (int) $entity->id(),
        'uid' => (int) $account->id(),
        'motivo' => $motivo,
        'description' => $description,
        'purpose' => $resolvedPurpose,
        'status' => self::STATUS_PENDING,
      ]);
      $report->save();
    }
    finally {
      $this->lock->release($lockName);
    }

    // O aviso nunca impede o registro: falha fica pendente para reenvio.
    try {
      $this->notifier->notifyNewReport($report);
    }
    catch (\Throwable $e) {
      $this->logger->error('Falha ao avisar moderação da denúncia @id (@class).', [
        '@id' => (int) $report->id(),
        '@class' => $e::class,
      ]);
    }

    return $report;
  }

  /**
   * Decide ou avança uma denúncia via moderação.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
   *   Moderador sem escopo sobre o purpose da denúncia.
   * @throws \Drupal\aculta_cgu\Exception\ReportNotAllowedException
   *   Status ou transição inválidos, ou parecer longo demais.
   */
  public function decideReport(CguReport $report, AccountInterface $moderator, string $status, string $notes): void {
    if (!$this->targetScope->allowsReport($report, $moderator)) {
      throw new AccessDeniedHttpException('Usuário sem permissão para moderar denúncias deste purpose.');
    }

    $notes = trim($notes);
    if (mb_strlen($notes) > self::NOTES_MAX_LENGTH) {
      throw new ReportNotAllowedException('Parecer da moderação excede o tamanho máximo.');
    }

    $reportId = (int) $report->id();
    if ($reportId <= 0) {
      throw new ReportNotAllowedException('Denúncia inválida para decisão.');
    }
    // A lock plus fresh reload prevents two frontend/admin moderators from
    // deciding a stale report concurrently and overwriting a final decision.
    $lockName = 'aculta_cgu:report_decision:' . $reportId;
    if (!$this->lock->acquire($lockName, 15.0)) {
      throw new ReportNotAllowedException('Esta denúncia está sendo avaliada em outra sessão.');
    }

    try {
      $fresh = $this->entityTypeManager->getStorage('aculta_cgu_report')->loadUnchanged($reportId);
      if (!$fresh instanceof CguReport) {
        throw new ReportNotAllowedException('Denúncia não encontrada.');
      }
      $freshPurpose = $fresh->get('purpose')->value;
      $purpose = is_scalar($freshPurpose) ? (string) $freshPurpose : '';
      if (!$this->targetScope->allowsReport($fresh, $moderator)) {
        throw new AccessDeniedHttpException('Usuário sem permissão para moderar denúncias deste purpose.');
      }

      $currentVal = $fresh->get('status')->value;
      $current = is_scalar($currentVal) ? (string) $currentVal : '';
      if (!in_array($status, self::TRANSITIONS[$current] ?? [], TRUE)) {
        throw new ReportNotAllowedException(sprintf('Transição de status inválida: %s para %s.', $current, $status));
      }

      $fresh->set('status', $status);
      $fresh->set('decision_notes', $notes);
      if ($status !== self::STATUS_REVIEW) {
        $fresh->set('decided_by', (int) $moderator->id());
        $fresh->set('decided_at', $this->time->getRequestTime());
      }
      $fresh->save();

      // Audit never reveals the reporting user's identity.
      $this->logger->info('Denúncia @id (purpose @purpose): @from para @to por moderador @uid.', [
        '@id' => $reportId,
        '@purpose' => $purpose,
        '@from' => $current,
        '@to' => $status,
        '@uid' => (int) $moderator->id(),
      ]);
    }
    finally {
      $this->lock->release($lockName);
    }
  }

  /**
   * Purpose LMS só existe quando um adaptador de Group está registrado (fail closed).
   */
  private function lmsPurpose(): string {
    if (!$this->targetScope->supports('lms')) {
      throw new ReportNotAllowedException('Contexto de moderação LMS ainda não habilitado.');
    }
    return 'lms';
  }

  /**
   * Resolves the authoritative reporting scope from the actual host.
   *
   * A Comment bundle is shared by multiple purposes. LMS discussions require
   * a dedicated Group-aware moderation adapter before CGU can accept reports;
   * unsupported hosts fail closed rather than enter the public Forum queue.
   */
  public function resolvePurpose(EntityInterface $entity): string {
    if ($entity instanceof CommentInterface) {
      $host = $entity->getCommentedEntity();
      if (!$host instanceof EntityInterface || $host instanceof CommentInterface) {
        throw new ReportNotAllowedException('Contexto do comentário não reconhecido.');
      }
      $purpose = $this->resolvePurpose($host);
      if (!in_array($purpose, ['forum', 'revista', 'wiki', 'lms'], TRUE)) {
        throw new ReportNotAllowedException('Contexto de moderação do comentário ainda não habilitado.');
      }
      return $purpose;
    }
    if ($entity->getEntityTypeId() === 'aculta_forum_post') {
      return 'forum';
    }
    if ($entity instanceof NodeInterface) {
      return match ($entity->bundle()) {
        'aculta_forum_topic' => 'forum',
        'revista' => 'revista',
        'wiki_entry' => 'wiki',
        'aculta_lms_topic' => $this->lmsPurpose(),
        default => 'portal',
      };
    }
    if (str_contains($entity->getEntityTypeId(), 'event')) {
      return 'eventos';
    }
    return 'portal';
  }

}
