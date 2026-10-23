<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_index\Kernel;

use Drupal\aculta_forum\Entity\ForumPost;
use Drupal\aculta_forum\Hook\RestrictedForumAccessHooks;
use Drupal\comment\Entity\Comment;
use Drupal\comment\CommentInterface;
use Drupal\aculta_index\Query\ContentIndexAccessFilter;
use Drupal\Core\Session\AnonymousUserSession;
use Drupal\domain\DomainNegotiatorInterface;
use Drupal\domain\Entity\Domain;
use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;
use Drupal\media\Entity\MediaType;
use Drupal\node\Entity\Node;
use Drupal\user\Entity\Role;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Exercises Index access against the real Forum grants and Domain policies.
 */
#[Group('aculta_index')]
#[RunTestsInSeparateProcesses]
final class ContentIndexForumAccessKernelTest extends ForumAccessKernelTestBase {

  /**
   * Forum attachment Media candidates use the production access hook/purpose.
   */
  public function testForumAttachmentMediaUsesCanonicalPurposeAndOwnerAccess(): void {
    $member = $this->createUser('forum-attachment-owner');
    $outsider = $this->createUser('forum-attachment-outsider');
    $file = File::create([
      'uri' => 'public://forum-attachment-index-test.pdf',
      'filename' => 'forum-attachment-index-test.pdf',
      'filemime' => 'application/pdf',
      'uid' => $member->id(),
    ]);
    $file->setPermanent();
    $file->save();
    $mediaType = MediaType::load('forum_attachment');
    self::assertInstanceOf(MediaType::class, $mediaType);
    $sourceField = $mediaType->getSource()->getSourceFieldDefinition($mediaType)->getName();
    $attachment = Media::create([
      'bundle' => 'forum_attachment',
      'name' => 'Unpublished Forum attachment',
      'status' => FALSE,
      $sourceField => ['target_id' => $file->id()],
    ]);
    $attachment->setOwner($member);
    $attachment->save();

    $providers = $this->container->get('aculta_index.provider_registry');
    self::assertSame('forum_attachment', $providers->findProviderIdForBundle('media', 'forum_attachment'));
    self::assertSame('forum', $providers->get('forum_attachment')->getAccessPurpose($attachment));
    $mainDomain = Domain::load('aculta_org');
    self::assertInstanceOf(Domain::class, $mainDomain);
    $negotiator = $this->container->get(DomainNegotiatorInterface::class);
    $negotiator->getActiveDomain();
    $negotiator->setActiveDomain($mainDomain);

    $candidate = [[
      'entity_type' => 'media',
      'entity_id' => (string) $attachment->id(),
      'langcode' => $attachment->language()->getId(),
    ],
    ];
    $filter = new ContentIndexAccessFilter(
      $this->container->get('entity_type.manager'),
      $providers,
      $this->container->get('aculta_portal.domain_purpose'),
    );
    self::assertFalse($attachment->access('view', $member, TRUE)->isAllowed(), 'The production Forum Media hook denies this bundle outside the Forum purpose.');
    self::assertCount(1, $filter->filter($candidate, $member)->entities, 'The Index applies the canonical Forum purpose, then preserves unpublished Media owner access.');
    self::assertSame('main', $this->container->get('aculta_portal.domain_purpose')->getCurrentPurpose());
    self::assertCount(0, $filter->filter($candidate, $outsider)->entities, 'The Index does not expose an unpublished attachment to another account.');
    self::assertSame('main', $this->container->get('aculta_portal.domain_purpose')->getCurrentPurpose());
  }

