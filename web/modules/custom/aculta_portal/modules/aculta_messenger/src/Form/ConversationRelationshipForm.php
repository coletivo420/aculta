<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Form;

use Drupal\aculta_user\Social\FriendshipManagerInterface;
use Drupal\aculta_user\Social\FriendshipStatus;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\private_message\Service\PrivateMessageBanManagerInterface;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * "Opções" of a conversation in the multidomain bar drawer: friendship and block with the other member.
 *
 * Each button is a submit of this form. The drawer posts it with fetch and shows the conversation again, with
 * the new state, so the drawer never leaves the page. The actions reuse the friendship and block services.
 */
final class ConversationRelationshipForm extends FormBase {

  public function __construct(
    private readonly FriendshipManagerInterface $friendshipManager,
    private readonly PrivateMessageBanManagerInterface $banManager,
    private readonly AccountProxyInterface $currentUser,
    private readonly EntityTypeManagerInterface $entityTypes,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('aculta_user.friendship_manager'),
      $container->get('private_message.ban_manager'),
      $container->get('current_user'),
      $container->get('entity_type.manager'),
    );
  }

  public function getFormId(): string {
    return 'aculta_messenger_conversation_relationship';
  }

  public function buildForm(array $form, FormStateInterface $formState, ?int $targetUid = NULL, ?string $postUrl = NULL): array {
    $targetUid ??= (int) ($formState->get('target') ?? 0);
    $postUrl ??= $formState->get('post_url');
    $target = $this->target($targetUid);
    if ($target === NULL) {
      return [];
    }
    $formState->set('target', (int) $target->id());
    $form['#attributes'] = ['data-aculta-drawer-action' => '', 'class' => ['aculta-messenger-relationship']];
    if ($postUrl !== NULL) {
      // The drawer POSTs to the thread's relationship route, not to the GET route the form was built on.
      $form['#action'] = $postUrl;
    }
    $form['#cache'] = ['contexts' => ['user'], 'max-age' => 0];
    $form['actions'] = ['#type' => 'actions'];

    // Notice mode: the one link that answers a blocked or friends-only conversation, shown in place of the reply box.
    if ($formState->get('mode') === 'notice') {
      $noticeTarget = (int) $target->id();
      if ($this->banManager->isBanned($noticeTarget)) {
        $form['actions']['unban'] = $this->button('unban', $this->t('Clique aqui para desbloquear'), 'conversation-notice-action');
      }
      elseif (!$this->friendshipManager->areFriendsWith($noticeTarget)
        && $this->friendshipManager->getRelationshipWith($noticeTarget)?->getStatus() !== FriendshipStatus::Pending->value) {
        $form['actions']['request'] = $this->button('request', $this->t('clique aqui para enviar um pedido de Amizade.'), 'conversation-notice-action');
      }
      return $form;
    }

    if ($formState->get('message')) {
      $form['message'] = ['#plain_text' => (string) $formState->get('message'), '#prefix' => '<p role="status" class="aculta-messenger-options__status">', '#suffix' => '</p>'];
    }

    $targetId = (int) $target->id();
    if ($this->friendshipManager->areFriendsWith($targetId)) {
      $form['actions']['remove'] = $this->button('remove', $this->t('Desfazer amizade'));
    }
    // Only a pending request blocks a new one: a removed or declined relationship is still a row, but not a request.
    elseif (($pending = $this->friendshipManager->getRelationshipWith($targetId)) === NULL || $pending->getStatus() !== FriendshipStatus::Pending->value) {
      $form['actions']['request'] = $this->button('request', $this->t('Adicionar amigo'));
    }
    // A request this member sent can be cancelled here. An incoming request is not cancelled from this form.
    elseif ((int) $pending->getRequesterId() === (int) $this->currentUser->id()) {
      $form['actions']['cancel'] = $this->button('cancel', $this->t('Cancelar pedido de amizade'));
    }
    // The member who received the request accepts or rejects it here.
    else {
      $form['actions']['accept'] = $this->button('accept', $this->t('Aceitar pedido de amizade'));
      $form['actions']['decline'] = $this->button('decline', $this->t('Rejeitar pedido de amizade'));
    }

    // Menu order: friendship actions, then "Limpar histórico" (passed in by the controller), then the block last.
    $clear = $formState->get('clear');
    if (is_array($clear)) {
      $form['actions']['clear'] = $clear + ['#weight' => 5];
    }
    if ($this->banManager->isBanned($targetId)) {
      $form['actions']['unban'] = $this->button('unban', $this->t('Desbloquear')) + ['#weight' => 10];
    }
    else {
      $form['actions']['ban'] = $this->button('ban', $this->t('Bloquear')) + ['#weight' => 10];
    }
    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $formState): void {
    $target = $this->target((int) $formState->get('target'));
    $formState->disableRedirect();
    // No rebuild: a rebuilt form comes back from the form cache without its constructor dependencies. The
    // controller builds the updated panel again, from a new state, after this submit has run.
    if ($target === NULL) {
      $formState->set('message', (string) $this->t('Não foi possível atualizar esse relacionamento.'));
      return;
    }
    $targetId = (int) $target->id();
    // Form API falls back to the first button when the posted button is missing. An action runs only when the
    // button it names was actually posted, so a request without it can never cancel or accept anything.
    $action = (string) ($formState->getTriggeringElement()['#name'] ?? '');
    if ($action === '' || !array_key_exists($action, (array) $formState->getUserInput())) {
      $formState->set('message', (string) $this->t('Não foi possível concluir a ação. Tente novamente.'));
      return;
    }
    try {
      switch ($action) {
        case 'request':
          $this->friendshipManager->request($target);
          break;

        case 'remove':
          $relationship = $this->friendshipManager->getRelationshipWith($targetId);
          if ($relationship === NULL) {
            throw new \DomainException('This relationship no longer exists.');
          }
          $this->friendshipManager->remove($relationship);
          break;

        case 'cancel':
          $relationship = $this->friendshipManager->getRelationshipWith($targetId);
          if ($relationship === NULL) {
            throw new \DomainException('This relationship no longer exists.');
          }
          $this->friendshipManager->cancel($relationship);
          break;

        case 'accept':
          $relationship = $this->friendshipManager->getRelationshipWith($targetId);
          if ($relationship === NULL) {
            throw new \DomainException('This relationship no longer exists.');
          }
          $this->friendshipManager->accept($relationship);
          break;

        case 'decline':
          $relationship = $this->friendshipManager->getRelationshipWith($targetId);
          if ($relationship === NULL) {
            throw new \DomainException('This relationship no longer exists.');
          }
          $this->friendshipManager->decline($relationship);
          break;

        case 'ban':
          $this->banManager->banUser($targetId);
          break;

        case 'unban':
          $this->banManager->unbanUser($targetId);
          break;

        default:
          throw new \DomainException('Unknown conversation action.');
      }
      $formState->set('message', (string) $this->t('Atualizado.'));
    }
    catch (\DomainException|\RuntimeException|\InvalidArgumentException) {
      $formState->set('message', (string) $this->t('Não foi possível concluir a ação. Tente novamente.'));
    }
  }

  /** Only another active member can be the target: never the current account, never an anonymous or blocked one. */
  private function target(int $uid): ?UserInterface {
    if ($uid <= 0 || $uid === (int) $this->currentUser->id() || !$this->currentUser->isAuthenticated()) {
      return NULL;
    }
    $user = $this->entityTypes->getStorage('user')->load($uid);
    return $user instanceof UserInterface && $user->isActive() ? $user : NULL;
  }

  private function button(string $name, $label, string $token = 'conversation-action'): array {
    return [
      '#type' => 'submit',
      '#name' => $name,
      '#value' => $label,
      '#attributes' => ['data-portal-ui' => $token],
    ];
  }

}
