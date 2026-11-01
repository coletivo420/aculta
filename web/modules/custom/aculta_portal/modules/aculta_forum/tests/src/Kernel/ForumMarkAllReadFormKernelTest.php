<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum\Kernel;

use Drupal\aculta_forum\Form\ForumMarkAllReadForm;
use Drupal\aculta_forum\Service\ForumReadMarker;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\Core\Form\FormState;
use Drupal\Core\Url;
use Drupal\domain\Entity\Domain;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\NodeType;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Proves the mark-all-read confirmation fails safely with no topics.
 */
#[Group('aculta_forum')]
#[RunTestsInSeparateProcesses]
final class ForumMarkAllReadFormKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   *
   * @var string[]
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'text',
    'node',
    'domain',
    'history',
  ];

  /**
   * Purpose resolver.
   */
  private DomainPurposeManager $purpose;

  /**
   * Form under test.
   */
  private ForumMarkAllReadForm $form;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installSchema('node', ['node_access']);
    $this->installSchema('history', ['history']);
    $this->installConfig(['system', 'user', 'field', 'text', 'node', 'domain', 'history']);
    foreach ([
      'aculta_org' => 'main.example.test',
      'forum_aculta_org' => 'forum.example.test',
    ] as $id => $hostname) {
      Domain::create([
        'id' => $id,
        'hostname' => $hostname,
        'name' => $id,
        'scheme' => 'https',
        'status' => TRUE,
        'is_default' => $id === 'aculta_org',
      ])->save();
    }
    NodeType::create(['type' => 'aculta_forum_topic', 'name' => 'Tópico'])->save();
    FieldStorageConfig::create([
      'entity_type' => 'node',
      'field_name' => 'field_domain_source',
      'type' => 'entity_reference',
      'settings' => ['target_type' => 'domain'],
    ])->save();
    FieldConfig::create([
      'entity_type' => 'node',
      'bundle' => 'aculta_forum_topic',
      'field_name' => 'field_domain_source',
      'label' => 'Domain',
    ])->save();
    $this->container->get('entity_field.manager')->clearCachedFieldDefinitions();
    $member = User::create(['name' => 'membro', 'status' => 1]);
    $member->save();
    $this->container->get('current_user')->setAccount($member);

    $this->purpose = new DomainPurposeManager(
      $this->container->get('entity_type.manager'),
      $this->container->get('domain.negotiator'),
      $this->container->get('config.factory'),
      new RequestStack(),
    );
    $marker = new ForumReadMarker(
      $this->container->get('entity_type.manager'),
      $this->purpose,
      $this->container->get('current_user'),
      $this->container->get('string_translation'),
      $this->container->get('messenger'),
    );
    $this->form = new ForumMarkAllReadForm(
      $marker,
      $this->container->get('current_user'),
      $this->purpose,
    );
  }

  /**
   * With no accessible topics the form reports it and redirects to the Forum.
   */
  public function testNoTopicsReportsAndRedirects(): void {
    $form_state = new FormState();
    $form = [];
    $this->purpose->runInPurpose('forum', function () use (&$form, $form_state): void {
      $this->form->submitForm($form, $form_state);
    });

    $messages = $this->container->get('messenger')->messagesByType('status');
    self::assertContains('Não há discussões públicas acessíveis para marcar.', array_map('strval', $messages));

    $redirect = $form_state->getRedirect();
    self::assertInstanceOf(Url::class, $redirect);
    self::assertStringContainsString('forum.example.test', (string) $redirect->getUri());
  }

  /**
   * Outside the Forum purpose no topic is considered eligible.
   */
  public function testEligibleCountIsZeroOutsideForumPurpose(): void {
    $marker = new ForumReadMarker(
      $this->container->get('entity_type.manager'),
      $this->purpose,
      $this->container->get('current_user'),
      $this->container->get('string_translation'),
      $this->container->get('messenger'),
    );
    self::assertSame(0, $marker->countEligibleTopics());
    self::assertSame(0, $this->purpose->runInPurpose('main', fn () => $marker->countEligibleTopics()));
  }

}