  /**
   * Published attachment candidates require a currently public Forum embed.
   */
  public function testPublishedForumAttachmentLosesAccessWithItsHost(): void {
    $author = $this->createUser('published-forum-attachment-author');
    $viewer = $this->createUser('published-forum-attachment-viewer');
    foreach (['anonymous', 'authenticated'] as $roleId) {
      $role = Role::load($roleId);
      self::assertInstanceOf(Role::class, $role);
      $role->grantPermission('view media');
      if ($roleId === 'anonymous') {
        $role->grantPermission('access content')->grantPermission('access comments');
      }
      $role->save();
    }

    $file = File::create([
      'uri' => 'public://forum-attachment-public-index-test.pdf',
      'filename' => 'forum-attachment-public-index-test.pdf',
      'filemime' => 'application/pdf',
      'uid' => $author->id(),
    ]);
    $file->setPermanent();
    $file->save();
    $media = Media::create([
      'bundle' => 'forum_attachment',
      'name' => 'Published Forum attachment',
      'status' => TRUE,
      'field_media_forum_attachment' => ['target_id' => $file->id()],
    ]);
    $media->setOwner($author);
    $media->save();

    $topic = Node::create([
      'type' => 'aculta_forum_topic',
      'title' => 'Public attachment host',
      'status' => TRUE,
      'uid' => $author->id(),
      'field_domain_source' => ['target_id' => 'forum_aculta_org'],
    ]);
    $topic->save();
    $comment = Comment::create([
      'comment_type' => 'aculta_discussion_post',
      'entity_type' => 'node',
      'entity_id' => (int) $topic->id(),
      'field_name' => 'field_aculta_discussion',
      'uid' => $author->id(),
      'subject' => 'Public attachment reference',
      'comment_body' => [
        'value' => sprintf('<drupal-media data-entity-type="media" data-entity-uuid="%s"></drupal-media>', $media->uuid()),
        'format' => 'plain_text',
      ],
      'status' => CommentInterface::PUBLISHED,
    ]);
    $comment->save();

    $providers = $this->container->get('aculta_index.provider_registry');
    $filter = new ContentIndexAccessFilter(
      $this->container->get('entity_type.manager'),
      $providers,
      $this->container->get('aculta_portal.domain_purpose'),
    );
    $candidate = [[
      'entity_type' => 'media',
      'entity_id' => (string) $media->id(),
      'langcode' => $media->language()->getId(),
    ],
    ];
    $mainDomain = Domain::load('aculta_org');
    self::assertInstanceOf(Domain::class, $mainDomain);
    $negotiator = $this->container->get(DomainNegotiatorInterface::class);
    $negotiator->getActiveDomain();
    $negotiator->setActiveDomain($mainDomain);

    self::assertTrue($topic->access('view', new AnonymousUserSession(), TRUE)->isAllowed());
    self::assertTrue($comment->access('view', new AnonymousUserSession(), TRUE)->isAllowed());
    self::assertTrue($media->isPublished(), 'The fixture deliberately starts with a published Media projection.');
    self::assertCount(1, $filter->filter($candidate, $viewer)->entities, 'The Index exposes the published Media through its currently public Comment reference.');
    self::assertSame('main', $this->container->get('aculta_portal.domain_purpose')->getCurrentPurpose());

    $topic->setUnpublished();
    $topic->save();
    self::assertTrue($media->isPublished(), 'Host changes do not synchronously rewrite this Media fixture.');
    self::assertCount(0, $filter->filter($candidate, $viewer)->entities, 'The Index must not trust a stale published flag after the host becomes private.');
    self::assertSame('main', $this->container->get('aculta_portal.domain_purpose')->getCurrentPurpose());
  }

  /**
   * A missing canonical Forum Domain cannot fall back to the active Domain.
   */
  public function testMissingForumDomainFailsClosed(): void {
    $this->createUser('forum-kernel-root-fixture');
    $viewer = $this->createUser('forum-public-viewer');
    $topic = Node::create([
      'type' => 'aculta_forum_topic',
      'title' => 'Public topic',
      'status' => TRUE,
      'uid' => $viewer->id(),
      'field_domain_source' => ['target_id' => 'forum_aculta_org'],
    ]);
    $topic->save();

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
    $candidate = [
      [
        'entity_type' => 'node',
        'entity_id' => (string) $topic->id(),
        'langcode' => $topic->language()->getId(),
      ],
    ];

    self::assertCount(1, $filter->filter($candidate, $viewer)->entities);
    self::assertSame('main', $this->container->get('aculta_portal.domain_purpose')->getCurrentPurpose());

    $forumDomain = Domain::load('forum_aculta_org');
    self::assertInstanceOf(Domain::class, $forumDomain);
    $forumDomain->delete();

    self::assertCount(0, $filter->filter($candidate, $viewer)->entities);
    self::assertSame('main', $this->container->get('aculta_portal.domain_purpose')->getCurrentPurpose());
  }

  /**
   * Revalidates topic and discussion entities with real Group grants.
   */
  public function testIndexFilterUsesForumDomainAndRestrictedGroupAccess(): void {
    // UID 1 bypasses Drupal entity access. Reserve it so the tested member
    // exercises the ordinary Group grants and Forum policy path.
    $this->createUser('forum-kernel-root-fixture');
    $member = $this->createUser('forum-member');
    $this->setAssociateActive($member);
    $outsider = $this->createUser('forum-outsider');
    $group = $this->container->get('entity_type.manager')->getStorage('group')->create([
      'type' => self::GROUP_TYPE,
      'label' => 'Restricted forum group',
      'uid' => $member->id(),
    ]);
    $group->save();
    $group->addMember($member, ['group_roles' => [self::MEMBER_ROLE]]);

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
    self::assertTrue($member->hasPermission('access content'));
    self::assertContains(self::MEMBER_ROLE, array_keys($group->getMember($member)->getRoles(FALSE)));
    self::assertSame(
      [RestrictedForumAccessHooks::REALM => [(int) $group->id()]],
      \Drupal::moduleHandler()->invokeAll('node_grants', [$member, 'view']),
    );
    self::assertSame(1, (int) $this->container->get('database')->select('node_access', 'na')
      ->condition('nid', (int) $topic->id())
      ->condition('realm', RestrictedForumAccessHooks::REALM)
      ->condition('gid', (int) $group->id())
      ->countQuery()
      ->execute()
      ->fetchField());

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

    // Grants can change outside entity saves, so the next Index query must
    // observe membership revocation without waiting for queue processing.
    $group->removeMember($member);
    self::assertFalse($group->getMember($member));
    self::assertFalse($member->hasPermission('bypass node access'));
    self::assertSame(
      [],
      \Drupal::moduleHandler()->invokeAll('node_grants', [$member, 'view']),
    );
    foreach ($candidates as $index => $candidate) {
      self::assertCount(
        0,
        $filter->filter($candidate, $member)->entities,
        sprintf('Revoked membership must deny candidate %d.', $index),
      );
      self::assertSame('main', $this->container->get('aculta_portal.domain_purpose')->getCurrentPurpose());
    }
  }

}
