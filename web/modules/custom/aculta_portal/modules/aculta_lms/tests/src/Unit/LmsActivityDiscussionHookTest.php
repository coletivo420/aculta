<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms\Unit;

use Drupal\aculta_lms\Hook\LmsActivityDiscussionHooks;
use Drupal\aculta_lms\Form\AnswerSubmissionForm;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Verifies registration of activity discussion hooks.
 */
#[Group('aculta_lms')]
final class LmsActivityDiscussionHookTest extends UnitTestCase {

  /**
   * The Form API hook name matches the answer form's declared ID.
   */
  public function testActivityDiscussionHookMatchesAnswerFormId(): void {
    $form = (new \ReflectionClass(AnswerSubmissionForm::class))->newInstanceWithoutConstructor();
    $method = new \ReflectionMethod(LmsActivityDiscussionHooks::class, 'answerFormAlter');
    $attributes = $method->getAttributes(Hook::class);

    self::assertCount(1, $attributes);
    self::assertSame('form_' . $form->getFormId() . '_alter', $attributes[0]->newInstance()->hook);
  }

}
