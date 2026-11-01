<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_maps\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\Core\Routing\RouteMatch;
use Drupal\Core\Session\UserSession;
use Drupal\aculta_maps\Form\MapSettingsForm;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Yaml\Yaml;

/**
 * Tests access and secrets isolation for map configuration.
 */
#[Group('aculta_maps')]
#[RunTestsInSeparateProcesses]
final class MapSettingsFormKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user', 'key', 'aculta_maps'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['aculta_maps']);
  }

  /**
   * Regular users cannot access the settings route.
   */
  public function testRegularUserCannotAccessSettings(): void {
    $route = $this->container->get('router.route_provider')->getRouteByName('aculta_maps.settings');
    $request = Request::create('/painel-administrativo/mapas');
    $request->attributes->set('_route', 'aculta_maps.settings');
    $request->attributes->set('_route_object', $route);
    $access = $this->container->get('access_manager')->check(
      RouteMatch::createFromRequest($request),
      new UserSession(['uid' => 2, 'roles' => ['authenticated']]),
      $request,
      TRUE,
    );

    $this->assertFalse($access->isAllowed());
  }

  /**
   * Saving settings persists only options and never a provider key.
   */
  public function testSaveDoesNotExportCredential(): void {
    $formObject = MapSettingsForm::create($this->container);
    $form = $formObject->buildForm([], new FormState());
    $state = new FormState();
    $state->setValues([
      'engine' => 'google',
      'center_lat' => -15.7,
      'center_lng' => -47.9,
      'zoom_initial' => 5,
      'zoom_location' => 14,
      'route_url_template' => 'https://www.google.com/maps/dir/?api=1&destination={destino}',
    ]);
    $formObject->submitForm($form, $state);

    $raw = $this->config('aculta_maps.settings')->getRawData();
    $export = Yaml::dump($raw);
    $this->assertSame('google', $raw['engine']);
    $this->assertArrayNotHasKey('google_maps_api_key', $raw);
    $this->assertStringNotContainsString('google_maps_api_key', $export);
    $this->assertSame('Ausente', (string) $form['credential_status']['#markup']);
  }

  /**
   * The route link template cannot use a script or a non-Google host.
   */
  public function testRouteTemplateRejectsJavascriptUrl(): void {
    $formObject = MapSettingsForm::create($this->container);
    $state = new FormState();
    $state->setValue('route_url_template', 'javascript:alert(1){destino}');
    $form = [];
    $formObject->validateForm($form, $state);

    $this->assertArrayHasKey('route_url_template', $state->getErrors());
  }

}
