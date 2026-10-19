<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Controller;

use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\aculta_messenger\Aviso\AvisoService;
use Drupal\aculta_messenger\Entity\Aviso;
use Drupal\aculta_messenger\Form\ConversationRelationshipForm;
use Drupal\aculta_messenger\Support\NoticeType;
use Drupal\aculta_messenger\Support\OptionsDisclosure;
use Drupal\aculta_portal\Render\PortalLinkElement;
use Drupal\Component\Utility\Unicode;
use Drupal\site_alert\Entity\SiteAlert;
use Drupal\aculta_messenger\Form\FriendRequestsForm;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Template\Attribute;
use Drupal\flag\FlagInterface;
use Drupal\flag\FlagServiceInterface;
use Drupal\node\NodeInterface;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Url;
use Drupal\message\MessageInterface;
use Drupal\private_message\Entity\PrivateMessageThreadInterface;
use Drupal\private_message\Service\PrivateMessageServiceInterface;
use Drupal\aculta_messenger\Form\PrivateMessageFormValidator;
use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\Component\Utility\Crypt;
use Drupal\aculta_messenger\Service\MessengerCounts;
use Drupal\aculta_messenger\Service\UnreadThreadCounter;
use Drupal\aculta_user\Social\FriendshipManagerInterface;
use Drupal\user\UserInterface;
use Drupal\site_alert\GetAlertsInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;

/** Pages for the account communication center. */
final class MessagesController extends ControllerBase {

  private const PRIVATE_MESSAGE_TEMPLATE = 'aculta_msg_private_notification';

