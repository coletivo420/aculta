<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_events\Kernel;

use Drupal\aculta_cgu\Entity\Contribution;
use Drupal\aculta_events\Entity\Event;
use Drupal\aculta_events\Entity\EventDate;
use Drupal\aculta_events\Form\EventContributionSubmissionForm;
use Drupal\aculta_image\FileRemover;
use Drupal\aculta_portal\Account\EmailConfirmationPolicy;
use Drupal\aculta_portal\Domain\DomainSiteManager;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormState;
use Drupal\domain\DomainInterface;
use Drupal\domain\DomainNegotiatorInterface;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Kernel test proving that event proposals never publish without editorial approval.
 *
 * Strict requirement of PLANO-CALENDARIO-DASHBOARD-CONTA.md Phase 3.
 */
#[CoversClass(EventContributionSubmissionForm::class)]
#[Group('aculta_events')]
#[RunTestsInSeparateProcesses]
final class EventProposalEditorialApprovalKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'key',
    'options',
    'link',
    'text',
    'filter',
    'taxonomy',
    'datetime',
    'datetime_range',
    'address',
    'geofield',
    'domain',
    'entity',
    'profile',
    'path_alias',
    'file',
    'image',
    'aculta_calendar',
    'aculta_maps',
    'aculta_user',
    'aculta_cgu',
    'aculta_index',
    'aculta_events',
  ];

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container): void {
    parent::register($container);

    $root = dirname(__DIR__, 9);
    $loader = require $root . '/vendor/autoload.php';
    $customRoot = $root . '/web/modules/custom/aculta_portal';
    $loader->addPsr4('Drupal\\aculta_portal\\', $customRoot . '/src', TRUE);
    $loader->addPsr4('Drupal\\aculta_image\\', $customRoot . '/modules/aculta_image/src', TRUE);
    $loader->addPsr4('Drupal\\aculta_user\\', $customRoot . '/modules/aculta_user/src', TRUE);
    $loader->addPsr4('Drupal\\aculta_cgu\\', $customRoot . '/modules/aculta_cgu/src', TRUE);
    $loader->addPsr4('Drupal\\aculta_index\\', $customRoot . '/modules/aculta_index/src', TRUE);
    $loader->addPsr4('Drupal\\aculta_events\\', $customRoot . '/modules/aculta_events/src', TRUE);

    $container->register('aculta_portal.domain_site', DomainSiteManager::class)
      ->addArgument($this->createMock(EntityTypeManagerInterface::class))
      ->addArgument($this->createMock(DomainNegotiatorInterface::class))
      ->addArgument($this->createMock(ConfigFactoryInterface::class))
      ->addArgument(new RequestStack());

    $container->register('aculta_image.render', \stdClass::class);
    $container->register('aculta_image.file_remover', FileRemover::class)
      ->addArgument(new Reference('file.usage'))
      ->addArgument(new Reference('entity_type.manager'))
      ->addArgument(new Reference('database'))
      ->addArgument(new Reference('file_system'))
      ->addArgument(new Reference('logger.factory'));

    $container->register('aculta_file_uploader.unused_file_cleaner', \stdClass::class);
    $container->register('aculta_portal.email_confirmation_policy', EmailConfirmationPolicy::class)
      ->addArgument(new Reference('user.data'))
      ->addArgument(new Reference('entity_type.manager'))
      ->addArgument(new Reference('module_handler'));
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('profile');
    $this->installSchema('user', ['users_data']);
    $this->installSchema('file', ['file_usage']);
    $this->installSchema('aculta_user', ['aculta_user_nickname']);
    $this->installEntitySchema('taxonomy_term');
    $this->installEntitySchema('aculta_org_member');
    $this->installEntitySchema('aculta_ownership_claim');
    $this->installEntitySchema('aculta_cgu_report');
    $this->installSchema('aculta_index', [
      'aculta_index_generation',
      'aculta_index_state',
      'aculta_index_entry',
      'aculta_index_relation',
      'aculta_index_work',
    ]);
    $this->installEntitySchema('aculta_event');
    $this->installEntitySchema('aculta_event_date');
    $this->installEntitySchema('aculta_event_location');
    $this->installEntitySchema('aculta_event_organization');
    $this->installEntitySchema('aculta_event_org_suggestion');
    $this->installEntitySchema('aculta_contribution');
    $this->installConfig(['system', 'user', 'taxonomy', 'domain', 'aculta_cgu']);

    FieldStorageConfig::create([
      'entity_type' => 'profile',
      'field_name' => 'field_nickname',
      'type' => 'string',
      'settings' => ['max_length' => 80],
    ])->save();
  }

  /**
   * Sets domain purpose to 'eventos'.
   */
  private function setEventosPurpose(): void {
    $eventsDomain = $this->createMock(DomainInterface::class);
    $eventsDomain->method('id')->willReturn('eventos_aculta_org');
    $negotiator = $this->createMock(DomainNegotiatorInterface::class);
    $negotiator->method('getActiveDomain')->willReturn($eventsDomain);

    $this->container->set('aculta_portal.domain_site', new DomainSiteManager(
      $this->createMock(EntityTypeManagerInterface::class),
      $negotiator,
      $this->createMock(ConfigFactoryInterface::class),
      new RequestStack(),
    ));
  }

  /**
   * Garante que a proposta de evento só é publicada após aprovação editorial.
   *
   * A submissão cria apenas uma entidade de contribuição pendente e nunca uma
   * entidade aculta_event publicada até a aprovação por um editor.
   */
  public function testCommonUserProposalNeverPublishesWithoutEditorialApproval(): void {
    $this->setEventosPurpose();

    $editorRole = Role::load('content_editor');
    if ($editorRole === NULL) {
      $editorRole = Role::create([
        'id' => 'content_editor',
        'label' => 'Content editor',
        'permissions' => [
          'create events_event contribution',
        ],
      ]);
      $editorRole->save();
    }

    $contributor = User::create([
      'name' => 'contributor_proposer',
      'mail' => 'proposer@example.com',
      'status' => 1,
    ]);
    $contributor->addRole('content_editor');
    $contributor->save();

    $this->container->get('current_user')->setAccount($contributor);

    $formObject = $this->container->get('class_resolver')
      ->getInstanceFromDefinition(EventContributionSubmissionForm::class);
    $formState = new FormState();
    $form = $formObject->buildForm([], $formState);

    $formState->setValue('payload', [
      'title' => 'Festival Comunitário sem Aprovação Editorial',
      'field_subtitulo' => 'Edição Independente',
      'body' => 'Texto descritivo do evento proposto por membro comum.',
      'field_url' => '',
      'field_organizacao' => '',
      'field_coorganizadores' => [],
      'dates' => [
        [
          'title' => 'Abertura do festival',
          'starts_at' => '2027-06-15T19:00:00-03:00',
          'ends_at' => '2027-06-15T22:00:00-03:00',
          'location_uuid' => '',
        ],
      ],
    ]);
    $formState->setValue('rationale', 'Proposta de atividade comunitária para avaliação.');
    $formState->setValue('submission_token', $form['submission_token']['#value']);

    $formObject->validateForm($form, $formState);
    self::assertFalse($formState->hasAnyErrors());
    $formObject->submitForm($form, $formState);

    // Confere que a contribuição foi criada com estado 'submitted' (pendente)
    $contributions = $this->container->get('entity_type.manager')
      ->getStorage('aculta_contribution')->loadByProperties([
        'uid' => (int) $contributor->id(),
        'source' => 'events_event',
      ]);
    self::assertCount(1, $contributions);
    $proposal = reset($contributions);
    self::assertInstanceOf(Contribution::class, $proposal);
    self::assertSame('submitted', $proposal->get('state')->value);

    // Comprova que NENHUM evento publicado (nem qualquer aculta_event) foi criado.
    $events = $this->container->get('entity_type.manager')
      ->getStorage('aculta_event')->loadByProperties([
        'title' => 'Festival Comunitário sem Aprovação Editorial',
      ]);
    self::assertCount(0, $events, 'Nenhum evento oficial pode ser criado sem aprovação editorial prévia.');

    // Comprova que nenhuma data de evento associada existe.
    $eventDates = $this->container->get('entity_type.manager')
      ->getStorage('aculta_event_date')->loadMultiple();
    foreach ($eventDates as $eventDate) {
      if (!$eventDate instanceof EventDate) {
        continue;
      }
      $ev = $eventDate->get('field_evento')->entity;
      if ($ev instanceof Event && $ev->label() === 'Festival Comunitário sem Aprovação Editorial') {
        self::fail('Nenhuma data de evento pode existir para uma proposta ainda não aprovada.');
      }
    }
  }

}
