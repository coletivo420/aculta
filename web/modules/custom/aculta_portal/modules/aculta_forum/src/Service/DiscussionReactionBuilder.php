<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Service;

use Drupal\aculta_editor\Service\FluentEmojiIconAdapterInterface;
use Drupal\aculta_forum\Access\DiscussionReactionEndpointAccess;
use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\user\EntityOwnerInterface;

/**
 * Builds the discussion reaction bar render array.
 */
class DiscussionReactionBuilder {

  use StringTranslationTrait;

  public function __construct(
    protected readonly DiscussionReactionManager $reactionManager,
    protected readonly DiscussionReactionEndpointAccess $reactionEndpointAccess,
    protected readonly CsrfTokenGenerator $csrfToken,
    protected readonly ?FluentEmojiIconAdapterInterface $emojiAdapter = NULL,
  ) {}

  /**
   * Builds the render array for the reaction bar of a comment or forum post.
   */
  public function build(EntityInterface $entity, AccountInterface $account): array {
    $entityType = $entity->getEntityTypeId();
    $entityId = (int) $entity->id();
    $uid = $account->isAuthenticated() ? (int) $account->id() : NULL;
    $authorUid = $entity instanceof EntityOwnerInterface ? (int) $entity->getOwnerId() : 0;
    $isOwnContent = ($uid !== NULL && $uid > 0 && $authorUid > 0 && $uid === $authorUid);

    $reactionsData = $this->reactionManager->loadReactions($entityType, $entityId, $uid);
    $access = $this->reactionEndpointAccess->access($entityType, (string) $entityId, $account);
    $canReact = $access->isAllowed();

    $reactUrl = Url::fromRoute('aculta_forum.reaction_react', [
      'entity_type' => $entityType,
      'entity_id' => $entityId,
    ])->toString();

    $pickerUrl = Url::fromRoute('aculta_forum.reaction_picker', [
      'entity_type' => $entityType,
      'entity_id' => $entityId,
    ])->toString();

    $csrfToken = $account->isAuthenticated()
      ? $this->csrfToken->get("aculta_forum:reaction:{$entityType}:{$entityId}")
      : '';

    $allowedReactions = $this->reactionManager->getAllowedReactions();
    $defaultReaction = $this->reactionManager->getDefaultReaction();

    $targetId = "discussion-reactions-{$entityType}-{$entityId}";

    $build = [
      '#type' => 'component',
      '#component' => 'aculta420_v2:discussion-reaction-bar',
      '#props' => [
        'target_id' => $targetId,
        'entity_type' => $entityType,
        'entity_id' => (string) $entityId,
        'reactions' => $reactionsData['reactions'],
        'total_count' => $reactionsData['total_count'],
        'user_reaction' => $reactionsData['user_reaction'],
        'allowed_reactions' => $allowedReactions,
        'default_reaction' => $defaultReaction,
        'can_react' => $canReact,
        'is_own_content' => $isOwnContent,
        'react_url' => $reactUrl,
        'picker_url' => $pickerUrl,
        'csrf_token' => $csrfToken,
      ],
      '#attached' => [
        'library' => [
          'core/drupal.htmx',
        ],
      ],
      '#cache' => [
        'contexts' => ['user', 'user.permissions'],
        'tags' => array_merge($entity->getCacheTags(), [
          "aculta_discussion_reactions:{$entityType}:{$entityId}",
          'config:aculta_forum.settings',
        ]),
      ],
    ];
    $cacheMetadata = CacheableMetadata::createFromRenderArray($build);
    $cacheMetadata->addCacheableDependency($access);
    $cacheMetadata->applyTo($build);

    return $build;
  }

