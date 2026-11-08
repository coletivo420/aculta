<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_events\Kernel;

use Drupal\aculta_events\Form\EventLocationModalForm;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Garante que o botão de busca do modal de local usa HTMX com o fragmento certo.
 */
#[Group('aculta_events')]
#[RunTestsInSeparateProcesses]
final class EventLocationModalHtmxKernelTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system', 'user', 'field', 'key', 'options', 'link', 'text', 'filter',
    'taxonomy', 'datetime', 'datetime_range', 'address', 'geofield',
    'aculta_calendar', 'aculta_events',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('user_role');
    $this->installEntitySchema('taxonomy_term');
    $this->installConfig(['system', 'user', 'taxonomy']);
    $this->installEntitySchema('aculta_event');
    $this->installEntitySchema('aculta_event_location');

    $role = Role::create(['id' => 'event_admin_role', 'label' => 'Event Admin']);
    $role->grantPermission('administer aculta events');
    $role->save();
    $editor = User::create(['name' => 'editor', 'mail' => 'editor@example.com', 'status' => 1]);
    $editor->addRole('event_admin_role');
    $editor->save();
    $this->container->get('current_user')->setAccount($editor);
  }

  /**
   * O botão de busca do modal declara hx-post, hx-select, hx-target e hx-swap.
   */
  public function testLocationModalSearchUsesHtmx(): void {
    $form = $this->container->get('form_builder')->getForm(EventLocationModalForm::class);
    $button = $form['search_bar']['search_submit'];
    $wrapper = 'aculta-location-modal-form-wrapper';

    self::assertSame('submit', $button['#type']);
    self::assertNotEmpty((string) $button['#attributes']['data-hx-post']);
    self::assertSame('#' . $wrapper, (string) $button['#attributes']['data-hx-select']);
    self::assertSame('#' . $wrapper, (string) $button['#attributes']['data-hx-target']);
    self::assertStringStartsWith('outerHTML', (string) $button['#attributes']['data-hx-swap']);
    self::assertContains('core/drupal.htmx', $button['#attached']['library']);
    self::assertStringContainsString($wrapper, (string) $form['#prefix']);
  }

}
