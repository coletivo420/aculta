<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_portal\Unit\Hook;

use Drupal\aculta_portal\Hook\CaptchaPresentationHooks;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the neutral presentation token on CAPTCHA render elements.
 */
#[Group('aculta_portal')]
final class CaptchaPresentationHooksTest extends UnitTestCase {

  /**
   * The hook adds the token after the contributed form alter inserts CAPTCHA.
   */
  public function testMarksNestedChallengeAndPreservesExistingPresentation(): void {
    $form = [
      'wrapper' => [
        'captcha' => [
          '#type' => 'captcha',
          '#attributes' => [
            'id' => 'preexisting-challenge',
            'data-portal-ui' => 'existing-token',
          ],
        ],
      ],
      'actions' => [
        '#type' => 'actions',
      ],
    ];
    $form_state = $this->createMock(FormStateInterface::class);

    (new CaptchaPresentationHooks())->formAlter($form, $form_state, 'example_form');

    self::assertSame('preexisting-challenge', $form['wrapper']['captcha']['#attributes']['id']);
    self::assertSame(
      'existing-token challenge-frame',
      $form['wrapper']['captcha']['#attributes']['data-portal-ui'],
    );
    self::assertArrayNotHasKey('data-portal-ui', $form['actions']['#attributes'] ?? []);
  }

  /**
   * The hook is idempotent when another alter rebuilds the same form.
   */
  public function testDoesNotDuplicateThePresentationToken(): void {
    $form = [
      'captcha' => [
        '#type' => 'captcha',
        '#attributes' => ['data-portal-ui' => 'challenge-frame'],
      ],
    ];
    $form_state = $this->createMock(FormStateInterface::class);
    $hooks = new CaptchaPresentationHooks();

    $hooks->formAlter($form, $form_state, 'example_form');
    $hooks->formAlter($form, $form_state, 'example_form');

    self::assertSame('challenge-frame', $form['captcha']['#attributes']['data-portal-ui']);
  }

}
