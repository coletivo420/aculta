<?php

declare(strict_types=1);

namespace Drupal\aculta_lms;

use Drupal\comment\CommentInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\group\Entity\GroupInterface;
use Drupal\aculta_lms\Notice\LearningEligibilityInterface;
use Drupal\user\UserInterface;

/**
 * Define o que é feedback de educador em uma resposta do LMS.
 *
 * O tipo `lms_answer_comment` aceita comentários tanto do aluno dono da
 * resposta quanto de educadores do curso. Só o educador recebe o papel
 * contextual "Educador(a)" e dispara a notificação de feedback; a apresentação
 * e o evento usam o mesmo critério.
 */
final class EducatorFeedbackPolicy {

  /**
   * Comment type owned by ACULTA answer entities. */
  public const COMMENT_TYPE = 'aculta_lms_answer_comment';

  public function __construct(
    private readonly LearningEligibilityInterface $eligibility,
  ) {}

  /**
   * Confirma que o autor do comentário é educador, não o dono da resposta.
   */
  public function isEducator(CommentInterface $comment): bool {
    $answer = $comment->getCommentedEntity();
    $author = $comment->getOwner();
    if (!$answer instanceof EntityInterface || $answer->getEntityTypeId() !== 'aculta_lms_answer' || !$author instanceof UserInterface) {
      return FALSE;
    }
    if ((string) $answer->get('uid')->value === (string) $author->id()) {
      return FALSE;
    }
    $course = $this->courseForAnswer($answer);
    return $course instanceof GroupInterface
      && $course->hasPermission('grade students', $author);
  }

  /**
   * Resolve o curso atual sem getters tipados em referências LMS antigas.
   */
  public function courseForAnswer(EntityInterface $answer): ?GroupInterface {
    return $this->eligibility->courseForAnswer($answer);
  }

}
