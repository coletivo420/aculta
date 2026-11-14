<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Hook;

use Drupal\aculta_user\Avatar\AvatarResolver;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\user\UserInterface;

/**
 * Registro de templates do fórum como tema.
 */
class ForumThemeHooks {

  public function __construct(
    private readonly AccountProxyInterface $currentUser,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly AvatarResolver $avatarResolver,
  ) {}

  /**
   * Avatar do usuário logado para o compositor de resposta.
   *
   * Usa o sistema de avatar do ACULTA: a foto da conta quando existe, ou o
   * avatar do acervo, que sempre está disponível no código.
   */
  #[Hook('preprocess_discussion_reply_composer')]
  public function preprocessDiscussionReplyComposer(array &$variables): void {
    if (!$this->currentUser->isAuthenticated()) {
      return;
    }
    $account = $this->entityTypeManager->getStorage('user')->load($this->currentUser->id());
    if (!$account instanceof UserInterface) {
      return;
    }
    $name = trim((string) $account->getDisplayName());
    $variables['avatar'] = $this->avatarResolver->build($account, $name, TRUE, new CacheableMetadata());
  }

  /**
   * Implements hook_theme().
   *
   * @return array<string, array<string, mixed>>
   *   Definições de tema para o fórum e discussões.
   */
  #[Hook('theme')]
  public function theme(mixed $existing, string $type, string $theme, string $path): array {
    return [
      'discussion_reply_composer' => [
        'variables' => [
          'status' => 'fechado',
          'mode' => 'responder',
          'target_id' => '',
          'parent_id' => NULL,
          'parent_author' => NULL,
          'parent_subject' => NULL,
          'host_title' => NULL,
          'messages' => NULL,
          'form' => NULL,
          'component' => NULL,
        ],
        'path' => $path . '/templates',
        'template' => 'discussion-reply-composer',
      ],
    ];
  }

}
