<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_ugc\Kernel;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\KernelTests\KernelTestBase;
use Drupal\aculta_ugc\Entity\UgcReport;
use Drupal\aculta_ugc\Exception\ReportNotAllowedException;
use Drupal\aculta_ugc\ModerationScope;
use Drupal\aculta_ugc\Service\ReportService;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Valida políticas de denúncia, escopo por purpose e transições (ARQ-06A).
 */
#[Group('aculta_ugc')]
#[RunTestsInSeparateProcesses]
final class UgcReportPolicyKernelTest extends KernelTestBase {

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
    'aculta_ugc',
  ];

  /**
   * Serviço de denúncias.
   */
  private ReportService $service;

  /**
   * Denunciante.
   */
  private User $reporter;

  /**
   * Autor do conteúdo.
   */
  private User $author;

  /**
   * Moderador do purpose portal.
   */
  private User $portalModerator;

  /**
   * Moderador do purpose forum.
   */
  private User $forumModerator;

  /**
   * Moderador transversal.
   */
  private User $globalModerator;

  /**
   * Conta sem papel de moderação.
   */
  private User $outsider;

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container): void {
    parent::register($container);
    $loader = require dirname(DRUPAL_ROOT) . '/vendor/autoload.php';
    $loader->addPsr4('Drupal\\aculta_ugc\\', dirname(__DIR__, 3) . '/src', TRUE);
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('aculta_ugc_report');
    $this->installSchema('node', ['node_access']);
    $this->installSchema('system', ['sequences']);
    $this->installConfig(['system', 'user', 'filter', 'node']);

    NodeType::create(['type' => 'article', 'name' => 'Article'])->save();
    NodeType::create(['type' => 'aculta_forum_topic', 'name' => 'Topic'])->save();

    Role::load('authenticated')?->grantPermission('access content')->save();
    $this->makeRole('mod_portal', [ModerationScope::permissionFor('portal')]);
    $this->makeRole('mod_forum', [ModerationScope::permissionFor('forum')]);
    $this->makeRole('mod_global', [ModerationScope::GLOBAL_PERMISSION]);

    // UID 1 é superusuário no Drupal; reservado.
    User::create(['name' => 'root', 'mail' => 'root@example.com', 'status' => 1])->save();
    $this->reporter = $this->makeUser('reporter');
    $this->author = $this->makeUser('author');
    $this->outsider = $this->makeUser('outsider');
    $this->portalModerator = $this->makeUser('mod_portal', 'mod_portal');
    $this->forumModerator = $this->makeUser('mod_forum', 'mod_forum');
    $this->globalModerator = $this->makeUser('mod_global', 'mod_global');
    $this->service = $this->container->get('aculta_ugc.report_service');
  }

  /**
   * Cria um papel com permissões.
   *
   * @param string $id
   *   ID do papel.
   * @param string[] $permissions
   *   Permissões do papel.
   */
  private function makeRole(string $id, array $permissions): void {
    $role = Role::create(['id' => $id, 'label' => $id]);
    foreach ($permissions as $permission) {
      $role->grantPermission($permission);
    }
    $role->save();
  }

  /**
   * Cria uma conta ativa, com papel opcional.
   */
  private function makeUser(string $name, ?string $role = NULL): User {
    $user = User::create(['name' => $name, 'mail' => $name . '@example.com', 'status' => 1]);
    if ($role !== NULL) {
      $user->addRole($role);
    }
    $user->save();
    return $user;
  }

  /**
   * Cria um nó do bundle informado.
   */
  private function makeNode(string $bundle = 'article', bool $published = TRUE): Node {
    $node = Node::create([
      'type' => $bundle,
      'title' => 'Conteúdo ' . $bundle,
      'status' => $published ? 1 : 0,
      'uid' => $this->author->id(),
    ]);
    $node->save();
    return $node;
  }

  /**
   * A denúncia exige que a conta possa ler a entidade denunciada.
   */
  public function testReportRequiresViewAccessToTarget(): void {
    $private = $this->makeNode('article', FALSE);

    $this->assertSame('sem_acesso', $this->service->reportDenial($private, $this->reporter));
    $this->assertFalse($this->service->canReport($private, $this->reporter));
    $this->expectException(ReportNotAllowedException::class);
    $this->service->createReport($private, $this->reporter, 'spam');
  }

  /**
   * Anônimo e alvo inválido são recusados.
   */
  public function testAnonymousAndInvalidTargetDenied(): void {
    $node = $this->makeNode();
    $anonymous = User::getAnonymousUser();
    $this->assertSame('anonimo', $this->service->reportDenial($node, $anonymous));

    $report = $this->service->createReport($node, $this->reporter, 'spam');
    $this->assertSame('alvo_invalido', $this->service->reportDenial($report, $this->outsider));
    $draft = Node::create(['type' => 'article', 'title' => 'x']);
    $this->assertSame('alvo_invalido', $this->service->reportDenial($draft, $this->outsider));
  }

  /**
   * O motivo é validado contra a lista aceita e normalizado.
   */
  public function testReasonValidation(): void {
    $node = $this->makeNode();
    foreach (['', '   ', 'qualquer coisa', str_repeat('a', 500)] as $invalid) {
      try {
        $this->service->createReport($node, $this->reporter, $invalid);
        $this->fail('Motivo inválido aceito: ' . $invalid);
      }
      catch (ReportNotAllowedException) {
        $this->assertTrue(TRUE);
      }
    }
    $report = $this->service->createReport($node, $this->reporter, '  SPAM ');
    $this->assertSame('spam', $report->get('motivo')->value);
  }

  /**
   * O purpose é resolvido no servidor; divergência do chamador é recusada.
   */
  public function testPurposeCannotBeSpoofed(): void {
    $topic = $this->makeNode('aculta_forum_topic');
    try {
      $this->service->createReport($topic, $this->reporter, 'spam', 'eventos');
      $this->fail('Purpose forjado aceito.');
    }
    catch (ReportNotAllowedException) {
      $this->assertTrue(TRUE);
    }
    $this->assertSame([], $this->container->get('entity_type.manager')->getStorage('aculta_ugc_report')->loadMultiple());

    $report = $this->service->createReport($topic, $this->reporter, 'spam', 'forum');
    $this->assertSame('forum', $report->get('purpose')->value);
  }

  /**
   * Duplicata aberta é recusada; após a decisão pode denunciar de novo.
   */
  public function testDuplicateOpenReportDenied(): void {
    $node = $this->makeNode();
    $first = $this->service->createReport($node, $this->reporter, 'spam');
    $this->assertSame('duplicada', $this->service->reportDenial($node, $this->reporter));

    $this->service->decideReport($first, $this->globalModerator, 'improcedente', 'ok');
    $this->assertNull($this->service->reportDenial($node, $this->reporter));
    // Outra conta continua podendo denunciar.
    $this->assertNull($this->service->reportDenial($node, $this->outsider));
  }

  /**
   * O teto diário provisório bloqueia a denúncia seguinte.
   */
  public function testProvisionalDailyLimit(): void {
    for ($i = 0; $i < ReportService::DAILY_LIMIT; $i++) {
      $this->service->createReport($this->makeNode(), $this->reporter, 'outro');
    }
    $extra = $this->makeNode();
    $this->assertSame('limite_diario', $this->service->reportDenial($extra, $this->reporter));
    // O limite é por conta.
    $this->assertNull($this->service->reportDenial($extra, $this->outsider));
  }

  /**
   * Moderador só decide denúncias do purpose que modera.
   */
  public function testModerationIsScopedByPurpose(): void {
    $portalReport = $this->service->createReport($this->makeNode(), $this->reporter, 'spam');
    $forumReport = $this->service->createReport($this->makeNode('aculta_forum_topic'), $this->reporter, 'spam');
    $this->assertSame('portal', $portalReport->get('purpose')->value);
    $this->assertSame('forum', $forumReport->get('purpose')->value);

    try {
      $this->service->decideReport($forumReport, $this->portalModerator, 'procedente', 'fora do escopo');
      $this->fail('Moderador de portal decidiu denúncia de forum.');
    }
    catch (AccessDeniedHttpException) {
      $this->assertSame('pendente', $forumReport->get('status')->value);
    }
    try {
      $this->service->decideReport($portalReport, $this->forumModerator, 'procedente', 'fora do escopo');
      $this->fail('Moderador de forum decidiu denúncia de portal.');
    }
    catch (AccessDeniedHttpException) {
      $this->assertSame('pendente', $portalReport->get('status')->value);
    }
    try {
      $this->service->decideReport($forumReport, $this->outsider, 'procedente', 'sem permissão');
      $this->fail('Conta sem permissão decidiu.');
    }
    catch (AccessDeniedHttpException) {
      $this->assertTrue(TRUE);
    }

    $this->service->decideReport($portalReport, $this->portalModerator, 'procedente', 'ok');
    $this->service->decideReport($forumReport, $this->forumModerator, 'improcedente', 'ok');
    $this->assertSame('procedente', $portalReport->get('status')->value);
    $this->assertSame('improcedente', $forumReport->get('status')->value);
  }

  /**
   * O escopo transversal modera todos os purposes e é distinto do escopo local.
   */
  public function testGlobalScopeCoversAllPurposes(): void {
    $this->assertSame(ModerationScope::PURPOSES, ModerationScope::purposesFor($this->globalModerator));
    $this->assertSame(['portal'], ModerationScope::purposesFor($this->portalModerator));
    $this->assertSame(['forum'], ModerationScope::purposesFor($this->forumModerator));
    $this->assertSame([], ModerationScope::purposesFor($this->outsider));
    $this->assertSame([], ModerationScope::purposesFor(User::getAnonymousUser()));
  }

  /**
   * Status inválidos, transições proibidas e redecisão são recusados.
   */
  public function testStatusTransitions(): void {
    $report = $this->service->createReport($this->makeNode(), $this->reporter, 'spam');

    foreach (['', 'pendente', 'arquivada', 'PROCEDENTE'] as $invalid) {
      try {
        $this->service->decideReport($report, $this->globalModerator, $invalid, '');
        $this->fail('Status inválido aceito: ' . $invalid);
      }
      catch (ReportNotAllowedException) {
        $this->assertSame('pendente', $report->get('status')->value);
      }
    }

    $this->service->decideReport($report, $this->globalModerator, 'em_analise', 'Em apuração');
    $this->assertSame('em_analise', $report->get('status')->value);
    $this->assertEmpty($report->get('decided_at')->value, 'Análise não é decisão final.');

    $this->service->decideReport($report, $this->globalModerator, 'procedente', 'Confirmada');
    $this->assertNotEmpty($report->get('decided_at')->value);
    $this->assertSame((int) $this->globalModerator->id(), (int) $report->get('decided_by')->value);

    // Decisão final não é reaberta nem trocada.
    foreach (['em_analise', 'improcedente', 'procedente'] as $next) {
      try {
        $this->service->decideReport($report, $this->globalModerator, $next, 'Reabrir');
        $this->fail('Redecisão aceita: ' . $next);
      }
      catch (ReportNotAllowedException) {
        $this->assertSame('procedente', $report->get('status')->value);
      }
    }
    $this->assertSame('Confirmada', $report->get('decision_notes')->value);
  }

  /**
   * O parecer tem tamanho máximo.
   */
  public function testDecisionNotesLengthLimit(): void {
    $report = $this->service->createReport($this->makeNode(), $this->reporter, 'spam');
    $this->expectException(ReportNotAllowedException::class);
    $this->service->decideReport($report, $this->globalModerator, 'procedente', str_repeat('x', ReportService::NOTES_MAX_LENGTH + 1));
  }

  /**
   * O handler de acesso respeita o escopo e a titularidade da denúncia.
   */
  public function testEntityAccessHandlerScoping(): void {
    $forumReport = $this->service->createReport($this->makeNode('aculta_forum_topic'), $this->reporter, 'spam');
    $this->assertInstanceOf(UgcReport::class, $forumReport);

    $this->assertTrue($forumReport->access('view', $this->forumModerator));
    $this->assertTrue($forumReport->access('update', $this->forumModerator));
    $this->assertTrue($forumReport->access('view', $this->globalModerator));
    $this->assertFalse($forumReport->access('view', $this->portalModerator));
    $this->assertFalse($forumReport->access('update', $this->portalModerator));
    $this->assertFalse($forumReport->access('view', $this->outsider));
    // O denunciante vê, mas não altera nem apaga a própria denúncia.
    $this->assertTrue($forumReport->access('view', $this->reporter));
    $this->assertFalse($forumReport->access('update', $this->reporter));
    $this->assertFalse($forumReport->access('delete', $this->reporter));
    $this->assertFalse($forumReport->access('view', User::getAnonymousUser()));
  }

  /**
   * Cada purpose tem uma permissão de moderação própria.
   */
  public function testPurposePermissionsAreDeclared(): void {
    $permissions = $this->container->get('user.permissions')->getPermissions();
    $this->assertArrayHasKey(ModerationScope::GLOBAL_PERMISSION, $permissions);
    foreach (ModerationScope::PURPOSES as $purpose) {
      $this->assertArrayHasKey(ModerationScope::permissionFor($purpose), $permissions);
      $this->assertTrue($permissions[ModerationScope::permissionFor($purpose)]['restrict access']);
    }
  }

}
