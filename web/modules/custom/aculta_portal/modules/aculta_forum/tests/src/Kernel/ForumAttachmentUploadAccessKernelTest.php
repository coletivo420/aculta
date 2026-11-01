<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum\Kernel;

use Drupal\aculta_forum\Access\ForumAttachmentUploadAccess;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\Core\Session\AnonymousUserSession;
use Drupal\domain\Entity\Domain;
use Drupal\filter\Entity\FilterFormat;
use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Proves the dialog requires authentication, the Forum format and purpose.
 */
#[Group('aculta_forum')]
#[RunTestsInSeparateProcesses]
final class ForumAttachmentUploadAccessKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   *
   * @var string[]
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'filter',
    'domain',
  ];

  /**
   * Access under test.
   */
  private ForumAttachmentUploadAccess $access;

  /**
   * Purpose resolver.
   */
  private DomainPurposeManager $purpose;

  /**
   * Member account.
   */
  private User $member;

  /**
   * Forum text format.
   */
  private FilterFormat $forumFormat;

  /**
   * A second text format.
   */
  private FilterFormat $otherFormat;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installConfig(['system', 'user', 'filter', 'domain']);
    foreach ([
      'aculta_org' => 'main.example.test',
      'forum_aculta_org' => 'forum.example.test',
    ] as $id => $hostname) {
      Domain::create([
        'id' => $id,
        'hostname' => $hostname,
        'name' => $id,
        'scheme' => 'https',
        'status' => TRUE,
        'is_default' => $id === 'aculta_org',
      ])->save();
    }
    $this->forumFormat = FilterFormat::create([
      'format' => 'forum',
      'name' => 'Fórum',
      'filters' => [],
    ]);
    $this->forumFormat->save();
    $this->otherFormat = FilterFormat::create([
      'format' => 'basic_html',
      'name' => 'HTML básico',
      'filters' => [],
    ]);
    $this->otherFormat->save();
    User::create(['name' => 'base', 'status' => 1])->save();
    $this->member = User::create(['name' => 'membro', 'status' => 1]);
    $this->member->save();

    $this->purpose = new DomainPurposeManager(
      $this->container->get('entity_type.manager'),
      $this->container->get('domain.negotiator'),
      $this->container->get('config.factory'),
      new RequestStack(),
    );
    $this->access = new ForumAttachmentUploadAccess($this->purpose);
  }

  /**
   * Member with the Forum format inside the Forum purpose is allowed.
   */
  public function testMemberWithForumFormatIsAllowed(): void {
    $result = $this->purpose->runInPurpose('forum', fn () => $this->access->access(
      $this->forumFormat,
      $this->member,
    ));

    self::assertTrue($result->isAllowed());
    self::assertContains('domain', $result->getCacheContexts());
    self::assertContains('user.roles:authenticated', $result->getCacheContexts());
    self::assertContains('config:filter.format.forum', $result->getCacheTags());
  }

  /**
   * Anonymous, other formats and other purposes are refused.
   */
  public function testOtherContextsAreRefused(): void {
    $anonymous = $this->purpose->runInPurpose('forum', fn () => $this->access->access(
      $this->forumFormat,
      new AnonymousUserSession(),
    ));
    self::assertFalse($anonymous->isAllowed());
    self::assertTrue($anonymous->isNeutral());

    $otherFormat = $this->purpose->runInPurpose('forum', fn () => $this->access->access(
      $this->otherFormat,
      $this->member,
    ));
    self::assertFalse($otherFormat->isAllowed());

    $main = $this->purpose->runInPurpose('main', fn () => $this->access->access(
      $this->forumFormat,
      $this->member,
    ));
    self::assertFalse($main->isAllowed());
  }

}
