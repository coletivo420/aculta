<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms\Kernel;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\webform\Entity\Webform;
use Drupal\webform\Entity\WebformSubmission;
use PHPUnit\Framework\Attributes\Group as TestGroup;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/** Verifies Webform Score access against persisted submission ownership. */
#[TestGroup('aculta_lms')]
#[RunTestsInSeparateProcesses]
final class WebformScoreOwnershipTest extends KernelTestBase {

  use UserCreationTrait;

  protected static $modules = [
    'system',
    'user',
    'field',
    'webform',
    'fraction',
    'webform_score',
  ];

  protected function setUp(): void {
    parent::setUp();
    $this->installSchema('webform', ['webform']);
    $this->installConfig(['webform']);
    $this->installEntitySchema('user');
    $this->installEntitySchema('webform_submission');
  }

  public function testOnlyOwnerWithPermissionCanViewPersistedSubmissionScore(): void {
    $owner = $this->createUser();
    $webform = Webform::create(['id' => 'score_ownership']);
    $webform->save();
    $submission = WebformSubmission::create([
      'webform_id' => $webform->id(),
      'uid' => $owner->id(),
      'data' => ['resposta' => 'correta'],
    ]);
    $submission->save();
    $submission = WebformSubmission::load($submission->id());
    // Webform Score computes scores from configured quiz elements. This test
    // targets access to an existing score, so populate the field after entity
    // save rather than pretending an empty form has a calculated score.
    $submission->set('webform_score', ['numerator' => 1, 'denominator' => 1]);

    $field = $submission->getFieldDefinition('webform_score');
    self::assertInstanceOf(FieldDefinitionInterface::class, $field);
    $items = $submission->get('webform_score');

    $owner_access = webform_score_entity_field_access('view', $field, $this->account((int) $owner->id()), $items);
    self::assertTrue($owner_access->isNeutral());
    self::assertContains('user', $owner_access->getCacheContexts());

    $other_access = webform_score_entity_field_access('view', $field, $this->account((int) $owner->id() + 1), $items);
    self::assertTrue($other_access->isForbidden());
    self::assertContains('user', $other_access->getCacheContexts());

    $edit_access = webform_score_entity_field_access('edit', $field, $this->account((int) $owner->id()), $items);
    self::assertTrue($edit_access->isForbidden());
  }

  private function account(int $id): AccountInterface {
    $account = $this->createMock(AccountInterface::class);
    $account->method('id')->willReturn((string) $id);
    $account->method('hasPermission')->willReturnCallback(static fn (string $permission): bool => $permission === 'view own submission score');
    return $account;
  }

}
