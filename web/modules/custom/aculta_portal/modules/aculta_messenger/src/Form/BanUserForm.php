<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Form;

use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\aculta_messenger\Service\PrivateMessageBanManagerInterface;

/**
 * Private Message users banning form.
 */
class BanUserForm extends FormBase {

  use AutowireTrait;

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected PrivateMessageBanManagerInterface $privateMessageBanManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'block_user_form';
  }

  /**
   * {@inheritdoc}
   *
   * @param array<string, mixed> $form
   *
   * @return array<string, mixed>
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('private_message.settings');

    $form['banned_user'] = [
      '#title' => $this->t('Select User'),
      '#required' => TRUE,
      '#type' => 'entity_autocomplete',
      '#target_type' => 'user',
      '#tags' => FALSE,
      '#selection_handler' => 'default:user',
      '#selection_settings' => [
        'include_anonymous' => FALSE,
        // @see \private_message_entity_query_user_alter()
        'private_message' => [
          'active_users_selection' => TRUE,
        ],
      ],
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $config->get('ban_label'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   *
   * @param array<string, mixed> $form
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    /** @var int|string $userId */
    $userId = $form_state->getValue('banned_user');
    // Add security to prevent blocking ourselves.
    if ($userId === $this->currentUser()->id()) {
      $form_state->setErrorByName((string) $userId, $this->t("You can't block yourself."));
    }

    // Add a security if the user id is unknown.
    if (empty($userId) ||
      empty($this->entityTypeManager->getStorage('user')->load($userId))) {
      $form_state->setErrorByName((string) $userId, $this->t('The user id is unknown.'));
    }

    if (!empty($userId) && $this->privateMessageBanManager->isBanned($userId)) {
      $form_state->setErrorByName((string) $userId, $this->t('The user is already blocked.'));
    }
  }

  /**
   * {@inheritdoc}
   *
   * @param array<string, mixed> $form
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    /** @var int|string $userId */
    $userId = $form_state->getValue('banned_user');
    $this->privateMessageBanManager->banUser($userId);
    /** @var \Drupal\user\UserInterface $bannedUser */
    $bannedUser = $this->entityTypeManager->getStorage('user')->load($userId);
    $this->messenger()->addStatus($this->t('The user %name has been banned.', [
      '%name' => $bannedUser->getDisplayName(),
    ]));
  }

}
