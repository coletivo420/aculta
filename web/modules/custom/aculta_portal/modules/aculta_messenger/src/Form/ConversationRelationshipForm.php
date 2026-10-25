<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Form;

use Drupal\aculta_messenger\Support\ConfirmDialog;
use Drupal\aculta_user\Social\FriendshipManagerInterface;
use Drupal\aculta_user\Social\FriendshipStatus;
use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Template\Attribute;
use Drupal\Core\Url;
use Drupal\private_message\Entity\PrivateMessageThreadInterface;
use Drupal\private_message\Service\PrivateMessageBanManagerInterface;
use Drupal\private_message\Service\PrivateMessageServiceInterface;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * "Opções" of a conversation in the multidomain bar drawer, and of a public profile.
 *
 * A member's box: friendship and block with that member only (state "target", flag "member_box"). Friendship and block
 * are per person, so a conversation with several members has one box for each of them, and never acts on the thread.
 * The conversation's menu ("group" state): only "Limpar histórico", for the current member. A block notice in a
 * one-to-one conversation uses "target" as well.
 *
 * Each button is a submit of this form. The drawer posts it with fetch and shows the conversation again, with the new
 * state; the profile posts it natively and goes back to the profile. Destructive actions ask first in a dialog.
 */
final class ConversationRelationshipForm extends FormBase {

