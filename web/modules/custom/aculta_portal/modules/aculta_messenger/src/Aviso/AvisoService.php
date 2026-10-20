<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Aviso;

use Drupal\comment\CommentInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\flag\FlagServiceInterface;
use Drupal\aculta_messenger\Entity\Aviso;
use Drupal\node\NodeInterface;
use Drupal\user\UserInterface;

/**
 * Avisos de resposta em tópico seguido (ACULTA Fórum, F5.2, F-D21).
 *
 * Um aviso por pessoa e por tópico. Cada comentário novo é contado uma vez (o último contado fica no próprio aviso).
 * Quem escreveu o comentário não recebe aviso dele. O aviso não é mensagem: não aparece na caixa de conversas.
 */
final class AvisoService {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly FlagServiceInterface $flagService,
    private readonly ForumNoticeAccessPolicy $accessPolicy,
  ) {}

  /** Regra de contagem: um comentário só conta se é mais novo que o último já contado. */
  public static function isNewComment(int $commentId, int $ultimoContado): bool {
    return $commentId > $ultimoContado;
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

  /** Zera o contador do site quando a pessoa lê o tópico. */
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

  /** Quantos tópicos têm respostas não lidas para a pessoa (para a contagem do site). */
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
