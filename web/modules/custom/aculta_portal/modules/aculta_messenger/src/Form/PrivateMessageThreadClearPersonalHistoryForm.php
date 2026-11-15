<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Form;

use Drupal\Core\Entity\ContentEntityConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;

/**
 * Form definition for the private message clear personal history form.
 *
 * @method \Drupal\aculta_messenger\Entity\PrivateMessageThreadInterface getEntity()
 */
class PrivateMessageThreadClearPersonalHistoryForm extends ContentEntityConfirmFormBase {

  /**
   * {@inheritdoc}
   */
  public function getQuestion(): TranslatableMarkup {
    $members = $this->getEntity()->getMembers();
    $member_names = [];
    foreach ($members as $member) {
      if ($member->id() == $this->currentUser()->id()) {
        continue;
      }
      $member_names[] = $member->getDisplayName();
    }
    return $this->t('Are you sure you want to clear chat history between you and the following users: @others?', ['@others' => implode(', ', $member_names)]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl(): Url {
    return Url::fromRoute('entity.private_message_thread.canonical', ['private_message_thread' => $this->getEntity()->id()]);
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText(): TranslatableMarkup {
    return $this->t('Clear history');
  }

  /**
   * {@inheritdoc}
   * @param array<string, mixed> $form
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    /** @var array<string, mixed> $form */
    parent::submitForm($form, $form_state);

    $this->getEntity()->clearAccountHistory($this->currentUser());
    $form_state->setRedirect('private_message.private_message_page');
  }

}
