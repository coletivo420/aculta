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
 * Wiki tab. The owner is the Wiki (node type wiki_entry, workflow wiki_editorial), without a module of its own.
 *
 * Verbetes lists the entries by editorial state through the Studio listing, with the node access of the owner.
 */
#[StudioTab(
  id: 'wiki',
  label: new TranslatableMarkup('Wiki'),
  weight: 25,
)]
final class WikiTab extends StudioTabBase {

  /** {@inheritdoc} */
  public function isAvailable(): bool {
    return $this->entityTypeManager->getStorage('node_type')->load('wiki_entry') !== NULL;
  }

  /** {@inheritdoc} */
  public function access(AccountInterface $account): AccessResultInterface {
    return AccessResult::allowedIfHasPermission($account, 'edit any wiki_entry content')
      ->orIf(AccessResult::allowedIfHasPermission($account, 'create wiki_entry content'));
  }

  /** {@inheritdoc} */
  public function sections(AccountInterface $account): array {
    $states = [
      'todos' => ['label' => $this->t('Todos'), 'state' => NULL],
      'rascunhos' => ['label' => $this->t('Rascunhos'), 'state' => 'draft'],
      'em-revisao' => ['label' => $this->t('Em revisão'), 'state' => 'review'],
      'publicados' => ['label' => $this->t('Publicados'), 'state' => 'published'],
    ];
    $listTabs = [];
    foreach ($states as $id => $item) {
      $listTabs[$id] = [
        'label' => $item['label'],
        'weight' => count($listTabs),
        'listing' => ['bundle' => 'wiki_entry', 'workflow' => 'wiki_editorial', 'state' => $item['state']],
        'actions' => [],
      ];
    }
    return [
      'verbetes' => [
        'label' => $this->t('Verbetes'),
        'weight' => 0,
        'tabs' => $listTabs,
      ],
    ];
  }

}
