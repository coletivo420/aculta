<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms\Unit;

use Drupal\aculta_lms\Hook\SubmissionImageHooks;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/** @coversClass(\Drupal\aculta_lms\Hook\SubmissionImageHooks::class) */
#[CoversClass(SubmissionImageHooks::class)]
#[Group('aculta_lms')]
final class SubmissionImageHooksTest extends UnitTestCase {

  public function testAddsAcultaImageConstraintToManagedAnswerUpload(): void {
    $form = [
      'file_upload' => [
        '#type' => 'managed_file',
        '#upload_validators' => [
          'FileExtension' => ['extensions' => 'png jpg pdf'],
        ],
      ],
    ];

    (new SubmissionImageHooks())->answerFormAlter($form, $this->createMock(FormStateInterface::class));

    self::assertArrayHasKey('AcultaLmsSubmissionImage', $form['file_upload']['#upload_validators']);
    self::assertSame([], $form['file_upload']['#upload_validators']['AcultaLmsSubmissionImage']);
    self::assertSame('png jpg pdf', $form['file_upload']['#upload_validators']['FileExtension']['extensions']);
  }

  public function testLeavesFormsWithoutManagedUploadAlone(): void {
    $form = ['answer' => ['#type' => 'textarea']];
    $original = $form;

    (new SubmissionImageHooks())->answerFormAlter($form, $this->createMock(FormStateInterface::class));

    self::assertSame($original, $form);
  }

}
