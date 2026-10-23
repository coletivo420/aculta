<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_index\Kernel;

use Drupal\aculta_forum\Entity\ForumPost;
use Drupal\aculta_forum\Hook\RestrictedForumAccessHooks;
use Drupal\comment\Entity\Comment;
use Drupal\aculta_index\Query\ContentIndexAccessFilter;
use Drupal\domain\DomainNegotiatorInterface;
use Drupal\domain\Entity\Domain;
use Drupal\node\Entity\Node;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Exercises Index access against the real Forum grants and Domain policies.
 */
#[Group('aculta_index')]
#[RunTestsInSeparateProcesses]
final class ContentIndexForumAccessKernelTest extends ForumAccessKernelTestBase {

  /**
   * Revalidates topic and discussion entities with real Group grants.
   */
  public function testIndexFilterUsesForumDomainAndRestrictedGroupAccess(): void {
    $member = $this->createUser('forum-member');
    $this->setAssociateActive($member);
    $outsider = $this->createUser('forum-outsider');
    $group = $this->container->get('entity_type.manager')->getStorage('group')->create([
      'type' => self::GROUP_TYPE,
      'label' => 'Restricted forum group',
      'uid' => $member->id(),
    ]);
    $group->save();
    $group->addMember($member);

    $topic = Node::create([
      'type' => 'aculta_forum_topic',
      'title' => 'Restricted topic',
      'status' => TRUE,
      'uid' => $member->id(),
      'field_domain_source' => ['target_id' => 'forum_aculta_org'],
    ]);
    $topic->save();
    $group->addRelationship($topic, 'group_node:aculta_forum_topic')->save();
    // Re-save so Core writes the production node_access_records grant.
    $topic->save();
    $forumAccessHooks = $this->container->get(RestrictedForumAccessHooks::class);
    self::assertSame(
      [RestrictedForumAccessHooks::REALM => [(int) $group->id()]],
      $forumAccessHooks->nodeGrants($member, 'view'),
      'The production grant hook derives view grants from the real Group membership.',
    );
    self::assertSame([[
      'realm' => RestrictedForumAccessHooks::REALM,
      'gid' => (int) $group->id(),
      'grant_view' => 1,
      'grant_update' => 0,
      'grant_delete' => 0,
    ],
    ], $forumAccessHooks->nodeAccessRecords($topic));

    $post = ForumPost::create([
      'uid' => $member->id(),
      'topic' => $topic->id(),
      'subject' => 'Restricted reply',
      'body' => ['value' => 'Not projected', 'format' => 'plain_text'],
      'status' => TRUE,
    ]);
    $post->save();

    $comment = Comment::create([
      'entity_type' => 'node',
      'entity_id' => (int) $topic->id(),
      'field_name' => 'field_aculta_discussion',
      'comment_type' => 'aculta_discussion_post',
      'uid' => $member->id(),
      'subject' => 'Legacy reply',
      'comment_body' => ['value' => 'Not projected', 'format' => 'plain_text'],
      'status' => 1,
    ]);
    $comment->save();

    $mainDomain = Domain::load('aculta_org');
    self::assertInstanceOf(Domain::class, $mainDomain);
    $negotiator = $this->container->get(DomainNegotiatorInterface::class);
    $negotiator->getActiveDomain();
    $negotiator->setActiveDomain($mainDomain);
    $filter = new ContentIndexAccessFilter(
      $this->container->get('entity_type.manager'),
      $this->container->get('aculta_index.provider_registry'),
      $this->container->get('aculta_portal.domain_purpose'),
    );
    $candidates = [
      [[
        'entity_type' => 'node',
        'entity_id' => (string) $topic->id(),
        'langcode' => $topic->language()->getId(),
      ],
      ],
      [[
        'entity_type' => 'aculta_forum_post',
        'entity_id' => (string) $post->id(),
        'langcode' => $post->language()->getId(),
      ],
      ],
      [[
        'entity_type' => 'comment',
        'entity_id' => (string) $comment->id(),
        'langcode' => $comment->language()->getId(),
      ],
      ],
    ];

    foreach ($candidates as $candidate) {
      self::assertCount(1, $filter->filter($candidate, $member)->entities);
      self::assertSame('main', $this->container->get('aculta_portal.domain_purpose')->getCurrentPurpose());
      self::assertCount(0, $filter->filter($candidate, $outsider)->entities);
      self::assertSame('main', $this->container->get('aculta_portal.domain_purpose')->getCurrentPurpose());
    }
  }

}
