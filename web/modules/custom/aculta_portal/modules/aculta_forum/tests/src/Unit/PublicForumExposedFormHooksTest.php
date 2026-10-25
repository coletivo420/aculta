<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum\Unit;

use Drupal\aculta_forum\Hook\PublicForumExposedFormHooks;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\taxonomy\TermInterface;
use Drupal\taxonomy\TermStorageInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\views\ViewExecutable;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests safe CAPTCHA and category controls on read-only Forum Views filters.
 */
#[Group('aculta_forum')]
final class PublicForumExposedFormHooksTest extends UnitTestCase {

  /**
   * CAPTCHA is removed from the public listing filters only.
   */
  public function testCaptchaIsRemovedFromPublicListingFilters(): void {
    foreach (['page_discussions', 'page_new_topics', 'page_unanswered', 'page_most_commented'] as $display) {
      $view = $this->view('aculta_forum_public_topics', $display);
      $form_state = $this->formState($view);
      $form = ['captcha' => ['#type' => 'captcha'], 'categoria' => ['#type' => 'select']];

      $this->hook()->formViewsExposedFormAlter($form, $form_state, 'views_exposed_form');

      self::assertArrayNotHasKey('captcha', $form, $display);
      self::assertSame('select', $form['categoria']['#type']);
    }
  }

  /**
   * Search has an access-aware human-readable category selector and no CAPTCHA.
   */
  public function testSearchUsesAccessCheckedCategoryOptions(): void {
    $visible = $this->createMock(TermInterface::class);
    $visible->method('id')->willReturn('76');
    $visible->method('label')->willReturn('Cultivo indoor');
    $visible->method('getCacheTags')->willReturn([]);
    $visible->method('getCacheContexts')->willReturn([]);
    $visible->method('getCacheMaxAge')->willReturn(Cache::PERMANENT);
    $visible->method('access')->with('view', self::isInstanceOf(AccountInterface::class), TRUE)->willReturn(AccessResult::allowed());

    $hidden = $this->createMock(TermInterface::class);
    $hidden->method('id')->willReturn('77');
    $hidden->method('label')->willReturn('Categoria privada');
    $hidden->method('getCacheTags')->willReturn([]);
    $hidden->method('getCacheContexts')->willReturn([]);
    $hidden->method('getCacheMaxAge')->willReturn(Cache::PERMANENT);
    $hidden->method('access')->with('view', self::isInstanceOf(AccountInterface::class), TRUE)->willReturn(AccessResult::forbidden());

    $storage = $this->createMock(TermStorageInterface::class);
    $storage->method('loadTree')->with('aculta_forum_categories', 0, NULL, TRUE)->willReturn([$visible, $hidden]);
    $entityType = $this->createMock(EntityTypeInterface::class);
    $entityType->method('getListCacheTags')->willReturn(['taxonomy_term_list']);
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')->with('taxonomy_term')->willReturn($storage);
    $entityTypeManager->method('getDefinition')->with('taxonomy_term')->willReturn($entityType);
    $entityRepository = $this->createMock(EntityRepositoryInterface::class);
    $entityRepository->method('getTranslationFromContext')->willReturnCallback(static fn (TermInterface $term): TermInterface => $term);

    $view = $this->view('aculta_forum_search', 'page_search');
    $form_state = $this->formState($view);
    $form = [
      'captcha' => ['#type' => 'captcha'],
      'categoria' => ['#type' => 'textfield', '#default_value' => ''],
    ];

    $this->hook($entityTypeManager, $entityRepository)->formViewsExposedFormAlter($form, $form_state, 'views_exposed_form');

    self::assertArrayNotHasKey('captcha', $form);
    self::assertSame('select', $form['categoria']['#type']);
    self::assertSame(['76' => 'Cultivo indoor'], $form['categoria']['#options']);
    self::assertInstanceOf(TranslatableMarkup::class, $form['categoria']['#empty_option']);
    self::assertSame('Todas as categorias', $form['categoria']['#empty_option']->getUntranslatedString());
    self::assertContains('taxonomy_term_list', $form['categoria']['#cache']['tags']);
  }

  /**
   * CAPTCHA remains on unrelated Views forms.
   */
  public function testCaptchaIsKeptForOtherViews(): void {
    $view = $this->view('other_view', 'page_1');
    $form_state = $this->formState($view);
    $form = ['captcha' => ['#type' => 'captcha']];

    $this->hook()->formViewsExposedFormAlter($form, $form_state, 'views_exposed_form');

    self::assertArrayHasKey('captcha', $form);
  }

  /**
   * Creates the hook with injectable collaborators.
   */
  private function hook(
    ?EntityTypeManagerInterface $entityTypeManager = NULL,
    ?EntityRepositoryInterface $entityRepository = NULL,
  ): PublicForumExposedFormHooks {
    return new PublicForumExposedFormHooks(
      $entityTypeManager ?? $this->createMock(EntityTypeManagerInterface::class),
      $this->createMock(AccountInterface::class),
      $entityRepository ?? $this->createMock(EntityRepositoryInterface::class),
      $this->createMock(TranslationInterface::class),
    );
  }

  /**
   * Creates a Views executable mock for the requested display.
   */
  private function view(string $id, string $display): ViewExecutable {
    $view = $this->createMock(ViewExecutable::class);
    $view->method('id')->willReturn($id);
    $view->current_display = $display;
    return $view;
  }

  /**
   * Creates Form API state containing a Views executable.
   */
  private function formState(ViewExecutable $view): FormStateInterface {
    $formState = $this->createMock(FormStateInterface::class);
    $formState->method('get')->with('view')->willReturn($view);
    $formState->method('getUserInput')->willReturn([]);
    return $formState;
  }

}
