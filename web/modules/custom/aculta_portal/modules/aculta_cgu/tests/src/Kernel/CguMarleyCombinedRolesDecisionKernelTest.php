<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_cgu\Kernel;

use Composer\Autoload\ClassLoader;
use Drupal\aculta_cgu\Entity\CguReport;
use Drupal\aculta_cgu\Exception\ReportNotAllowedException;
use Drupal\aculta_cgu\ModerationScope;
use Drupal\aculta_cgu\Service\CguReportQueue;
use Drupal\aculta_cgu\Service\ReportService;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Exercises CGU decisions and multi-role isolation using the real test DB.
 *
 * No Runtime data, router modification or external HTTP requests are used.
 */
#[Group('aculta_cgu')]
#[RunTestsInSeparateProcesses]
final class CguMarleyCombinedRolesDecisionKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'text',
    'filter',
    'node',
    'aculta_cgu',
  ];

  private ReportService $reports;

  private CguReportQueue $queue;

  private User $reporter;

  private User $author;

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container): void {
    parent::register($container);
    $loader = require dirname(DRUPAL_ROOT) . '/vendor/autoload.php';
    self::assertInstanceOf(ClassLoader::class, $loader);
    $loader->addPsr4('Drupal\\aculta_cgu\\', dirname(__DIR__, 3) . '/src', TRUE);
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('aculta_cgu_report');
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['system', 'user', 'filter', 'node']);

    foreach (['aculta_forum_topic', 'revista', 'wiki_entry'] as $bundle) {
      NodeType::create(['type' => $bundle, 'name' => $bundle])->save();
    }
    Role::load('authenticated')?->grantPermission('access content')->save();
    $this->role('forum', ['review aculta forum reports']);
    $this->role('wiki', [ModerationScope::permissionFor('wiki')]);
    $this->role('revista', [ModerationScope::permissionFor('revista')]);
    $this->role('topic_operator', ['moderate aculta forum topics']);
    $this->role('cgu_global', [ModerationScope::GLOBAL_PERMISSION]);

    // UID 1 is reserved so it does not bypass access checks in these tests.
    User::create(['name' => 'root', 'mail' => 'root@example.invalid', 'status' => 1])->save();
    $this->author = $this->user('author');
    $this->reporter = $this->user('reporter');
    $this->reports = $this->container->get('aculta_cgu.report_service');
    $this->queue = $this->container->get('aculta_cgu.report_queue');
  }

  /**
   * A Forum+Wiki moderator sees exactly those reports, not Revista reports.
   */
  public function testCombinedRolesKeepQueuePurposeIsolation(): void {
    $topic = $this->topic('aculta_forum_topic');
    $wiki = $this->topic('wiki_entry');
    $magazine = $this->topic('revista');
    $forumReport = $this->report($topic, 'forum');
    $wikiReport = $this->report($wiki, 'wiki');
    $magazineReport = $this->report($magazine, 'revista');

    $moderator = $this->user('forum_wiki', ['forum', 'wiki']);
    self::assertSame(['forum', 'wiki'], ModerationScope::purposesFor($moderator));

    $forums = $this->queue->getQueueItems($moderator, 'forum');
    self::assertSame(1, $forums['total_items']);
    self::assertSame('forum', $forums['items'][0]['purpose']);
    self::assertSame((int) $topic->id(), $forums['items'][0]['target_id']);
    self::assertArrayNotHasKey('uid', $forums['items'][0], 'A fila não divulga a identidade do denunciante.');

    $wikis = $this->queue->getQueueItems($moderator, 'wiki');
    self::assertSame(1, $wikis['total_items']);
    self::assertSame((int) $wiki->id(), $wikis['items'][0]['target_id']);
    $both = $this->queue->getQueueItems($moderator);
    self::assertSame(2, $both['total_items']);
    self::assertEqualsCanonicalizing(['forum', 'wiki'], array_column($both['items'], 'purpose'));

    try {
      $this->queue->getQueueItems($moderator, 'revista');
      self::fail('Papel composto Fórum+Wiki não pode abrir a fila Revista.');
    }
    catch (AccessDeniedHttpException) {
      // Permission boundary is enforced by the CGU service.
    }

    // Existing reports are unchanged by failed queue reads.
    self::assertSame('pendente', $this->reload($forumReport)->get('status')->value);
    self::assertSame('pendente', $this->reload($wikiReport)->get('status')->value);
    self::assertSame('pendente', $this->reload($magazineReport)->get('status')->value);
  }

  /**
   * A Forum + topic operator role cannot decide a Wiki or Revista report.
   */
  public function testTopicOperatorGrantDoesNotCrossCguPurposes(): void {
    $topic = $this->topic('aculta_forum_topic');
    $magazine = $this->topic('revista');
    $forum = $this->report($topic, 'forum');
    $revista = $this->report($magazine, 'revista');
    $operator = $this->user('forum_topic_operator', ['forum', 'topic_operator']);

    self::assertSame(['forum'], ModerationScope::purposesFor($operator));

    try {
      $this->reports->decideReport($revista, $operator, 'procedente', 'Não autorizado.');
      self::fail('Grant de tópicos não concede decisão de propósito Revista.');
    }
    catch (AccessDeniedHttpException) {
      // No mutation is permitted.
    }

    self::assertSame('pendente', $this->reload($revista)->get('status')->value);
    $this->reports->decideReport($forum, $operator, 'improcedente', 'Revisão concluída.');
    self::assertSame('improcedente', $this->reload($forum)->get('status')->value);
    self::assertTrue($topic->isPublished(), 'A decisão não altera o tópico.');
  }

  /**
   * Different report purposes on the same entity stay separate in the queue.
   */
  public function testSameTargetWithDifferentPurposesIsNeverCrossCounted(): void {
    $topic = $this->topic('aculta_forum_topic');
    $forum = $this->report($topic, 'forum');
    $foreign = $this->report($topic, 'revista');
    $forumOnly = $this->user('forum_only_same_target', ['forum']);
    $mixed = $this->user('forum_revista_same_target', ['forum', 'revista']);

    $forumRows = $this->queue->getQueueItems($forumOnly, 'forum');
    self::assertSame(1, $forumRows['total_items']);
    self::assertSame(1, $forumRows['items'][0]['total']);
    self::assertSame('forum', $forumRows['items'][0]['purpose']);

    $all = $this->queue->getQueueItems($mixed);
    self::assertSame(2, $all['total_items']);
    self::assertEqualsCanonicalizing(['forum', 'revista'], array_column($all['items'], 'purpose'));

    $this->reports->decideReport($forum, $forumOnly, 'procedente', 'Caso do Fórum.');
    self::assertSame('procedente', $this->reload($forum)->get('status')->value);
    self::assertSame('pendente', $this->reload($foreign)->get('status')->value);
  }

  /**
   * Two form copies cannot override a previously committed final decision.
   */
  public function testStaleReportCannotOverwriteFinalDecision(): void {
    $topic = $this->topic('aculta_forum_topic');
    $report = $this->report($topic, 'forum');
    $stale = $this->container->get('entity_type.manager')
      ->getStorage('aculta_cgu_report')->loadUnchanged($this->reportId($report));
    self::assertInstanceOf(CguReport::class, $stale);
    $first = $this->user('moderator_first', ['forum']);
    $second = $this->user('moderator_second', ['forum']);

    $this->reports->decideReport($report, $first, 'procedente', 'Primeiro parecer.');
    $final = $this->reload($report);
    self::assertSame('procedente', $final->get('status')->value);
    $firstDecider = $final->get('decided_by')->value;
    self::assertTrue(is_numeric($firstDecider));
    self::assertSame((int) $first->id(), (int) $firstDecider);

    try {
      $this->reports->decideReport($stale, $second, 'improcedente', 'Segunda tentativa.');
      self::fail('Formulário desatualizado substituiu decisão final.');
    }
    catch (ReportNotAllowedException) {
      // ReportService reloads state under a per-report lock.
    }
    $after = $this->reload($report);
    self::assertSame('procedente', $after->get('status')->value);
    self::assertSame('Primeiro parecer.', $after->get('decision_notes')->value);
    $storedDecider = $after->get('decided_by')->value;
    self::assertTrue(is_numeric($storedDecider));
    self::assertSame((int) $first->id(), (int) $storedDecider);
    self::assertTrue($topic->isPublished(), 'A decisão não despublica o conteúdo.');
  }

  /**
   * A held decision lock must fail closed and be released for later use.
   */
  public function testDecisionLockPreventsSimultaneousWrite(): void {
    $report = $this->report($this->topic('aculta_forum_topic'), 'forum');
    $moderator = $this->user('moderator_lock', ['forum']);
    $lock = $this->container->get('lock');
    $key = 'aculta_cgu:report_decision:' . $report->id();
    self::assertTrue($lock->acquire($key, 15.0));
    try {
      try {
        $this->reports->decideReport($report, $moderator, 'procedente', 'Tentativa concorrente.');
        self::fail('Uma decisão concorrente não pode ignorar o lock.');
      }
      catch (ReportNotAllowedException) {
        self::assertSame('pendente', $this->reload($report)->get('status')->value);
      }
    }
    finally {
      $lock->release($key);
    }

    $this->reports->decideReport($report, $moderator, 'em_analise', 'Caso assumido.');
    self::assertSame('em_analise', $this->reload($report)->get('status')->value);
  }

  /**
   * A restricted target is not made readable by a CGU moderation grant.
   */
  public function testUnpublishedTargetDoesNotGainViewPermission(): void {
    $private = $this->topic('aculta_forum_topic', FALSE);
    $report = $this->report($private, 'forum');
    $forumModerator = $this->user('private_moderator', ['forum']);
    self::assertFalse($private->access('view', $forumModerator));
    self::assertSame('forum', $report->get('purpose')->value);
    // The queue service is purpose scoped, not a public renderer. The Marley
    // controller must additionally recheck target/host Entity Access.
    $queue = $this->queue->getQueueItems($forumModerator, 'forum');
    self::assertSame(1, $queue['total_items']);
    self::assertArrayNotHasKey('uid', $queue['items'][0]);
  }

  /**
   * The named purpose owns the decision, including for dual-purpose roles.
   */
  public function testOnlyAuthorizedPurposeCanDecide(): void {
    $forumReport = $this->report($this->topic('aculta_forum_topic'), 'forum');
    $wikiReport = $this->report($this->topic('wiki_entry'), 'wiki');
    $magazineReport = $this->report($this->topic('revista'), 'revista');
    $dual = $this->user('forum_wiki_decider', ['forum', 'wiki']);

    $this->reports->decideReport($forumReport, $dual, 'em_analise', 'Fórum em revisão.');
    $this->reports->decideReport($wikiReport, $dual, 'procedente', 'Wiki analisada.');
    try {
      $this->reports->decideReport($magazineReport, $dual, 'procedente', 'Sem escopo.');
      self::fail('Papel composto não deve acessar terceiro purpose.');
    }
    catch (AccessDeniedHttpException) {
      // Unrelated purpose stays untouched.
    }

    self::assertSame('em_analise', $this->reload($forumReport)->get('status')->value);
    self::assertSame('procedente', $this->reload($wikiReport)->get('status')->value);
    self::assertSame('pendente', $this->reload($magazineReport)->get('status')->value);
  }

  /**
   * Creates a persisted CGU report without notification side effects.
   */
  private function report(Node $node, string $purpose): CguReport {
    $report = CguReport::create([
      'target_type' => 'node',
      'target_id' => (int) $node->id(),
      'uid' => (int) $this->reporter->id(),
      'motivo' => 'spam',
      'purpose' => $purpose,
      'status' => ReportService::STATUS_PENDING,
    ]);
    $report->save();
    return $report;
  }

  /**
   * Returns a confirmed persisted report identifier.
   */
  private function reportId(CguReport $report): int|string {
    $id = $report->id();
    self::assertNotNull($id);
    return $id;
  }

  private function reload(CguReport $report): CguReport {
    $fresh = $this->container->get('entity_type.manager')
      ->getStorage('aculta_cgu_report')->loadUnchanged($this->reportId($report));
    self::assertInstanceOf(CguReport::class, $fresh);
    return $fresh;
  }

  private function topic(string $bundle, bool $published = TRUE): Node {
    $topic = Node::create([
      'type' => $bundle,
      'title' => 'Conteúdo Kernel ' . $bundle,
      'status' => $published ? 1 : 0,
      'uid' => (int) $this->author->id(),
    ]);
    $topic->save();
    return $topic;
  }

  /**
   * @param list<string> $permissions
   *   Role-granted permissions.
   */
  private function role(string $id, array $permissions): void {
    $role = Role::create(['id' => $id, 'label' => $id]);
    foreach ($permissions as $permission) {
      $role->grantPermission($permission);
    }
    $role->save();
  }

  /**
   * @param list<string> $roles
   *   Role IDs for the user.
   */
  private function user(string $name, array $roles = []): User {
    $user = User::create([
      'name' => $name,
      'mail' => $name . '@example.invalid',
      'status' => 1,
    ]);
    foreach ($roles as $role) {
      $user->addRole($role);
    }
    $user->save();
    return $user;
  }

}
