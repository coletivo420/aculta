<?php

declare(strict_types=1);

namespace Drupal\aculta_lms;

use Drupal\comment\CommentInterface;
use Drupal\lms\Entity\AnswerInterface;
use Drupal\lms\Entity\Bundle\CourseInterface;
use Drupal\lms\Entity\CourseStatusInterface;
use Drupal\lms\Entity\LessonStatusInterface;
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
   * Confirma que o autor do comentário é educador, não o dono da resposta.
   */
  public function isEducator(CommentInterface $comment): bool {
    $answer = $comment->getCommentedEntity();
    $author = $comment->getOwner();
    if (!$answer instanceof AnswerInterface || !$author instanceof UserInterface) {
      return FALSE;
    }
    if ((string) $answer->getUserId() === (string) $author->id()) {
      return FALSE;
    }
    $course = $this->courseForAnswer($answer);
    return $course instanceof CourseInterface
      && $course->hasPermission('grade students', $author);
  }

  /**
   * Resolve o curso atual sem getters tipados em referências LMS antigas.
   */
  public function courseForAnswer(AnswerInterface $answer): ?CourseInterface {
    $lessonStatus = $answer->get('lesson_status')->entity;
    if (!$lessonStatus instanceof LessonStatusInterface) {
      return NULL;
    }
    $courseStatus = $lessonStatus->get('course_status')->entity;
    if (!$courseStatus instanceof CourseStatusInterface) {
      return NULL;
    }
    $course = $courseStatus->get(CourseStatusInterface::COURSE_FIELD)->entity;
    return $course instanceof CourseInterface && $course->bundle() === 'lms_course'
      ? $course
      : NULL;
  }

}
