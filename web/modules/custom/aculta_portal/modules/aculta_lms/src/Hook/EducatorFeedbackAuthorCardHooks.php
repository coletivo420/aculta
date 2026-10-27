<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Hook;

use Drupal\aculta_lms\EducatorFeedbackPolicy;
use Drupal\aculta_user\Identity\AuthorCardBuilder;
use Drupal\comment\CommentInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\lms\Entity\AnswerInterface;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Mostra o cartão do autor no feedback publicado sobre uma resposta.
 *
 * O cartão usa a identidade pública de aculta_user. O rótulo "Educador(a)" é o
 * papel contextual, não um dado do perfil, e só aparece quando o autor é de
 * fato educador do curso; o aluno dono da resposta continua com o cartão
 * comum, sem o rótulo. Sem perfil público, o cartão usa nome + acervo.
 */
final class EducatorFeedbackAuthorCardHooks {

  public function __construct(
    #[Autowire(service: 'aculta_user.author_card_builder')]
    private readonly AuthorCardBuilder $cardBuilder,
    #[Autowire(service: 'aculta_lms.educator_feedback_policy')]
    private readonly EducatorFeedbackPolicy $policy,
  ) {}

  /**
   * Implements hook_entity_view() for LMS answer feedback comments.
   */
  #[Hook('entity_view')]
  public function entityView(array &$build, EntityInterface $entity, EntityViewDisplayInterface $display, string $view_mode): void {
    if ($entity->getEntityTypeId() !== 'comment' || $entity->bundle() !== 'lms_answer_comment' || !$entity instanceof CommentInterface) {
      return;
    }
    $author = $entity->getOwner();
    if (!$author instanceof UserInterface) {
      return;
    }
    $isEducator = $this->policy->isEducator($entity);
    $designation = $isEducator ? (string) new TranslatableMarkup('Educador(a)') : NULL;
    $card = $this->cardBuilder->build($author, 'profile_compact', [], $designation, 'sm')
      ?? $this->cardBuilder->buildFallback($author, 'sm', $designation);
    $card['#weight'] = -20;
    $build['author_card'] = $card;

    $cacheability = new CacheableMetadata();
    $answer = $entity->getCommentedEntity();
    if ($answer instanceof EntityInterface) {
      $cacheability->addCacheableDependency($answer);
    }
    if ($answer instanceof AnswerInterface) {
      $course = $this->policy->courseForAnswer($answer);
      if ($course !== NULL) {
        $cacheability->addCacheableDependency($course);
        $cacheability->addCacheTags(['group_relationship_list:plugin:group_membership:group:' . $course->id()]);
      }
    }
    $cacheability->applyTo($build);
  }

}
