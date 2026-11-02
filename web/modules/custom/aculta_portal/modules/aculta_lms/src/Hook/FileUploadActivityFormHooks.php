<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Hook;

use Drupal\aculta_lms\Plugin\ActivityAnswer\FileUpload;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\lms\TrainingManager;
use Symfony\Component\DependencyInjection\Attribute\AutowireCallable;

/**
 * Default file extensions on new File upload activities (ported from lms_file_upload).
 */
final class FileUploadActivityFormHooks {

  public function __construct(
    #[AutowireCallable(service: TrainingManager::class, method: 'getActivityAnswerPlugin', lazy: TRUE)]
    protected \Closure $getActivityAnswerPlugin,
  ) {}

  /**
   * Sets the default file extensions of a new activity.
   */
  #[Hook('form_lms_activity_form_alter')]
  public function formAlter(array &$form, FormStateInterface $form_state): void {
    /** @var \Drupal\Core\Entity\EntityFormInterface $form_object */
    $form_object = $form_state->getFormObject();
    $activity = $form_object->getEntity();
    if (!$activity->isNew()) {
      return;
    }

    $plugin = ($this->getActivityAnswerPlugin)($activity);
    if (!$plugin instanceof FileUpload) {
      return;
    }
    $form['field_file_extensions']['widget'][0]['value']['#default_value'] = $plugin->defaultAllowedExtensions();
  }

}
