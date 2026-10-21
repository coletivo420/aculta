<?php

declare(strict_types=1);

namespace Drupal\aculta_studio\Plugin\StudioTab;

use Drupal\aculta_studio\Attribute\StudioTab;
use Drupal\aculta_studio\StudioTabBase;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Fórum tab. The owner is aculta_forum (discussions, topics and moderation of reports).
 *
 * The area is shown to accounts with the forum posting or moderation permission. Each action still goes to
 * the owner's route in the forum domain, and the owner's access decides whether the action is offered.
 */
#[StudioTab(
  id: 'forum',
  label: new TranslatableMarkup('Fórum'),
  weight: 20,
)]
final class ForumTab extends StudioTabBase {

  /** {@inheritdoc} */
  public function isAvailable(): bool {
    return $this->moduleHandler->moduleExists('aculta_forum');
  }

  /** {@inheritdoc} */
  public function access(AccountInterface $account): AccessResultInterface {
    return AccessResult::allowedIfHasPermission($account, 'create aculta forum posts')
      ->orIf(AccessResult::allowedIfHasPermission($account, 'moderar denuncias do forum'));
  }

  /** {@inheritdoc} */
  public function sections(AccountInterface $account): array {
    return [
      'discussoes' => [
        'label' => $this->t('Discussões'),
        'weight' => 0,
        'tabs' => [
          'publicas' => [
            'label' => $this->t('Todas'),
            'weight' => 0,
            'actions' => [
              $this->routeAction($this->t('Discussões'), 'view.aculta_forum_public_topics.page_discussions', [], $account, 'forum'),
            ],
          ],
          'novas' => [
            'label' => $this->t('Novas'),
            'weight' => 10,
            'actions' => [
              $this->routeAction($this->t('Discussões novas'), 'view.aculta_forum_public_topics.page_new_topics', [], $account, 'forum'),
            ],
          ],
        ],
      ],
      'criar' => [
        'label' => $this->t('Criar'),
        'weight' => 10,
        'tabs' => [
          'topico' => [
            'label' => $this->t('Tópico'),
            'weight' => 0,
            'actions' => [
              $this->routeAction($this->t('Novo tópico'), 'aculta_forum.topic_add', [], $account, 'forum'),
            ],
          ],
        ],
      ],
      'moderacao' => [
        'label' => $this->t('Moderação'),
        'weight' => 20,
        'tabs' => [
          'denuncias' => [
            'label' => $this->t('Denúncias'),
            'weight' => 0,
            'actions' => [
              $this->routeAction($this->t('Denúncias do fórum'), 'aculta_user.denuncias_forum', [], $account, 'main'),
            ],
          ],
        ],
      ],
    ];
  }

}
