<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Controller;

use Drupal\aculta_messenger\Form\NewConversationForm;
use Drupal\aculta_messenger\Service\ConversationPolicy;
use Drupal\aculta_portal\Render\PortalLinkElement;
use Drupal\aculta_user\Social\FriendshipManagerInterface;
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
use Drupal\private_message\Service\PrivateMessageBanManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * "Iniciar nova conversa" in the multidomain bar drawer: find a member, then write the first message.
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
    private readonly FriendshipManagerInterface $friendshipManager,
    private readonly FormBuilderInterface $forms,
    private readonly RendererInterface $renderer,
    private readonly ConversationPolicy $policy,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('current_user'),
      $container->get('user.data'),
      $container->get('private_message.ban_manager'),
      $container->get('aculta_user.friendship_manager'),
      $container->get('form_builder'),
      $container->get('renderer'),
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
   * The send of the new-conversation form: the chips (members[]) and the message, posted together. Missing members or an
   * empty message are reported by the form's validation; a valid send answers with the new conversation.
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
   * The search results: one "Adicionar" button per member. The drawer turns a press into a chip in the field above
   * (messenger-drawers.js); the chips stay there while the results change.
   *
   * @param \Drupal\user\UserInterface[] $candidates
   */
  private function memberResults(array $candidates): array {
    // The same list as the header search's preview (search-preview-list, styled as its dropdown). Each name is a link:
    // the drawer turns a press into a chip (messenger-drawers.js) and does not follow the link.
    $items = [];
    $group = Url::fromRoute('aculta_messenger.new_conversation_group');
    foreach ($candidates as $user) {
      $items[] = PortalLinkElement::build($user->getDisplayName(), $group, [
        'data-aculta-add-member' => (string) $user->id(),
        'data-aculta-member-name' => $user->getDisplayName(),
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
   * Second step: the first-message form for one member. The drawer posts the form here with fetch, so a
   * successful POST answers with the new conversation and an invalid one with the form and its errors.
   */
  public function compose(UserInterface $user, Request $request): Response {
    if ((int) $user->id() === (int) $this->account->id() || !$this->policy->canReceiveMessages($user)) {
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
      'title' => ['#type' => 'html_tag', '#tag' => 'h2', '#value' => $this->t('Nova conversa com @name', ['@name' => $user->getDisplayName()])],
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

  /** Members the current account may message: active, not self, not blocking it, and allowed by privacy. */
  private function candidates(string $term): array {
    $ids = $this->entityTypes->getStorage('user')->getQuery()
      ->accessCheck(FALSE)
      ->condition('status', 1)
      ->condition('uid', 0, '>')
      ->condition('uid', (int) $this->account->id(), '<>')
      ->condition('name', '%' . $this->escapeLike($term) . '%', 'LIKE')
      ->sort('name')
      ->range(0, 50)
      ->execute();
    $found = [];
    $staff = $this->account->hasRole('administrator') || $this->account->hasRole('content_editor');
    foreach ($this->entityTypes->getStorage('user')->loadMultiple($ids) as $user) {
      if (!$user instanceof UserInterface || !$this->policy->canReceiveMessages($user)
        || $this->banManager->isCurrentUserBannedByUser((int) $user->id())) {
        continue;
      }
      $preference = $this->userData->get('aculta_messenger', (int) $user->id(), 'message_privacy');
      if (!$staff && $preference === 'friends' && !$this->friendshipManager->areFriendsWith((int) $user->id())) {
        continue;
      }
      $found[] = $user;
    }
    return $found;
  }

  private function escapeLike(string $value): string {
    return addcslashes($value, '\\%_');
  }

  /** "← Todas as conversas", the same link and place as in the conversation view (top, under the drawer's X). */
  private function backLink(): array {
    return PortalLinkElement::build($this->t('← Todas as conversas'), Url::fromRoute('aculta_messenger.drawer_conversations'), [
      'data-aculta-drawer-back' => '',
      'data-portal-ui' => 'messenger-back-link',
    ]) + ['#weight' => -100];
  }

  private function fragment(array $build): Response {
    $content = $this->renderer->renderInIsolation($build);
    return new Response((string) $content, Response::HTTP_OK, [
      'Content-Type' => 'text/html; charset=UTF-8',
      'Cache-Control' => 'private, no-store, no-cache, must-revalidate',
      'X-Content-Type-Options' => 'nosniff',
    ]);
  }

}
