<?php

declare(strict_types=1);

namespace Drupal\aculta_user;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Regras de participação no Fórum (plano aculta_user, seções E6 e E7).
 *
 * Tudo é derivado de contagens reais de comentários publicados. Não há contador persistido.
 * Editor de conteúdo e administrador ficam isentos (papeis_isentos). Matriculados em curso ficam para a fase LMS.
 */
final class ParticipationRules {

  public const COMMENT_TYPE = 'comment_forum';

  public const TOPIC_TYPE = 'forum';

  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /** Configuração ativa das regras (valores padrão quando a configuração não existe). */
  public function settings(): array {
    return $this->configFactory->get('aculta_user.settings')->getRawData() + [
      'topico_limite_ativo' => TRUE,
      'topico_comentarios_minimos' => 10,
      'primeiro_comentario_moderado' => TRUE,
      'bloqueio_ate_aprovacao' => TRUE,
      'papeis_isentos' => ['content_editor', 'administrator'],
    ];
  }

  /** Verdadeiro para contas isentas pelo papel. */
  public function isExempt(AccountInterface $account): bool {
    $exempt = $this->settings()['papeis_isentos'];
    return array_intersect($exempt, $account->getRoles()) !== [];
  }

  /** Número de comentários de Fórum publicados pela conta. */
  public function publishedCount(int $uid): int {
    return (int) $this->commentQuery($uid, TRUE)->count()->execute();
  }

  /** Número de comentários de Fórum não publicados (fila de moderação) da conta. */
  public function pendingCount(int $uid): int {
    return (int) $this->commentQuery($uid, FALSE)->count()->execute();
  }

  /** Verdadeiro quando o primeiro comentário da conta está na fila (nenhum publicado e pelo menos um pendente). */
  public function firstCommentPending(int $uid): bool {
    return $this->publishedCount($uid) === 0 && $this->pendingCount($uid) > 0;
  }

  private function commentQuery(int $uid, bool $published) {
    return $this->entityTypeManager->getStorage('comment')->getQuery()
      ->accessCheck(FALSE)
      ->condition('comment_type', self::COMMENT_TYPE)
      ->condition('entity_type', 'node')
      ->condition('uid', $uid)
      ->condition('status', $published ? 1 : 0);
  }

}