  /**
   * Builds the render array for the extra reactions picker popover.
   */
  public function buildPicker(EntityInterface $entity, AccountInterface $account): array {
    $entityType = $entity->getEntityTypeId();
    $entityId = (int) $entity->id();
    $uid = $account->isAuthenticated() ? (int) $account->id() : NULL;

    $reactionsData = $this->reactionManager->loadReactions($entityType, $entityId, $uid);
    $access = $this->reactionEndpointAccess->access($entityType, (string) $entityId, $account);

    $reactUrl = Url::fromRoute('aculta_forum.reaction_react', [
      'entity_type' => $entityType,
      'entity_id' => $entityId,
    ])->toString();

    $csrfToken = $account->isAuthenticated()
      ? $this->csrfToken->get("aculta_forum:reaction:{$entityType}:{$entityId}")
      : '';

    $allowedReactions = $this->reactionManager->getAllowedReactions();
    $targetId = "discussion-reactions-{$entityType}-{$entityId}";

    $categoryLabels = [
      'cultivo' => (string) $this->t('Cultivo e Botânica'),
      'vibe' => (string) $this->t('Expressões e Comunidade'),
      'saude' => (string) $this->t('Ciência e Saúde'),
      'direitos' => (string) $this->t('Direitos e Sociedade'),
    ];

    $categorySlots = [];
    if ($this->emojiAdapter !== NULL) {
      $rawCategories = $this->emojiAdapter->getCategories();
      foreach ($rawCategories as $catKey => $items) {
        $filteredItems = [];
        foreach ($allowedReactions as $reactionKey => $reactionInfo) {
          $emojiIcon = $reactionInfo['icon'];
          // Se o ícone do emoji existir nesta categoria do catálogo.
          if (isset($items[$emojiIcon])) {
            $catItem = $items[$emojiIcon];
            $filteredItems[$reactionKey] = $this->buildPickerOption(
              $reactionKey,
              $reactionInfo['label'],
              $emojiIcon,
              array_merge([$reactionInfo['label']], $catItem['keywords'] ?? []),
              $targetId,
              $reactUrl,
              $csrfToken,
              $reactionsData['user_reaction'] === $reactionKey,
            );
          }
        }
        if (!empty($filteredItems)) {
          $categorySlots[] = [
            '#type' => 'component',
            '#component' => 'aculta420_v2:discussion-reaction-category',
            '#props' => [
              'category_key' => (string) $catKey,
              'label' => $categoryLabels[$catKey] ?? ucfirst((string) $catKey),
            ],
            '#slots' => [
              'items' => array_values($filteredItems),
            ],
          ];
        }
      }
    }

    if ($categorySlots === []) {
      $categorySlots[] = [
        '#type' => 'component',
        '#component' => 'aculta420_v2:discussion-reaction-category',
        '#props' => [
          'category_key' => 'geral',
          'label' => (string) $this->t('Reações'),
        ],
        '#slots' => [
          'items' => array_map(
            fn(array $item): array => $this->buildPickerOption(
              $item['id'],
              $item['label'],
              $item['icon'],
              [$item['label']],
              $targetId,
              $reactUrl,
              $csrfToken,
              $reactionsData['user_reaction'] === $item['id'],
            ),
            $allowedReactions,
          ),
        ],
      ];
    }

    $build = [
      '#type' => 'component',
      '#component' => 'aculta420_v2:discussion-reaction-picker',
      '#props' => [],
      '#slots' => ['categories' => $categorySlots],
      '#cache' => [
        'contexts' => ['user', 'user.permissions'],
        'tags' => array_merge($entity->getCacheTags(), [
          "aculta_discussion_reactions:{$entityType}:{$entityId}",
          'config:aculta_forum.settings',
        ]),
      ],
    ];
    CacheableMetadata::createFromRenderArray($build)
      ->addCacheableDependency($access)
      ->applyTo($build);

    return $build;
  }

  /**
   * Builds the render array for one selectable reaction.
   *
   * @param list<string> $keywords
   *   Search keywords for the picker.
   *
   * @return array<string, mixed>
   *   The reaction option SDC render array.
   */
  private function buildPickerOption(
    string $reactionId,
    string $name,
    string $icon,
    array $keywords,
    string $targetId,
    string $reactUrl,
    string $csrfToken,
    bool $selected,
  ): array {
    return [
      '#type' => 'component',
      '#component' => 'aculta420_v2:discussion-reaction-option',
      '#props' => [
        'reaction_id' => $reactionId,
        'name' => $name,
        'icon' => $icon,
        'keywords' => implode(' ', $keywords),
        'target_id' => $targetId,
        'react_url' => $reactUrl,
        'csrf_token' => $csrfToken,
        'selected' => $selected,
      ],
    ];
  }

}
