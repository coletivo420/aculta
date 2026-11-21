<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum\Unit;

use Drupal\aculta_forum\Form\TopicPopupLayout;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Proves the topic popup renders the Forum guidelines SDC.
 */
#[CoversClass(TopicPopupLayout::class)]
#[Group('aculta_forum')]
final class TopicPopupLayoutTest extends UnitTestCase {

  /**
   * The topic popup mounts forum-composer-guidelines collapsed, with tips.
   */
  public function testTopicPopupMountsForumComposerGuidelines(): void {
    $container = new ContainerBuilder();
    $container->set('string_translation', $this->getStringTranslationStub());
    \Drupal::setContainer($container);
    $form = [
      'title' => [],
      'body' => [],
      'field_aculta_tags' => [],
      'poll_builder' => [
        '#type' => 'container',
        'poll_enabled' => ['#type' => 'checkbox', '#name' => 'poll_enabled'],
        'poll' => [
          '#type' => 'container',
          '#states' => ['visible' => [':input[name="poll_enabled"]' => ['checked' => TRUE]]],
          'poll_options' => ['#type' => 'textarea', '#name' => 'poll_options'],
        ],
      ],
      'actions' => [],
      'unrelated_admin_field' => [],
    ];
    TopicPopupLayout::apply($form);

    self::assertSame(['discussion_topic_composer'], $form['#theme_wrappers']);
    self::assertArrayHasKey('field_aculta_tags', $form);
    self::assertArrayHasKey('poll_builder', $form);
    self::assertArrayNotHasKey('unrelated_admin_field', $form);
    self::assertSame('poll_enabled', $form['poll_builder']['poll_enabled']['#name']);
    self::assertSame('poll_options', $form['poll_builder']['poll']['poll_options']['#name']);
    self::assertSame(['visible' => [':input[name="poll_enabled"]' => ['checked' => TRUE]]], $form['poll_builder']['poll']['#states']);
    self::assertArrayHasKey('forum_composer_guidelines', $form);
    $guidelines = $form['forum_composer_guidelines'];
    self::assertIsArray($guidelines);
    self::assertSame('portal_component', $guidelines['#theme']);
    self::assertSame('forum-composer-guidelines', $guidelines['#name']);
    $props = $guidelines['#props'] ?? NULL;
    self::assertIsArray($props);
    self::assertTrue($props['collapsed'] ?? NULL);
    self::assertIsArray($props['tips'] ?? NULL);
    self::assertCount(3, $props['tips']);
    self::assertArrayHasKey('community_rules', $props);
    self::assertNull($props['community_rules']);
  }

}
