<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum_lms\Unit;

use Drupal\aculta_forum_lms\Context\Lmsaculta_H5PDiscussionResolver;
use Drupal\aculta_forum_lms\Hook\Lmsaculta_H5PDiscussionHooks;
use Drupal\aculta_lms_h5p\Access\aculta_H5PCourseAccessManager;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\group\Entity\GroupInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Tests the Group context passed to H5P topic autocomplete. */
#[CoversClass(Lmsaculta_H5PDiscussionHooks::class)]
#[Group('aculta_forum_lms')]
final class Lmsaculta_H5PDiscussionHooksTest extends UnitTestCase {

  /**
   * A Group-scoped topic form carries its Group ID to autocomplete. */
  public function testTopicFormAddsGroupIdToAutocompleteRequest(): void {
    $group = $this->createMock(GroupInterface::class);
    $group->method('id')->willReturn('22');
    $request = Request::create('/group/22/content/create/group_node:aculta_lms_topic');
    $request->attributes->set('group', $group);
    $requestStack = new RequestStack();
    $requestStack->push($request);

    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $resolver = new Lmsaculta_H5PDiscussionResolver(
      $entityTypeManager,
      $this->createMock(TranslationInterface::class),
    );
    $hook = new Lmsaculta_H5PDiscussionHooks(
      $resolver,
      new aculta_H5PCourseAccessManager($entityTypeManager),
      $this->createMock(AccountProxyInterface::class),
      $entityTypeManager,
      $requestStack,
    );
    $form = [
      'field_h5p_content' => [
        'widget' => [
          0 => ['target_id' => ['#type' => 'entity_autocomplete']],
        ],
      ],
    ];

    $hook->topicFormAlter($form, new FormState());

    self::assertSame('22', $form['field_h5p_content']['widget'][0]['target_id']['#autocomplete_query_parameters']['group_id']);
  }

}
