<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Controller;

use Drupal\aculta_messenger\Form\NewConversationForm;
use Drupal\aculta_user\Social\FriendshipManagerInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\user\UserDataInterface;
use Drupal\user\UserInterface;
use Drupal\private_message\Service\PrivateMessageBanManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

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
    );
  }

  /** First step: the member search field, with the hint that the header search also shows. */
  public function start(): Response {
    $build = [
      'title' => ['#type' => 'html_tag', '#tag' => 'h2', '#value' => $this->t('Nova conversa')],
      'search' => $this->searchForm(),
      '#attached' => ['library' => ['aculta_portal/search-preview']],
      '#cache' => ['contexts' => ['user'], 'max-age' => 0],
    ];
    return $this->fragment($build);
  }

  /** Search results for the typed name; the header search's rule (three characters) applies here too. */
  public function search(Request $request): Response {
    $term = trim((string) $request->query->get('q', ''));
    $build = ['#cache' => ['contexts' => ['user'], 'max-age' => 0]];
    if (mb_strlen($term) < self::MIN_CHARS) {
      $build['hint'] = ['#plain_text' => $this->t('Digite pelo menos @n caracteres para ver resultados.', ['@n' => self::MIN_CHARS])];
      return $this->fragment($build);
    }

    $items = [];
    foreach ($this->candidates($term) as $user) {
      $items[] = [
        '#type' => 'link',
        '#title' => $user->getDisplayName(),
        '#url' => Url::fromRoute('aculta_messenger.new_conversation_compose', ['user' => $user->id()]),
        '#attributes' => [
          'data-aculta-conversation-url' => Url::fromRoute('aculta_messenger.new_conversation_compose', ['user' => $user->id()])->toString(),
          'data-portal-ui' => 'new-conversation-result',
        ],
      ];
      if (count($items) >= self::MAX_RESULTS) {
        break;
      }
    }
    if ($items === []) {
      $build['empty'] = ['#plain_text' => $this->t('Nenhum membro encontrado.')];
    }
    else {
      $build['results'] = [
        '#theme' => 'item_list',
        '#items' => $items,
        '#attributes' => ['data-portal-ui' => 'new-conversation-results'],
      ];
    }
    return $this->fragment($build);
  }

  /** Second step: the first-message form for one member. */
  public function compose(UserInterface $user): Response {
    if ((int) $user->id() === (int) $this->account->id() || !$user->isActive()) {
      throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
    }
    $build = [
      'title' => ['#type' => 'html_tag', '#tag' => 'h2', '#value' => $this->t('Nova conversa com @name', ['@name' => $user->getDisplayName()])],
      'form' => $this->forms->getForm(NewConversationForm::class, (int) $user->id()),
      '#cache' => ['contexts' => ['user'], 'max-age' => 0],
    ];
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
      if (!$user instanceof UserInterface || $this->banManager->isCurrentUserBannedByUser((int) $user->id())) {
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

  /**
   * The member search: a real form (the preview script reads input.form), a search input with the header
   * search's token, and the preview box that holds the hint and the results.
   */
  private function searchForm(): array {
    return [
      '#type' => 'html_tag',
      '#tag' => 'form',
      '#attributes' => ['method' => 'get', 'action' => '#', 'role' => 'search', 'data-aculta-new-conversation' => ''],
      'input' => [
        '#type' => 'html_tag',
        '#tag' => 'input',
        '#attributes' => [
          'type' => 'search',
          'name' => 'q',
          'maxlength' => '100',
          'autocomplete' => 'off',
          'placeholder' => (string) $this->t('Buscar membro pelo nome'),
          'aria-label' => (string) $this->t('Buscar membro'),
          'data-portal-ui' => 'search-preview-input',
          'data-preview-url' => Url::fromRoute('aculta_messenger.new_conversation_search')->toString(),
          'data-preview-target' => 'aculta-new-conversation-preview',
          'data-min-chars' => (string) self::MIN_CHARS,
        ],
      ],
      'preview' => [
        '#type' => 'container',
        '#attributes' => ['id' => 'aculta-new-conversation-preview', 'data-portal-ui' => 'search-preview', 'aria-live' => 'polite'],
        'hint' => ['#plain_text' => $this->t('Digite pelo menos @n caracteres para ver resultados.', ['@n' => self::MIN_CHARS])],
      ],
    ];
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
