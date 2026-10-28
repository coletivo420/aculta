<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_events\Kernel;

use Drupal\aculta_calendar\Recurrence\RecurrenceExpander;
use Drupal\aculta_events\Entity\Event;
use Drupal\aculta_events\Entity\EventDate;
use Drupal\aculta_events\Form\EventDateOccurrenceForm;
use Drupal\aculta_events\Form\EventDateRuleForm;
use Drupal\Core\Form\FormState;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Regra e ocorrência de série: edição, validação, cancelamento e reagendamento (D-14).
 *
 * O fuso é fixo em America/Sao_Paulo; a ocorrência de meia-noite é o caso que
 * costuma quebrar quando o campo guarda UTC e a saída não volta ao fuso do site.
 */
#[Group('aculta_events')]
#[RunTestsInSeparateProcesses]
final class RecurrenceManagementKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system', 'user', 'taxonomy', 'link', 'text', 'filter', 'datetime', 'datetime_range',
    'options', 'field', 'key', 'address', 'geofield', 'aculta_calendar', 'aculta_events',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('taxonomy_term');
    $this->installConfig(['system', 'taxonomy']);
    $this->installEntitySchema('aculta_event');
    $this->installEntitySchema('aculta_event_date');
    $this->installEntitySchema('aculta_event_location');
    $this->installEntitySchema('aculta_event_override');
  }

  /**
   * Data de evento em Brasília, com regra opcional.
   */
  private function data(string $inicioBr, ?string $regra = NULL, string $fimBr = ''): EventDate {
    $fuso = new \DateTimeZone('America/Sao_Paulo');
    $utc = new \DateTimeZone('UTC');
    $inicio = new \DateTimeImmutable($inicioBr, $fuso);
    $fim = $fimBr !== '' ? new \DateTimeImmutable($fimBr, $fuso) : $inicio->modify('+2 hours');

    $evento = Event::create(['title' => 'Série de teste', 'field_status' => 'publicado']);
    $evento->save();

    $data = EventDate::create([
      'title' => 'Série de teste',
      'field_evento' => ['target_id' => $evento->id()],
      'field_quando' => [
        'value' => $inicio->setTimezone($utc)->format('Y-m-d\TH:i:s'),
        'end_value' => $fim->setTimezone($utc)->format('Y-m-d\TH:i:s'),
      ],
      'field_regra' => $regra,
    ]);
    $data->save();

    return $data;
  }

  /**
   * Ocorrências da data na janela, em Brasília.
   */
  private function ocorrencias(EventDate $data, string $deBr, string $ateBr): array {
    $fuso = new \DateTimeZone('America/Sao_Paulo');
    $agora = (new \DateTimeImmutable('@' . $this->container->get('datetime.time')->getCurrentTime()))->setTimezone($fuso);
    return $this->container->get('aculta_events.occurrences')->expandir(
      $data,
      new \DateTimeImmutable($deBr, $fuso),
      new \DateTimeImmutable($ateBr, $fuso),
      $agora,
    );
  }

  /**
   * Envia um formulário com os valores dados, devolvendo o estado usado.
   */
  private function envia(FormState $form_state, object $form_object, array $args): FormState {
    $form = $form_object->buildForm([], $form_state, ...$args);
    $form_object->validateForm($form, $form_state);
    if ($form_state->getErrors() === []) {
      $form_object->submitForm($form, $form_state);
    }
    return $form_state;
  }

  /**
   * A regra é gravada na forma canônica, mesmo escrita em outra ordem.
   */
  public function testGravaRegraCanonica(): void {
    $data = $this->data('2030-03-04 19:00');

    $form_state = new FormState();
    $form_state->setValues([
      'data_id' => $data->id(),
      'field_regra' => 'FREQ=WEEKLY;BYDAY=WE,MO',
      'field_limite_meses' => 6,
    ]);
    $this->envia($form_state, EventDateRuleForm::create($this->container), [$data]);
    $this->assertSame([], $form_state->getErrors());

    $salva = $this->container->get('entity_type.manager')->getStorage('aculta_event_date')->loadUnchanged($data->id());
    $this->assertSame('FREQ=WEEKLY;INTERVAL=1;BYDAY=MO,WE', $salva->get('field_regra')->value);
    $this->assertSame(6, (int) $salva->get('field_limite_meses')->value);
  }

  /**
   * Regra inválida é recusada com mensagem e não altera o campo.
   */
  public function testRegraInvalidaNaoSalva(): void {
    $data = $this->data('2030-03-04 19:00', 'FREQ=DAILY');

    $form_state = new FormState();
    $form_state->setValues([
      'data_id' => $data->id(),
      'field_regra' => 'FREQ=YEARLY;COUNT=2',
      'field_limite_meses' => 12,
    ]);
    $form_state = $this->envia($form_state, EventDateRuleForm::create($this->container), [$data]);

    $this->assertArrayHasKey('field_regra', $form_state->getErrors());
    $salva = $this->container->get('entity_type.manager')->getStorage('aculta_event_date')->loadUnchanged($data->id());
    $this->assertSame('FREQ=DAILY', $salva->get('field_regra')->value);
  }

  /**
   * Cancelar a ocorrência grava exceção sem horário; a série a mostra cancelada.
   */
  public function testCancelarOcorrencia(): void {
    $data = $this->data('2030-03-04 19:00', 'FREQ=WEEKLY;COUNT=3');
    $ocorrencias = $this->ocorrencias($data, '2030-03-01', '2030-03-31');
    $this->assertCount(3, $ocorrencias);
    $segunda = $ocorrencias[1]['original']->getTimestamp();

    $form_state = new FormState();
    $form_state->setValues([
      'data_id' => $data->id(),
      'inicio' => $segunda,
      'acao' => 'cancelar',
      'motivo' => 'Feriado',
    ]);
    $this->envia($form_state, EventDateOccurrenceForm::create($this->container), [$data, (string) $segunda]);
    $this->assertSame([], $form_state->getErrors());

    $depois = $this->ocorrencias($data, '2030-03-01', '2030-03-31');
    $this->assertTrue($depois[1]['cancelada']);
    $this->assertFalse($depois[0]['cancelada']);
  }

  /**
   * Reagendar mantém a chave original e muda só o horário daquela ocorrência.
   */
  public function testReagendarOcorrencia(): void {
    $data = $this->data('2030-03-04 19:00', 'FREQ=WEEKLY;COUNT=3');
    $segunda = $this->ocorrencias($data, '2030-03-01', '2030-03-31')[1]['original']->getTimestamp();

    $form_state = new FormState();
    $form_state->setValues([
      'data_id' => $data->id(),
      'inicio' => $segunda,
      'acao' => 'reagendar',
      'novo_inicio' => '06/03/2030 20:30',
      'novo_fim' => '06/03/2030 22:00',
    ]);
    $this->envia($form_state, EventDateOccurrenceForm::create($this->container), [$data, (string) $segunda]);
    $this->assertSame([], $form_state->getErrors());

    $depois = $this->ocorrencias($data, '2030-03-01', '2030-03-31');
    $this->assertSame($segunda, $depois[1]['original']->getTimestamp());
    $this->assertSame('06/03/2030 20:30', $depois[1]['inicio']->format('d/m/Y H:i'));
    $this->assertSame('06/03/2030 22:00', $depois[1]['fim']->format('d/m/Y H:i'));
  }

  /**
   * Restaurar apaga a exceção e devolve o horário da regra.
   */
  public function testRestaurarOcorrencia(): void {
    $data = $this->data('2030-03-04 19:00', 'FREQ=WEEKLY;COUNT=3');
    $segunda = $this->ocorrencias($data, '2030-03-01', '2030-03-31')[1]['original']->getTimestamp();

    $cancelar = new FormState();
    $cancelar->setValues(['data_id' => $data->id(), 'inicio' => $segunda, 'acao' => 'cancelar']);
    $this->envia($cancelar, EventDateOccurrenceForm::create($this->container), [$data, (string) $segunda]);
    $this->assertTrue($this->ocorrencias($data, '2030-03-01', '2030-03-31')[1]['cancelada']);

    $restaurar = new FormState();
    $restaurar->setValues(['data_id' => $data->id(), 'inicio' => $segunda, 'acao' => 'restaurar']);
    $this->envia($restaurar, EventDateOccurrenceForm::create($this->container), [$data, (string) $segunda]);
    $this->assertSame([], $restaurar->getErrors());

    $depois = $this->ocorrencias($data, '2030-03-01', '2030-03-31');
    $this->assertFalse($depois[1]['cancelada']);
    $this->assertSame('19:00', $depois[1]['inicio']->format('H:i'));
  }

  /**
   * Ocorrência que não existe na série é recusada.
   */
  public function testOcorrenciaInexistenteRecusada(): void {
    $data = $this->data('2030-03-04 19:00', 'FREQ=WEEKLY;COUNT=3');

    // A validação é chamada direto: o buildForm recusaria com 404 antes disso, e o
    // que se testa aqui é o guarda de POST forjado (início fora da série).
    $form_object = EventDateOccurrenceForm::create($this->container);
    $form_state = new FormState();
    $form_state->setValues([
      'data_id' => $data->id(),
      'inicio' => (new \DateTimeImmutable('2030-03-05 12:00', new \DateTimeZone('America/Sao_Paulo')))->getTimestamp(),
      'acao' => 'cancelar',
    ]);
    $form = [];
    $form_object->validateForm($form, $form_state);
    $this->assertArrayHasKey('acao', $form_state->getErrors());
  }

  /**
   * Ocorrência de meia-noite continua às 00:00 de Brasília, não às 21:00 do dia anterior.
   */
  public function testExpansaoMeiaNoiteFusoBrasilia(): void {
    $data = $this->data('2030-01-01 00:00', 'FREQ=DAILY;COUNT=2', '2030-01-01 01:00');

    $ocorrencias = $this->ocorrencias($data, '2030-01-01', '2030-01-03');
    $this->assertCount(2, $ocorrencias);
    $this->assertSame('01/01/2030 00:00', $ocorrencias[0]['inicio']->format('d/m/Y H:i'));
    $this->assertSame('02/01/2030 00:00', $ocorrencias[1]['inicio']->format('d/m/Y H:i'));
    // 00:00 de Brasília é 03:00 UTC: o campo guarda UTC, a saída volta ao fuso.
    $this->assertSame('03:00', $ocorrencias[0]['inicio']->setTimezone(new \DateTimeZone('UTC'))->format('H:i'));
    $this->assertSame(RecurrenceExpander::FUSO, $ocorrencias[0]['inicio']->getTimezone()->getName());
  }

  /**
   * As rotas de edição exigem a permissão administrativa de eventos.
   */
  public function testRotasExigemPermissaoDeAdministracao(): void {
    $provider = $this->container->get('router.route_provider');
    foreach (['aculta_events.date_rule', 'aculta_events.occurrence'] as $nome) {
      $rota = $provider->getRouteByName($nome);
      $this->assertSame('administer aculta events', $rota->getRequirement('_permission'));
    }
  }

}
