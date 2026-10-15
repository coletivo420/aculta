<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\user\UserInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
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
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('current_user'),
      $container->get('private_message.service'),
      $container->get('aculta_messenger.private_message_form_validator'),
      $container->get('entity_type.manager'),
    );
  }

  public function getFormId(): string {
    return 'aculta_messenger_new_conversation';
  }

  public function buildForm(array $form, FormStateInterface $formState, ?int $recipientId = NULL): array {
    $recipientId ??= (int) ($formState->get('recipient') ?? 0);
    $form['#attributes'] = ['data-aculta-new-conversation-form' => ''];
    $form['recipient'] = ['#type' => 'hidden', '#value' => (string) $recipientId];
    $form['message'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Mensagem'),
      '#required' => TRUE,
      '#rows' => 4,
      '#maxlength' => 5000,
      '#attributes' => ['data-portal-ui' => 'new-conversation-message'],
    ];
    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Enviar'),
      '#attributes' => ['data-portal-ui' => 'new-conversation-submit'],
    ];
    // Cancelar: discard the draft and return to the member search. Voltar: the conversation list.
    // Link elements, not Link::toRenderable(): the drawer finds these links by their data attributes,
    // and toRenderable() drops the attributes, so the click would load the fragment as a whole page.
    $form['links'] = [
      '#type' => 'container',
      '#attributes' => ['data-portal-ui' => 'new-conversation-links'],
      'cancel' => [
        '#type' => 'link',
        '#title' => $this->t('Cancelar'),
        '#url' => Url::fromRoute('aculta_messenger.new_conversation'),
        '#attributes' => ['data-aculta-conversation-url' => Url::fromRoute('aculta_messenger.new_conversation')->toString(), 'data-portal-ui' => 'new-conversation-back'],
      ],
      'home' => [
        '#type' => 'link',
        '#title' => $this->t('Voltar para o início'),
        '#url' => Url::fromRoute('aculta_messenger.preview'),
        '#attributes' => ['data-aculta-drawer-back' => '', 'data-portal-ui' => 'new-conversation-back'],
      ],
    ];
    $form['#cache'] = ['max-age' => 0];
    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $formState): void {
    $recipient = $this->recipient($formState);
    if ($recipient === NULL || (int) $recipient->id() === (int) $this->currentUser->id()) {
      $formState->setErrorByName('recipient', $this->t('Não foi possível iniciar uma conversa com este destinatário.'));
      return;
    }
    // The same rules as the native composer, applied to a thread that is not saved yet.
    $thread = PrivateMessageThread::create(['members' => [(int) $this->currentUser->id(), (int) $recipient->id()]]);
    $formState->set('pmt_entity', $thread);
    $this->validator->validateBlockedRecipients($form, $formState);
    $this->validator->validateNewConversationPrivacy($form, $formState);
    $this->validator->validateRateLimit($form, $formState);
  }

  public function submitForm(array &$form, FormStateInterface $formState): void {
    $recipient = $this->recipient($formState);
    if ($recipient === NULL) {
      return;
    }
    // The Private Message mapper reads ->id() from each member, so the members are user entities, not IDs.
    $sender = $this->entityTypes->getStorage('user')->load($this->currentUser->id());
    $thread = $this->privateMessageService->getThreadForMembers([$sender, $recipient]);
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

  private function recipient(FormStateInterface $formState): ?UserInterface {
    $uid = (int) ($formState->getValue('recipient') ?: $formState->get('recipient') ?: 0);
    $user = $uid > 0 ? $this->entityTypes->getStorage('user')->load($uid) : NULL;
    return $user instanceof UserInterface && $user->isActive() ? $user : NULL;
  }

}
