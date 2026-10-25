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
 * Os propósitos de Domain são exercitados pelo cabeçalho Host, que troca o
 * domínio ativo sem exigir DNS: a conexão continua no servidor de teste.
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
    $this->createDomain('aculta_org', 'aculta.test', 'ACULTA', 0);
    $this->createDomain('conta_aculta_org', 'conta.aculta.test', 'Conta', 1);
    $this->createDomain('forum_aculta_org', 'forum.aculta.test', 'Fórum', 2);
  }

  /**
   * Anônimo não acessa criação, publicação, moderação nem leitura marcada.
   */
  public function testAnonymousRoutesAreDenied(): void {
    $routes = [
      '/painel-administrativo/forum' => 'aculta.test',
      '/criar-topico' => 'forum.aculta.test',
      '/publicacao/nova' => 'forum.aculta.test',
      '/discussoes/marcar-tudo-como-lido' => 'forum.aculta.test',
      '/painel-administrativo/forum/configuracoes' => 'aculta.test',
      '/painel-administrativo/forum/publicacoes-revisionaveis' => 'aculta.test',
    ];
    foreach ($routes as $path => $host) {
      $this->requestAs($host, $path);
      $this->assertSession()->statusCodeEquals(403);
    }
  }

  /**
   * Cada tela responde 200 com a permissão correspondente.
   */
  public function testAuthenticatedRoutesRenderWithPermission(): void {
    $topicAuthor = $this->drupalCreateUser([
      'create aculta_forum_topic content',
      'access content',
    ]);
    $this->loginAs($topicAuthor);
    $this->requestAs('forum.aculta.test', '/criar-topico');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->fieldExists('title[0][value]');

    $overview = $this->drupalCreateUser(['access content overview']);
    $this->loginAs($overview);
    $this->requestAs('aculta.test', '/painel-administrativo/forum');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Painel Growroom420');

    $settings = $this->drupalCreateUser(['administer aculta forum settings']);
    $this->loginAs($settings);
    $this->requestAs('aculta.test', '/painel-administrativo/forum/configuracoes');
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
    $this->requestAs('forum.aculta.test', '/criar-topico');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->fieldExists('title[0][value]');
    $page = $this->getSession()->getPage();
    $page->fillField('title[0][value]', 'Tópico funcional');
    $page->fillField('body[0][value]', 'Corpo do tópico criado pelo teste funcional.');
    $page->find('css', '#edit-submit')->click();

    $node = $this->container->get('entity_type.manager')->getStorage('node')
      ->loadByProperties(['title' => 'Tópico funcional']);
    self::assertCount(1, $node);
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
    $this->requestAs('aculta.test', '/painel-administrativo/forum');
    $this->assertSession()->statusCodeEquals(404);
    self::assertFalse($this->container->get('config.storage')->exists('aculta_forum.settings'));
  }

  /**
   * Usuário autenticado sem a permissão da rota continua negado.
   */
  public function testAuthenticatedWithoutPermissionIsDenied(): void {
    $user = $this->drupalCreateUser(['access content']);
    $this->loginAs($user);
    $this->requestAs('forum.aculta.test', '/criar-topico');
    $this->assertSession()->statusCodeEquals(403);
    $this->requestAs('aculta.test', '/painel-administrativo/forum');
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Creates a Domain entity for one purpose host.
   */
  private function createDomain(string $id, string $hostname, string $name, int $weight): void {
    Domain::create([
      'id' => $id,
      'hostname' => $hostname,
      'name' => $name,
      'scheme' => 'http',
      'status' => TRUE,
      'weight' => $weight,
    ])->save();
  }

  /**
   * Requests a path with the Host header of one purpose.
   */
  private function requestAs(string $host, string $path): void {
    $this->getSession()->setRequestHeader('Host', $host);
    $this->container->get('domain.negotiator')->getActiveDomain(TRUE);
    $this->drupalGet($path);
  }

  /**
   * Logs in on the account host without depending on the submit label.
   */
  private function loginAs(UserInterface $account): void {
    $this->requestAs('conta.aculta.test', Url::fromRoute('user.login')->toString());
    $page = $this->getSession()->getPage();
    $page->fillField('name', $account->getAccountName());
    $page->fillField('pass', $account->passRaw);
    $page->find('css', 'input[type="submit"]')->click();
    $this->loggedInUser = $account;
    $this->container->get('current_user')->setAccount($account);
    $cookie = \Drupal::service('session_configuration')
      ->getOptions(\Drupal::request())['name'];
    self::assertNotEmpty(
      $this->getSession()->getCookie($cookie),
      'Login response: ' . $this->getSession()->getCurrentUrl()
      . ' | ' . mb_substr($page->getText(), 0, 300),
    );
  }

}
