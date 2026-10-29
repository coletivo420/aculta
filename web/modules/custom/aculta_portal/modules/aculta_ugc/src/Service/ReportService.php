<?php

declare(strict_types=1);

namespace Drupal\aculta_ugc\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\aculta_ugc\Entity\UgcReport;
use Drupal\aculta_ugc\Exception\ReportNotAllowedException;
use Drupal\aculta_ugc\ModerationScope;
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

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly TimeInterface $time,
    private readonly ModerationNotifier $notifier,
    private readonly LockBackendInterface $lock,
    private readonly LoggerInterface $logger,
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
    if ($entity instanceof UgcReport || $entity->isNew()) {
      return 'alvo_invalido';
    }
    // Só denuncia o que a própria conta pode ler.
    if (!$entity->access('view', $account)) {
      return 'sem_acesso';
    }

    $uid = (int) $account->id();
    $storage = $this->entityTypeManager->getStorage('aculta_ugc_report');

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
   * @throws \Drupal\aculta_ugc\Exception\ReportNotAllowedException
   */
  public function createReport(EntityInterface $entity, AccountInterface $account, string $motivo, ?string $purpose = NULL): UgcReport {
    $motivo = strtolower(trim($motivo));
    if (!in_array($motivo, self::REASONS, TRUE)) {
      throw new ReportNotAllowedException('Motivo de denúncia inválido.');
    }
    $resolvedPurpose = $this->resolvePurpose($entity);
    if ($purpose !== NULL && $purpose !== $resolvedPurpose) {
      throw new ReportNotAllowedException('Purpose da denúncia não corresponde ao conteúdo denunciado.');
    }

    // A checagem e a gravação ficam sob lock para evitar duplicata por corrida.
    $lockName = sprintf('aculta_ugc:report:%s:%d:%d', $entity->getEntityTypeId(), (int) $entity->id(), (int) $account->id());
    if (!$this->lock->acquire($lockName, 15.0)) {
      throw new ReportNotAllowedException('Denúncia não permitida neste momento.');
    }
    try {
      if ($this->reportDenial($entity, $account) !== NULL) {
        throw new ReportNotAllowedException('Denúncia não permitida.');
      }
      /** @var \Drupal\aculta_ugc\Entity\UgcReport $report */
      $report = $this->entityTypeManager->getStorage('aculta_ugc_report')->create([
        'target_type' => $entity->getEntityTypeId(),
        'target_id' => (int) $entity->id(),
        'uid' => (int) $account->id(),
        'motivo' => $motivo,
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
   * @throws \Drupal\aculta_ugc\Exception\ReportNotAllowedException
   *   Status ou transição inválidos, ou parecer longo demais.
   */
  public function decideReport(UgcReport $report, AccountInterface $moderator, string $status, string $notes): void {
    $purpose = (string) $report->get('purpose')->value;
    if (!ModerationScope::allows($moderator, $purpose)) {
      throw new AccessDeniedHttpException('Usuário sem permissão para moderar denúncias deste purpose.');
    }

    $current = (string) $report->get('status')->value;
    if (!in_array($status, self::TRANSITIONS[$current] ?? [], TRUE)) {
      throw new ReportNotAllowedException(sprintf('Transição de status inválida: %s para %s.', $current, $status));
    }
    $notes = trim($notes);
    if (mb_strlen($notes) > self::NOTES_MAX_LENGTH) {
      throw new ReportNotAllowedException('Parecer da moderação excede o tamanho máximo.');
    }

    $report->set('status', $status);
    $report->set('decision_notes', $notes);
    if ($status !== self::STATUS_REVIEW) {
      $report->set('decided_by', (int) $moderator->id());
      $report->set('decided_at', $this->time->getRequestTime());
    }
    $report->save();

    // Auditoria sem dado do denunciante.
    $this->logger->info('Denúncia @id (purpose @purpose): @from para @to por moderador @uid.', [
      '@id' => (int) $report->id(),
      '@purpose' => $purpose,
      '@from' => $current,
      '@to' => $status,
      '@uid' => (int) $moderator->id(),
    ]);
  }

  /**
   * Infere o purpose com base no tipo da entidade.
   */
  public function resolvePurpose(EntityInterface $entity): string {
    $type = $entity->getEntityTypeId();
    if (str_contains($type, 'event')) {
      return 'eventos';
    }
    if (str_contains($type, 'forum') || str_contains($type, 'comment')) {
      return 'forum';
    }
    if ($type === 'node') {
      /** @var \Drupal\node\NodeInterface $entity */
      $bundle = $entity->bundle();
      if ($bundle === 'revista') {
        return 'revista';
      }
      if ($bundle === 'wiki_entry') {
        return 'wiki';
      }
      if ($bundle === 'aculta_forum_topic') {
        return 'forum';
      }
    }
    return 'portal';
  }

}
