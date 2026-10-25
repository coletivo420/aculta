<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum\Functional;

use Drupal\aculta_forum\Attachment\ForumAttachmentCleanup;
use Drupal\Core\Url;
use Drupal\domain\Entity\Domain;
use Drupal\Tests\BrowserTestBase;
use Drupal\user\UserInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Rotas do Fórum por HTTP: negação anônima, telas e formulário autenticados.
 *
 * A costura `aculta_domain_purpose_test_relaxed` permite exercitar todos os
 * propósitos em um único host de teste; ela nunca é definida em produção.
 */
#[Group('aculta_forum')]
#[RunTestsInSeparateProcesses]
final class ForumRoutesFunctionalTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   *
   * The standard profile ships the body field as text_with_summary, matching
   * the site configuration; the testing profile's text_long conflicts with it.
   */
  protected $profile = 'standard';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['aculta_forum'];

  /**
   * Exige URL base de teste; nunca aponta para o Runtime.
   */
  protected function setUp(): void {
    if (getenv('SIMPLETEST_BASE_URL') === FALSE || getenv('SIMPLETEST_BASE_URL') === '') {
      $this->markTestSkipped('Sem SIMPLETEST_BASE_URL: teste funcional pulado.');
    }
    parent::setUp();
    Domain::create([
      'id' => 'forum_aculta_org',
      'hostname' => '127.0.0.1',
      'name' => 'Fórum',
      'scheme' => 'http',
      'status' => TRUE,
      'weight' => 0,
    ])->save();
    // Topic creation is gated by published-comment rules on the real site;
    // this suite exercises the routes and the form itself.
    \Drupal::configFactory()->getEditable('aculta_user.settings')
      ->set('topico_limite_ativo', FALSE)
      ->set('bloqueio_ate_aprovacao', FALSE)
      ->save();
    $terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')
      ->loadByProperties(['vid' => 'aculta_forum_categories']);
    if ($terms === []) {
      \Drupal\taxonomy\Entity\Term::create([
        'vid' => 'aculta_forum_categories',
        'name' => 'Categoria funcional',
      ])->save();
    }
    // The login form is captcha-protected on the site; tests authenticate by
    // password and do not solve the challenge.
    if (\Drupal::moduleHandler()->moduleExists('captcha')) {
      \Drupal::configFactory()->getEditable('captcha.settings')
        ->set('default_challenge', '')->save();
      foreach (\Drupal::entityTypeManager()->getStorage('captcha_point')->loadMultiple() as $point) {
        $point->delete();
      }
    }
  }

  /**
   * Adds the single-host seam to the test site settings before installation.
   */
  protected function prepareSettings() {
    parent::prepareSettings();
    $file = DRUPAL_ROOT . '/' . $this->siteDirectory . '/settings.php';
    file_put_contents($file, "\n\$settings['aculta_domain_purpose_test_relaxed'] = TRUE;\n", FILE_APPEND);
  }

  /**
   * Anônimo não acessa criação, publicação, moderação nem leitura marcada.
   */
  public function testAnonymousRoutesAreDenied(): void {
    $statuses = [];
    foreach ([
      '/painel-administrativo/forum',
      '/criar-topico',
      '/publicacao/nova',
      '/discussoes/marcar-tudo-como-lido',
      '/painel-administrativo/forum/configuracoes',
      '/painel-administrativo/forum/publicacoes-revisionaveis',
    ] as $path) {
      $this->drupalGet($path);
      $statuses[$path] = $this->getSession()->getStatusCode();
    }
    self::assertSame(array_fill_keys(array_keys($statuses), 403), $statuses);
  }

  /**
   * Cada tela responde 200 com a permissão correspondente.
   */
  public function testAuthenticatedRoutesRenderWithPermission(): void {
    $editor = $this->drupalCreateUser([
      'create aculta_forum_topic content',
      'access content',
      'access content overview',
      'administer aculta forum settings',
    ]);
    $this->loginAs($editor);

    $this->drupalGet('/criar-topico');
    $this->assertPageOk();
    $this->assertSession()->fieldExists('title[0][value]');

    $this->drupalGet('/painel-administrativo/forum');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Painel Growroom420');

    $this->drupalGet('/painel-administrativo/forum/configuracoes');
    $this->assertSession()->statusCodeEquals(200);
  }

  /**
   * O formulário de criação publica um tópico no propósito do Fórum.
   */
  public function testTopicCreationFormCreatesTopic(): void {
    $author = $this->drupalCreateUser([
      'create aculta_forum_topic content',
      'access content',
    ]);
    $this->loginAs($author);
    $this->drupalGet('/criar-topico');
    $this->assertPageOk();
    $this->assertSession()->fieldExists('title[0][value]');
    $page = $this->getSession()->getPage();
    $page->fillField('title[0][value]', 'Tópico funcional');
    $page->fillField('body[0][value]', 'Corpo do tópico criado pelo teste funcional.');
    $term = reset($this->container->get('entity_type.manager')->getStorage('taxonomy_term')
      ->loadByProperties(['vid' => 'aculta_forum_categories']));
    $select = $page->findField('field_aculta_categories[]')
      ?: $page->findField('field_aculta_categories');
    $select->selectOption((string) $term->id());
    $page->find('css', '#edit-submit')->click();
    $node = $this->container->get('entity_type.manager')->getStorage('node')
      ->loadByProperties(['title' => 'Tópico funcional']);
    $text = mb_substr($this->getSession()->getPage()->getText(), 0, 700);
    self::assertCount(1, $node, 'Status ' . $this->getSession()->getStatusCode() . ' | ' . $text);
    $topic = reset($node);
    self::assertSame('aculta_forum_topic', $topic->bundle());
  }

  /**
   * A limpeza respeita o lock de banco compartilhado entre processos.
   */
  public function testCleanupRespectsTheSharedLock(): void {
    $lock = $this->container->get('lock');
    $cleanup = $this->container->get('aculta_forum.attachment_cleanup');
    self::assertTrue($lock->acquire(ForumAttachmentCleanup::LOCK_NAME, 0.0));
    try {
      self::assertSame(0, $cleanup->cleanup());
    }
    finally {
      $lock->release(ForumAttachmentCleanup::LOCK_NAME);
    }

    $cleanup->cleanup();
    self::assertTrue($lock->acquire(ForumAttachmentCleanup::LOCK_NAME, 0.0));
    $lock->release(ForumAttachmentCleanup::LOCK_NAME);
  }

  /**
   * Desinstalar remove rotas e configuração sem depender de limpeza manual.
   */
  public function testModuleUninstallRemovesRoutesAndConfiguration(): void {
    $this->container->get('module_installer')->uninstall(['aculta_forum']);
    $this->container->get('router.builder')->rebuild();
    self::assertFalse($this->container->get('config.storage')->exists('aculta_forum.settings'));
    $this->expectException(\Symfony\Component\Routing\Exception\RouteNotFoundException::class);
    $this->container->get('router.route_provider')->getRouteByName('aculta_forum.admin');
  }

  /**
   * Usuário autenticado sem a permissão da rota continua negado.
   */
  public function testAuthenticatedWithoutPermissionIsDenied(): void {
    $user = $this->drupalCreateUser(['access content']);
    $this->loginAs($user);
    $this->drupalGet('/criar-topico');
    $this->assertSession()->statusCodeEquals(403);
    $this->drupalGet('/painel-administrativo/forum');
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Asserts a 200 page and reports the latest error log when it fails.
   */
  private function assertPageOk(): void {
    $status = $this->getSession()->getStatusCode();
    if ($status === 200) {
      return;
    }
    $detail = 'sem log';
    try {
      $log = $this->container->get('database')->select('watchdog', 'w')
        ->fields('w', ['message', 'variables'])
        ->condition('severity', 3)
        ->orderBy('wid', 'DESC')
        ->range(0, 1)
        ->execute()
        ->fetch();
      if ($log) {
        $detail = (string) $log->message . ' ' . (string) $log->variables;
      }
    }
    catch (\Throwable) {
      // Keep the status-only report when the log table is unavailable.
    }
    $text = mb_substr($this->getSession()->getPage()->getText(), 0, 700);
    self::fail('Status ' . $status . ' | ' . $detail . ' | ' . $text);
  }

  /**
   * Logs in on the account host without depending on the submit label.
   */
  private function loginAs(UserInterface $account): void {
    $this->drupalGet(Url::fromRoute('user.login'));
    $page = $this->getSession()->getPage();
    $page->fillField('name', $account->getAccountName());
    $page->fillField('pass', $account->passRaw);
    $page->find('css', 'input[type="submit"]')->click();
    $this->loggedInUser = $account;
    $this->container->get('current_user')->setAccount($account);
    $url = $this->getSession()->getCurrentUrl();
    self::assertTrue(
      str_contains($url, '/identidade/')
      || str_contains($page->getText(), $account->getAccountName()),
      'Login response: ' . $url . ' | ' . mb_substr($page->getText(), 0, 300),
    );
  }

}
