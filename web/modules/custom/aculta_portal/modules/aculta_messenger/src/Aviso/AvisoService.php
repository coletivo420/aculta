<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Aviso;

use Drupal\comment\CommentInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\SynchronizableInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\IntegrityConstraintViolationException;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\aculta_forum\Entity\ForumPost;
use Drupal\aculta_messenger\Entity\Aviso;
use Drupal\aculta_messenger\Database\ForumNoticeEventSchema;
use Drupal\flag\FlagServiceInterface;
use Drupal\node\NodeInterface;
use Drupal\user\UserInterface;

/**
 * Notices for replies in followed topics (ACULTA Forum, F5.2, F-D21).
 *
 * One notice is kept per person and topic. Each new comment is counted once;
 * the last counted comment is stored on the notice. Authors do not receive
 * notices for their own replies. Notices do not appear in the message inbox.
 */
final class AvisoService implements ForumNoticeTransferInterface, ForumNoticeRegistrarInterface, ForumNoticeReadInterface {

  /**
   * Maximum number of personal notice aggregates returned by default.
   */
  public const PERSONAL_NOTICE_LIST_LIMIT = 100;

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly FlagServiceInterface $flagService,
    private readonly ForumNoticeAccessPolicyInterface $accessPolicy,
    private readonly ?Connection $database = NULL,
    private readonly ?TimeInterface $time = NULL,
    private readonly ?LockBackendInterface $lock = NULL,
    private readonly ?ModuleHandlerInterface $moduleHandler = NULL,
  ) {}

  /**
   * Whether a comment ID is newer than the last counted comment.
   *
   * @param int $commentId
   *   Comment ID to compare.
   * @param int $ultimoContado
   *   Last counted Comment ID.
   *
   * @return bool
   *   Whether the comment should be counted.
   */
  public static function isNewComment(int $commentId, int $ultimoContado): bool {
    return $commentId > $ultimoContado;
  }

  /**
   * Whether a ForumPost revision has not yet been counted in a response notice.
   */
  public static function isNewPost(int $revisionId, int $ultimoContado): bool {
    return $revisionId > $ultimoContado;
  }

  /**
   * Whether a revision ID is newer than the last counted revision.
   *
   * @param int $revisionId
   *   Revision ID to compare.
   * @param int $ultimaContada
   *   Last counted revision ID.
   *
   * @return bool
   *   Whether the revision should be counted.
   */
  public static function isNewRevision(int $revisionId, int $ultimaContada): bool {
    return $revisionId > $ultimaContada;
  }

  /**
   * Notifies topic followers about a new revision.
   *
   * This replaces message delivery for topic updates (F-D21).
   *
   * @param \Drupal\node\NodeInterface $topico
   *   Published topic being updated.
   * @param int $revisionId
   *   Revision ID that triggered the notice.
   * @param int|null $recipientUid
   *   Optional recipient being revalidated by a shared event consumer.
   *
   * @return int
   *   Number of notices updated.
   */
  public function notificarAtualizacao(NodeInterface $topico, int $revisionId, ?int $recipientUid = NULL): int {
    if (!$this->accessPolicy->supportsTopic($topico) || !$topico->isPublished()) {
      return 0;
    }
    $flag = $this->flagService->getFlagById('seguir_topico');
    if ($flag === NULL) {
      return 0;
    }
    $atualizados = 0;
    $authorUid = method_exists($topico, 'getRevisionUserId') ? (int) $topico->getRevisionUserId() : 0;
    foreach ($this->flagService->getEntityFlaggings($flag, $topico) as $flagging) {
      $uid = (int) $flagging->getOwnerId();
      if ($uid <= 0 || ($recipientUid !== NULL && $uid !== $recipientUid)
        || $uid === $authorUid || !$this->accessPolicy->canViewTopic($topico, $uid)) {
        continue;
      }
      if ($this->recordNoticeEvent(
        $uid,
        $topico,
        Aviso::TIPO_ATUALIZACAO,
        'topic_revision',
        $revisionId,
        'ultimo_evento',
      )) {
        $atualizados++;
      }
    }
    return $atualizados;
  }

  /**
   * Loads a person's notices, with unread items first and newest first.
   *
   * @return \Drupal\aculta_messenger\Entity\Aviso[]
   *   Accessible notices for the account.
   */
  public function listarDaPessoa(int $uid, int $limite = self::PERSONAL_NOTICE_LIST_LIMIT, ?string $readState = NULL): array {
    return $this->listarDaPessoaPaginada($uid, $limite, 0, $readState)['items'];
  }

  /**
   * Loads one authorized page of Forum notices for an account.
   *
   * The extra candidate determines whether a next page exists. Access checks
   * remain after loading the page so hidden topics never become render items.
   *
   * @return array{items: \Drupal\aculta_messenger\Entity\Aviso[], has_next: bool}
   *   Accessible notices for this page and whether another candidate page is
   *   available.
   */
  public function listarDaPessoaPaginada(int $uid, int $limite, int $offset, ?string $readState = NULL): array {
    $limite = max(1, $limite);
    $offset = max(0, $offset);
    $query = $this->entityTypeManager->getStorage('aculta_aviso')->getQuery()
      ->accessCheck(FALSE)
      ->condition('uid', $uid);
    if (in_array($readState, ['read', 'unread'], TRUE)) {
      $query->condition('contador_site', 0, $readState === 'read' ? '=' : '>');
    }
    $ids = $query
      ->sort('contador_site', 'DESC')
      ->sort('changed', 'DESC')
      ->sort('id', 'DESC')
      ->range($offset, $limite + 1)
      ->execute();
    $hasNext = count($ids) > $limite;
    $ids = array_slice($ids, 0, $limite);
    $notices = $this->entityTypeManager->getStorage('aculta_aviso')->loadMultiple($ids);
    $topicIds = [];
    foreach ($notices as $aviso) {
      $topicIds[(int) $aviso->get('assunto_nid')->value] = TRUE;
    }
    $topics = $this->entityTypeManager->getStorage('node')->loadMultiple(array_keys($topicIds));
    $recipient = $this->entityTypeManager->getStorage('user')->load($uid);
    if (!$recipient instanceof UserInterface || !$recipient->isActive()) {
      return ['items' => [], 'has_next' => FALSE];
    }
    $accessible = array_values(array_filter($notices, fn(Aviso $aviso): bool => $this->accessPolicy->canViewTopic(
      $topics[(int) $aviso->get('assunto_nid')->value] ?? NULL,
      $recipient,
    )));
    return ['items' => $accessible, 'has_next' => $hasNext];
  }

  /**
   * Counts a person's unread notices for one topic.
   *
   * @param int $uid
   *   Recipient account ID.
   * @param int $nid
   *   Topic node ID.
   *
   * @return int
   *   Number of unread notices across all types for the topic.
   */
  public function contadorDoTopico(int $uid, int $nid): int {
    $topic = $this->loadTopic($nid);
    if (!$this->accessPolicy->canViewTopic($topic, $uid)) {
      return 0;
    }
    $total = 0;
    foreach ($this->entityTypeManager->getStorage('aculta_aviso')->loadMultiple(
      $this->entityTypeManager->getStorage('aculta_aviso')->getQuery()
        ->accessCheck(FALSE)
        ->condition('uid', $uid)
        ->condition('assunto_nid', $nid)
        ->execute()
    ) as $aviso) {
      $total += (int) $aviso->get('contador_site')->value;
    }
    return $total;
  }

  /**
   * Counts topics with unread personal notices.
   *
   * @param int $uid
   *   Recipient account ID.
   *
   * @return int
   *   Number of accessible topics with unread notices.
   */
  public function naoLidosTotal(int $uid): int {
    $ids = $this->entityTypeManager->getStorage('aculta_aviso')->getQuery()
      ->accessCheck(FALSE)
      ->condition('uid', $uid)
      ->condition('contador_site', 0, '>')
      ->execute();
    $topicIds = [];
    foreach ($this->entityTypeManager->getStorage('aculta_aviso')->loadMultiple($ids) as $aviso) {
      $topicIds[(int) $aviso->get('assunto_nid')->value] = TRUE;
    }
    $topics = $this->entityTypeManager->getStorage('node')->loadMultiple(array_keys($topicIds));
    $recipient = $this->entityTypeManager->getStorage('user')->load($uid);
    if (!$recipient instanceof UserInterface || !$recipient->isActive()) {
      return 0;
    }
    $total = 0;
    foreach ($topicIds as $nid => $_) {
      if ($this->accessPolicy->canViewTopic($topics[$nid] ?? NULL, $recipient)) {
        $total++;
      }
    }
    $this->moduleHandler?->alter('aculta_messenger_personal_notice_count', $total, $uid);
    return $total;
  }

  /**
   * Registers a published reply for followers of its topic.
   *
   * @param \Drupal\comment\CommentInterface $comment
   *   Published Forum comment.
   * @param int|null $recipientUid
   *   Optional recipient being revalidated by a shared event consumer.
   *
   * @return int
   *   Number of notices updated.
   */
  public function registrarResposta(CommentInterface $comment, ?int $recipientUid = NULL): int {
    if (!$comment->isPublished() || $comment->bundle() !== 'aculta_discussion_post') {
      return 0;
    }
    $topico = $comment->getCommentedEntity();
    if ($topico === NULL || !$this->accessPolicy->supportsTopic($topico)) {
      return 0;
    }
    $flag = $this->flagService->getFlagById('seguir_topico');
    if ($flag === NULL) {
      return 0;
    }
    $commentId = (int) $comment->id();
    if ($commentId <= 0) {
      return 0;
    }
    $atualizados = 0;
    foreach ($this->flagService->getEntityFlaggings($flag, $topico) as $flagging) {
      $uid = (int) $flagging->getOwnerId();
      if ($uid <= 0 || ($recipientUid !== NULL && $uid !== $recipientUid)
        || $uid === (int) $comment->getOwnerId() || !$this->accessPolicy->canViewComment($comment, $uid)) {
        continue;
      }
      if ($this->recordNoticeEvent(
        $uid,
        $topico,
        Aviso::TIPO_RESPOSTA,
        'comment',
        $commentId,
        'ultimo_comentario',
      )) {
        $atualizados++;
      }
    }
    return $atualizados;
  }

  /**
   * Registers a newly published revisionable post on followed Forum topics.
   */
  public function registrarPost(ForumPost $post, ?int $recipientUid = NULL): int {
    if (!$post->isPublished() || !$post->isDefaultRevision()
      || ($post instanceof SynchronizableInterface && $post->isSyncing())
      || !$post->hasField('topic') || $post->get('topic')->isEmpty()) {
      return 0;
    }
    $topic = $post->get('topic')->entity;
    if (!$topic instanceof NodeInterface || !$this->accessPolicy->supportsTopic($topic)) {
      return 0;
    }
    $revisionId = (int) $post->getRevisionId();
    if ($revisionId <= 0) {
      return 0;
    }
    $flag = $this->flagService->getFlagById('seguir_topico');
    if ($flag === NULL) {
      return 0;
    }
    $atualizados = 0;
    foreach ($this->flagService->getEntityFlaggings($flag, $topic) as $flagging) {
      $uid = (int) $flagging->getOwnerId();
      if ($uid <= 0 || ($recipientUid !== NULL && $uid !== $recipientUid)
        || $uid === (int) $post->getOwnerId()
        || !$this->accessPolicy->canViewPost($post, $uid)) {
        continue;
      }
      if ($this->recordNoticeEvent(
        $uid,
        $topic,
        Aviso::TIPO_RESPOSTA,
        'forum_post_revision',
        $revisionId,
        'ultimo_post',
      )) {
        $atualizados++;
      }
    }
    return $atualizados;
  }

  /**
   * Transfers whole per-user response aggregates between Forum topics.
   *
   * The historic counter cannot identify individual comments. Moving the whole
   * record is therefore intentional; destination records are merged.
   */
  public function transferTopicResponseNotices(int $sourceNid, int $destinationNid): int {
    if ($sourceNid <= 0 || $destinationNid <= 0
      || $sourceNid === $destinationNid) {
      return 0;
    }
    $destination = $this->loadTopic($destinationNid);
    if (!$destination instanceof NodeInterface
      || !$this->accessPolicy->supportsTopic($destination)
      || !$destination->isPublished()) {
      return 0;
    }
    $storage = $this->entityTypeManager->getStorage('aculta_aviso');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('assunto_nid', $sourceNid)
      ->condition('tipo', Aviso::TIPO_RESPOSTA)
      ->execute();
    $moved = 0;
    foreach ($storage->loadMultiple($ids) as $source) {
      $uid = (int) $source->get('uid')->target_id;
      if ($uid <= 0 || !$this->accessPolicy->canViewTopic($destination, $uid)) {
        continue;
      }
      $target_ids = $this->query($uid, $destinationNid, Aviso::TIPO_RESPOSTA);
      if ($target_ids !== []) {
        $target = $storage->load(reset($target_ids));
        $target->set('contador_site', (int) $target->get('contador_site')->value
          + (int) $source->get('contador_site')->value);
        $target->set('contador_email', (int) $target->get('contador_email')->value
          + (int) $source->get('contador_email')->value);
        $target->set('ultimo_comentario', max(
          (int) $target->get('ultimo_comentario')->value,
          (int) $source->get('ultimo_comentario')->value,
        ));
        $ultimoPost = max(
          (int) $target->get('ultimo_post')->value,
          (int) $source->get('ultimo_post')->value,
        );
        if ($ultimoPost > (int) $target->get('ultimo_post')->value) {
          $target->set('ultimo_post', $ultimoPost);
        }
        $target->set('titulo', mb_substr((string) $destination->label(), 0, 255));
        $target->save();
        $source->delete();
      }
      else {
        $source->set('assunto_nid', $destinationNid);
        $source->set('titulo', mb_substr((string) $destination->label(), 0, 255));
        $source->save();
      }
      $moved++;
    }
    return $moved;
  }

  /**
   * Zera o contador do site quando a pessoa lê o tópico.
   */
  public function marcarLido(int $uid, int $nid): void {
    if (!$this->accessPolicy->canViewTopic($this->loadTopic($nid), $uid)) {
      return;
    }
    // Reading a topic clears its reply and update notices.
    $ids = $this->entityTypeManager->getStorage('aculta_aviso')->getQuery()
      ->accessCheck(FALSE)
      ->condition('uid', $uid)
      ->condition('assunto_nid', $nid)
      ->execute();
    foreach ($this->entityTypeManager->getStorage('aculta_aviso')->loadMultiple($ids) as $aviso) {
      if ((int) $aviso->get('contador_site')->value > 0) {
        $aviso->set('contador_site', 0);
        $aviso->save();
      }
    }
  }

  /**
   * Quantos avisos pessoais não lidos entram na contagem global do site.
   */
  public function naoLidosNoSite(int $uid): int {
    return $this->naoLidosTotal($uid);
  }

  /**
   * Checks current recipient access for a topic notice.
   *
   * @param int $uid
   *   Recipient account ID.
   * @param int $nid
   *   Topic node ID.
   *
   * @return bool
   *   Whether the recipient can view the topic.
   */
  public function canRecipientViewTopic(int $uid, int $nid): bool {
    return $this->accessPolicy->canViewTopic($this->loadTopic($nid), $uid);
  }

  /**
   * Records one Forum event and increments its aggregate notice atomically.
   */
  private function recordNoticeEvent(
    int $uid,
    NodeInterface $topic,
    string $noticeType,
    string $eventType,
    int $eventId,
    string $watermarkField,
  ): bool {
    if ($eventId <= 0) {
      return FALSE;
    }
    if (!$this->database instanceof Connection || !$this->time instanceof TimeInterface) {
      throw new \LogicException('Forum notice registration requires the database and time services.');
    }
    if (!in_array($watermarkField, ['ultimo_comentario', 'ultimo_post', 'ultimo_evento'], TRUE)) {
      throw new \LogicException('Forum notice registration received an unsupported watermark field.');
    }

    $nid = (int) $topic->id();
    return $this->withNoticeLock($uid, $nid, $noticeType, function () use (
      $uid,
      $nid,
      $topic,
      $noticeType,
      $eventType,
      $eventId,
      $watermarkField,
    ): bool {
      $transaction = $this->database->startTransaction();
      try {
        $this->database->insert(ForumNoticeEventSchema::TABLE)
          ->fields([
            'uid' => $uid,
            'event_type' => $eventType,
            'event_id' => $eventId,
            'nid' => $nid,
            'created' => $this->time->getRequestTime(),
          ])
          ->execute();
      }
      catch (IntegrityConstraintViolationException) {
        $transaction->rollBack();
        return FALSE;
      }

      try {
        $aviso = $this->avisoDe($uid, $nid, (string) $topic->label(), $noticeType);
        $aviso->set('contador_site', (int) $aviso->get('contador_site')->value + 1);
        $aviso->set('contador_email', (int) $aviso->get('contador_email')->value + 1);
        $aviso->set($watermarkField, max(
          $eventId,
          (int) $aviso->get($watermarkField)->value,
        ));
        $aviso->save();
        unset($transaction);
        return TRUE;
      }
      catch (\Throwable $exception) {
        $transaction->rollBack();
        throw $exception;
      }
    });
  }

  /**
   * Serializes updates to one recipient's aggregate notice.
   *
   * @param int $uid
   *   The recipient's user ID.
   * @param int $nid
   *   The Forum topic node ID.
   * @param string $type
   *   The notice type.
   * @param callable(): bool $update
   *   The operation to run while holding the aggregate lock.
   */
  private function withNoticeLock(int $uid, int $nid, string $type, callable $update): bool {
    if (!$this->lock instanceof LockBackendInterface) {
      throw new \LogicException('Forum notice updates require the lock backend service.');
    }
    $name = sprintf('aculta_messenger:notice:%d:%d:%s', $uid, $nid, $type);
    if (!$this->lock->acquire($name, 60.0)) {
      $this->lock->wait($name, 60);
      if (!$this->lock->acquire($name, 60.0)) {
        throw new \RuntimeException('Unable to acquire the Forum notice aggregate lock.');
      }
    }

    try {
      return $update();
    }
    finally {
      $this->lock->release($name);
    }
  }

  /**
   * Clears only the email-pending counter for one Forum aggregate.
   */
  public function clearPendingEmailCount(int $uid, int $nid, string $type): void {
    $this->updatePendingEmailCount($uid, $nid, $type, 0, FALSE);
  }

  /**
   * Acknowledges the number of events represented by a delivered digest row.
   */
  public function acknowledgeEmailCount(int $uid, int $nid, string $type, int $sentCount): void {
    if ($sentCount > 0) {
      $this->updatePendingEmailCount($uid, $nid, $type, $sentCount, TRUE);
    }
  }

  /**
   * Returns a stable generation for Forum notice rows in a pending digest.
   *
   * The event ledger's auto-increment ID advances for every newly accepted
   * event, including approvals delivered out of Comment ID order. Unlike the
   * pending counter, it does not return to an earlier value after a digest is
   * acknowledged, so a later batch with the same count receives a new Mailer
   * correlation.
   *
   * @param int $uid
   *   Recipient account ID.
   * @param int[] $nids
   *   Forum topic IDs represented in the digest.
   *
   * @return int[]
   *   Latest ledger row ID keyed by topic ID.
   */
  public function digestBatchGenerations(int $uid, array $nids): array {
    $nids = array_values(array_unique(array_filter(array_map('intval', $nids), static fn(int $nid): bool => $nid > 0)));
    if ($uid <= 0 || $nids === []) {
      return [];
    }

    $query = $this->database?->select(ForumNoticeEventSchema::TABLE, 'event');
    if ($query === NULL) {
      throw new \LogicException('Forum digest generations require the event ledger database connection.');
    }
    $query->addField('event', 'nid');
    $query->addExpression('MAX(event.id)', 'generation');
    $query->condition('event.uid', $uid);
    $query->condition('event.nid', $nids, 'IN');
    $query->groupBy('event.nid');

    $generations = [];
    foreach ($query->execute() as $row) {
      $generations[(int) $row->nid] = (int) $row->generation;
    }
    return $generations;
  }

  /**
   * Updates an email counter under the same lock used by Forum event writers.
   */
  private function updatePendingEmailCount(int $uid, int $nid, string $type, int $amount, bool $subtract): void {
    $this->withNoticeLock($uid, $nid, $type, function () use ($uid, $nid, $type, $amount, $subtract): bool {
      $storage = $this->entityTypeManager->getStorage('aculta_aviso');
      $avisos = $storage->loadByProperties(['uid' => $uid, 'assunto_nid' => $nid, 'tipo' => $type]);
      $aviso = reset($avisos);
      if (!$aviso instanceof Aviso) {
        return FALSE;
      }
      $current = (int) $aviso->get('contador_email')->value;
      $next = $subtract ? max(0, $current - $amount) : 0;
      if ($next !== $current) {
        $aviso->set('contador_email', $next)->save();
      }
      return TRUE;
    });
  }

  /**
   * Loads a notice or creates one for a person, topic, and type.
   *
   * @param int $uid
   *   Recipient account ID.
   * @param int $nid
   *   Topic node ID.
   * @param string $titulo
   *   Topic title stored on a new notice.
   * @param string $tipo
   *   Notice type.
   *
   * @return \Drupal\aculta_messenger\Entity\Aviso
   *   Existing or new notice entity.
   */
  private function avisoDe(int $uid, int $nid, string $titulo, string $tipo): Aviso {
    $ids = $this->query($uid, $nid, $tipo);
    $storage = $this->entityTypeManager->getStorage('aculta_aviso');
    if ($ids !== []) {
      return $storage->load(reset($ids));
    }
    return $storage->create([
      'uid' => $uid,
      'tipo' => $tipo,
      'assunto_nid' => $nid,
      'titulo' => mb_substr($titulo, 0, 255),
      'contador_site' => 0,
      'contador_email' => 0,
      'ultimo_comentario' => 0,
      'ultimo_post' => 0,
      'ultimo_evento' => 0,
    ]);
  }

  /**
   * Loads a notice topic by node ID.
   */
  private function loadTopic(int $nid): ?EntityInterface {
    return $this->entityTypeManager->getStorage('node')->load($nid);
  }

  /**
   * Finds the notice matching one person, topic, and type.
   *
   * @param int $uid
   *   Recipient account ID.
   * @param int $nid
   *   Topic node ID.
   * @param string $tipo
   *   Notice type.
   *
   * @return int[]
   *   Matching notice entity IDs.
   */
  private function query(int $uid, int $nid, string $tipo = Aviso::TIPO_RESPOSTA): array {
    return $this->entityTypeManager->getStorage('aculta_aviso')->getQuery()
      ->accessCheck(FALSE)
      ->condition('uid', $uid)
      ->condition('tipo', $tipo)
      ->condition('assunto_nid', $nid)
      ->range(0, 1)
      ->execute();
  }

}
