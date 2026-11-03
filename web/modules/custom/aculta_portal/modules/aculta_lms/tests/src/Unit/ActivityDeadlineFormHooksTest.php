<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms\Unit;

use Drupal\aculta_lms\Deadline\ClassActivityDeadlineManager;
use Drupal\aculta_lms\Hook\ActivityDeadlineFormHooks;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ActivityDeadlineFormHooks::class)]
final class ActivityDeadlineFormHooksTest extends UnitTestCase {

  public function testNonAnswerFormIsLeftUnchanged(): void {
    $form = ['existing' => ['#plain_text' => 'answer form']];
    $state = $this->createMock(FormStateInterface::class);
    $state->method('get')->willReturn(NULL);
    $hooks = new ActivityDeadlineFormHooks(
      (new \ReflectionClass(ClassActivityDeadlineManager::class))->newInstanceWithoutConstructor(),
      $this->createMock(DateFormatterInterface::class),
      $this->createMock(EntityTypeManagerInterface::class),
      $this->createMock(AccountProxyInterface::class),
      $this->getStringTranslationStub(),
    );

    $hooks->answerFormAlter($form, $state);

    self::assertSame(['existing' => ['#plain_text' => 'answer form']], $form);
  }

}
