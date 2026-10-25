<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_activity\Unit\Hook;

use Drupal\aculta_activity\Hook\ActivityHooks;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\node\NodeInterface;
use Drupal\Tests\UnitTestCase;

/** @coversDefaultClass \Drupal\aculta_activity\Hook\ActivityHooks */
#[\PHPUnit\Framework\Attributes\CoversClass(ActivityHooks::class)]
#[\PHPUnit\Framework\Attributes\Group('aculta_activity')]
final class ActivityHooksTest extends UnitTestCase {

  /** @covers ::formNodeFormAlter */
  public function testActivityFormIsGroupedAndUsesOwnerCallback(): void {
    $node = $this->createMock(NodeInterface::class);
    $node->method('bundle')->willReturn('activity');
    $formObject = new class($node) {

      public function __construct(private readonly NodeInterface $node) {}

      public function getEntity(): NodeInterface {
        return $this->node;
      }

    };
    $formState = $this->createMock(FormStateInterface::class);
    $formState->method('getFormObject')->willReturn($formObject);
    $translation = $this->createMock(TranslationInterface::class);
    $translation->method('translate')->willReturnArgument(0);

    $form = [
      'title' => [],
      'body' => [],
      'field_media_image' => [],
      'field_gallery' => [],
      'field_related_activity' => [],
      'field_meta_tags' => [],
      'author' => [],
      'options' => [],
    ];
    $hooks = new ActivityHooks($translation);
    $hooks->formNodeFormAlter($form, $formState, 'node_activity_form');

    self::assertSame('editorial_content', $form['title']['#group']);
    self::assertSame('editorial_image', $form['field_media_image']['#group']);
    self::assertSame('editorial_relations', $form['field_related_activity']['#group']);
    self::assertSame('editorial_seo', $form['field_meta_tags']['#group']);
    self::assertSame(['aculta_activity.form_callbacks:validateActivity'], $form['#validate']);
  }

  /** @covers ::formNodeFormAlter */
  public function testOtherBundlesAreUnaffected(): void {
    $node = $this->createMock(NodeInterface::class);
    $node->method('bundle')->willReturn('revista');
    $formObject = new class($node) {

      public function __construct(private readonly NodeInterface $node) {}

      public function getEntity(): NodeInterface {
        return $this->node;
      }

    };
    $formState = $this->createMock(FormStateInterface::class);
    $formState->method('getFormObject')->willReturn($formObject);
    $translation = $this->createMock(TranslationInterface::class);

    $form = ['title' => []];
    (new ActivityHooks($translation))->formNodeFormAlter($form, $formState, 'node_revisa_form');

    self::assertSame(['title' => []], $form);
  }

}
