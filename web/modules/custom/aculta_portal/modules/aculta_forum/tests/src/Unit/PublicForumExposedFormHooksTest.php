<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum\Unit;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\views\ViewExecutable;
use Drupal\aculta_forum\Hook\PublicForumExposedFormHooks;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the read-only public forum Views filter CAPTCHA exception.
 */
#[Group('aculta_forum')]
final class PublicForumExposedFormHooksTest extends UnitTestCase {

  /**
   * CAPTCHA is removed only from the public topics page filter.
   */
  public function testCaptchaIsRemovedOnlyFromPublicTopicFilter(): void {
    $view = $this->createMock(ViewExecutable::class);
    $view->method('id')->willReturn('aculta_forum_public_topics');
    $view->current_display = 'page_discussions';
    $form_state = $this->createMock(FormStateInterface::class);
    $form_state->method('get')->with('view')->willReturn($view);
    $form = ['captcha' => ['#type' => 'captcha'], 'categoria' => ['#type' => 'select']];

    (new PublicForumExposedFormHooks())->formViewsExposedFormAlter($form, $form_state, 'views_exposed_form');

    self::assertArrayNotHasKey('captcha', $form);
    self::assertArrayHasKey('categoria', $form);
  }

  /**
   * CAPTCHA is removed from each public discovery listing filter.
   */
  public function testCaptchaIsRemovedFromAdditionalReadOnlyListings(): void {
    foreach (['page_new_topics', 'page_unanswered', 'page_most_commented'] as $display) {
      $view = $this->createMock(ViewExecutable::class);
      $view->method('id')->willReturn('aculta_forum_public_topics');
      $view->current_display = $display;
      $form_state = $this->createMock(FormStateInterface::class);
      $form_state->method('get')->with('view')->willReturn($view);
      $form = ['captcha' => ['#type' => 'captcha']];

      (new PublicForumExposedFormHooks())->formViewsExposedFormAlter($form, $form_state, 'views_exposed_form');

      self::assertArrayNotHasKey('captcha', $form, $display);
    }
  }

  /**
   * CAPTCHA remains on other Views forms.
   */
  public function testCaptchaIsKeptForOtherViews(): void {
    $view = $this->createMock(ViewExecutable::class);
    $view->method('id')->willReturn('other_view');
    $view->current_display = 'page_1';
    $form_state = $this->createMock(FormStateInterface::class);
    $form_state->method('get')->with('view')->willReturn($view);
    $form = ['captcha' => ['#type' => 'captcha']];

    (new PublicForumExposedFormHooks())->formViewsExposedFormAlter($form, $form_state, 'views_exposed_form');

    self::assertArrayHasKey('captcha', $form);
  }

}
