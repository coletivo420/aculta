<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Aviso;

use Drupal\comment\CommentInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\SynchronizableInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\aculta_forum\Entity\ForumPost;
use Drupal\aculta_messenger\Entity\Aviso;
use Drupal\flag\FlagServiceInterface;
use Drupal\node\NodeInterface;
use Drupal\user\UserInterface;

/**
 * Avisos de resposta em tópico seguido (ACULTA Fórum, F5.2, F-D21).
 *
 * Um aviso por pessoa e por tópico. Cada comentário novo é contado uma vez (o último contado fica no próprio aviso).
 * Quem escreveu o comentário não recebe aviso dele. O aviso não é mensagem: não aparece na caixa de conversas.
 */
final class AvisoService implements ForumNoticeTransferInterface {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly FlagServiceInterface $flagService,
    private readonly ForumNoticeAccessPolicyInterface $accessPolicy,
    private readonly ?ModuleHandlerInterface $moduleHandler = NULL,
  ) {}

  /** Regra de contagem: um comentário só conta se é mais novo que o último já contado. */
  public static function isNewComment(int $commentId, int $ultimoContado): bool {
    return $commentId > $ultimoContado;
  }

  /**
   * Whether a Forum post has not yet been counted in a response notice.
   */
  public static function isNewPost(int $postId, int $ultimoContado): bool {
    return $postId > $ultimoContado;
  }

  /** Regra de contagem de atualização: uma revisão só conta se é mais nova que a última contada. */
  public static function isNewRevision(int $revisionId, int $ultimaContada): bool {
    return $revisionId > $ultimaContada;
  }

  /**
   * Notifica quem segue o tópico de uma atualização (revisão nova). Conta uma vez por revisão.
   *
   * Substitui a entrega de atualização por mensagem (F-D21). Retorna quantos avisos mudaram.
   */
  public function notificarAtualizacao(NodeInterface $topico, int $revisionId): int {
    if (!$this->accessPolicy->supportsTopic($topico) || !$topico->isPublished()) {
      return 0;
    }
    $flag = $this->flagService->getFlagById('seguir_topico');
    if ($flag === NULL) {
      return 0;
    }
    $atualizados = 0;
    foreach ($this->flagService->getEntityFlaggings($flag, $topico) as $flagging) {
      $uid = (int) $flagging->getOwnerId();
      if ($uid <= 0 || !$this->accessPolicy->canViewTopic($topico, $uid)) {
        continue;
      }
      $aviso = $this->avisoDe($uid, (int) $topico->id(), (string) $topico->label(), Aviso::TIPO_ATUALIZACAO);
      if (!self::isNewRevision($revisionId, (int) $aviso->get('ultimo_evento')->value)) {
        continue;
      }
      $aviso->set('contador_site', (int) $aviso->get('contador_site')->value + 1);
      $aviso->set('contador_email', (int) $aviso->get('contador_email')->value + 1);
      $aviso->set('ultimo_evento', $revisionId);
      $aviso->save();
      $atualizados++;
    }
    return $atualizados;
  }

  /**
   * Avisos da própria pessoa, do mais recente para o mais antigo. Mostra lidos e não lidos (contador > 0 primeiro).
   *
   * @return \Drupal\aculta_messenger\Entity\Aviso[]
   */
  public function listarDaPessoa(int $uid, int $limite = 100): array {
    $ids = $this->entityTypeManager->getStorage('aculta_aviso')->getQuery()
      ->accessCheck(FALSE)
      ->condition('uid', $uid)
      ->sort('contador_site', 'DESC')
      ->sort('changed', 'DESC')
      ->range(0, $limite)
      ->execute();
    $notices = $this->entityTypeManager->getStorage('aculta_aviso')->loadMultiple($ids);
    $topicIds = [];
    foreach ($notices as $aviso) {
      $topicIds[(int) $aviso->get('assunto_nid')->value] = TRUE;
    }
    $topics = $this->entityTypeManager->getStorage('node')->loadMultiple(array_keys($topicIds));
    $recipient = $this->entityTypeManager->getStorage('user')->load($uid);
    if (!$recipient instanceof UserInterface || !$recipient->isActive()) {
      return [];
    }
    return array_values(array_filter($notices, fn(Aviso $aviso): bool => $this->accessPolicy->canViewTopic(
      $topics[(int) $aviso->get('assunto_nid')->value] ?? NULL,
      $recipient,
    )));
  }

  /** Itens não lidos da pessoa em um tópico, somando todos os tipos (contador individual do tópico). */
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

  /** Quantos tópicos (avisos agrupados por tipo + assunto) têm itens não lidos para a pessoa. */
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
   * Registra um comentário publicado de tópico: soma nos avisos de quem segue o tópico. Retorna quantos avisos mudaram.
   */
  public function registrarResposta(CommentInterface $comment): int {
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
    $atualizados = 0;
    foreach ($this->flagService->getEntityFlaggings($flag, $topico) as $flagging) {
      $uid = (int) $flagging->getOwnerId();
      if ($uid <= 0 || $uid === (int) $comment->getOwnerId() || !$this->accessPolicy->canViewComment($comment, $uid)) {
        continue;
      }
      $aviso = $this->avisoDe($uid, (int) $topico->id(), (string) $topico->label(), Aviso::TIPO_RESPOSTA);
      if (!self::isNewComment((int) $comment->id(), (int) $aviso->get('ultimo_comentario')->value)) {
        continue;
      }
      $aviso->set('contador_site', (int) $aviso->get('contador_site')->value + 1);
      $aviso->set('contador_email', (int) $aviso->get('contador_email')->value + 1);
      $aviso->set('ultimo_comentario', (int) $comment->id());
      $aviso->save();
      $atualizados++;
    }
    return $atualizados;
  }

  /**
   * Registers a newly published revisionable post on followed Forum topics.
   */
  public function registrarPost(ForumPost $post): int {
    if (!$post->isPublished() || !$post->isDefaultRevision()
      || ($post instanceof SynchronizableInterface && $post->isSyncing())
      || !$post->hasField('topic') || $post->get('topic')->isEmpty()) {
      return 0;
    }
    $topic = $post->get('topic')->entity;
    if (!$topic instanceof NodeInterface || !$this->accessPolicy->supportsTopic($topic)) {
      return 0;
    }
    $flag = $this->flagService->getFlagById('seguir_topico');
    if ($flag === NULL) {
      return 0;
    }
    $atualizados = 0;
    foreach ($this->flagService->getEntityFlaggings($flag, $topic) as $flagging) {
      $uid = (int) $flagging->getOwnerId();
      if ($uid <= 0 || $uid === (int) $post->getOwnerId()
        || !$this->accessPolicy->canViewPost($post, $uid)) {
        continue;
      }
      $aviso = $this->avisoDe($uid, (int) $topic->id(), (string) $topic->label(), Aviso::TIPO_RESPOSTA);
      $ultimoPost = (int) $aviso->get('ultimo_post')->value;
      if (!self::isNewPost((int) $post->id(), $ultimoPost)) {
        continue;
      }
      $aviso->set('contador_site', (int) $aviso->get('contador_site')->value + 1);
      $aviso->set('contador_email', (int) $aviso->get('contador_email')->value + 1);
      $aviso->set('ultimo_post', (int) $post->id());
      $aviso->save();
      $atualizados++;
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
    // Todos os tipos de aviso deste tópico: ler o tópico zera respostas e atualizações.
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