  public function __construct(
    private readonly DomainPurposeManager $domainPurposeManager,
    private readonly EntityTypeManagerInterface $entityTypes,
    private readonly AccountInterface $account,
    private readonly PrivateMessageServiceInterface $privateMessageService,
    private readonly UnreadThreadCounter $unreadThreadCounter,
    private readonly FriendshipManagerInterface $friendshipManager,
    private readonly GetAlertsInterface $siteAlerts,
    private readonly FlagServiceInterface $flagService,
    private readonly TranslationInterface $translation,
    private readonly FormBuilderInterface $forms,
    private readonly RendererInterface $renderer,
    private readonly DateFormatterInterface $dateFormatter,
    private readonly RequestStack $requestStack,
    private readonly MessengerCounts $counts,
    private readonly PrivateMessageFormValidator $replyValidator,
    private readonly CsrfTokenGenerator $csrfToken,
    private readonly AvisoService $avisos,
    private readonly ?ModuleHandlerInterface $extensionModuleHandler = NULL,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('aculta_portal.domain_purpose'),
      $container->get('entity_type.manager'),
      $container->get('current_user'),
      $container->get('private_message.service'),
      $container->get('aculta_messenger.unread_thread_counter'),
      $container->get('aculta_user.friendship_manager'),
      $container->get('site_alert.get_alerts'),
      $container->get('flag'),
      $container->get('string_translation'),
      $container->get('form_builder'),
      $container->get('renderer'),
      $container->get('date.formatter'),
      $container->get('request_stack'),
      $container->get('aculta_messenger.counts'),
      $container->get('aculta_messenger.private_message_form_validator'),
      $container->get('csrf_token'),
      $container->get('aculta_messenger.aviso_service'),
      $container->get('module_handler'),
    );
  }

  /** Returns the inbox preview used by the existing drawer fetch. */
  public function preview(): Response {
    $build = $this->newConversationAction() + $this->buildConversationList();
    // The native clear-history action remains a Drupal confirmation form.
    $build['#attached']['library'][] = 'core/drupal.dialog.ajax';
    return $this->buildDrawerResponse($build, [
      'X-Aculta-Messages-Count' => (string) ($this->unreadThreadCounter->count() + count($this->friendshipManager->getIncomingRequests())),
    ]);
  }

  /**
   * Returns the conversation list fragment for navigation inside the drawer.
   *
   * The "Voltar" links of a conversation load this fragment, so it carries the same first action as preview().
   */
  public function drawerConversations(): Response {
    $build = $this->conversasBuild();
    return $this->buildDrawerResponse($build, [
      'X-Aculta-Messages-Count' => (string) ($this->unreadThreadCounter->count() + count($this->friendshipManager->getIncomingRequests())),
    ]);
  }

  /** Returns a member-authorized thread fragment for the open drawer. */
  public function drawerConversation(PrivateMessageThreadInterface $private_message_thread): Response {
    $thread = $private_message_thread;
    $access = $thread->access('view', $this->account, TRUE);
    if (!$access->isAllowed() || !$thread->isMember($this->account->id())) {
      throw new NotFoundHttpException();
    }

    // Rendering the thread is what marks it as read in Private Message's view builder. Cross-site
    // requests and prefetches (which can carry the session cookie) must not render it, so they get an
    // empty response and the conversation stays unread.
    if (!$this->isMemberInitiatedRequest()) {
      return new Response('', Response::HTTP_NO_CONTENT, ['Cache-Control' => 'private, no-store']);
    }
    return $this->conversationResponse($thread, $access);
  }

  /**
   * The "Opções" menu of one conversation, for a row of the list: the same form as the conversation's, loaded when the
   * member opens the row's "Opções" (see lazyOptionsDisclosure).
   */
  public function drawerOptions(PrivateMessageThreadInterface $private_message_thread): Response {
    $access = $private_message_thread->access('view', $this->account, TRUE);
    if (!$access->isAllowed() || !$private_message_thread->isMember($this->account->id())) {
      throw new NotFoundHttpException();
    }
    $build = $this->relationshipForm($private_message_thread);
    $content = $this->renderer->renderInIsolation($build);
    return new Response((string) $content, Response::HTTP_OK, [
      'Content-Type' => 'text/html; charset=UTF-8',
      'Cache-Control' => 'private, no-store, no-cache, must-revalidate',
      'X-Content-Type-Options' => 'nosniff',
    ]);
  }

  /**
   * The "Opções" form of a conversation posts here: the drawer gets the conversation again, with the new state.
   * The form is processed while the conversation is built, so the answer already shows the change.
   */
  public function drawerRelationship(PrivateMessageThreadInterface $private_message_thread): Response {
    $access = $private_message_thread->access('view', $this->account, TRUE);
    if (!$access->isAllowed() || !$private_message_thread->isMember($this->account->id())) {
      throw new NotFoundHttpException();
    }
    if (!$this->isMemberInitiatedRequest()) {
      return new Response('', Response::HTTP_NO_CONTENT, ['Cache-Control' => 'private, no-store']);
    }
    // The posted action runs with the request's input; the panel is then built again from a new state, which
    // carries the result message (for example a refused request) to the member.
    $panel = $this->runRelationshipAction($private_message_thread);
    // A thread the member has just cleared has no messages for them, and Private Message does not let them open it:
    // the drawer goes back to the list, which shows what happened.
    if (($panel['cleared'] ?? FALSE) || !$private_message_thread->access('view', $this->account, TRUE)->isAllowed()) {
      return $this->listWithMessage($panel['message'] ?? NULL);
    }
    return $this->conversationResponse($private_message_thread, $access, $panel);
  }

  /**
   * A reply sent from the drawer: the native Private Message form processes the post here, and the drawer gets
   * the conversation back. The redirect the native form would send (to its own page) is not followed.
   */
  public function drawerReply(PrivateMessageThreadInterface $private_message_thread): Response {
    $access = $private_message_thread->access('view', $this->account, TRUE);
    if (!$access->isAllowed() || !$private_message_thread->isMember($this->account->id())) {
      throw new NotFoundHttpException();
    }
    if (!$this->isMemberInitiatedRequest()) {
      return new Response('', Response::HTTP_NO_CONTENT, ['Cache-Control' => 'private, no-store']);
    }
    // The same rules as the native composer (block, friends-only, send limit), then the message is saved here.
    // The native form would redirect to its own page, which does not exist on this domain.
    $input = $this->requestStack->getCurrentRequest()?->request->all() ?? [];
    $text = trim((string) ($input['message'][0]['value'] ?? ''));
    $formState = new FormState();
    $formState->set('thread', $private_message_thread);
    $form = [];
    // The token of the reply form (the same check the Form API makes): a post without it is not accepted.
    // Core seeds a form's token with the placeholder of its form id (FormBuilder::doBuildForm).
    $seed = 'form_token_placeholder_' . Crypt::hashBase64('private_message_add_form');
    if (!$this->csrfToken->validate((string) ($input['reply_token'] ?? ''), $seed)) {
      $formState->setErrorByName('message', $this->translation->translate('A sessão expirou. Recarregue a página e tente de novo.'));
    }
    if ($text === '') {
      $formState->setErrorByName('message', $this->translation->translate('Escreva uma mensagem.'));
    }
    $this->replyValidator->validateBlockedRecipients($form, $formState);
    $this->replyValidator->validateRateLimit($form, $formState);
    $panel = [];
    if ($formState->hasAnyErrors()) {
      $panel['message'] = implode(' ', array_map(static fn($error) => (string) $error, array_values($formState->getErrors())));
    }
    else {
      $message = $this->entityTypes->getStorage('private_message')->create([
        'owner' => (int) $this->account->id(),
        'message' => ['value' => $text, 'format' => 'plain_text'],
      ]);
      $message->save();
      $private_message_thread->addMessage($message);
      $private_message_thread->save();
    }
    return $this->conversationResponse($private_message_thread, $access, $panel);
  }

  /**
   * Runs the posted "Opções" action with the request's input. Returns what the panel must show: the message of the
   * action, and the step of the two-step "Limpar histórico".
   */
  private function runRelationshipAction(PrivateMessageThreadInterface $thread): array {
    $state = $this->threadFormState($thread);
    // getForm() takes the method from the request; this state is built by hand, so it is set here.
    $state->setRequestMethod('POST');
    $input = $this->requestStack->getCurrentRequest()?->request->all() ?? [];
    // No cached form: a form that comes back from the form cache is unserialized without its constructor services,
    // so its submit would read uninitialized properties. The form is built here, with its services, from the input.
    unset($input['form_build_id']);
    $state->setUserInput($input);
    // A notice posts its own buttons (for example "Clique aqui para desbloquear"): process them as the notice does.
    if (($input['mode'] ?? '') === 'notice') {
      $state->set('mode', 'notice');
    }
    if (($input['clear_step'] ?? '') === 'confirm') {
      $state->set('clear_step', 'confirm');
    }
    $this->forms->buildForm(ConversationRelationshipForm::class, $state);
    // The confirmation step is shown only right after "Limpar histórico" was pressed. Any other action ends it.
    $trigger = (string) ($state->getTriggeringElement()['#name'] ?? '');
    return array_filter([
      'message' => $state->get('message') === NULL ? NULL : (string) $state->get('message'),
      'clear_step' => $trigger === 'clear' ? $state->get('clear_step') : NULL,
      // After a confirmed clear the answer is the list: the conversation is not rendered in the same response.
      'cleared' => $trigger === 'clear_confirm' ? TRUE : NULL,
    ], fn($value) => $value !== NULL);
  }

  /** The conversation list, with an optional message from the action just run. */
  private function listWithMessage(?string $message): Response {
    $build = $this->newConversationAction() + $this->buildConversationList();
    if ($message !== NULL) {
      $build['message'] = [
        '#theme' => 'aculta_portal_element',
        '#tag' => 'p',
        '#element_attributes' => new Attribute(['data-portal-ui' => 'messenger-options-status', 'role' => 'status']),
        '#value' => $message,
        '#weight' => -90,
      ];
    }
    return $this->buildDrawerResponse($build, [
      'X-Aculta-Messages-Count' => (string) ($this->unreadThreadCounter->count() + count($this->friendshipManager->getIncomingRequests())),
    ]);
  }

  /**
   * The "Opções" of a row in the list. Its menu is not built for every row of every list: the row carries the address
   * of the menu, and the drawer loads it the first time the member opens it (drawerOptions).
   */
  private function lazyOptionsDisclosure(PrivateMessageThreadInterface $thread): array {
    $box = OptionsDisclosure::build((string) $this->translation->translate('Opções'), []);
    $box['#attributes']['data-aculta-options-url'] = Url::fromRoute('aculta_messenger.drawer_options', ['private_message_thread' => $thread->id()])->toString();
    return $box;
  }

  /** The conversation container: toolbar (back on the left, "Opções" on the right), then the thread. */
  private function conversationResponse(PrivateMessageThreadInterface $thread, $access, array $panel = []): Response {
    $threadBuild = $this->entityTypes->getViewBuilder('private_message_thread')->view($thread, 'full');
    $build = [
      '#type' => 'container',
      '#attributes' => [
        'data-portal-ui' => 'messenger-drawer-conversation',
        'data-aculta-conversation-view' => '',
        'data-aculta-thread-id' => (string) $thread->id(),
      ],
      'toolbar' => [
        '#type' => 'container',
        '#attributes' => ['data-portal-ui' => 'messenger-conversation-toolbar'],
        '#weight' => -10,
        'back' => $this->backToConversationsLink(),
        'options' => $this->optionsDisclosure($thread, $panel),
      ],
      'thread' => $threadBuild,
      '#cache' => ['contexts' => ['user'], 'max-age' => 0],
    ];
    (new CacheableMetadata())->addCacheableDependency($thread)->addCacheableDependency($access)->applyTo($build);
    return $this->buildDrawerResponse($build, [
      'X-Aculta-Messages-Count' => (string) ($this->unreadThreadCounter->count() + count($this->friendshipManager->getIncomingRequests())),
    ]);
  }

  /**
   * The menu of a thread: the friendship and block form of the other member (one-to-one), or only "Limpar histórico"
   * (group). Built from a new state each time: a rebuilt form comes back from the cache without its constructor
   * dependencies.
   */
  private function relationshipForm(PrivateMessageThreadInterface $thread, array $panel = []): array {
    $state = $this->threadFormState($thread);
    // A menu shown in a response is built, not submitted: its input is not processed, so a POST of another action does
    // not submit this form again. The form keeps its token, which the menu's own POST sends back.
    // Its input is empty: Form API then takes no form_build_id from the request, so no cached copy (without its services)
    // is loaded and no submit runs again.
    $state->setUserInput([]);
    $state->setProcessInput(FALSE);
    if (isset($panel['message'])) {
      $state->set('message', $panel['message']);
    }
    if (isset($panel['clear_step'])) {
      $state->set('clear_step', $panel['clear_step']);
    }
    return $this->forms->buildForm(ConversationRelationshipForm::class, $state);
  }

  /** The form state of a thread's menu: the other member for one-to-one, the thread for a group. */
  private function threadFormState(PrivateMessageThreadInterface $thread): FormState {
    $others = array_values(array_filter($thread->getMembers(), fn($member) => (int) $member->id() !== (int) $this->account->id()));
    $state = new FormState();
    if (count($others) === 1) {
      $state->set('target', (int) $others[0]->id());
    }
    else {
      $state->set('group', TRUE);
      $state->set('thread_id', (int) $thread->id());
    }
    $state->set('post_url', Url::fromRoute('aculta_messenger.drawer_relationship', ['private_message_thread' => $thread->id()])->toString());
    return $state;
  }

  /**
   * "Opções": a native disclosure (a sanfona). On a narrow screen the label is hidden and only the hamburger
   * icon remains, which is how the CSS decides whether it fits. Its items are the relationship form, which holds
   * the friendship actions, "Limpar histórico" and the block, in that order.
   */
  private function optionsDisclosure(PrivateMessageThreadInterface $thread, array $panel = []): array {
    // After an action the menu stays open, so its result (a message, the clear confirmation) is what the member sees.
    return OptionsDisclosure::build(
      (string) $this->translation->translate('Opções'),
      ['relationship' => $this->relationshipForm($thread, $panel)],
      $panel !== [],
    );
  }

  /**
   * "← Todas as conversas": back to the drawer's conversation list. Used by every drawer subview, so it sits
   * in the same place (top, right-aligned, under the X) in each one.
   */
  private function backToConversationsLink(): array {
    return PortalLinkElement::build(
      $this->translation->translate('← Todas as conversas'),
      Url::fromRoute('aculta_messenger.drawer_conversations'),
      ['data-aculta-drawer-back' => '', 'data-portal-ui' => 'messenger-back-link'],
    ) + ['#weight' => -100];
  }

  /**
   * "Iniciar nova conversa": the first action of the first window.
   *
   * A button element: links and html_tag are rendered as strings in their pre-render, outside the theme
   * hooks that map data-portal-ui tokens; the button goes through the input theme, which does.
   */
  private function newConversationAction(): array {
    return ['new_conversation' => [
      '#type' => 'button',
      '#value' => $this->translation->translate('Iniciar nova conversa'),
      '#attributes' => [
        'type' => 'button',
        'data-aculta-conversation-url' => Url::fromRoute('aculta_messenger.new_conversation')->toString(),
        'data-portal-ui' => 'new-conversation-link',
      ],
      '#weight' => -100,
    ]];
  }

  /**
   * Lista de conversas da gaveta (ação de nova conversa + lista). Usada pela gaveta da barra e pela aba Mensagens da
   * conta, para que as duas mostrem a mesma coisa com o mesmo comportamento.
   */
  public function conversasBuild(): array {
    return $this->newConversationAction() + $this->buildConversationList();
  }

  /** Builds the authorized conversation list for drawer AJAX navigation. */
  private function buildConversationList(): array {
    $threads = $this->privateMessageService->getThreadsForUser(50)['threads'];
    $items = [];
    $cacheability = (new CacheableMetadata())
      ->addCacheContexts(['user'])
      ->setCacheMaxAge(0);

    // Private Message already returns threads newest first. Keep that order
    // explicit here because each sender is positioned by their latest
    // conversation.
    usort($threads, static fn ($a, $b): int => (int) $b->getUpdatedTime() <=> (int) $a->getUpdatedTime());
    $senderBoxes = [];

    foreach ($threads as $thread) {
      if (!$thread instanceof PrivateMessageThreadInterface) {
        continue;
      }
      $access = $thread->access('view', $this->account, TRUE);
      $cacheability->addCacheableDependency($access);
      if (!$access->isAllowed()) {
        continue;
      }

      $cacheability->addCacheableDependency($thread);
      $isUnread = $this->unreadThreadCounter->isUnread($thread);
      $others = array_values(array_filter($thread->getMembers(), fn($member) => (int) $member->id() !== (int) $this->account->id()));
      // A group (more than two members) is its own row, named by its members. It is never merged into the row of one
      // of them, which would hide the group and its "Limpar histórico".
      $isGroup = count($others) > 1;
      $sender = $isGroup ? NULL : $this->getConversationSender($thread);
      $senderName = $isGroup
        ? implode(', ', array_map(static fn($member) => $member->getDisplayName(), $others))
        : ($sender?->getDisplayName() ?: (string) $this->translation->translate('Membro'));
      if ($sender !== NULL) {
        $cacheability->addCacheableDependency($sender);
      }
      $drawerConversationUrl = Url::fromRoute('aculta_messenger.drawer_conversation', [
        'private_message_thread' => $thread->id(),
      ]);
      $lastMessageTimestamp = (int) $thread->getNewestMessageCreationTimestamp();
      $sentDate = $this->dateFormatter->format(
        $lastMessageTimestamp > 0 ? $lastMessageTimestamp : (int) $thread->getUpdatedTime(),
        'custom',
        'd/m/Y H:i',
      );
      $linkTitle = [
        '#type' => 'container',
        'date' => [
          '#theme' => 'aculta_portal_element',
          '#tag' => 'span',
          '#element_attributes' => new Attribute(['data-portal-ui' => 'messenger-sender-date']),
          '#value' => $sentDate,
        ],
        'name' => [
          '#theme' => 'aculta_portal_element',
          '#tag' => 'span',
          '#element_attributes' => new Attribute(['data-portal-ui' => 'messenger-sender-name']),
          '#value' => $senderName,
        ],
      ];
      // Conversations are read in the bar drawer only; without JavaScript the link opens the same drawer route.
      $openLink = PortalLinkElement::build($linkTitle, $drawerConversationUrl, [
          'data-portal-ui' => 'messenger-sender-link',
          'data-aculta-conversation-url' => $drawerConversationUrl->toString(),
          'data-aculta-thread-id' => (string) $thread->id(),
          'data-aculta-thread-unread' => $isUnread ? 'true' : 'false',
          'data-aculta-sent-date' => $sentDate,
          'aria-label' => (string) $this->translation->translate('Abrir conversa com @name. Última mensagem em @date.', [
            '@name' => $senderName,
            '@date' => $sentDate,
          ]),
      ]);
      $senderId = $sender?->id() ?? 'thread-' . $thread->id();
      $senderKey = (string) $senderId;
      if (!isset($senderBoxes[$senderKey])) {
        $avatar = NULL;
        if ($sender instanceof UserInterface && $sender->hasField('user_picture') && !$sender->get('user_picture')->isEmpty()) {
          $avatarAccess = $sender->access('view', $this->account, TRUE);
          $cacheability->addCacheableDependency($avatarAccess);
          if ($avatarAccess->isAllowed()) {
            $avatar = $sender->get('user_picture')->view('default');
          }
        }
        // The same "Opções" box as inside the conversation (friendship, block, clear), per member.
        $actions = [
          '#type' => 'container',
          '#attributes' => ['data-portal-ui' => 'messenger-conversation-actions'],
          'options' => $this->lazyOptionsDisclosure($thread),
        ];
        $senderBoxes[$senderKey] = [
          'updated' => (int) $thread->getUpdatedTime(),
          'thread_id' => (string) $thread->id(),
          'name' => $senderName,
          'sender' => $sender,
          'avatar' => $avatar,
          'open' => $openLink,
          'unread' => $isUnread,
          'actions' => $actions,
        ];
      }
    }

    // The groups were first encountered at their newest thread; sort anyway
    // so this remains true if the upstream mapper changes its order later.
    uasort($senderBoxes, static fn (array $a, array $b): int => $b['updated'] <=> $a['updated']);
    foreach ($senderBoxes as $senderBox) {
      $box = [
        '#type' => 'container',
        '#attributes' => [
          'data-portal-ui' => 'messenger-sender',
          'data-aculta-thread-id' => $senderBox['thread_id'],
          'data-aculta-thread-unread' => $senderBox['unread'] ? 'true' : 'false',
          'tabindex' => '0',
        ],
        'avatar' => [
          '#type' => 'container',
          '#attributes' => ['data-portal-ui' => 'messenger-sender-avatar', 'aria-hidden' => 'true'],
          'image' => $senderBox['avatar'] ?? ['#plain_text' => mb_substr($senderBox['name'], 0, 1)],
        ],
        'name' => $senderBox['open'] ?? ['#plain_text' => $senderBox['name']],
        // Rótulo Lido / Não lido à direita, fora do link: a caixa inteira continua sendo o link.
        'estado' => $this->readLabel($senderBox['unread']),
        'actions' => $senderBox['actions'],
      ];
      if ($senderBox['sender'] instanceof UserInterface) {
        $cacheability->addCacheableDependency($senderBox['sender']);
        $box['avatar']['image']['#cache']['contexts'][] = 'user';
      }
      $items[] = $box;
    }

    if ($items === []) {
      $items[] = ['#plain_text' => (string) $this->translation->translate('Você ainda não tem conversas.')];
    }

    $build = [
      '#type' => 'container',
      '#attributes' => ['data-portal-ui' => 'messenger-preview'],
      'threads' => ['#theme' => 'item_list', '#items' => $items],
      '#cache' => ['max-age' => 0],
    ];
    $cacheability->applyTo($build);
    return $build;
  }

  /** Selects the latest incoming author, or the conversation partner. */
  private function getConversationSender(PrivateMessageThreadInterface $thread): ?UserInterface {
    $messages = array_values($thread->getMessages());
    $latestMessage = $messages === [] ? NULL : $messages[array_key_last($messages)];
    $sender = $latestMessage?->getOwner();
    if ($sender !== NULL && (int) $sender->id() !== (int) $this->account->id()) {
      return $sender;
    }

    foreach ($thread->getMembers() as $member) {
      if ((int) $member->id() !== (int) $this->account->id()) {
        return $member;
      }
    }

    return $sender;
  }

  /** Displays currently active native Site Alerts in the same-origin modal. */
  public function alertsPreview(): Response {
    $resultado = $this->alertsBuild();
    return $this->buildDrawerResponse($resultado['build'], [
      'X-Aculta-Alerts-Count' => (string) $resultado['contador'],
    ]);
  }

  /**
   * Monta a lista de avisos da gaveta (comunicados, avisos da pessoa, alertas). Usada pela gaveta da barra e pela aba
   * Avisos da conta, para que as duas mostrem a mesma coisa com o mesmo comportamento.
   *
   * @return array{build: array, contador: int}
   */
  public function alertsBuild(): array {
    $storage = $this->entityTypes->getStorage('site_alert');
    $cacheability = (new CacheableMetadata())
      ->addCacheContexts(['active_site_alerts'])
      ->addCacheTags($this->entityTypes->getDefinition('site_alert')->getListCacheTags())
      ->setCacheMaxAge($storage->getCacheMaxAge());
    if ($this->account->isAuthenticated()) {
      $cacheability->addCacheContexts(['user'])->setCacheMaxAge(0);
    }
    $items = [];

    // Persistent notices always lead the list, ahead of the dismissable site alerts.
    foreach ($this->buildPersistentNoticeItems(50, $cacheability) as $item) {
      $items[] = $item;
    }

    // Avisos por pessoa (respostas e atualizações de tópicos seguidos): só da própria pessoa, com link para a página.
    if ($this->account->isAuthenticated()) {
      $cacheability->addCacheContexts(['user'])->setCacheMaxAge(0)->addCacheTags(['aculta_aviso_list']);
      $pessoais = $this->avisos->listarDaPessoa((int) $this->account->id(), 5);
      foreach ($pessoais as $aviso) {
        $nid = (int) $aviso->get('assunto_nid')->value;
        $contador = (int) $aviso->get('contador_site')->value;
        // Aviso do Fórum: rótulo Lido/Não lido no topo da caixa, sem cor de tipo. Ler o tópico zera o contador.
        $items[] = $this->noticeSummary(
          (string) $aviso->get('titulo')->value,
          '',
          Url::fromRoute('entity.node.canonical', ['node' => $nid]),
          [],
          NULL,
          FALSE,
          NULL,
          $this->readLabel($contador > 0),
        );
      }
    }

    // Same criteria as the header bar, from the shared service.
    $activeAlertCount = $this->counts->visibleActiveAlertCount($this->account, $cacheability);
    $dismissalFlag = $this->flagService->getFlagById('aculta_site_alert_dismissed');
    foreach ($this->siteAlerts->getActiveAlerts() as $alert) {
      $access = $alert->access('view', $this->account, TRUE);
      $cacheability->addCacheableDependency($alert)->addCacheableDependency($access);
      if (!$access->isAllowed()) {
        continue;
      }
      if ($this->account->isAuthenticated() && $dismissalFlag instanceof FlagInterface && $this->flagService->getFlagging($dismissalFlag, $alert, $this->account) !== NULL) {
        continue;
      }
      // Match the public render contract of SiteAlertBlock; formatted text and
      // severity remain owned by the contrib entity and its theme hook.
      // The drawer lists a summary; the full alert opens in the same drawer (alertDetail).
      // Alerta do site: rótulo Lido / Não lido (o aviso persistente não tem rótulo). Abrir o alerta marca como lido.
      $rotulo = NULL;
      if ($this->account->isAuthenticated()) {
        $leituraFlag = $this->flagService->getFlagById('aculta_site_alert_read');
        $lido = $leituraFlag instanceof FlagInterface && $this->flagService->getFlagging($leituraFlag, $alert, $this->account) !== NULL;
        $rotulo = $this->readLabel(!$lido);
      }
      $items[] = $this->noticeSummary(
        (string) $alert->getLabel(),
        $this->excerpt((string) $alert->getMessage()),
        Url::fromRoute('aculta_messenger.alert_detail', ['site_alert' => $alert->id()]),
        $this->buildDismissalLink($dismissalFlag, $alert),
        NULL,
        FALSE,
        NoticeType::fromSeverity($alert->getSeverity()),
        $rotulo,
      );
    }

    $this->extensionModuleHandler?->alter('aculta_messenger_personal_notices', $items, $this->account);

    if ($items === []) {
      $items[] = ['#plain_text' => (string) $this->translation->translate('Não há avisos ativos neste momento.')];
    }

    $build = [
      '#type' => 'container',
      '#attributes' => ['data-portal-ui' => 'messenger-preview'],
      'alerts' => ['#theme' => 'item_list', '#items' => $items],
    ];
    $cacheability->applyTo($build);
    return [
      'build' => $build,
      'contador' => $activeAlertCount + $this->counts->unreadPersistentNoticeCount($this->account),
    ];
  }

  /** Renders a same-origin, uncached HTML fragment for the Bootstrap drawers. */
  private function buildDrawerResponse(array $build, array $headers = []): Response {
    $content = $this->renderer->renderInIsolation($build);
    return new Response((string) $content, Response::HTTP_OK, $headers + [
      'Content-Type' => 'text/html; charset=UTF-8',
      'Cache-Control' => 'private, no-store, no-cache, must-revalidate',
      'X-Content-Type-Options' => 'nosniff',
    ]);
  }

  /** Builds authorized notice render arrays and native per-member Flag actions. */
  private function buildPersistentNoticeItems(int $limit, CacheableMetadata $cacheability): array {
    $ids = $this->entityTypes->getStorage('node')->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'aculta_notice')
      ->condition('status', 1)
      ->sort('created', 'DESC')
      ->range(0, $limit)
      ->execute();
    $nodes = $this->entityTypes->getStorage('node')->loadMultiple($ids);
    $flag = $this->flagService->getFlagById('aculta_notice_read');
    $items = [];
    foreach ($nodes as $node) {
      if (!$node instanceof NodeInterface) {
        continue;
      }
      $access = $node->access('view', $this->account, TRUE);
      $cacheability->addCacheableDependency($node)->addCacheableDependency($access);
      if (!$access->isAllowed()) {
        continue;
      }
      $read = FALSE;
      if ($flag instanceof FlagInterface) {
        $flagging = $this->flagService->getFlagging($flag, $node, $this->account);
        $read = $flagging !== NULL;
        $cacheability->addCacheTags($this->entityTypes->getDefinition('flagging')->getListCacheTags());
      }
      // The drawer lists a summary; the full notice opens in the same drawer (noticeDetail).
      // A persistent notice is not dismissed (no "×"): it stays in the list, and its own kind of card tells it apart.
      $items[] = $this->noticeSummary(
        (string) $node->label(),
        $this->excerpt($this->noticeText($node)),
        Url::fromRoute('aculta_messenger.notice_detail', ['node' => $node->id()]),
        [],
        $read ? (string) $this->translation->translate('Lido') : (string) $this->translation->translate('Não lido'),
        TRUE,
        NoticeType::normalize($node->hasField('field_aculta_notice_type') && !$node->get('field_aculta_notice_type')->isEmpty() ? (string) $node->get('field_aculta_notice_type')->value : NULL),
      );
    }
    return $items;
  }

  /**
   * One notice in the drawer's list: a button-like summary (title, excerpt, state). Clicking it loads the full notice in
   * the same drawer; the dismissal link stays on the summary.
   *
   * @param array<string, mixed> $dismiss
   */
  private function noticeSummary(string $title, string $excerpt, Url $url, array $dismiss, ?string $state, bool $persistent = FALSE, ?string $type = NULL, ?array $rotulo = NULL): array {
    $title_parts = [
      '#type' => 'container',
    ];
    $title_parts['title'] = ['#theme' => 'aculta_portal_element', '#tag' => 'span', '#element_attributes' => new Attribute(['data-portal-ui' => 'notice-summary-title']), '#value' => $title];
    if ($excerpt !== '') {
      $title_parts['excerpt'] = ['#theme' => 'aculta_portal_element', '#tag' => 'span', '#element_attributes' => new Attribute(['data-portal-ui' => 'notice-summary-excerpt']), '#value' => $excerpt];
    }
    if ($state !== NULL) {
      $title_parts['state'] = ['#theme' => 'aculta_portal_element', '#tag' => 'span', '#element_attributes' => new Attribute(['data-portal-ui' => 'notice-summary-state']), '#value' => $state];
    }
    return [
      '#type' => 'container',
      '#attributes' => ['data-portal-ui' => trim(
        ($persistent ? 'notice-summary notice-summary-persistent' : 'notice-summary')
        . ($type !== NULL ? ' notice-type-' . NoticeType::normalize($type) : ''),
      )],
      'link' => PortalLinkElement::build($title_parts, $url, [
        'data-aculta-conversation-url' => $url->toString(),
        'data-portal-ui' => 'notice-summary-link',
      ]),
      // Rótulo de lido/não lido (avisos do Fórum) à direita, fora do link; o persistente segue com o estado no rodapé.
      'rotulo' => $rotulo ?? [],
      'dismiss' => $dismiss,
    ];
  }

  /**
   * Rótulo "Lido" ou "Não lido" de uma caixa de conversa ou de aviso do Fórum. Substitui a marcação por cor: o texto é a
   * única indicação de estado. Os avisos persistentes não usam este rótulo.
   */
  private function readLabel(bool $naoLido): array {
    return [
      '#theme' => 'aculta_portal_element',
      '#tag' => 'span',
      '#element_attributes' => new Attribute(['data-portal-ui' => $naoLido ? 'read-label read-label-unread' : 'read-label']),
      '#value' => $naoLido ? (string) $this->translation->translate('Não lido') : (string) $this->translation->translate('Lido'),
    ];
  }

  /** The text of a notice's summary, or its body when the summary is empty. */
  private function noticeText(NodeInterface $node): string {
    if (!$node->hasField('body') || $node->get('body')->isEmpty()) {
      return '';
    }
    $body = $node->get('body');
    return (string) ($body->summary ?: $body->value);
  }

  /** A short plain text of an HTML body, for a summary. */
  private function excerpt(string $html): string {
    // Block tags become spaces, so two paragraphs do not run into one word.
    $spaced = (string) preg_replace('/<\/?(p|h[1-6]|li|div|br)\b[^>]*>/i', ' ', $html);
    return Unicode::truncate(trim((string) preg_replace('/\s+/', ' ', strip_tags($spaced))), 160, TRUE, TRUE);
  }

  /** "← Todos os avisos": back to the notices list, in the same drawer. */
  private function backToNoticesLink(): array {
    return PortalLinkElement::build(
      $this->translation->translate('← Todos os avisos'),
      Url::fromRoute('aculta_messenger.alerts_preview'),
      ['data-aculta-drawer-back' => '', 'data-portal-ui' => 'messenger-back-link'],
    ) + ['#weight' => -100];
  }

  /** One site notice in full, in the drawer. */
  public function alertDetail(SiteAlert $site_alert): Response {
    $access = $site_alert->access('view', $this->account, TRUE);
    if (!$access->isAllowed()) {
      throw new NotFoundHttpException();
    }
    // Ler o alerta marca como lido. Pré-buscas e pedidos de terceiros não contam como leitura.
    $leituraFlag = $this->flagService->getFlagById('aculta_site_alert_read');
    if ($this->account->isAuthenticated() && $leituraFlag instanceof FlagInterface && $this->isMemberInitiatedRequest()
      && $this->flagService->getFlagging($leituraFlag, $site_alert, $this->account) === NULL) {
      $this->flagService->flag($leituraFlag, $site_alert, $this->account);
    }
    $build = [
      '#type' => 'container',
      '#attributes' => ['data-portal-ui' => 'notice-detail'],
      'back' => $this->backToNoticesLink(),
      'alert' => [
        '#theme' => 'site_alert',
        '#alert' => [
          'severity' => $site_alert->getSeverity(),
          'label' => $site_alert->getLabel(),
          'message' => ['#type' => 'markup', '#markup' => $site_alert->getMessage()],
        ],
      ],
    ];
    (new CacheableMetadata())->addCacheableDependency($site_alert)->addCacheableDependency($access)->applyTo($build);
    return $this->buildDrawerResponse($build);
  }

  /** One notice in full, in the drawer, with its state and its dismissal. */
  public function noticeDetail(NodeInterface $node): Response {
    $access = $node->access('view', $this->account, TRUE);
    if ($node->bundle() !== 'aculta_notice' || !$access->isAllowed()) {
      throw new NotFoundHttpException();
    }
    $flag = $this->flagService->getFlagById('aculta_notice_read');
    $build = [
      '#type' => 'container',
      '#attributes' => ['data-portal-ui' => 'notice-detail'],
      'back' => $this->backToNoticesLink(),
      'notice' => $this->entityTypes->getViewBuilder('node')->view($node, 'full'),
    ];
    if ($flag instanceof FlagInterface) {
      $build['read'] = $this->buildReadLink($flag, $node);
    }
    (new CacheableMetadata())->addCacheableDependency($node)->addCacheableDependency($access)->addCacheContexts(['user'])->setCacheMaxAge(0)->applyTo($build);
    return $this->buildDrawerResponse($build);
  }

  /** "Marcar como lido" in a persistent notice's full view: the read state, as text, never an "×". */
  private function buildReadLink(FlagInterface $flag, EntityInterface $entity): array {
    if (!$this->account->isAuthenticated()) {
      return [];
    }
    $link = $flag->getLinkTypePlugin()->getAsFlagLink($flag, $entity, 'default');
    $link['#title'] = $this->translation->translate('Marcar como lido');
    return $link;
  }

  /** Builds the native per-user flag link used to dismiss a notice. */
  private function buildDismissalLink(?FlagInterface $flag, EntityInterface $entity): array {
    if (!$flag instanceof FlagInterface || !$this->account->isAuthenticated()) {
      return [];
    }
    $link = $flag->getLinkTypePlugin()->getAsFlagLink($flag, $entity, 'default');
    $link['#title'] = ['#markup' => '×'];
    $link['#attributes']['data-aculta-alert-dismiss'] = 'true';
    $link['#attributes']['aria-label'] = (string) $this->translation->translate('Dispensar aviso');
    $link['#attributes']['title'] = (string) $this->translation->translate('Dispensar aviso');
    return $link;
  }

  /** A same-site, non-prefetch request, i.e. one the member opened on this site. */
  private function isMemberInitiatedRequest(): bool {
    $request = $this->requestStack->getCurrentRequest();
    if ($request === NULL) {
      return FALSE;
    }
    if ($request->headers->get('Sec-Fetch-Site') === 'cross-site') {
      return FALSE;
    }
    $purpose = strtolower((string) ($request->headers->get('Sec-Purpose') ?? $request->headers->get('Purpose') ?? ''));
    return !str_contains($purpose, 'prefetch') && !str_contains($purpose, 'prerender');
  }

}
