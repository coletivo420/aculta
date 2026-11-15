<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Utility\Html;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element\StatusMessages;
use Drupal\Core\TypedData\TypedDataManagerInterface;
use Drupal\aculta_messenger\Ajax\PrivateMessageInboxTriggerUpdateCommand;
use Drupal\aculta_messenger\Ajax\PrivateMessageLoadNewMessagesCommand;
use Drupal\aculta_messenger\Entity\PrivateMessage;
use Drupal\aculta_messenger\Entity\PrivateMessageThread;
use Drupal\aculta_messenger\Entity\PrivateMessageThreadInterface;
use Drupal\aculta_messenger\Model\BlockType;
use Drupal\aculta_messenger\Service\PrivateMessageBanManagerInterface;
use Drupal\aculta_messenger\Service\PrivateMessageServiceInterface;
use Drupal\aculta_messenger\Service\PrivateMessageThreadManagerInterface;
use Drupal\aculta_messenger\Traits\PrivateMessageSettingsTrait;
use Drupal\user\UserInterface;

/**
 * Defines the private message form.
 */
class PrivateMessageForm extends ContentEntityForm {

  use AutowireTrait;
  use PrivateMessageSettingsTrait;

  /**
   * A unique instance identifier for the form.
   */
  protected string $formId = '';

  public function __construct(
    EntityRepositoryInterface $entityRepository,
    EntityTypeBundleInfoInterface $entity_type_bundle_info,
    TimeInterface $time,
    ConfigFactoryInterface $configFactory,
    protected TypedDataManagerInterface $typedDataManager,
    protected PrivateMessageServiceInterface $privateMessageService,
    protected PrivateMessageThreadManagerInterface $privateMessageThreadManager,
    protected PrivateMessageBanManagerInterface $privateMessageBanManager,
    protected FormBuilderInterface $formBuilder,
  ) {
    parent::__construct($entityRepository, $entity_type_bundle_info, $time);
    $this->configFactory = $configFactory;
  }

  /**
   * Set the ID of the form.
   *
   * This allows for the form to be used multiple times on a page.
   *
   * @param string|int $id
   *   An ID required to be unique each time the form is called on a page.
   *
   * @return void
   */
  public function setFormId($id) {
    $this->formId = Html::escape((string) $id);
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    $form_id = parent::getFormId();

    if ($this->formId) {
      $form_id .= '-' . $this->formId;
    }

    return $form_id;
  }

  /**
   * {@inheritdoc}
   *
   * @param array<string, mixed> $form
   *
   * @return array<string, mixed>
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?PrivateMessageThreadInterface $privateMessageThread = NULL): array {
    /** @var array<string, mixed> $form */
    $form = parent::buildForm($form, $form_state);

