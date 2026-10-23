<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms_resources\Kernel;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\aculta_index\Provider\ContentIndexProviderRegistry;
use Drupal\aculta_index\Query\ContentIndexAccessFilter;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\domain\DomainNegotiatorInterface;
use Drupal\domain\Entity\Domain;
use Drupal\group\Entity\Group;
use Drupal\group\Entity\GroupRole;
use Drupal\group\PermissionScopeInterface;
use Drupal\domain\DomainInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use PHPUnit\Framework\Attributes\Group as TestGroup;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Yaml\Yaml;

/**
 * Verifies Content Index LMS policy with real Group courses and memberships.
 */
#[TestGroup('aculta_lms_resources')]
#[RunTestsInSeparateProcesses]
final class LmsCourseContentIndexAccessKernelTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * Canonical domain IDs activated during the current access test.
   *
   * @var list<string>
   */
  private array $domainSwitches = [];

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'file',
    'image',
    'media',
    'entity_usage',
    'media_duplicates',
    'aculta_image',
    'field',
    'filter',
    'flexible_permissions',
    'group',
    'domain',
    'views',
    'options',
    'text',
    'lms',
    'lms_classes',
  ];

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container): void {
    parent::register($container);
    $domainStorage = $this->createMock(EntityStorageInterface::class);
    $domainStorage->method('load')->willReturnCallback(static fn (string $id): ?Domain => Domain::load($id));
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')->with('domain')->willReturn($domainStorage);
    $domainNegotiator = $this->createMock(DomainNegotiatorInterface::class);
    $domainNegotiator->method('getActiveDomain')->willReturnCallback(
      static function (bool $reset = FALSE): ?DomainInterface {
        return \Drupal::service('domain.negotiator')->getActiveDomain($reset);
      },
    );
    $domainNegotiator->method('setActiveDomain')->willReturnCallback(
      function (DomainInterface $domain): void {
        \Drupal::service('domain.negotiator')->setActiveDomain($domain);
        $this->domainSwitches[] = $domain->id();
      },
    );
    $container->set('aculta_portal.domain_purpose', new DomainPurposeManager(
      $entityTypeManager,
      $domainNegotiator,
      $this->createMock(ConfigFactoryInterface::class),
      new RequestStack(),
    ));

    // Compile the provider's production declaration and tag from the owner
    // module without enabling its full dependency chain in this Kernel.
    $ownerServices = Yaml::parseFile(
      DRUPAL_ROOT . '/modules/custom/aculta_portal/modules/aculta_lms_resources/aculta_lms_resources.services.yml',
      Yaml::PARSE_CUSTOM_TAGS,
    )['services'];
    $service = $ownerServices['aculta_lms_resources.course_content_index_provider'];
    $definition = (new Definition($service['class'], [new Reference('aculta_portal.domain_purpose')]))
      ->setPublic(TRUE);
    foreach ($service['tags'] ?? [] as $tag) {
      $definition->addTag($tag['name'], array_diff_key($tag, ['name' => TRUE]));
    }
    $container->setDefinition('aculta_lms_resources.course_content_index_provider', $definition);
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['group', 'lms', 'lms_classes', 'user']);
    $this->installEntitySchema('domain');
    foreach (['aculta_org' => 'main', 'cursos_aculta_org' => 'courses'] as $id => $name) {
      Domain::create([
        'id' => $id,
        'hostname' => $name . '.example.invalid',
        'name' => $name,
        'scheme' => 'https',
        'weight' => 0,
        'status' => TRUE,
      ])->save();
    }
    $domainNegotiator = $this->container->get(DomainNegotiatorInterface::class);
    $domainNegotiator->getActiveDomain();
    $domainNegotiator->setActiveDomain(Domain::load('aculta_org'));
    $this->domainSwitches = [];
    $this->installEntitySchema('user');
    $this->installSchema('user', ['users_data']);
    $this->installSchema('entity_usage', ['entity_usage']);
    $this->installEntitySchema('group');
    $this->installEntitySchema('group_relationship');
    $this->installEntitySchema('group_config_wrapper');
  }

  /**
   * Combines Entity Access, canonical Domain and LMS policy.
   */
  public function testStudentAndEducatorPolicyUsesTheirCourseMembership(): void {
    $owner = $this->createUser(['administer lms']);
    $student = $this->createUser();
    $educator = $this->createUser();
    $outsider = $this->createUser();
    $course = Group::create([
      'type' => 'lms_course',
      'label' => 'Curso para validar acesso do Index',
      'uid' => $owner->id(),
    ]);
    $course->save();

    GroupRole::create([
      'id' => 'lms_course-index_student',
      'label' => 'Estudante de teste',
      'group_type' => 'lms_course',
      'scope' => PermissionScopeInterface::INDIVIDUAL_ID,
      'permissions' => ['take course', 'view group'],
    ])->save();
    GroupRole::create([
      'id' => 'lms_course-index_educator',
      'label' => 'Educador de teste',
      'group_type' => 'lms_course',
      'scope' => PermissionScopeInterface::INDIVIDUAL_ID,
      'permissions' => ['grade students', 'view group'],
    ])->save();
    $course->addMember($student, ['group_roles' => ['lms_course-index_student']]);
    $course->addMember($educator, ['group_roles' => ['lms_course-index_educator']]);

    $provider = $this->container->get('aculta_lms_resources.course_content_index_provider');
    $providers = new ContentIndexProviderRegistry([$provider]);
    $filter = new ContentIndexAccessFilter(
      $this->container->get('entity_type.manager'),
      $providers,
      $this->container->get('aculta_portal.domain_purpose'),
    );
    $candidate = [
      [
        'entity_type' => 'group',
        'entity_id' => (string) $course->id(),
        'langcode' => $course->language()->getId(),
      ],
    ];

    self::assertTrue($course->access('view', $student, TRUE)->isAllowed(), 'The student fixture has Group Entity Access.');
    self::assertCount(1, $filter->filter($candidate, $student)->entities, 'The Index accepts a student with takeAccess.');
    self::assertSame(['cursos_aculta_org', 'aculta_org'], $this->domainSwitches, 'The student access check uses and restores its canonical Domain.');
    self::assertSame('main', $this->container->get('aculta_portal.domain_purpose')->getCurrentPurpose(), 'The active Domain is restored after each access check.');
    $this->domainSwitches = [];
    self::assertFalse($course->takeAccess($educator)->isAllowed(), 'The educator fixture does not have student access.');
    self::assertTrue($course->hasPermission('grade students', $educator));
    self::assertTrue($course->access('view', $educator, TRUE)->isAllowed(), 'The educator fixture has Group Entity Access.');
    self::assertCount(1, $filter->filter($candidate, $educator)->entities, 'The Index accepts an educator with contextual permission.');
    self::assertSame(['cursos_aculta_org', 'aculta_org'], $this->domainSwitches, 'The educator access check uses and restores its canonical Domain.');
    self::assertSame('main', $this->container->get('aculta_portal.domain_purpose')->getCurrentPurpose(), 'The Domain context is restored after educator access.');
    self::assertCount(0, $filter->filter($candidate, $outsider)->entities, 'An unrelated account is denied.');
  }

}