  public function __construct(
    private readonly FriendshipManagerInterface $friendshipManager,
    private readonly PrivateMessageBanManagerInterface $banManager,
    private readonly AccountProxyInterface $currentUser,
    private readonly EntityTypeManagerInterface $entityTypes,
    private readonly PrivateMessageServiceInterface $privateMessageService,
    private readonly CacheTagsInvalidatorInterface $cacheTagsInvalidator,
    private readonly CsrfTokenGenerator $csrfToken,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('aculta_user.friendship_manager'),
      $container->get('private_message.ban_manager'),
      $container->get('current_user'),
      $container->get('entity_type.manager'),
      $container->get('private_message.service'),
      $container->get('cache_tags.invalidator'),
      $container->get('csrf_token'),
    );
  }

  public function getFormId(): string {
    return 'aculta_messenger_conversation_relationship';
  }

  public function buildForm(array $form, FormStateInterface $formState, ?int $targetUid = NULL, ?string $postUrl = NULL): array {
    $postUrl ??= $formState->get('post_url');
    if ($formState->get('group') === TRUE) {
      return $this->groupForm($formState, $postUrl);
    }
    $targetUid ??= (int) ($formState->get('target') ?? 0);
    $target = $this->target($targetUid);
    if ($target === NULL) {
      return [];
    }
    $formState->set('target', (int) $target->id());
    // The form carries the drawer marker only: the styles and the script select it by this attribute.
    $form['#attributes'] = ['data-aculta-drawer-action' => ''];
    if ($postUrl !== NULL) {
      // The drawer POSTs to the thread's relationship route, not to the GET route the form was built on.
      $form['#action'] = $this->actionWithSessionToken($postUrl);
    }
    $form['#cache'] = ['contexts' => ['user'], 'max-age' => 0];
    $form['actions'] = ['#type' => 'actions'];

    // Notice mode exposes the unblock action when this member blocked the other participant.
    if ($formState->get('mode') === 'notice') {
      // The hidden mode tells the POST answer which buttons the notice posted (their labels differ from the menu's).
      $form['mode'] = ['#type' => 'hidden', '#value' => 'notice'];
      $noticeTarget = (int) $target->id();
      if ($this->banManager->isBanned($noticeTarget)) {
        $form['actions']['unban'] = $this->button('unban', $this->t('Clique aqui para desbloquear'), 'conversation-notice-action');
      }
      return $form;
    }

    if ($formState->get('message')) {
      $form['message'] = $this->statusLine((string) $formState->get('message'));
    }

    $targetId = (int) $target->id();
    if ($this->friendshipManager->areFriendsWith($targetId)) {
      $form = ConfirmDialog::add($form, 'remove', $this->t('Desfazer amizade'), $this->t('Você quer mesmo desfazer a amizade com @name?', ['@name' => $target->getDisplayName()]), $this->t('Sim'), $this->t('Cancelar'));
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
    // On a profile there is no "Limpar histórico": the profile offers "Iniciar conversa" in its place.
    if ($formState->get('profile') === TRUE) {
      $form['return'] = ['#type' => 'hidden', '#value' => (string) $formState->get('return_path')];
    }
    // The box of one member carries only that member's actions: "Limpar histórico" stays in the conversation's menu.
    elseif ($formState->get('member_box') !== TRUE) {
      $this->clearItems($form, $formState);
    }
    if ($this->banManager->isBanned($targetId)) {
      $form['actions']['unban'] = $this->button('unban', $this->t('Desbloquear')) + ['#weight' => 10];
    }
    else {
      $form = ConfirmDialog::add($form, 'ban', $this->t('Bloquear'), $this->t('Você quer mesmo bloquear @name?', ['@name' => $target->getDisplayName()]), $this->t('Sim'), $this->t('Cancelar'), 10);
    }
    return $form;
  }

  /** The group menu: only "Limpar histórico" for the current member. */
  private function groupForm(FormStateInterface $formState, ?string $postUrl): array {
    $form['#attributes'] = ['data-aculta-drawer-action' => ''];
    if ($postUrl !== NULL) {
      $form['#action'] = $this->actionWithSessionToken($postUrl);
    }
    $form['#cache'] = ['contexts' => ['user'], 'max-age' => 0];
    $form['actions'] = ['#type' => 'actions'];
    if ($formState->get('message')) {
      $form['message'] = $this->statusLine((string) $formState->get('message'));
    }
    $this->clearItems($form, $formState);
    return $form;
  }

  /**
   * "Limpar histórico" in two steps inside the menu: the first click asks, the confirmation clears for this member only.
   */
  private function clearItems(array &$form, FormStateInterface $formState): void {
    if ($formState->get('clear_step') === 'confirm') {
      // The hidden step tells the POST answer which button is posted (the confirmation's label differs from the menu's).
      $form['clear_step'] = ['#type' => 'hidden', '#value' => 'confirm'];
      $form['clear_text'] = $this->statusLine((string) $this->t('Limpar o histórico desta conversa só para você?'));
      $form['actions']['clear_confirm'] = $this->button('clear_confirm', $this->t('Confirmar limpeza do histórico')) + ['#weight' => 5];
    }
    else {
      $form['actions']['clear'] = $this->button('clear', $this->t('Limpar histórico')) + ['#weight' => 5];
    }
  }

  /** A status line of the menu (a result message or a question), a neutral paragraph the theme styles. */
  private function statusLine(string $text): array {
    return [
      '#theme' => 'aculta_portal_element',
      '#tag' => 'p',
      '#element_attributes' => new Attribute(['data-portal-ui' => 'messenger-options-status', 'role' => 'status']),
      '#value' => $text,
    ];
  }

  /**
   * Replaces an unresolved route-token placeholder on fragment-rendered forms.
   *
   * Route URL generation can return a lazy placeholder when a form is rendered
   * in isolation. The fragment response does not process that placeholder, so
   * generate the route token from the internal action path directly.
   */
  private function actionWithSessionToken(string $postUrl): string {
    $path = parse_url($postUrl, PHP_URL_PATH);
    if (!is_string($path) || !str_starts_with($path, '/')) {
      throw new \InvalidArgumentException('Relationship form actions must use an internal path.');
    }
    $token = $this->csrfToken->get(ltrim($path, '/'));
    return Url::fromUserInput($path, ['query' => ['token' => $token]])->toString();
  }

  public function submitForm(array &$form, FormStateInterface $formState): void {
    if ($formState->get('group') === TRUE) {
      $this->submitGroup($formState);
      return;
    }
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

        case 'clear':
          // First step: ask for confirmation in the same menu. Nothing is cleared yet.
          $formState->set('clear_step', 'confirm');
          return;

        case 'clear_confirm':
          // Second step: the same operation as the native clear-history form, for this member only.
          $sender = $this->entityTypes->getStorage('user')->load((int) $this->currentUser->id());
          $this->clearHistory($this->privateMessageService->getThreadForMembers([$sender, $target]));
          $formState->set('message', (string) $this->t('Histórico limpo para você.'));
          return;

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

  /**
   * Applies the two clear-history actions to the thread named in form state.
   */
  private function submitGroup(FormStateInterface $formState): void {
    $formState->disableRedirect();
    $action = (string) ($formState->getTriggeringElement()['#name'] ?? '');
    if ($action === '' || !array_key_exists($action, (array) $formState->getUserInput())) {
      $formState->set('message', (string) $this->t('Não foi possível concluir a ação. Tente novamente.'));
      return;
    }
    $thread = $this->entityTypes->getStorage('private_message_thread')->load((int) $formState->get('thread_id'));
    if (!$thread instanceof PrivateMessageThreadInterface || !$thread->isMember((int) $this->currentUser->id())) {
      $formState->set('message', (string) $this->t('Não foi possível concluir a ação. Tente novamente.'));
      return;
    }
    switch ($action) {
      case 'clear':
        $formState->set('clear_step', 'confirm');
        return;

      case 'clear_confirm':
        $formState->set('message', $this->clearHistory($thread)
          ? (string) $this->t('Histórico limpo para você.')
          : (string) $this->t('Não foi possível concluir a ação. Tente novamente.'));
        return;

      default:
        $formState->set('message', (string) $this->t('Não foi possível concluir a ação. Tente novamente.'));
    }
  }

  /**
   * Clears current-member history after revalidating account and thread access.
   *
   * The message list is cached per message. Invalidate thread and message tags
   * so the response does not retain the old list.
   *
   * @return bool
   *   TRUE when the history was cleared for the current member.
   */
  private function clearHistory(PrivateMessageThreadInterface $thread): bool {
    $uid = (int) $this->currentUser->id();
    if (!$this->currentUser->isAuthenticated() || $uid < 1 || !$thread->isMember($uid)
      || !$thread->access('view', $this->currentUser, TRUE)->isAllowed()) {
      return FALSE;
    }

    $sender = $this->entityTypes->getStorage('user')->load($uid);
    if (!$sender instanceof UserInterface || !$sender->isActive()) {
      return FALSE;
    }
    $thread->clearAccountHistory($sender);
    $tags = $thread->getCacheTags();
    foreach ($thread->get('private_messages')->referencedEntities() as $message) {
      $tags = array_merge($tags, $message->getCacheTags());
    }
    $this->cacheTagsInvalidator->invalidateTags($tags);
    return TRUE;
  }

  /**
   * Returns an active target distinct from the current account.
   */
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