    if ($privateMessageThread) {

      $threadMembers = $privateMessageThread->getMemberIds();
      $bannedUsers = $this->privateMessageBanManager->getBannedUsers($this->currentUser()->id());

      $banned = FALSE;
      foreach ($bannedUsers as $bannedUser) {
        if (\in_array($bannedUser, $threadMembers)) {

          if (!empty($this->getPrivateMessageSettings()->get('ban_message'))) {
            /** @var string $ban_message */
            $ban_message = $this->getPrivateMessageSettings()->get('ban_message');
            $this->messenger()
              ->addError($ban_message);

            $form['message_for_user'] = [
              StatusMessages::renderMessages(),
            ];
          }

          /** @var array<string, mixed> $actions */
          $actions = &$form['actions'];
          /** @var array<string, mixed> $submit */
          $submit = &$actions['submit'];
          /** @var array<string, mixed> $submit_attributes */
          $submit_attributes = &$submit['#attributes'];
          $submit_attributes['disabled'] = 'disabled';
          unset($form['message']);
          $banned = TRUE;
          break;
        }
      }

      // Block messaging form for active blocking mode.
      if (!$banned) {
        $ban_mode = $this->getPrivateMessageSettings()->get('ban_mode');
        if ($ban_mode === BlockType::Active) {

          foreach ($threadMembers as $threadMember) {
            $bannedUsers = $this->privateMessageBanManager->getBannedUsers($threadMember);
            if (in_array($this->currentUser()->id(), $bannedUsers)) {

              if (!empty($this->getPrivateMessageSettings()->get('ban_message'))) {
                /** @var string $ban_message */
                $ban_message = $this->getPrivateMessageSettings()->get('ban_message');
                $this->messenger()
                  ->addError($ban_message);

                $form['message_for_user'] = [
                  StatusMessages::renderMessages(),
                ];
              }

              /** @var array<string, mixed> $actions */
              $actions = &$form['actions'];
              /** @var array<string, mixed> $submit */
              $submit = &$actions['submit'];
              /** @var array<string, mixed> $submit_attributes */
              $submit_attributes = &$submit['#attributes'];
              $submit_attributes['disabled'] = 'disabled';
              unset($form['message']);
              $banned = TRUE;
              break;
            }
          }
        }
      }

      if (!$banned) {
        $form_state->set('thread', $privateMessageThread);
        /** @var array<string, mixed> $actions */
        $actions = &$form['actions'];
        /** @var array<string, mixed> $submit */
        $submit = &$actions['submit'];
        $submit['#ajax'] = [
          'callback' => '::ajaxCallback',
        ];

        // Only to do these when using #ajax.
        /** @var array<string, mixed> $attached */
        $attached = &$form['#attached'];
        /** @var array<int|string, mixed> $libraries */
        $libraries = &$attached['library'];
        $libraries[] = 'private_message/message_form';
        /** @var array<string, mixed> $drupal_settings */
        $drupal_settings = &$attached['drupalSettings'];
        $drupal_settings['privateMessageSendKey'] = $this->getPrivateMessageSettings()->get('keys_send');
        $autofocus_enabled = $this->getPrivateMessageSettings()->get('autofocus_enable');
        if ($autofocus_enabled) {
          /** @var array<string, mixed> $message_element */
          $message_element = &$form['message'];
          /** @var array<int|string, mixed> $widget */
          $widget = &$message_element['widget'];
          /** @var array<string, mixed> $widget_item */
          $widget_item = &$widget[0];
          /** @var array<string, mixed> $widget_attributes */
          $widget_attributes = &$widget_item['#attributes'];
          $widget_attributes['autofocus'] = 'autofocus';
        }
      }

    }
    else {
      // Create a dummy private message thread form so as to retrieve the
      // members element from it.
      /** @var \Drupal\aculta_messenger\Entity\PrivateMessageThreadInterface $private_message_thread */
      $private_message_thread = PrivateMessageThread::create();
      $form_copy = $form;
      $form_state_copy = clone($form_state);
      $form_display = EntityFormDisplay::collectRenderDisplay($private_message_thread, 'default');
      $form_display->buildForm($private_message_thread, $form_copy, $form_state_copy);
      // Copy the build information from the dummy form_state object.
      if (empty($form_state->get(['field_storage', '#parents', '#fields', 'members']))) {
        $storage = $form_state_copy->getStorage();
        $copy_storage = $form_state_copy->getStorage();
        /** @var array<string, mixed> $copy_field */
        $copy_field = $copy_storage['field_storage'];
        /** @var array<string, mixed> $copy_parents */
        $copy_parents = $copy_field['#parents'];
        /** @var array<string, mixed> $copy_fields */
        $copy_fields = $copy_parents['#fields'];
        /** @var array<string, mixed> $storage_field */
        $storage_field = &$storage['field_storage'];
        /** @var array<string, mixed> $storage_parents */
        $storage_parents = &$storage_field['#parents'];
        /** @var array<string, mixed> $storage_fields */
        $storage_fields = &$storage_parents['#fields'];
        $storage_fields['members'] = $copy_fields['members'];
        $form_state->setStorage($storage);
      }
      $form_state->set('pmt_form_display', $form_display);
      $form_state->set('pmt_entity', $private_message_thread);

      // Checking if the 'subject' field exists in $form_copy.
      // If present, copying it to $form['subject'].
      if (isset($form_copy['subject'])) {
        $form['subject'] = $form_copy['subject'];
      }
      $form['members'] = $form_copy['members'];

      /** @var array<int|string, mixed> $validate_callbacks */
      $validate_callbacks = &$form['#validate'];
      $validate_callbacks[] = '::validateMembers';
    }

    /** @var array<int|string, mixed> $validate_callbacks */
    $validate_callbacks = &$form['#validate'];
    $validate_callbacks[] = '::validateBannedMembers';

    if ($save_label = $this->getPrivateMessageSettings()->get('save_message_label')) {
      /** @var array<string, mixed> $actions */
      $actions = &$form['actions'];
      /** @var array<string, mixed> $submit */
      $submit = &$actions['submit'];
      $submit['#value'] = $save_label;
    }

