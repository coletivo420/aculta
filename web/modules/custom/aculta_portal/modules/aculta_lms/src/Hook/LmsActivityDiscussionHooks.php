<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Hook;

use Drupal\aculta_lms\Context\LmsActivityDiscussionResolver;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\group\Entity\GroupInterface;
use Drupal\user\UserInterface;

/**
 * Adds course discussion links to ACULTA activity answer submissions.
 */
final class LmsActivityDiscussionHooks {

  public function __construct(
    private readonly LmsActivityDiscussionResolver $resolver,
    private readonly AccountProxyInterface $currentUser,
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Adds discussion links to the active learner's answer form.
   */
  #[Hook('form_aculta_lms_answer_submission_alter')]
  public function answerFormAlter(array &$form, FormStateInterface $formState): void {
    $activity = $formState->get('aculta_lms_activity');
    $course = $formState->get('aculta_lms_course');
    if (!$activity instanceof ContentEntityInterface || $activity->getEntityTypeId() !== 'aculta_lms_activity'
      || !$course instanceof GroupInterface || $course->bundle() !== 'lms_course') {
      return;
    }
    $account = $this->entityTypeManager->getStorage('user')->load($this->currentUser->id());
    if (!$account instanceof UserInterface || !$account->isActive()
      || (int) $account->id() !== (int) $this->currentUser->id()) {
      return;
    }
    $discussions = $this->resolver->build($activity, $course, $account);
    if (!empty($discussions['links']['#items'])) {
      $form['aculta_forum_lms_activity_discussions'] = $discussions + ['#weight' => 100];
    }
  }

}
