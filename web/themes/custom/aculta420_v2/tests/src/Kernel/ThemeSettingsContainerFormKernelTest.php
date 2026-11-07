<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta420_v2\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\KernelTests\KernelTestBase;
use Drupal\aculta420_v2\Layout\PurposeContainerLayout;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Valida a renderização e o comportamento do formulário de opções do tema V2.
 */
#[Group('aculta420_v2')]
#[RunTestsInSeparateProcesses]
final class ThemeSettingsContainerFormKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->container->get('theme_installer')->install(['aculta420_v2']);
    $this->container->get('config.factory')->getEditable('system.theme')
      ->set('default', 'aculta420_v2')
      ->save();

    // Define purposes ativos no State API para popular as subabas.
    $this->container->get('state')->set('aculta_portal.active_purposes', [
      'main',
      'account',
      'support',
      'magazine',
      'wiki',
      'shop',
      'courses',
      'links',
      'forum',
      'eventos',
    ]);
  }

  /**
   * Garante que a subaba de layout de container é construída com os campos esperados.
   */
  public function testContainerFormAlterStructure(): void {
    require_once dirname(__DIR__, 3) . '/theme-settings.php';

    $form = [];
    $formState = new FormState();
    aculta420_v2_form_system_theme_settings_alter($form, $formState);

    self::assertArrayHasKey('aculta_container_purpose', $form);
    self::assertSame('details', $form['aculta_container_purpose']['#type']);
    self::assertSame('Layout de container', (string) $form['aculta_container_purpose']['#title']);

    $grid = $form['aculta_container_purpose']['aculta_purpose_container'];
    self::assertArrayHasKey('main', $grid);
    self::assertArrayNotHasKey('account', $grid);
    self::assertArrayHasKey('shop', $grid);
    self::assertArrayHasKey('magazine', $grid);
    self::assertArrayHasKey('wiki', $grid);

    // Valida campos do purpose main
    $mainCard = $grid['main'];
    self::assertSame('select', $mainCard['mode']['#type']);
    self::assertSame('default', $mainCard['mode']['#default_value']);
    self::assertSame('select', $mainCard['style']['#type']);
    self::assertSame('boxed', $mainCard['style']['#default_value']);
    self::assertSame('number', $mainCard['container_width']['#type']);
    self::assertSame(1200, $mainCard['container_width']['#default_value']);
    self::assertSame('number', $mainCard['narrow_width']['#type']);
    self::assertSame(750, $mainCard['narrow_width']['#default_value']);
  }

  /**
   * Garante que valores salvos na configuração são refletidos na reabertura do formulário.
   */
  public function testSavedValuesAppearInForm(): void {
    require_once dirname(__DIR__, 3) . '/theme-settings.php';

    // Salva configuração personalizada de container
    $config = $this->container->get('config.factory')->getEditable('aculta420_v2.settings');
    $config->set('aculta_purpose_container', [
      'main' => [
        'mode' => 'full',
        'container_width' => 1400,
        'narrow_width' => 800,
      ],
      'magazine' => [
        'mode' => 'narrow',
        'container_width' => 1200,
        'narrow_width' => 680,
      ],
    ])->save();

    // Reabre o formulário
    $form = [];
    $formState = new FormState();
    aculta420_v2_form_system_theme_settings_alter($form, $formState);

    $grid = $form['aculta_container_purpose']['aculta_purpose_container'];
    self::assertSame('full', $grid['main']['mode']['#default_value']);
    self::assertSame(1400, $grid['main']['container_width']['#default_value']);
    self::assertSame(800, $grid['main']['narrow_width']['#default_value']);


    // Shop não foi personalizado, deve manter o fallback
    self::assertSame('default', $grid['shop']['mode']['#default_value']);
    self::assertSame('narrow', $grid['magazine']['mode']['#default_value']);
    self::assertSame(680, $grid['magazine']['narrow_width']['#default_value']);
    self::assertSame(1200, $grid['shop']['container_width']['#default_value']);
    self::assertSame(750, $grid['shop']['narrow_width']['#default_value']);
  }

}