    return $form;
  }

  /**
   * Validates members that have been added to a private message thread.
   *
   * Validates that submitted members have permission to use the Private
   * message system. This validation is not added automatically, as the members
   * field is not part of the Private Message entity, but rather something that
   * has been shoehorned in from the PrivateMessageThread entity, to make for a
   * better user experience, by creating a thread and a message in a single
   * form.
   *
   * @see \Drupal\aculta_messenger\Entity\PrivateMessageThead::baseFieldDefinitions
   * @param array<array-key, mixed> &$form
   */
  public function validateMembers(array &$form, FormStateInterface $formState): void {
    // The members form element was loaded from the PrivateMessageThread entity
    // type. As it is not a part of the PrivateMessage entity, for which this
    // form is built, the constraints that are a part of the field on the
    // Private Message Thread are not applied. As such, the constraints need to
    // be checked manually.
    // First, get the PrivateMessageThread entity type.
    $entity_type = $this->entityTypeManager->getDefinition('private_message_thread');
    // Next, load the field definitions as defined on the entity type.
    $field_definitions = PrivateMessageThread::baseFieldDefinitions($entity_type);

    /** @var \Drupal\aculta_messenger\Entity\PrivateMessageThreadInterface $entity */
    $entity = $formState->get('pmt_entity');
    /** @var \Drupal\Core\Entity\Display\EntityFormDisplayInterface $form_display */
    $form_display = $formState->get('pmt_form_display');

    /** @var array<string, mixed> $form */
    $form_display->extractFormValues($entity, $form, $formState);
    // Get the member's field, as this is the field to be validated.
    $members_field = $field_definitions['members'];

    // Retrieve any members submitted on the form.
    $members = [];
    $selectedMembers = [];
    foreach ($entity->get('members') as $referenceItem) {
      if (!$referenceItem instanceof EntityReferenceItem) {
        continue;
      }

      $referenceEntity = $referenceItem->entity ?? NULL;
      if (!$referenceEntity instanceof UserInterface) {
        continue;
      }

      $selectedMembers[] = $referenceEntity;
      if ($referenceEntity->isActive()) {
        $members[] = $referenceEntity;
      }
    }

    if ((count($members) === 1 && $this->currentUser()->id() === $members[0]->id())) {
      /** @var array<string, mixed> $members_element */
      $members_element = $form['members'];
      $formState->setError($members_element, $this->t('You can not send a message to yourself only.'));
    }

    if (count($members) <> count($selectedMembers)) {
      /** @var array<string, mixed> $members_element */
      $members_element = $form['members'];
      $formState->setError($members_element, $this->t('You can not send a message because there are inactive users selected for this thread.'));
    }

    // Get a typed data element that can be used for validation.
    $typed_data = $this->typedDataManager->create($members_field, $members);

    // Validate the submitted members.
    $violations = $typed_data->validate();

    // Check to see if any constraint violations were found.
    if ($violations->count() > 0) {
      // Output any errors for found constraint violations.
      foreach ($violations as $violation) {
        /** @var array<string, mixed> $members_element */
        $members_element = $form['members'];
        $formState->setError($members_element, $violation->getMessage());
      }
    }
  }

  /**
   * Validates that the current user isn't replying to a banned member.
   *
   * @param array<array-key, mixed> &$form
   */
  public function validateBannedMembers(array &$form, FormStateInterface $formState): void {
    /** @var \Drupal\aculta_messenger\Entity\PrivateMessageThread|null $privateMessageThread */
    $privateMessageThread = $formState->get('thread');
    $threadMembers = $privateMessageThread ? $privateMessageThread->getMemberIds() : [];
    $bannedUsers = $this->privateMessageBanManager->getBannedUsers($this->currentUser()->id());

    foreach ($bannedUsers as $bannedUser) {
      if (in_array($bannedUser, $threadMembers)) {
        /** @var array<string, mixed> $form */
        $formState->setError($form, $this->t('You cannot send a message to a blocked user.'));
        break;
      }
    }
  }

  /**
   * Ajax callback for the PrivateMessageForm.
   *
   * Re-render form after submission, so user could write new message.
   *
   * @param array<string, mixed> $form
   */
  public function ajaxCallback(array $form, FormStateInterface $formState): AjaxResponse {
    $response = new AjaxResponse();

    // Return early if there are any form validation errors.
    if ($formState->hasAnyErrors()) {
      // Make sure validation errors are displayed.
      $form['status_messages'] = [
        '#type' => 'status_messages',
        '#weight' => -1000,
      ];
      $form['#sorted'] = FALSE;

      $response->addCommand(new ReplaceCommand('.private-message-add-form', $form));
      return $response;
    }

    /** @var \Drupal\Core\Entity\EntityFormInterface $current_form_object */
    $current_form_object = $formState->getFormObject();
    /** @var \Drupal\aculta_messenger\Entity\PrivateMessageInterface $message */
    $message = $current_form_object->getEntity();

    // @todo move to message method
    $threads = $this->entityTypeManager
      ->getStorage('private_message_thread')
      ->loadByProperties(['private_messages' => $message->id()]);
    $thread = reset($threads);

    $private_message = PrivateMessage::create();

    /** @var \Drupal\aculta_messenger\Form\PrivateMessageForm $form_object */
    $form_object = $this->entityTypeManager
      ->getFormObject('private_message', 'add')
      ->setEntity($private_message);

    // We create form State manually so that Drupal won't fill it and
    // submit automatically.
    $new_form_state = new FormState();
    $new_form_state->addBuildInfo('args', [$thread]);
    $new_form_state->setUserInput([]);

    $new_form = $this->formBuilder
      ->buildForm($form_object, $new_form_state);

    /** @var array<string, mixed> $attachments */
    $attachments = $new_form['#attached'];
    $response->setAttachments($attachments);

    $response->addCommand(new ReplaceCommand('.private-message-add-form', $new_form));
    $response->addCommand(new PrivateMessageLoadNewMessagesCommand());
    $response->addCommand(new PrivateMessageInboxTriggerUpdateCommand());

    return $response;
  }

  /**
   * After build callback for the Private Message Form.
   *
   * @param array<string, mixed> $element
   *
   * @return array<string, mixed>
   */
  public function afterBuild(array $element, FormStateInterface $form_state): array {
    /** @var array<string, mixed> $message_element */
    $message_element = &$element['message'];
    /** @var array<int|string, mixed> $widget */
    $widget = &$message_element['widget'];
    /** @var array<string, mixed> $widget_item */
    $widget_item = &$widget[0];
    /** @var array<string, mixed> $format */
    $format = &$widget_item['format'];
    /** @var array<string, mixed> $format_attributes */
    $format_attributes = &$format['#attributes'];
    /** @var array<int|string, mixed> $format_classes */
    $format_classes = &$format_attributes['class'];
    $format_classes[] = 'hidden';
    return $element;
  }

  /**
   * {@inheritdoc}
   *
   * @param array<string, mixed> $form
   */
  public function save(array $form, FormStateInterface $form_state): int {
    $status = parent::save($form, $form_state);

    /** @var \Drupal\aculta_messenger\Entity\PrivateMessageThreadInterface|null $entity */
    $entity = $form_state->get('pmt_entity');

    /** @var \Drupal\aculta_messenger\Entity\PrivateMessageThreadInterface|null $private_message_thread */
    $private_message_thread = $form_state->get('thread');
    if (!$private_message_thread) {
      // Generate an array containing the members of the thread.
      $current_user = $this->entityTypeManager
        ->getStorage('user')
        ->load($this->currentUser()->id());

      $members = [];
      if ($current_user instanceof UserInterface) {
        $members[] = $current_user;
      }

      if ($entity !== NULL) {
        foreach ($entity->get('members') as $user) {
          if ($user instanceof EntityReferenceItem) {
            $user = $user->entity;
          }
          if ($user instanceof UserInterface) {
            $members[] = $user;
          }
        }
      }
      // Get a private message thread containing the given users.
      $private_message_thread = $this->privateMessageService->getThreadForMembers($members);
    }

    // Add subject to thread.
    // Only if the thread subject is empty.
    if ($private_message_thread->get('subject')->isEmpty()
      && $entity && !$entity->get('subject')->isEmpty()) {
      $private_message_thread->set('subject', $entity->get('subject')->value);
    }

    // Save the thread.
    /** @var \Drupal\aculta_messenger\Entity\PrivateMessageInterface $private_message */
    $private_message = $this->entity;
    $thread_members = [];
    $members_field = $private_message_thread->get('members');
    if ($members_field->getFieldDefinition()->getSetting('target_type') === 'user') {
      /** @var array<int, \Drupal\user\UserInterface> $thread_members */
      $thread_members = $members_field->referencedEntities();
    }
    $this->privateMessageThreadManager->saveThread($private_message, $thread_members, $private_message_thread);

    // Save the thread to the form state.
    $form_state->set('private_message_thread', $private_message_thread);

    // Send the user to the private message page. As this thread is the newest,
    // it wll be at the top of the list.
    $form_state->setRedirect('entity.private_message_thread.canonical', ['private_message_thread' => $private_message_thread->id()]);

    return $status;
  }

}
