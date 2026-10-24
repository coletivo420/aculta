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
 * Wiki tab owned by the Wiki module.
 *
 * The owner provides node type wiki_entry, workflow wiki_editorial, and
 * authorized queue services. GanjaLab presents those services without owning
 * data or access rules.
 */
#[StudioTab(
  id: 'wiki',
  label: new TranslatableMarkup('Wiki'),
  weight: 25,
)]
final class WikiTab extends StudioTabBase {

  /**
   * {@inheritdoc}
   */
  public function isAvailable(): bool {
    return $this->moduleHandler->moduleExists('aculta_wiki')
      && $this->entityTypeManager->getStorage('node_type')->load('wiki_entry') !== NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function access(AccountInterface $account): AccessResultInterface {
    return AccessResult::allowedIfHasPermission($account, 'edit any wiki_entry content')
      ->orIf(AccessResult::allowedIfHasPermission($account, 'moderate wiki proposals'))
      ->orIf(AccessResult::allowedIfHasPermission($account, 'view wiki_entry revisions'));
  }

  /**
   * {@inheritdoc}
   */
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
    $sections = [];
    // Contributor-owned work belongs in WE SATIVA. GanjaLab lists the shared
    // editorial inventory only for accounts authorized to edit any entry.
    if ($account->hasPermission('edit any wiki_entry content')) {
      $sections['verbetes'] = [
        'label' => $this->t('Verbetes'),
        'weight' => 0,
        'tabs' => $listTabs,
      ];
    }
    if ($account->hasPermission('view wiki_entry revisions')) {
      $sections['revisoes'] = [
        'label' => $this->t('Revisões'),
        'weight' => 10,
        'tabs' => [
          'todas' => [
            'label' => $this->t('Todas'),
            'weight' => 0,
            'listing' => ['entity' => 'revision', 'bundle' => 'wiki_entry'],
            'actions' => [],
          ],
        ],
      ];
    }

    $canReviewEntries = $account->hasPermission('edit any wiki_entry content')
      && ($account->hasPermission('use wiki_editorial transition review_to_draft')
        || $account->hasPermission('use wiki_editorial transition review_to_published'));
    if ($canReviewEntries) {
      $sections['moderacao'] = [
        'label' => $this->t('Moderação'),
        'weight' => 10,
        'tabs' => [
          'revisoes' => [
            'label' => $this->t('Verbetes em revisão'),
            'weight' => 0,
            'listing' => ['entity' => 'wiki_entry_review_queue'],
            'actions' => [],
          ],
        ],
      ];
    }
    if ($this->moduleHandler->moduleExists('aculta_wiki') && $account->hasPermission('moderate wiki proposals')) {
      $sections['moderacao'] ??= [
        'label' => $this->t('Moderação'),
        'weight' => 10,
        'tabs' => [],
      ];
      $sections['moderacao']['tabs']['propostas'] = [
        'label' => $this->t('Propostas'),
        'weight' => 1,
        'listing' => ['entity' => 'wiki_proposal_queue'],
        'actions' => [],
      ];
    }
    return $sections;
  }

}
