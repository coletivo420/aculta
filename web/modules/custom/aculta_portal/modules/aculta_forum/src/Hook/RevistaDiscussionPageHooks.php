<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\node\NodeInterface;

/**
 * Popups e bibliotecas de discussão em páginas que não passam pelo entity_view_alter do fórum.
 *
 * Revista e Wiki montam o comentário pelo Layout Builder, então o popup de resposta e o popup
 * administrativo são renderizados uma vez por página, no rodapé. Fórum e LMS já recebem o popup
 * de resposta pelo DiscussionProgressHooks, então aqui só entra o popup administrativo.
 */
final class RevistaDiscussionPageHooks {

  /**
   * Bundles com Layout Builder que ainda não recebem o popup de resposta.
   */
  private const LAYOUT_BUNDLES = ['revista', 'wiki_entry'];

  /**
   * Bundles that host the contextual discussion field.
   */
  private const DISCUSSION_BUNDLES = ['revista', 'wiki_entry', 'aculta_forum_topic', 'aculta_lms_topic'];

  public function __construct(
    private readonly RouteMatchInterface $routeMatch,
    private readonly AccountProxyInterface $currentUser,
  ) {}

  /**
   * Anexa as bibliotecas da discussão nas páginas com o campo.
   */

  /**
 * Implements hook_page_attachments().
 *
 * @param array<array-key, mixed> $attachments
 *   Value used for $attachments.
 */
  #[Hook('page_attachments')]
  public function pageAttachments(array &$attachments): void {
    $node = $this->discussionNode();
    if ($node === NULL) {
      return;
    }
    if (!isset($attachments['#attached']) || !is_array($attachments['#attached'])) {
      $attachments['#attached'] = [];
    }
    if (!isset($attachments['#attached']['library']) || !is_array($attachments['#attached']['library'])) {
      $attachments['#attached']['library'] = [];
    }
    if (in_array($node->bundle(), self::LAYOUT_BUNDLES, TRUE)) {
      $attachments['#attached']['library'][] = 'aculta_forum/discussion-reply-popup';
      $attachments['#attached']['library'][] = 'aculta_forum/discussion-side';
    }
    if ($this->canModerate()) {
      $attachments['#attached']['library'][] = 'aculta_forum/comment-admin-popup';
    }
  }

  /**
 * Renderiza os popups da discussão uma vez por página.
 *
 * @param array<array-key, mixed> $page
 *   Value used for $page.
 */
  #[Hook('page_bottom')]
  public function pageBottom(array &$page): void {
    $node = $this->discussionNode();
    if ($node === NULL) {
      return;
    }
    if (in_array($node->bundle(), self::LAYOUT_BUNDLES, TRUE)) {
      $page['aculta_reply_popup'] = [
        '#theme' => 'portal_component',
        '#name' => 'popup',
        '#props' => [
          'id' => 'aculta-reply-dialog',
          'title' => (string) t('Responder à discussão'),
          'close_label' => (string) t('Fechar'),
        ],
        '#slots' => [
          'content' => [
            [
              '#theme' => 'portal_component',
              '#name' => 'posting-overlay',
              '#props' => [
                'title' => (string) t('Aguarde'),
                'text' => (string) t('Postando seu comentário…'),
              ],
            ],
            ['#markup' => '<div id="aculta-reply-dialog-body" class="aculta-reply-dialog__body"></div>'],
          ],
        ],
      ];
    }
    if ($this->canModerate()) {
      $page['aculta_admin_popup'] = [
        '#theme' => 'portal_component',
        '#name' => 'popup',
        '#props' => [
          'id' => 'aculta-admin-dialog',
          'title' => (string) t('Controles do comentário'),
          'close_label' => (string) t('Fechar'),
        ],
        '#slots' => [
          'content' => ['#markup' => '<div id="aculta-admin-dialog-body" class="aculta-admin-dialog__body"></div>'],
        ],
      ];
    }
  }

  /**
   * Nó da página quando ele hospeda o campo de discussão; NULL nos demais casos.
   */
  private function discussionNode(): ?NodeInterface {
    $node = $this->routeMatch->getParameter('node');
    if (!$node instanceof NodeInterface || !in_array($node->bundle(), self::DISCUSSION_BUNDLES, TRUE) || !$node->hasField('field_aculta_discussion')) {
      return NULL;
    }
    return $node;
  }

  /**
   * Provides the canModerate operation.
   */
  private function canModerate(): bool {
    return $this->currentUser->hasPermission('administer comments');
  }

}
