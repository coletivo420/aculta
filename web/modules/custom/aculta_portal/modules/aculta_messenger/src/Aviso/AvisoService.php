<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Aviso;

use Drupal\comment\CommentInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\flag\FlagServiceInterface;
use Drupal\aculta_messenger\Entity\Aviso;

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
  ) {}

  /** Regra de contagem: um comentário só conta se é mais novo que o último já contado. */
  public static function isNewComment(int $commentId, int $ultimoContado): bool {
    return $commentId > $ultimoContado;
  }

  /**
   * Registra um comentário publicado de tópico: soma nos avisos de quem segue o tópico. Retorna quantos avisos mudaram.
   */
  public function registrarResposta(CommentInterface $comment): int {
    if (!$comment->isPublished() || $comment->bundle() !== 'comment_forum') {
      return 0;
    }
    $topico = $comment->getCommentedEntity();
    if ($topico === NULL || $topico->bundle() !== 'forum') {
      return 0;
    }
    $flag = $this->flagService->getFlagById('seguir_topico');
    if ($flag === NULL) {
      return 0;
    }
    $atualizados = 0;
    foreach ($this->flagService->getEntityFlaggings($flag, $topico) as $flagging) {
      $uid = (int) $flagging->getOwnerId();
      if ($uid <= 0 || $uid === (int) $comment->getOwnerId()) {
        continue;
      }
      $aviso = $this->avisoDe($uid, (int) $topico->id(), (string) $topico->label());
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
    $ids = $this->query($uid, $nid);
    foreach ($this->entityTypeManager->getStorage('aculta_aviso')->loadMultiple($ids) as $aviso) {
      if ((int) $aviso->get('contador_site')->value > 0) {
        $aviso->set('contador_site', 0);
        $aviso->save();
      }
    }
  }

  /** Quantos tópicos têm respostas não lidas para a pessoa (para a contagem do site). */
  public function naoLidosNoSite(int $uid): int {
    return (int) $this->entityTypeManager->getStorage('aculta_aviso')->getQuery()
      ->accessCheck(FALSE)
      ->condition('uid', $uid)
      ->condition('tipo', Aviso::TIPO_RESPOSTA)
      ->condition('contador_site', 0, '>')
      ->count()
      ->execute();
  }

  private function avisoDe(int $uid, int $nid, string $titulo): Aviso {
    $ids = $this->query($uid, $nid);
    $storage = $this->entityTypeManager->getStorage('aculta_aviso');
    if ($ids !== []) {
      return $storage->load(reset($ids));
    }
    return $storage->create([
      'uid' => $uid,
      'tipo' => Aviso::TIPO_RESPOSTA,
      'assunto_nid' => $nid,
      'titulo' => mb_substr($titulo, 0, 255),
      'contador_site' => 0,
      'contador_email' => 0,
      'ultimo_comentario' => 0,
    ]);
  }

  private function query(int $uid, int $nid): array {
    return $this->entityTypeManager->getStorage('aculta_aviso')->getQuery()
      ->accessCheck(FALSE)
      ->condition('uid', $uid)
      ->condition('tipo', Aviso::TIPO_RESPOSTA)
      ->condition('assunto_nid', $nid)
      ->range(0, 1)
      ->execute();
  }

}
