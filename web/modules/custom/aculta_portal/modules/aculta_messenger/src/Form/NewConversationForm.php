<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\user\UserInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\aculta_portal\Render\PortalLinkElement;
use Drupal\Core\Url;
use Drupal\aculta_messenger\Controller\NewConversationController;
use Drupal\aculta_messenger\Service\ConversationPolicy;
use Drupal\private_message\Entity\PrivateMessage;
use Drupal\private_message\Entity\PrivateMessageThread;
use Drupal\private_message\Service\PrivateMessageServiceInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * First message of a new conversation, written inside the multidomain bar drawer.
 *
 * Reuses the send rules of the Messenger (block, friends-only preference, rate limit) and the Private
 * Message thread API. An existing conversation between the two members is reused.
 */
final class NewConversationForm extends FormBase {

  public function __construct(
    private readonly AccountProxyInterface $currentUser,
    private readonly PrivateMessageServiceInterface $privateMessageService,
    private readonly PrivateMessageFormValidator $validator,
    private readonly EntityTypeManagerInterface $entityTypes,
    private readonly ConversationPolicy $policy,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('current_user'),
      $container->get('private_message.service'),
      $container->get('aculta_messenger.private_message_form_validator'),
      $container->get('entity_type.manager'),
      $container->get('aculta_messenger.conversation_policy'),
    );
  }

  public function getFormId(): string {
    return 'aculta_messenger_new_conversation';
  }

  /**
   * One form for the whole new-conversation step: the member search with the chosen members, the first message and the
   * actions. Without a fixed member (the search step) the members come from the chips; with one (the profile's
   * "Iniciar conversa") the form is only the message.
   */
  public function buildForm(array $form, FormStateInterface $formState): array {
    $fixed = $formState->get('recipient') !== NULL;
    $limit = $this->policy->maxRecipients(ConversationPolicy::KIND_GROUP, $this->currentUser);
    $form['#attributes'] = ['data-aculta-new-conversation-form' => ''];
    if (!$fixed) {
      // The drawer reads the search field and the chips (messenger-drawers.js); the form posts to the group route.
      $form['#action'] = Url::fromRoute('aculta_messenger.new_conversation_group')->toString();
      $form['#attributes'] += [
        'data-aculta-new-conversation' => '',
        'data-aculta-group-max' => (string) $limit,
        'aria-label' => (string) $this->t('Nova conversa'),
      ];
      $form['chips'] = [
        '#type' => 'container',
        '#attributes' => ['data-aculta-chips' => '', 'data-portal-ui' => 'new-conversation-chips', 'aria-live' => 'polite'],
      ];
      foreach ($this->recipients($formState, FALSE) as $index => $user) {
        $form['chips']['chip_' . $index] = $this->chip($user);
      }
      $form['q'] = [
        '#type' => 'search',
        '#name' => 'q',
        '#title' => (string) $this->t('Buscar membro'),
        '#title_display' => 'invisible',
        '#placeholder' => (string) $this->t('Buscar membro pelo nome'),
        '#maxlength' => 100,
        '#weight' => -10,
        '#attributes' => [
          'autocomplete' => 'off',
          'data-portal-ui' => 'search-preview-input',
          'data-preview-url' => Url::fromRoute('aculta_messenger.new_conversation_search')->toString(),
          'data-preview-target' => 'aculta-new-conversation-preview',
          'data-min-chars' => (string) NewConversationController::MIN_CHARS,
        ],
      ];
      $form['preview'] = [
        '#type' => 'container',
        '#weight' => -9,
        '#attributes' => [
          'id' => 'aculta-new-conversation-preview',
          'data-portal-ui' => 'search-preview',
          'role' => 'region',
          'aria-label' => $this->t('Resultados de membros'),
          'aria-live' => 'polite',
        ],
        'hint' => ['#plain_text' => $this->t('Digite pelo menos @n caracteres para ver resultados.', ['@n' => NewConversationController::MIN_CHARS])],
      ];
    }
    else {
      foreach ($this->recipientIds($formState) as $index => $uid) {
        $form['members_' . $index] = ['#type' => 'hidden', '#name' => 'members[]', '#value' => (string) $uid];
      }
    }
    $form['message'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Mensagem'),
      '#rows' => 4,
      '#maxlength' => 5000,
      '#weight' => 10,
      '#attributes' => ['data-portal-ui' => 'new-conversation-message'],
    ];
    // Cancelar: discard the draft and return to the member search. Voltar: the conversation list.
    // PortalLinkElement, not #type link: the theme maps data-portal-ui only on elements with a theme template, so each
    // one takes its own button colour. The drawer finds them by their data attributes, which the helper keeps.
    $cancelUrl = Url::fromRoute('aculta_messenger.new_conversation')->toString();
    $form['links'] = [
      '#type' => 'container',
      '#weight' => 20,
      '#attributes' => ['data-portal-ui' => 'new-conversation-links'],
      'cancel' => PortalLinkElement::build($this->t('Cancelar'), Url::fromRoute('aculta_messenger.new_conversation'), ['data-aculta-conversation-url' => $cancelUrl, 'data-portal-ui' => 'new-conversation-cancel']),
      'home' => PortalLinkElement::build($this->t('Voltar para o início'), Url::fromRoute('aculta_messenger.preview'), ['data-aculta-drawer-back' => '', 'data-portal-ui' => 'new-conversation-back']),
    ];
    $form['actions'] = ['#type' => 'actions', '#weight' => 30];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Enviar'),
      '#attributes' => ['data-portal-ui' => 'new-conversation-submit'],
    ];
    $form['#cache'] = ['max-age' => 0];
    return $form;
  }

  /** One chip: the member's name, a "×" to take it off, and the hidden field the send reads (members[]). */
  private function chip(UserInterface $user): array {
    return [
      '#type' => 'container',
      '#attributes' => ['data-aculta-chip' => '', 'data-portal-ui' => 'new-conversation-chip'],
      'name' => ['#type' => 'html_tag', '#tag' => 'span', '#value' => $user->getDisplayName(), '#attributes' => ['data-aculta-chip-name' => '', 'data-portal-ui' => 'new-conversation-chip-name']],
      'remove' => [
        '#type' => 'html_tag',
        '#tag' => 'button',
        '#value' => '×',
        '#attributes' => [
          'type' => 'button',
          'data-aculta-chip-remove' => '',
          'data-portal-ui' => 'new-conversation-chip-remove',
          'aria-label' => (string) $this->t('Remover @name', ['@name' => $user->getDisplayName()]),
        ],
      ],
      'member' => ['#type' => 'hidden', '#name' => 'members[]', '#value' => (string) $user->id()],
    ];
  }

  public function validateForm(array &$form, FormStateInterface $formState): void {
    if (trim((string) $formState->getValue('message')) === '') {
      $formState->setErrorByName('message', $this->t('Escreva a mensagem antes de enviar.'));
    }
    $recipients = $this->recipients($formState);
    $limit = $this->policy->maxRecipients(ConversationPolicy::KIND_GROUP, $this->currentUser);
    if ($recipients === [] || count($recipients) > $limit) {
      $formState->setErrorByName('members', $this->t('Escolha de 1 a @n pessoas.', ['@n' => $limit]));
      return;
    }
    foreach ($recipients as $recipient) {
      if ((int) $recipient->id() === (int) $this->currentUser->id()) {
        $formState->setErrorByName('members', $this->t('Não foi possível iniciar uma conversa com este destinatário.'));
        return;
      }
    }
    // The same rules as the native composer, applied to a thread that is not saved yet: every member is checked.
    $members = [(int) $this->currentUser->id()];
    foreach ($recipients as $recipient) {
      $members[] = (int) $recipient->id();
    }
    $thread = PrivateMessageThread::create(['members' => $members]);
    $formState->set('pmt_entity', $thread);
    $this->validator->validateBlockedRecipients($form, $formState);
    $this->validator->validateNewConversationPrivacy($form, $formState);
    $this->validator->validateRateLimit($form, $formState);
  }

  public function submitForm(array &$form, FormStateInterface $formState): void {
    $recipients = $this->recipients($formState);
    if ($recipients === []) {
      return;
    }
    // The Private Message mapper reads ->id() from each member, so the members are user entities, not IDs.
    $sender = $this->entityTypes->getStorage('user')->load($this->currentUser->id());
    $thread = $this->privateMessageService->getThreadForMembers(array_merge([$sender], $recipients));
    $message = PrivateMessage::create([
      'owner' => (int) $this->currentUser->id(),
      'message' => ['value' => (string) $formState->getValue('message'), 'format' => 'plain_text'],
    ]);
    $message->save();
    $thread->addMessage($message);
    $thread->save();
    $formState->set('thread', $thread);
    // The drawer receives the conversation as the answer to its POST (NewConversationController::compose).
    // A Form API redirect would send the drawer back to the compose step with a 303.
    $formState->disableRedirect();
  }

  /** The ids of the conversation: the fixed member of the profile's route, else the chips posted with the form. */
  private function recipientIds(FormStateInterface $formState): array {
    $single = $formState->get('recipient');
    if ($single !== NULL) {
      return [(int) $single];
    }
    $posted = $formState->getUserInput()['members'] ?? [];
    $ids = is_array($posted) ? array_map('intval', array_filter($posted, 'is_scalar')) : [];
    return array_values(array_unique($ids));
  }

  /**
   * The members the conversation will have besides the author. For the send, a member that does not exist or is inactive
   * makes the list incomplete, which validation refuses. For the chips ($strict FALSE) the existing ones are shown.
   *
   * @return \Drupal\user\UserInterface[]
   */
  private function recipients(FormStateInterface $formState, bool $strict = TRUE): array {
    $recipients = [];
    foreach ($this->recipientIds($formState) as $uid) {
      $user = $uid > 0 ? $this->entityTypes->getStorage('user')->load($uid) : NULL;
      if (!$user instanceof UserInterface || !$user->isActive()) {
        if ($strict) {
          return [];
        }
        continue;
      }
      $recipients[] = $user;
    }
    return $recipients;
  }

}
