<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Hook;

use Drupal\aculta_user\Avatar\AvatarResolver;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\aculta_user\Identity\AuthorCardBuilder;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
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
    #[Autowire(service: 'aculta_user.author_card_builder')]
    private readonly AuthorCardBuilder $authorCardBuilder,
  ) {}

  /**
   * Avatar do autor do tópico, montado do mesmo cartão público do autor.
   *
   * Um render array novo a cada preprocess evita a reutilização de um array
   * já impresso, que o Drupal renderiza vazio.
   */
  #[Hook('preprocess_node')]
  public function preprocessNode(array &$variables): void {
    $node = $variables['node'] ?? NULL;
    if (!$node instanceof NodeInterface || $node->bundle() !== 'aculta_forum_topic') {
      return;
    }
    $owner = $node->getOwner();
    if ($owner === NULL) {
      return;
    }
    // Sem apelido, build() não tem identidade: o fallback do acervo mantém o avatar.
    $card = $this->authorCardBuilder->build($owner, 'author_forum') ?? $this->authorCardBuilder->buildFallback($owner);
    $variables['opening_author_avatar'] = $card['#avatar'] ?? NULL;
  }

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
