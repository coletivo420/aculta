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
 * Drawer fragments replace the current content, following the shared pattern.
 * Search starts after three characters and waits 300 ms, like the header
 * search preview.
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

  /**
   * {@inheritdoc}
   */
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

  /**
   * Renders the initial member-search step.
   */
  public function start(Request $request): Response {
    $term = trim((string) $request->query->get('q', ''));
    $selectedIds = NewConversationForm::normalizeRecipientIds($request->query->all('members'));
    $formState = new FormState();
    $formState->setUserInput(['members' => $selectedIds]);
    $build = [
      'back' => $this->backLink(),
      'title' => ['#type' => 'html_tag', '#tag' => 'h2', '#value' => $this->t('Nova conversa')],
      'fallback_search' => $this->nativeSearchForm($term, $selectedIds),
      'form' => $this->forms->buildForm(NewConversationForm::class, $formState),
      '#cache' => ['contexts' => ['user'], 'max-age' => 0],
    ];
    if (mb_strlen($term) >= self::MIN_CHARS) {
      $candidates = array_slice($this->candidates($term), 0, self::MAX_RESULTS);
      $build['search_results'] = $candidates === []
        ? ['#plain_text' => $this->t('Nenhum membro encontrado.')]
        : $this->memberResults($candidates, $selectedIds);
    }
    return $this->fragment($build);
  }

  /**
   * Renders eligible member results for the search term.
   */
  public function search(Request $request): Response {
    $term = trim((string) $request->query->get('q', ''));
    $build = ['#cache' => ['contexts' => ['user'], 'max-age' => 0]];
    if (mb_strlen($term) < self::MIN_CHARS) {
      $build['hint'] = ['#plain_text' => $this->t('Digite pelo menos @n caracteres para ver resultados.', ['@n' => self::MIN_CHARS])];
      return $this->fragment($build);
    }

    $candidates = array_slice($this->candidates($term), 0, self::MAX_RESULTS);
    $selectedIds = NewConversationForm::normalizeRecipientIds($request->query->all('members'));
    if ($candidates === []) {
      $build['empty'] = ['#plain_text' => $this->t('Nenhum membro encontrado.')];
    }
    else {
      $build['results'] = $this->memberResults($candidates, $selectedIds);
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
    $selectedIds = NewConversationForm::normalizeRecipientIds(
      $request->isMethod('GET') ? $request->query->all('members') : $request->request->all('members'),
    );
    if ($request->isMethod('GET')) {
      // This is a POST form; explicitly seed its chips from the GET selection
      // carried by the progressive-enhancement links.
      $formState->setUserInput(['members' => $selectedIds]);
    }
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
      'fallback_search' => $this->nativeSearchForm('', $selectedIds),
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
   * @param int[] $selectedIds
   *   IDs already selected in the group form.
   */
  private function memberResults(array $candidates, array $selectedIds = []): array {
    // The drawer turns links into chips. Without JavaScript each link carries
    // the selected recipient IDs to the normal group-composition form.
    $items = [];
    $max = $this->policy->maxRecipients(ConversationPolicy::KIND_GROUP, $this->account);
    if (count($selectedIds) >= $max) {
      return ['#plain_text' => $this->t('Você atingiu o limite de @count pessoas nesta conversa.', ['@count' => $max])];
    }
    $selected = array_fill_keys($selectedIds, TRUE);
    foreach ($candidates as $candidate) {
      $user = $candidate['user'];
      $uid = (int) $user->id();
      if (isset($selected[$uid]) || count($selected) >= $max) {
        continue;
      }
      $name = $candidate['name'];
      $members = [...$selectedIds, $uid];
      $group = Url::fromRoute('aculta_messenger.new_conversation_group', [], ['query' => ['members' => $members]]);
      $items[] = PortalLinkElement::build($name, $group, [
        'data-aculta-add-member' => (string) $uid,
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
   * Builds a native GET search form for members without JavaScript.
   *
   * @param string $term
   *   Current search term.
   * @param int[] $selectedIds
   *   Selected recipients carried across search requests.
   */
  private function nativeSearchForm(string $term, array $selectedIds): array {
    $form = [
      '#type' => 'form',
      '#method' => 'get',
      '#action' => Url::fromRoute('aculta_messenger.new_conversation')->toString(),
      '#attributes' => ['data-aculta-nojs-search' => '', 'data-portal-ui' => 'new-conversation-fallback-search'],
      'q' => [
        '#type' => 'search',
        '#name' => 'q',
        '#title' => $this->t('Buscar membro pelo nome'),
        '#value' => $term,
        '#maxlength' => 100,
      ],
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('Buscar'),
      ],
    ];
    foreach ($selectedIds as $index => $uid) {
      $form['member_' . $index] = ['#type' => 'hidden', '#name' => 'members[]', '#value' => (string) $uid];
    }
    return $form;
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
      // The drawer follows this redirect with fetch, so the conversation
      // returns with its toolbar and options.
      $url = Url::fromRoute('aculta_messenger.drawer_conversation', [
        'private_message_thread' => $thread->id(),
      ])->toString();
      return new RedirectResponse(
        $url,
        Response::HTTP_SEE_OTHER,
        ['Cache-Control' => 'private, no-store'],
      );
    }

    $build = [
      'back' => $this->backLink(),
      'title' => [
        '#type' => 'html_tag',
        '#tag' => 'h2',
        '#value' => $this->t('Nova conversa com @name', ['@name' => $publicName]),
      ],
      'form' => $form,
      '#cache' => ['contexts' => ['user'], 'max-age' => 0],
    ];
    // The drawer shows only this fragment, so list form errors (for example,
    // the send limit) here as well.
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

  /**
   * Members searchable by public nickname and eligible to receive messages. */
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
