<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_events\Kernel;

use Drupal\aculta_events\Entity\Event;
use Drupal\Core\Session\AnonymousUserSession;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Rascunhos exigem permissão; criação exige autorização de Eventos.
 */
#[Group('aculta_events')]
#[RunTestsInSeparateProcesses]
final class EventAccessKernelTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system', 'user', 'key', 'address', 'geofield', 'taxonomy', 'link', 'text',
    'filter', 'options', 'aculta_calendar', 'aculta_events',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('user_role');
    $this->installConfig(['system', 'user']);
    $this->installEntitySchema('aculta_event');
    // Evita atribuir o uid 1 de superusuário às contas de teste.
    $this->createUser();
  }

  /**
   * Evento criado com a situação informada.
   */
  private function evento(string $situacao): Event {
    $evento = Event::create(['title' => 'Oficina', 'field_status' => $situacao]);
    $evento->save();
    return $evento;
  }

  /**
   * Rascunhos exigem permissão específica e eventos publicados são públicos.
   */
  public function testRascunhoSoParaQuemPodeVerRascunho(): void {
    $rascunho = $this->evento('rascunho');
    $publicado = $this->evento('publicado');
    $visitante = new AnonymousUserSession();
    $leitor = $this->createUser(['view aculta event drafts']);

    $this->assertFalse($rascunho->access('view', $visitante));
    $this->assertTrue($rascunho->access('view', $leitor));
    $this->assertTrue($publicado->access('view', $visitante));
  }

  /**
   * Cancelado continua visível para o público (a página mostra o aviso).
   */
  public function testCanceladoContinuaVisivel(): void {
    $cancelado = $this->evento('cancelado');
    $this->assertTrue($cancelado->access('view', new AnonymousUserSession()));
  }

  /**
   * Criar evento exige a permissão de administração de eventos.
   */
  public function testCriarExigeAdministracao(): void {
    $comum = $this->createUser(['view aculta event drafts']);
    $admin = $this->createUser(['administer aculta events']);
    $handler = $this->container->get('entity_type.manager')->getAccessControlHandler('aculta_event');

    $this->assertFalse($handler->createAccess(NULL, $comum));
    $this->assertTrue($handler->createAccess(NULL, $admin));
  }

}
