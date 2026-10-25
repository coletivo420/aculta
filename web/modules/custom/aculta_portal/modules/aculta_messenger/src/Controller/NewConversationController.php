<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Controller;

use Drupal\aculta_messenger\Form\NewConversationForm;
use Drupal\aculta_messenger\Service\ConversationPolicy;
use Drupal\aculta_portal\Render\PortalLinkElement;
use Drupal\aculta_user\Social\FriendshipManagerInterface;
use Drupal\aculta_user\Identity\PublicMemberIdentityInterface;
use Drupal\aculta_portal\Profile\PerfilDoPapel;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\user\UserDataInterface;
use Drupal\user\UserInterface;
use Drupal\private_message\Entity\PrivateMessageThreadInterface;
use Drupal\private_message\Mapper\PrivateMessageMapperInterface;
use Drupal\private_message\Service\PrivateMessageBanManagerInterface;
use Drupal\profile\Entity\ProfileInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Starts a new conversation from the multidomain bar drawer.
 *
 * The three fragments are rendered for the drawer and replace its content, as the other drawer views do.
 * Searching follows the header search: it starts from three characters and waits 300 ms (search-preview.js).
 */
final class NewConversationController extends ControllerBase {

  public const MIN_CHARS = 3;

  private const MAX_RESULTS = 10;

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypes,
    private readonly AccountInterface $account,
    private readonly UserDataInterface $userData,
    private readonly PrivateMessageBanManagerInterface $banManager,
    private readonly PrivateMessageMapperInterface $privateMessageMapper,
    private readonly FriendshipManagerInterface $friendshipManager,
    private readonly FormBuilderInterface $forms,
    private readonly RendererInterface $renderer,
    private readonly PublicMemberIdentityInterface $memberIdentity,
    private readonly ConversationPolicy $policy,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('current_user'),
      $container->get('user.data'),
      $container->get('private_message.ban_manager'),
      $container->get('private_message.mapper'),
      $container->get('aculta_user.friendship_manager'),
      $container->get('form_builder'),
      $container->get('renderer'),
      $container->get(PublicMemberIdentityInterface::class),
      $container->get('aculta_messenger.conversation_policy'),
    );
  }

  /** First step: the member search field, with the hint that the header search also shows. */
  public function start(): Response {
    $build = [
      'back' => $this->backLink(),
      'title' => ['#type' => 'html_tag', '#tag' => 'h2', '#value' => $this->t('Nova conversa')],
      'form' => $this->forms->buildForm(NewConversationForm::class, new FormState()),
      '#cache' => ['contexts' => ['user'], 'max-age' => 0],
    ];
    return $this->fragment($build);
  }

  /** Search results for the typed name: one checkbox per member, up to the group limit per choice. */
  public function search(Request $request): Response {
    $term = trim((string) $request->query->get('q', ''));
    $build = ['#cache' => ['contexts' => ['user'], 'max-age' => 0]];
    if (mb_strlen($term) < self::MIN_CHARS) {
      $build['hint'] = ['#plain_text' => $this->t('Digite pelo menos @n caracteres para ver resultados.', ['@n' => self::MIN_CHARS])];
      return $this->fragment($build);
    }

    $candidates = array_slice($this->candidates($term), 0, self::MAX_RESULTS);
    if ($candidates === []) {
      $build['empty'] = ['#plain_text' => $this->t('Nenhum membro encontrado.')];
    }
    else {
      $build['results'] = $this->memberResults($candidates);
    }
    return $this->fragment($build);
  }

  /**
   * Handles the new-conversation form submission.
   *
   * The chips and message are posted together. Form validation reports missing
   * members or an empty message; a valid send redirects to the conversation.
   */
  public function composeGroup(Request $request): Response {
    $formState = new FormState();
    $form = $this->forms->buildForm(NewConversationForm::class, $formState);

    $thread = $formState->get('thread');
    if ($request->isMethod('POST') && $thread instanceof PrivateMessageThreadInterface && !$formState->hasAnyErrors()) {
      return new RedirectResponse(Url::fromRoute('aculta_messenger.drawer_conversation', ['private_message_thread' => $thread->id()])->toString(), Response::HTTP_SEE_OTHER, [
        'Cache-Control' => 'private, no-store',
      ]);
    }
    $build = [
      'back' => $this->backLink(),
      'title' => ['#type' => 'html_tag', '#tag' => 'h2', '#value' => $this->t('Nova conversa')],
      'form' => $form,
      '#cache' => ['contexts' => ['user'], 'max-age' => 0],
    ];
    $errors = array_values(array_map(static fn($message) => (string) $message, $formState->getErrors()));
    if ($formState->get('aculta_messenger_new_conversation_privacy_rejected')) {
      $errors[] = (string) $this->t('Não foi possível iniciar uma conversa com este destinatário.');
    }
    if ($request->isMethod('POST') && $errors !== []) {
      $build['errors'] = [
        '#theme' => 'item_list',
        '#items' => $errors,
        '#attributes' => ['role' => 'alert'],
        '#weight' => -50,
      ];
    }
    return $this->fragment($build);
  }

  /**
   * Builds one "Adicionar" button per eligible member.
   *
   * The drawer turns each press into a chip and preserves chips across results.
   *
   * @param array<int, array{user: UserInterface, name: string}> $candidates
   *   Members and their policy-approved display names.
   */
  private function memberResults(array $candidates): array {
    // The same list as the header search's preview (search-preview-list, styled as its dropdown). Each name is a link:
    // the drawer turns a press into a chip (messenger-drawers.js) and does not follow the link.
    $items = [];
    $group = Url::fromRoute('aculta_messenger.new_conversation_group');
    foreach ($candidates as $candidate) {
      $user = $candidate['user'];
      $name = $candidate['name'];
      $items[] = PortalLinkElement::build($name, $group, [
        'data-aculta-add-member' => (string) $user->id(),
        'data-aculta-member-name' => $name,
        'data-portal-ui' => 'new-conversation-add',
      ]);
    }
    return [
      '#theme' => 'item_list',
      '#items' => $items,
      '#attributes' => ['data-portal-ui' => 'search-preview-list'],
    ];
  }

  /**
   * Builds and submits the first-message form for one member.
   *
   * The drawer posts with Fetch and receives either a redirect or the form with
   * its validation errors.
   */
  public function compose(UserInterface $user, Request $request): Response {
    $publicName = $this->eligibleRecipientName($user, TRUE);
    if ($publicName === NULL) {
      throw new NotFoundHttpException();
    }
    $formState = new FormState();
    $formState->set('recipient', (int) $user->id());
    $form = $this->forms->buildForm(NewConversationForm::class, $formState);

    $thread = $formState->get('thread');
    if ($request->isMethod('POST') && $thread instanceof PrivateMessageThreadInterface && !$formState->hasAnyErrors()) {
      // Send, then show the conversation the way the drawer opens any thread: the drawer follows this redirect
      // with fetch, so the conversation comes with its toolbar and "Opções" (MessagesController::drawerConversation).
      return new RedirectResponse(Url::fromRoute('aculta_messenger.drawer_conversation', ['private_message_thread' => $thread->id()])->toString(), Response::HTTP_SEE_OTHER, [
        'Cache-Control' => 'private, no-store',
      ]);
    }

    $build = [
      'back' => $this->backLink(),
      'title' => ['#type' => 'html_tag', '#tag' => 'h2', '#value' => $this->t('Nova conversa com @name', ['@name' => $publicName])],
      'form' => $form,
      '#cache' => ['contexts' => ['user'], 'max-age' => 0],
    ];
    // The drawer shows only this fragment, so the form errors (e.g. the send limit) must be listed here too.
    if ($request->isMethod('POST') && $formState->hasAnyErrors()) {
      $build['errors'] = [
        '#theme' => 'item_list',
        '#items' => array_values(array_map(static fn($message) => (string) $message, $formState->getErrors())),
        '#attributes' => ['role' => 'alert'],
        '#weight' => -50,
      ];
    }
    return $this->fragment($build);
  }

  /** Members searchable by public nickname and eligible to receive messages. */
  private function candidates(string $term): array {
    $profileStorage = $this->entityTypes->getStorage('profile');
    $profileIds = $profileStorage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', PerfilDoPapel::TIPOS, 'IN')
      ->condition('field_nickname', '%' . $this->escapeLike($term) . '%', 'LIKE')
      ->sort('field_nickname')
      ->range(0, 100)
      ->execute();
    $userIds = [];
    foreach ($profileStorage->loadMultiple($profileIds) as $profile) {
      if (!$profile instanceof ProfileInterface
        || !$profile->hasField('field_nickname')
        || $profile->get('field_nickname')->isEmpty()) {
        continue;
      }
      $uid = (int) $profile->getOwnerId();
      if ($uid > 0 && $uid !== (int) $this->account->id()) {
        $userIds[$uid] = $uid;
      }
    }

    $users = $this->entityTypes->getStorage('user')->loadMultiple(array_values($userIds));
    $found = [];
    foreach ($users as $user) {
      if (!$user instanceof UserInterface) {
        continue;
      }
      $publicName = $this->eligibleRecipientName($user);
      if ($publicName === NULL || mb_stripos($publicName, $term) === FALSE) {
        continue;
      }
      $found[] = ['user' => $user, 'name' => $publicName];
    }
    return $found;
  }

  /**
   * Returns a public name when the account may contact the member here.
   */
  private function eligibleRecipientName(UserInterface $user, bool $allowExistingThread = FALSE): ?string {
    $uid = (int) $user->id();
    if ($uid < 1 || $uid === (int) $this->account->id() || !$this->policy->canReceiveMessages($user)) {
      return NULL;
    }
    // Blocks are bilateral and remain in force for staff accounts.
    if ($this->banManager->isBanned($uid) || $this->banManager->isCurrentUserBannedByUser($uid)) {
      return NULL;
    }
    $publicName = $this->memberIdentity->publicDisplayName($user);
    if ($publicName === NULL) {
      return NULL;
    }
    $staff = $this->account->hasRole('administrator') || $this->account->hasRole('content_editor');
    $preference = $this->userData->get('aculta_messenger', $uid, 'message_privacy');
    if (!$staff && $preference === 'friends' && !$this->friendshipManager->areFriendsWith($uid)) {
      if (!$allowExistingThread || !$this->hasExistingThread($user)) {
        return NULL;
      }
    }
    return $publicName;
  }

  /**
   * Checks for a thread with exactly the current members.
   */
  private function hasExistingThread(UserInterface $recipient): bool {
    $sender = $this->entityTypes->getStorage('user')->load((int) $this->account->id());
    return $sender instanceof UserInterface
      && (bool) $this->privateMessageMapper->getThreadIdForMembers([$sender, $recipient]);
  }

  /**
   * Escapes wildcard characters for the profile nickname query.
   */
  private function escapeLike(string $value): string {
    return addcslashes($value, '\\%_');
  }

  /**
   * Returns the back link shown beneath the drawer's close button.
   */
  private function backLink(): array {
    return PortalLinkElement::build($this->t('← Todas as conversas'), Url::fromRoute('aculta_messenger.drawer_conversations'), [
      'data-aculta-drawer-back' => '',
      'data-portal-ui' => 'messenger-back-link',
    ]) + ['#weight' => -100];
  }

  /**
   * Renders a private, uncached HTML fragment.
   */
  private function fragment(array $build): Response {
    $content = $this->renderer->renderInIsolation($build);
    return new Response((string) $content, Response::HTTP_OK, [
      'Content-Type' => 'text/html; charset=UTF-8',
      'Cache-Control' => 'private, no-store, no-cache, must-revalidate',
      'X-Content-Type-Options' => 'nosniff',
    ]);
  }

}
