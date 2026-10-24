<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Hook;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Url;
use Drupal\aculta_messenger\Form\ConversationRelationshipForm;
use Drupal\aculta_messenger\Service\ConversationMessagingState;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\private_message\Entity\PrivateMessageThreadInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/** Keeps the native Private Message reply form available in thread views. */
final class PrivateMessageReplyHooks {

  public function __construct(
    #[Autowire(service: 'entity_type.manager')]
    private readonly EntityTypeManagerInterface $entityTypeManager,
    #[Autowire(service: 'form_builder')]
    private readonly FormBuilderInterface $formBuilder,
    #[Autowire(service: 'current_user')]
    private readonly AccountInterface $currentUser,
    #[Autowire(service: 'aculta_portal.domain_purpose')]
    private readonly DomainPurposeManager $domainPurposeManager,
    #[Autowire(service: 'aculta_messenger.conversation_messaging')]
    private readonly ConversationMessagingState $messagingState,
  ) {}

  /** Adds the native composer when a site's thread display omitted its field. */
  #[Hook('entity_view')]
  public function entityView(array &$build, EntityInterface $entity, EntityViewDisplayInterface $display, string $view_mode): void {
    if (!$entity instanceof PrivateMessageThreadInterface
      || $view_mode !== 'full'
      || !$this->currentUser->isAuthenticated()
      || !$this->currentUser->hasPermission('use private messaging system')
      || !$this->currentUser->hasPermission('access user profiles')
      || !$entity->isMember($this->currentUser->id())) {
      return;
    }

    $access = $entity->access('view', $this->currentUser, TRUE);
    if (!$access->isAllowed()) {
      return;
    }

    // The "← Todas as conversas" link is placed by MessagesController::drawerConversation, above the thread.
    $build['#cache']['contexts'][] = 'user';
    $build['#cache']['max-age'] = 0;

    // A blocked conversation keeps its history and shows a notice in place of the reply box.
    $messagingState = $this->messagingState->stateFor($entity);
    if ($messagingState !== ConversationMessagingState::OPEN) {
      $build['aculta_messenger_notice'] = $this->notice($entity, $messagingState);
      return;
    }

    if (isset($build['private_message_form'])) {
      return;
    }

    $message = $this->entityTypeManager->getStorage('private_message')->create();
    $formObject = $this->entityTypeManager
      ->getFormObject('private_message', 'add')
      ->setEntity($message);
    $replyForm = $this->formBuilder->getForm($formObject, $entity);
    $replyForm['#attributes']['id'] = 'aculta-messenger-reply-form';
    $replyForm['#attributes']['data-aculta-reply-form'] = '';
    // The drawer posts the reply to its own route, so the conversation comes back in the drawer.
    $replyForm['#action'] = Url::fromRoute('aculta_messenger.drawer_reply', ['private_message_thread' => $entity->id()])->toString();

    // The native conversation history is rendered after the entity fields.
    // Keep the composer after that history in the account page and drawer.
    $replyForm['#weight'] = 10000;
    $build['aculta_messenger_reply_form'] = $replyForm;
  }

  /**
   * Adjusts the thread view that Private Message renders in the drawer.
   *
   * Alter, not view: the module adds its link in hook_entity_view(), and the order between modules is not ours.
   */
  #[Hook('entity_view_alter')]
  public function entityViewAlter(array &$build, EntityInterface $entity, EntityViewDisplayInterface $display): void {
    if (!$entity instanceof PrivateMessageThreadInterface || $display->getMode() !== 'full') {
      return;
    }
    // "Limpar histórico" moved into the conversation's "Opções" (MessagesController::optionsDisclosure).
    unset($build['clear_history_link']);
  }

  /**
   * The members field label ("Member(s)") is a base field label: it is set on the field definition, so the
   * thread view and its forms show the same Portuguese name.
   */
  #[Hook('entity_base_field_info_alter')]
  public function entityBaseFieldInfoAlter(array &$fields, EntityTypeInterface $entity_type): void {
    if ($entity_type->id() === 'private_message_thread' && isset($fields['members'])) {
      $fields['members']->setLabel(new TranslatableMarkup('Participantes'));
    }
  }

  /**
   * The notice that replaces the reply box: the state's sentence, and the one action it allows (if any).
   */
  private function notice(PrivateMessageThreadInterface $thread, string $state): array {
    $other = $this->messagingState->otherMember($thread);
    $notice = ['#type' => 'container', '#attributes' => ['data-portal-ui' => 'messenger-notice', 'role' => 'status']];
    $text = match (TRUE) {
      $state === ConversationMessagingState::BLOCKED_BY_ME => 'Você bloqueou essa pessoa, ',
      $state === ConversationMessagingState::BLOCKED_BY_THEM => 'Essa pessoa bloqueou você, por isso não pode enviar ou receber mensagens.',
      default => 'Não é possível enviar mensagens nesta conversa.',
    };
    $notice['text'] = ['#plain_text' => $text];
    $needsAction = $other !== NULL && $state === ConversationMessagingState::BLOCKED_BY_ME;
    if ($needsAction) {
      $formState = new FormState();
      $formState->set('target', $other);
      $formState->set('post_url', Url::fromRoute('aculta_messenger.drawer_relationship', ['private_message_thread' => $thread->id()])->toString());
      $formState->set('mode', 'notice');
      $notice['action'] = $this->formBuilder->buildForm(ConversationRelationshipForm::class, $formState);
    }
    return $notice;
  }

}
