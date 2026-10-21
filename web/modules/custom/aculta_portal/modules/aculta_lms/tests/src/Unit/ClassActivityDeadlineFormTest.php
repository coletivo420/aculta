<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms\Unit;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\CurrentRouteMatch;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\aculta_lms\Form\ClassActivityDeadlineForm;
use Drupal\group\Entity\GroupInterface;
use Drupal\lms\Entity\ActivityInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\user\UserInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the educator-facing class activity deadline form.
 */
#[CoversClass(ClassActivityDeadlineForm::class)]
#[Group('aculta_lms')]
final class ClassActivityDeadlineFormTest extends UnitTestCase {

  /**
   * The form interprets the entered deadline in the educator's account zone.
   */
  public function testDateInputUsesEducatorTimezone(): void {
    $form = $this->createForm('America/Sao_Paulo');
    $build = $form->buildForm([], $this->createMock(\Drupal\Core\Form\FormStateInterface::class));

    self::assertSame('America/Sao_Paulo', $build['due_at']['#date_timezone']);
    self::assertStringContainsString('America/Sao_Paulo', (string) $build['intro']['#description']);
  }

  /**
   * Accounts without a timezone use UTC consistently.
   */
  public function testDateInputFallsBackToUtc(): void {
    $form = $this->createForm(NULL);
    $build = $form->buildForm([], $this->createMock(\Drupal\Core\Form\FormStateInterface::class));

    self::assertSame('UTC', $build['due_at']['#date_timezone']);
    self::assertStringContainsString('UTC', (string) $build['intro']['#description']);
  }

  /**
   * Builds the form with only the entities and services it needs.
   */
  private function createForm(?string $timezone): ClassActivityDeadlineForm {
    $class = $this->createMock(GroupInterface::class);
    $class->method('id')->willReturn('12');
    $class->method('label')->willReturn('Turma teste');
    $class->method('getRelationshipsByEntity')->willReturn([]);
    $activity = $this->createMock(ActivityInterface::class);
    $activity->method('label')->willReturn('Atividade teste');
    $course = $this->createMock(GroupInterface::class);
    $course->method('id')->willReturn('3');

    $routeMatch = $this->createMock(CurrentRouteMatch::class);
    $routeMatch->method('getParameter')->willReturnMap([
      ['course', $course],
      ['group', $class],
      ['activity', $activity],
    ]);
    $account = $this->createMock(AccountProxyInterface::class);
    $account->method('id')->willReturn('7');
    $user = $this->createMock(UserInterface::class);
    $user->method('getTimeZone')->willReturn($timezone);
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->with('7')->willReturn($user);
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')->with('user')->willReturn($storage);

    $form = new ClassActivityDeadlineForm($entityTypeManager, $routeMatch, $account);
    $form->setStringTranslation($this->getStringTranslationStub());
    return $form;
  }

}
