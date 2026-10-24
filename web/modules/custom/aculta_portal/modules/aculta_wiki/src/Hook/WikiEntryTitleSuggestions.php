<?php

declare(strict_types=1);

namespace Drupal\aculta_wiki\Hook;

use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityFormInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\node\NodeInterface;

/**
 * Provides an optional, access-checked title similarity check for new entries.
 */
final class WikiEntryTitleSuggestions {

  use StringTranslationTrait;

  /**
   * Form-state key containing the most recent title-check results.
   */
  private const STORAGE_KEY = 'aculta_wiki.title_suggestions';

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly DomainPurposeManager $domainPurposes,
    private readonly Connection $database,
    private readonly AccountInterface $currentUser,
    TranslationInterface $stringTranslation,
  ) {
    $this->stringTranslation = $stringTranslation;
  }

  /**
   * Adds the optional title-check action to the native creation form.
   */
  #[Hook('form_node_wiki_entry_form_alter')]
  #[Hook('form_node_wiki_entry_edit_form_alter')]
  public function formAlter(array &$form, FormStateInterface $form_state): void {
    $formObject = $form_state->getFormObject();
    $node = $formObject instanceof EntityFormInterface
      ? $formObject->getEntity()
      : NULL;
    if (!$node instanceof NodeInterface || $node->bundle() !== 'wiki_entry' || !$node->isNew()) {
      return;
    }

    if (isset($form['title'], $form['actions']['submit'])) {
      $form['actions']['check_similar_titles'] = [
        '#type' => 'submit',
        '#value' => $this->t('Verificar títulos parecidos'),
        '#submit' => [self::class . ':checkSimilarTitles'],
        '#limit_validation_errors' => [$form['title']['#parents'] ?? ['title']],
        '#weight' => -10,
      ];
    }

    $results = $form_state->get(self::STORAGE_KEY);
    if (!is_array($results) || !isset($results['term'], $results['ids']) || !isset($form['title'])) {
      return;
    }

    $submittedTitle = trim((string) $form_state->getValue(['title', 0, 'value'], ''));
    if ($submittedTitle === '' || mb_strtolower($submittedTitle) !== mb_strtolower((string) $results['term'])) {
      return;
    }

    $form['similar_titles'] = [
      '#type' => 'container',
      '#weight' => 0.5,
      '#attributes' => ['data-portal-ui' => 'wiki-title-suggestions'],
      '#cache' => ['max-age' => 0],
      'heading' => [
        '#type' => 'html_tag',
        '#tag' => 'h2',
        '#value' => $this->t('Verifique se algum verbete já aborda este tema'),
      ],
      'term' => ['#plain_text' => $this->t('Títulos relacionados a “@title”', ['@title' => $results['term']])],
    ];

    $items = $this->loadVisibleSuggestions($results['ids']);
    if ($items === []) {
      $form['similar_titles']['empty'] = ['#plain_text' => $this->t('Nenhum verbete publicado com título parecido foi encontrado. Você pode continuar a criação.')];
    }
    else {
      $form['similar_titles']['list'] = [
        '#theme' => 'item_list',
        '#items' => $items,
      ];
    }
    $form['similar_titles']['continue'] = ['#plain_text' => $this->t('Esta verificação é apenas uma sugestão; ela não impede a criação do verbete.')];
  }

  /**
   * Stores visible title matches and rebuilds without saving the Node.
   */
  public function checkSimilarTitles(array &$form, FormStateInterface $form_state): void {
    $title = trim((string) $form_state->getValue(['title', 0, 'value'], ''));
    if (mb_strlen($title) < 3 || mb_strlen($title) > 255) {
      $form_state->setErrorByName('title', $this->t('Digite pelo menos três caracteres para verificar títulos parecidos.'));
      return;
    }

    $ids = $this->domainPurposes->runInPurpose('wiki', function () use ($title): array {
      $domain = $this->domainPurposes->getDomain('wiki');
      if ($domain === NULL) {
        return [];
      }
      $pattern = '%' . $this->database->escapeLike($title) . '%';
      return $this->entityTypeManager->getStorage('node')->getQuery()
        ->accessCheck(TRUE)
        ->addMetaData('account', $this->currentUser)
        ->condition('type', 'wiki_entry')
        ->condition('field_domain_source.target_id', $domain->id())
        ->condition('status', 1)
        ->condition('title', $pattern, 'LIKE')
        ->sort('title')
        ->range(0, 6)
        ->execute();
    });

    $form_state->set(self::STORAGE_KEY, [
      'term' => $title,
      'ids' => is_array($ids) ? array_values($ids) : [],
    ]);
    $form_state->setRebuild();
  }

  /**
   * Loads and revalidates public matches before rendering links.
   *
   * @param array $ids
   *   Candidate node IDs from the private Form API state.
   *
   * @return array
   *   Renderable, access-checked title links.
   */
  private function loadVisibleSuggestions(array $ids): array {
    return $this->domainPurposes->runInPurpose('wiki', function () use ($ids): array {
      $storage = $this->entityTypeManager->getStorage('node');
      $items = [];
      foreach ($storage->loadMultiple($ids) as $node) {
        if (!$node instanceof NodeInterface || !$node->isPublished() || !$node->access('view')) {
          continue;
        }
        $items[] = [
          '#type' => 'link',
          '#title' => $node->label(),
          '#url' => $node->toUrl('canonical'),
        ];
      }
      return $items;
    }) ?? [];
  }

}
