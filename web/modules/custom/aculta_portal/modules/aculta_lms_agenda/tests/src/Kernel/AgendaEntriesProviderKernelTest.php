<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms_agenda\Kernel;

use Drupal\aculta_events\Entity\Event;
use Drupal\aculta_events\Entity\EventDate;
use Drupal\lms\Entity\Activity;
use Drupal\lms\Entity\ActivityType;
use Drupal\lms\Entity\Lesson;
use Drupal\group\Entity\Group;
use Drupal\Tests\aculta_lms\Kernel\AcultaLmsDependencyHarnessTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Component\Serialization\Yaml;
use PHPUnit\Framework\Attributes\Group as TestGroup;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Agenda do aluno: prazos e eventos das turmas em que ele está matriculado, e só esses.
 */
#[TestGroup('aculta_lms_agenda')]
#[RunTestsInSeparateProcesses]
final class AgendaEntriesProviderKernelTest extends KernelTestBase {

  use AcultaLmsDependencyHarnessTrait;
  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system', 'key', 'address', 'geofield', 'domain', 'datetime', 'datetime_range', 'user', 'file', 'image', 'media', 'options',
    'entity_print', 'entity_usage', 'aculta_image', 'field', 'filter', 'flexible_permissions',
    'group', 'lms', 'lms_classes', 'views', 'text', 'taxonomy', 'aculta_lms', 'aculta_calendar',
    'aculta_events', 'aculta_lms_agenda',
  ];

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container): void {
    parent::register($container);
    $this->registerLmsDependencyServices($container);
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['system', 'group', 'lms', 'lms_classes']);
    foreach ([
      'group.relationship_type.lms_class-lms_class_activity_due',
      'field.storage.group_relationship.field_activity_due_at',
      'field.field.group_relationship.lms_class-lms_class_activity_due.field_activity_due_at',
      'core.entity_form_display.group_relationship.lms_class-lms_class_activity_due.default',
      'core.entity_view_display.group_relationship.lms_class-lms_class_activity_due.default',
    ] as $config_id) {
      $path = DRUPAL_ROOT . '/../config/sync/' . $config_id . '.yml';
      $this->container->get('config.storage')->write($config_id, Yaml::decode(file_get_contents($path)));
    }
    $this->container->get('entity_field.manager')->clearCachedFieldDefinitions();
    $this->container->get('group_relation_type.manager')->clearCachedPluginMaps();
    $this->installEntitySchema('action');
    $this->installEntitySchema('user');
    $this->installSchema('user', ['users_data']);
    $this->installEntitySchema('group');
    $this->installEntitySchema('group_relationship');
    $this->installEntitySchema('group_config_wrapper');
    $this->installEntitySchema('lms_lesson');
    $this->installEntitySchema('lms_activity');
    $this->installEntitySchema('lms_course_status');
    $this->installEntitySchema('lms_lesson_status');
    $this->installEntitySchema('lms_answer');
    $this->installEntitySchema('taxonomy_term');
    $this->installEntitySchema('aculta_event');
    $this->installEntitySchema('aculta_event_date');
    $this->installEntitySchema('aculta_event_location');
    $this->installEntitySchema('aculta_event_override');
  }

  /**
   * Converte horário de Brasília para o valor guardado (UTC, sem sufixo).
   */
  private function utc(string $brasilia): string {
    return (new \DateTimeImmutable($brasilia, new \DateTimeZone('America/Sao_Paulo')))
      ->setTimezone(new \DateTimeZone('UTC'))
      ->format('Y-m-d\TH:i:s');
  }

  /**
   * Aluno matriculado vê prazo e evento da turma; visitante não vê nada.
   */
  public function testSoMatriculadosVeemAsEntradasDaTurma(): void {
    $dono = $this->createUser();
    ActivityType::create(['id' => 'agenda_test', 'name' => 'Agenda test', 'pluginId' => 'no_answer'])->save();
    $atividade = Activity::create(['type' => 'agenda_test', 'name' => 'Atividade de agenda', 'status' => 1, 'uid' => $dono->id()]);
    $atividade->save();
    $aula = Lesson::create(['name' => 'Aula', 'status' => 1, 'uid' => $dono->id()]);
    $aula->get('activities')->appendItem(['target_id' => $atividade->id()]);
    $aula->save();
    $curso = Group::create(['type' => 'lms_course', 'label' => 'Curso de agenda', 'uid' => $dono->id(), 'status' => 1]);
    $curso->get('lessons')->appendItem(['target_id' => $aula->id()]);
    $curso->save();
    $turma = Group::create(['type' => 'lms_class', 'label' => 'Turma A', 'uid' => $dono->id(), 'status' => 1]);
    $turma->save();
    $curso->addRelationship($turma, 'lms_classes');
    $turma->addRelationship($atividade, 'lms_class_activity_due', ['field_activity_due_at' => $this->utc('2026-11-02 12:00:00')]);

    $evento = Event::create(['title' => 'Roda de conversa', 'field_status' => 'publicado', 'field_turma' => $turma->id()]);
    $evento->save();
    EventDate::create([
      'title' => 'Roda de conversa',
      'field_evento' => $evento->id(),
      'field_quando' => ['value' => $this->utc('2026-11-04 19:00:00'), 'end_value' => $this->utc('2026-11-04 21:00:00')],
    ])->save();

    $aluno = $this->createUser();
    $turma->addMember($aluno);
    $visitante = $this->createUser();

    $de = new \DateTimeImmutable('2026-11-01 00:00:00', new \DateTimeZone('America/Sao_Paulo'));
    $ate = new \DateTimeImmutable('2026-11-30 23:59:59', new \DateTimeZone('America/Sao_Paulo'));
    $servico = $this->container->get('aculta_lms_agenda.entries');

    $entradas = $servico->forAccount($aluno, $de, $ate);
    $this->assertSame(['prazo', 'evento'], array_column($entradas, 'tipo'));
    $this->assertSame('02/11/2026 12:00', $entradas[0]['inicio']->format('d/m/Y H:i'));
    $this->assertSame('04/11/2026 19:00', $entradas[1]['inicio']->format('d/m/Y H:i'));
    $this->assertSame([], $servico->forAccount($visitante, $de, $ate));
  }


  /**
   * O painel mostra os próximos itens do aluno e nada para o visitante.
   */
  public function testPainelMostraProximosSoParaMatriculados(): void {
    $fuso = new \DateTimeZone('America/Sao_Paulo');
    $agora = new \DateTimeImmutable('now', $fuso);
    $prazo = $agora->modify('+2 days')->setTime(12, 0);
    $evento = $agora->modify('+3 days')->setTime(19, 0);

    $dono = $this->createUser();
    ActivityType::create(['id' => 'painel_test', 'name' => 'Painel test', 'pluginId' => 'no_answer'])->save();
    $atividade = Activity::create(['type' => 'painel_test', 'name' => 'Leitura do painel', 'status' => 1, 'uid' => $dono->id()]);
    $atividade->save();
    $aula = Lesson::create(['name' => 'Aula do painel', 'status' => 1, 'uid' => $dono->id()]);
    $aula->get('activities')->appendItem(['target_id' => $atividade->id()]);
    $aula->save();
    $curso = Group::create(['type' => 'lms_course', 'label' => 'Curso do painel', 'uid' => $dono->id(), 'status' => 1]);
    $curso->get('lessons')->appendItem(['target_id' => $aula->id()]);
    $curso->save();
    $turma = Group::create(['type' => 'lms_class', 'label' => 'Turma do painel', 'uid' => $dono->id(), 'status' => 1]);
    $turma->save();
    $curso->addRelationship($turma, 'lms_classes');
    $turma->addRelationship($atividade, 'lms_class_activity_due', ['field_activity_due_at' => $prazo->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s')]);

    $eventoEntidade = Event::create(['title' => 'Encontro do painel', 'field_status' => 'publicado', 'field_turma' => $turma->id()]);
    $eventoEntidade->save();
    EventDate::create([
      'title' => 'Encontro do painel',
      'field_evento' => $eventoEntidade->id(),
      'field_quando' => [
        'value' => $evento->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s'),
        'end_value' => $evento->modify('+2 hours')->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s'),
      ],
    ])->save();

    $aluno = $this->createUser();
    $turma->addMember($aluno);
    $bloco = $this->container->get('plugin.manager.block')->createInstance('aculta_lms_agenda_proximos', []);

    $this->setCurrentUser($aluno);
    $renderizado = $bloco->build();
    $html = (string) $this->render($renderizado);
    $this->assertStringContainsString($prazo->format('d/m/Y 12:00'), $html);
    $this->assertStringContainsString('Encontro do painel', $html);

    $this->setCurrentUser($this->createUser());
    $vazio = $bloco->build();
    $this->assertStringContainsString('Nenhum prazo ou evento', (string) $this->render($vazio));
  }


  /**
   * Link pessoal: o aluno gera o token, o .ics traz o prazo da turma, e o revogado deixa de valer.
   */
  public function testIcsPessoalComTokenRevogavel(): void {
    $fuso = new \DateTimeZone('America/Sao_Paulo');
    $prazo = (new \DateTimeImmutable('now', $fuso))->modify('+2 days')->setTime(12, 0);

    $dono = $this->createUser();
    ActivityType::create(['id' => 'pessoal_test', 'name' => 'Pessoal test', 'pluginId' => 'no_answer'])->save();
    $atividade = Activity::create(['type' => 'pessoal_test', 'name' => 'Leitura pessoal', 'status' => 1, 'uid' => $dono->id()]);
    $atividade->save();
    $aula = Lesson::create(['name' => 'Aula pessoal', 'status' => 1, 'uid' => $dono->id()]);
    $aula->get('activities')->appendItem(['target_id' => $atividade->id()]);
    $aula->save();
    $curso = Group::create(['type' => 'lms_course', 'label' => 'Curso pessoal', 'uid' => $dono->id(), 'status' => 1]);
    $curso->get('lessons')->appendItem(['target_id' => $aula->id()]);
    $curso->save();
    $turma = Group::create(['type' => 'lms_class', 'label' => 'Turma pessoal', 'uid' => $dono->id(), 'status' => 1]);
    $turma->save();
    $curso->addRelationship($turma, 'lms_classes');
    $turma->addRelationship($atividade, 'lms_class_activity_due', ['field_activity_due_at' => $prazo->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s')]);

    $aluno = $this->createUser();
    $turma->addMember($aluno);

    $tokens = $this->container->get('aculta_lms_agenda.tokens');
    $token = $tokens->gerar((int) $aluno->id());
    $resposta = \Drupal\aculta_lms_agenda\Controller\PersonalCalendarController::create($this->container)->exportar($token);

    $this->assertSame(200, $resposta->getStatusCode());
    $this->assertStringContainsString('SUMMARY:Prazo: Leitura pessoal', (string) $resposta->getContent());
    $this->assertStringContainsString('no-store', (string) $resposta->headers->get('Cache-Control'));

    $tokens->revogar((int) $aluno->id());
    $this->assertSame(404, \Drupal\aculta_lms_agenda\Controller\PersonalCalendarController::create($this->container)->exportar($token)->getStatusCode());
  }

}
