<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Hook;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Hook\Order\Order;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\taxonomy\TermStorageInterface;
use Drupal\views\ViewExecutable;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Keeps read-only public Forum filters usable with global CAPTCHA enabled.
 */
final class PublicForumExposedFormHooks {

  use StringTranslationTrait;

  public function __construct(
    #[Autowire(service: 'entity_type.manager')]
    private readonly EntityTypeManagerInterface $entityTypeManager,
    #[Autowire(service: 'current_user')]
    private readonly AccountInterface $currentUser,
    #[Autowire(service: 'entity.repository')]
    private readonly EntityRepositoryInterface $entityRepository,
    #[Autowire(service: 'string_translation')]
    TranslationInterface $stringTranslation,
  ) {
    $this->stringTranslation = $stringTranslation;
  }

  /**
   * Adjusts public read-only listing/search filters and removes their CAPTCHA.
   *
   * The forms only query public topics and cannot mutate content. The search
   * category selector uses current Taxonomy labels and access, never TIDs in
   * the visible interface.
   */
  #[Hook('form_alter', order: Order::Last)]
  public function formViewsExposedFormAlter(array &$form, FormStateInterface $form_state, string $form_id): void {
    $view = $form_state->get('view');
    if (!$view instanceof ViewExecutable) {
      return;
    }

    $isListing = $view->id() === 'aculta_forum_public_topics'
      && in_array($view->current_display, [
        'page_discussions',
        'page_new_topics',
        'page_unanswered',
        'page_most_commented',
      ], TRUE);
    $isSearch = $view->id() === 'aculta_forum_search'
      && $view->current_display === 'page_search';
    if (!$isListing && !$isSearch) {
      return;
    }

    unset($form['captcha']);
    if (!$isSearch || !isset($form['categoria'])) {
      return;
    }

    [$options, $cacheability] = $this->categoryOptions();
    $input = $form_state->getUserInput();
    $selected = $input['categoria']
      ?? $form['categoria']['#value']
      ?? $form['categoria']['#default_value']
      ?? '';
    $form['categoria']['#type'] = 'select';
    $form['categoria']['#options'] = $options;
    $form['categoria']['#empty_option'] = $this->t('Todas as categorias');
    $form['categoria']['#empty_value'] = '';
    $form['categoria']['#default_value'] = $selected;
    unset(
      $form['categoria']['#size'],
      $form['categoria']['#maxlength'],
      $form['categoria']['#autocomplete_route_name'],
      $form['categoria']['#autocomplete_route_parameters'],
    );
    $cacheability->applyTo($form['categoria']);
  }

  /**
   * Builds hierarchical category options using current taxonomy access.
   *
   * @return array{0: array<int, string>, 1: \Drupal\Core\Cache\CacheableMetadata}
   *   Select options keyed by term ID and their cacheability metadata.
   */
  private function categoryOptions(): array {
    $storage = $this->entityTypeManager->getStorage('taxonomy_term');
    assert($storage instanceof TermStorageInterface);
    $cacheability = (new CacheableMetadata())
      ->addCacheContexts(['user.permissions'])
      ->addCacheTags($this->entityTypeManager->getDefinition('taxonomy_term')->getListCacheTags());
    $options = [];
    foreach ($storage->loadTree('aculta_forum_categories', 0, NULL, TRUE) as $term) {
      $access = $term->access('view', $this->currentUser, TRUE);
      $cacheability->addCacheableDependency($term)->addCacheableDependency($access);
      if (!$access->isAllowed()) {
        continue;
      }

      $translated = $this->entityRepository->getTranslationFromContext($term);
      $depth = isset($term->depth) ? (int) $term->depth : 0;
      $options[(int) $term->id()] = str_repeat('— ', $depth) . $translated->label();
    }

    return [$options, $cacheability];
  }

}
