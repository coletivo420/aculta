<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_studio\Kernel;

use Drupal\aculta_calendar\Item\CalendarQuery;
use Drupal\aculta_events\Calendar\EventCalendarItemProvider;
use Drupal\aculta_events\Entity\Event;
use Drupal\aculta_events\Entity\EventDate;
use Drupal\aculta_events\Entity\EventLocation;
use Drupal\aculta_portal\Saved\SavedContentQuery;
use Drupal\aculta_studio\Calendar\StudioCoursesCalendarItemProvider;
use Drupal\aculta_studio\Calendar\StudioEventsCalendarItemProvider;
use Drupal\aculta_studio\Calendar\StudioMyEventsCalendarItemProvider;
use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Session\AnonymousUserSession;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\aculta_lms\Kernel\AcultaLmsDependencyHarnessTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;
use PHPUnit\Framework\Attributes\Group as TestGroup;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Testes de Kernel dos provedores de calendário do Studio (Fase 1).
 */
#[TestGroup('aculta_studio')]
#[RunTestsInSeparateProcesses]
final class StudioCalendarProvidersKernelTest extends KernelTestBase {

  use AcultaLmsDependencyHarnessTrait;
  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system', 'key', 'address', 'geofield', 'domain', 'datetime', 'datetime_range', 'user', 'file', 'image', 'media', 'options', 'link',
    'entity_print', 'entity_usage', 'aculta_image', 'aculta_file_uploader', 'field', 'filter', 'flexible_permissions',
    'comment', 'group', 'views', 'text', 'taxonomy', 'aculta_lms', 'aculta_calendar',
    'aculta_events', 'aculta_lms_agenda', 'aculta_studio',
  ];

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container): void {
    parent::register($container);
    $root = dirname(__DIR__, 9);
    $loader = require $root . '/vendor/autoload.php';
    $loader->addPsr4('Drupal\\aculta_studio\\', $root . '/web/modules/custom/aculta_portal/modules/aculta_studio/src');
    $loader->addPsr4('Drupal\\aculta_portal\\', $root . '/web/modules/custom/aculta_portal/src');

    $this->registerLmsDependencyServices($container);

    $container->register('aculta_portal.saved_content_query', SavedContentQuery::class)
      ->addArgument(new Reference('database'))
      ->addArgument(new Reference('entity_type.manager'));

    // Instancia o compositor e ganjalab necessários caso a rota seja construída.
    $container->register('aculta_studio.learner_dashboard_composer', \Drupal\aculta_studio\Learning\LearnerDashboardComposer::class)
      ->addArgument(new Reference('aculta_learning_contracts.student_learning_summary_reader', ContainerBuilder::NULL_ON_INVALID_REFERENCE))
      ->addArgument(new Reference('current_user'))
      ->addArgument(new Reference('date.formatter'))
      ->addArgument(new Reference('string_translation'));

    $container->register('aculta_studio.calendar_provider.cursos', StudioCoursesCalendarItemProvider::class)
      ->addArgument(new Reference('aculta_lms_agenda.entries', ContainerBuilder::NULL_ON_INVALID_REFERENCE))
      ->addArgument(new Reference('current_user'))
      ->addTag('aculta_calendar.item_provider');

    $container->register('aculta_studio.calendar_provider.eventos', StudioEventsCalendarItemProvider::class)
      ->addArgument(new Reference('aculta_events.calendar_provider', ContainerBuilder::NULL_ON_INVALID_REFERENCE))
      ->addArgument(new Reference('current_user'))
      ->addTag('aculta_calendar.item_provider');

    $container->register('aculta_studio.calendar_provider.meus_eventos', StudioMyEventsCalendarItemProvider::class)
      ->addArgument(new Reference('entity_type.manager'))
      ->addArgument(new Reference('aculta_events.calendar_provider', ContainerBuilder::NULL_ON_INVALID_REFERENCE))
      ->addArgument(new Reference('current_user'))
      ->addArgument(new Reference('aculta_user.organization_membership_manager', ContainerBuilder::NULL_ON_INVALID_REFERENCE))
      ->addTag('aculta_calendar.item_provider');
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig(['system', 'comment', 'group']);
    foreach ([
      'group.type.lms_class',
      'group.type.lms_course',
      'group.relationship_type.lms_class-group_membership',
      'group.relationship_type.lms_class-lms_class_activity_due',
      'group.relationship_type.lms_course-group_membership',
      'group.relationship_type.lms_course-lms_classes',
      'group.role.lms_class-member',
      'group.role.lms_course-anonymous',
      'group.role.lms_course-course_creator',
      'group.role.lms_course-member',
      'group.role.lms_course-outsider',
      'group.role.lms_course-teacher',
      'field.storage.group_relationship.field_activity_due_at',
      'field.field.group_relationship.lms_class-lms_class_activity_due.field_activity_due_at',
      'core.entity_form_display.group_relationship.lms_class-lms_class_activity_due.default',
      'core.entity_view_display.group_relationship.lms_class-lms_class_activity_due.default',
    ] as $config_id) {
      $path = DRUPAL_ROOT . '/../config/sync/' . $config_id . '.yml';
      if (file_exists($path)) {
        $data = Yaml::decode(file_get_contents($path));
        if (str_starts_with($config_id, 'group.type.')) {
          $storage = $this->container->get('entity_type.manager')->getStorage('group_type');
          if (!$storage->load($data['id'])) {
            $storage->create($data)->save();
          }
        }
        elseif (str_starts_with($config_id, 'group.relationship_type.')) {
          $storage = $this->container->get('entity_type.manager')->getStorage('group_relationship_type');
          if (!$storage->load($data['id'])) {
            $storage->create($data)->save();
          }
        }
        else {
          $this->container->get('config.storage')->write($config_id, $data);
        }
      }
    }

    $this->installEntitySchema('user');
    $this->installEntitySchema('group');
    $this->installEntitySchema('group_relationship');
    $this->installEntitySchema('aculta_event');
    $this->installEntitySchema('aculta_event_date');
    $this->installEntitySchema('aculta_event_location');
    $this->installEntitySchema('aculta_lms_activity');
    $this->installEntitySchema('aculta_lms_lesson');
    $this->installSchema('system', ['sequences']);
  }

  public function testAnonimoRecebeListaVazia(): void {
    $anonymous = new AnonymousUserSession();
    \Drupal::currentUser()->setAccount($anonymous);

    /** @var \Drupal\aculta_studio\Calendar\StudioCoursesCalendarItemProvider $cursos */
    $cursos = \Drupal::service('aculta_studio.calendar_provider.cursos');
    /** @var \Drupal\aculta_studio\Calendar\StudioMyEventsCalendarItemProvider $meusEventos */
    $meusEventos = \Drupal::service('aculta_studio.calendar_provider.meus_eventos');

    $query = new CalendarQuery(
      new \DateTimeImmutable('2026-11-01 00:00:00'),
      new \DateTimeImmutable('2026-11-30 23:59:59'),
      new \DateTimeImmutable('2026-11-06 12:00:00'),
    );

    $this->assertSame([], iterator_to_array($cursos->items($query)));
    $this->assertSame([], iterator_to_array($meusEventos->items($query)));
  }

  public function testUsuarioSemMatriculaNaoVeCursos(): void {
    $user = $this->createUser();
    \Drupal::currentUser()->setAccount($user);

    /** @var \Drupal\aculta_studio\Calendar\StudioCoursesCalendarItemProvider $cursos */
    $cursos = \Drupal::service('aculta_studio.calendar_provider.cursos');

    $query = new CalendarQuery(
      new \DateTimeImmutable('2026-11-01 00:00:00'),
      new \DateTimeImmutable('2026-11-30 23:59:59'),
      new \DateTimeImmutable('2026-11-06 12:00:00'),
    );

    $this->assertSame([], iterator_to_array($cursos->items($query)));
  }

  public function testMeusEventosNaoRepeteOMesmoEvento(): void {
    $user = $this->createUser();
    \Drupal::currentUser()->setAccount($user);

    // Cria um local publicado
    $local = EventLocation::create([
      'title' => 'Galpão Cultural',
      'field_status' => 'publicado',
    ]);
    $local->save();

    // Cria um evento pertencente ao usuário
    $evento = Event::create([
      'title' => 'Festival da Primavera',
      'field_status' => 'publicado',
      'field_organizador' => $user->id(),
    ]);
    $evento->save();

    // Cria duas datas para o mesmo evento
    $data1 = EventDate::create([
      'field_evento' => $evento->id(),
      'field_local' => $local->id(),
      'field_quando' => [
        'value' => '2026-11-15T14:00:00',
        'end_value' => '2026-11-15T18:00:00',
      ],
      'field_regra' => '',
    ]);
    $data1->save();

    $data2 = EventDate::create([
      'field_evento' => $evento->id(),
      'field_local' => $local->id(),
      'field_quando' => [
        'value' => '2026-11-16T14:00:00',
        'end_value' => '2026-11-16T18:00:00',
      ],
      'field_regra' => '',
    ]);
    $data2->save();

    /** @var \Drupal\aculta_studio\Calendar\StudioMyEventsCalendarItemProvider $meusEventos */
    $meusEventos = \Drupal::service('aculta_studio.calendar_provider.meus_eventos');

    $query = new CalendarQuery(
      new \DateTimeImmutable('2026-11-01 00:00:00'),
      new \DateTimeImmutable('2026-11-30 23:59:59'),
      new \DateTimeImmutable('2026-11-06 12:00:00'),
    );

    $items = iterator_to_array($meusEventos->items($query));
    // As duas datas distintas do evento aparecem
    $this->assertCount(2, $items);
    $this->assertSame('meus_eventos', $items[0]->origem);
    $this->assertSame('meus_eventos', $items[1]->origem);

    // Desduplicação estável por ocorrência/data
    $ids = array_map(fn($it) => $it->id, $items);
    $this->assertCount(2, array_unique($ids));
  }

}
