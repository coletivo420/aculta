<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_faq\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\block_content\Entity\BlockContent;
use Drupal\node\Entity\Node;
use Drupal\Core\Routing\RouteObjectInterface;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;

/**
 * Covers safe markup formats and administrative route access.
 *
 * @group aculta_faq
 */
#[RunTestsInSeparateProcesses]
final class FaqEditorialSafetyKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system', 'user', 'file', 'field', 'node', 'node_storage_body_field', 'text', 'filter',
    'block_content', 'block_content_storage_body_field', 'menu_ui', 'aculta_faq',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('block_content');
    $this->installConfig([
      'system', 'filter', 'node_storage_body_field', 'block_content_storage_body_field', 'node', 'block_content', 'aculta_faq',
    ]);
  }

  /**
   * Verifies script, event attributes and javascript URLs are removed.
   */
  public function testEditorialFormatSanitizesUntrustedMarkup(): void {
    self::assertSame(
      ['aculta_editorial'],
      $this->config('field.field.node.aculta_faq.body')->get('settings.allowed_formats'),
    );
    self::assertSame(
      ['aculta_editorial'],
      $this->config('field.field.block_content.aculta_editorial.body')->get('settings.allowed_formats'),
    );
    $input = '<p><strong>ênfase</strong><em>itálico</em></p>'
      . '<script>alert(1)</script><a href="javascript:alert(2)" onclick="alert(3)">link</a>'
      . '<img src=x onerror="alert(4)"><ul><li>item</li></ul>';
    $build = [
      '#type' => 'processed_text',
      '#text' => $input,
      '#format' => 'aculta_editorial',
    ];
    $safe = (string) $this->container->get('renderer')->renderInIsolation($build);

    self::assertStringContainsString('<strong>ênfase</strong>', $safe);
    self::assertStringContainsString('<em>itálico</em>', $safe);
    self::assertStringContainsString('<ul><li>item</li></ul>', $safe);
    self::assertStringNotContainsString('<script', $safe);
    self::assertStringNotContainsString('javascript:', $safe);
    self::assertStringNotContainsString('onclick', $safe);
    self::assertStringNotContainsString('onerror', $safe);
  }

  /**
   * Ordinary users cannot open the editorial administration page.
   */
  public function testAdministrativeRouteDeniesOrdinaryUser(): void {
    $user = User::create(['uid' => 2, 'name' => 'faq-reader']);
    $user->save();

    $access = $this->container->get('access_manager')->checkNamedRoute(
      'aculta_faq.admin',
      [],
      $user,
      TRUE,
    );
    self::assertFalse($access->isAllowed());
  }

  /**
   * FAQ question and editorial block edit routes deny ordinary users.
   */
  public function testQuestionAndBlockEditRoutesDenyOrdinaryUser(): void {
    $user = User::create(['uid' => 3, 'name' => 'faq-editorial-reader']);
    $user->save();
    $question = Node::create([
      'type' => 'aculta_faq',
      'title' => 'Pergunta de teste de acesso',
      'status' => 1,
    ]);
    $question->save();
    $block = BlockContent::create([
      'type' => 'aculta_editorial',
      'info' => 'Bloco de teste de acesso',
    ]);
    $block->save();

    $route_provider = $this->container->get('router.route_provider');
    $question_request = Request::create('/painel-administrativo/faq/perguntas/' . $question->id() . '/editar');
    $question_request->attributes->set(RouteObjectInterface::ROUTE_NAME, 'aculta_faq.question_edit');
    $question_request->attributes->set(
      RouteObjectInterface::ROUTE_OBJECT,
      $route_provider->getRouteByName('aculta_faq.question_edit'),
    );
    $question_request->attributes->set('node', $question);
    $question_access = $this->container->get('access_manager')->checkRequest($question_request, $user, TRUE);

    $block_request = Request::create('/painel-administrativo/faq/blocos/' . $block->id() . '/editar');
    $block_request->attributes->set(RouteObjectInterface::ROUTE_NAME, 'aculta_faq.block_edit');
    $block_request->attributes->set(
      RouteObjectInterface::ROUTE_OBJECT,
      $route_provider->getRouteByName('aculta_faq.block_edit'),
    );
    $block_request->attributes->set('block_content', $block);
    $block_access = $this->container->get('access_manager')->checkRequest($block_request, $user, TRUE);

    self::assertFalse($question_access->isAllowed());
    self::assertFalse($block_access->isAllowed());
  }

}
