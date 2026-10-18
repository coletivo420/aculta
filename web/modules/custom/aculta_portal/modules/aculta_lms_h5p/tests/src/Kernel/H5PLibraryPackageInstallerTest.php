<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms_h5p\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/** Exercises package validation and managed library storage end to end. */
#[RunTestsInSeparateProcesses]
final class H5PLibraryPackageInstallerTest extends KernelTestBase {

  use UserCreationTrait;

  /** {@inheritdoc} */
  protected static $modules = ['system', 'user', 'file', 'views', 'aculta_image', 'aculta_lms', 'aculta_lms_h5p'];

  /** {@inheritdoc} */
  protected function setUpFilesystem(): void {
    mkdir($this->siteDirectory, 0775, TRUE);
    mkdir($this->siteDirectory . '/files', 0775, TRUE);
    mkdir($this->siteDirectory . '/private', 0775, TRUE);
    $this->setSetting('file_public_path', $this->siteDirectory . '/files');
    $this->setSetting('file_private_path', $this->siteDirectory . '/private');
  }

  /** {@inheritdoc} */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['system', 'user', 'file']);
    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installEntitySchema('h5p_content');
    $this->installSchema('file', ['file_usage']);
    $this->installSchema('aculta_image', ['aculta_image_fingerprint', 'aculta_image_quarantine', 'aculta_image_origin']);
    $this->installSchema('aculta_lms_h5p', [
      'h5p_content_libraries',
      'h5p_libraries',
      'h5p_libraries_hub_cache',
      'h5p_libraries_libraries',
      'h5p_libraries_languages',
      'h5p_points',
      'h5p_content_user_data',
      'h5p_events',
      'h5p_counters',
    ]);
    $this->container->get('config.factory')->getEditable('aculta_lms_h5p.settings')
      ->set('aculta_lms_h5p_default_path', 'h5p-kernel-test')
      ->set('aculta_lms_h5p_hub_is_enabled', 0)
      ->set('aculta_lms_h5p_send_usage_statistics', 0)
      ->save();
    $this->container->get('current_user')->setAccount($this->createUser(['update h5p libraries']));
  }

  /** Verifies a valid package installs private, managed library files. */
  public function testInstallsValidatedLibrariesAndTracksManagedAssets(): void {
    $package = $this->createPackage([
      'H5P.Test-1.0/library.json' => json_encode([
        'title' => 'Teste H5P',
        'majorVersion' => 1,
        'minorVersion' => 0,
        'patchVersion' => 0,
        'machineName' => 'H5P.Test',
        'runnable' => 1,
        'preloadedJs' => [['path' => 'script.js']],
      ], JSON_THROW_ON_ERROR),
      'H5P.Test-1.0/script.js' => 'window.H5PTest = true;',
      'H5P.Test-1.0/assets/icon.svg' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1 1"><path d="M0 0h1v1H0z"/></svg>',
    ]);
    try {
      $installer = $this->container->get('aculta_lms_h5p.library_package_installer');
      self::assertSame(1, $installer->install($package));
      $library = $this->container->get('database')->select('h5p_libraries', 'l')
        ->fields('l', ['machine_name', 'major_version', 'minor_version'])
        ->condition('machine_name', 'H5P.Test')
        ->execute()->fetchAssoc();
      self::assertSame(['machine_name' => 'H5P.Test', 'major_version' => '1', 'minor_version' => '0'], $library);
      $file = $this->container->get('file.repository')->loadByUri('private://h5p-kernel-test/libraries/H5P.Test-1.0/script.js');
      self::assertNotNull($file);
      self::assertFalse($file->isTemporary());
      self::assertSame('window.H5PTest = true;', file_get_contents($this->container->get('file_system')->realpath($file->getFileUri())));
      self::assertNotEmpty($this->container->get('file.usage')->listUsage($file)['aculta_lms_h5p']['h5p_library']['H5P.Test-1.0'] ?? NULL);
      $image = $this->container->get('file.repository')->loadByUri('private://h5p-kernel-test/libraries/H5P.Test-1.0/assets/icon.svg');
      self::assertNotNull($image);
      self::assertTrue(is_file($this->container->get('file_system')->realpath($image->getFileUri())));
      self::assertNotEmpty($this->container->get('file.usage')->listUsage($image)['aculta_image']['h5p_library_asset'] ?? NULL);
    }
    finally {
      unlink($package);
    }
  }

  /** Checks a package without library metadata fails without database writes. */
  public function testRejectsPackageWithoutLibraries(): void {
    $package = $this->createPackage(['readme.txt' => 'Not a library.']);
    try {
      try {
        $this->container->get('aculta_lms_h5p.library_package_installer')->install($package);
        self::fail('Expected an empty package to be rejected.');
      }
      catch (\UnexpectedValueException $exception) {
        self::assertSame('O pacote não contém bibliotecas H5P instaláveis.', $exception->getMessage());
      }
      self::assertSame(0, (int) $this->container->get('database')->select('h5p_libraries', 'l')->countQuery()->execute()->fetchField());
    }
    finally {
      unlink($package);
    }
  }

  /** Confirms invalid late assets are rejected before any library is saved. */
  public function testPreflightsEveryAssetBeforePersistingPackageLibraries(): void {
    $package = $this->createPackage([
      'H5P.Bad-1.0/library.json' => json_encode([
        'title' => 'Pacote inválido',
        'majorVersion' => 1,
        'minorVersion' => 0,
        'patchVersion' => 0,
        'machineName' => 'H5P.Bad',
        'runnable' => 1,
        'preloadedJs' => [['path' => 'script.js']],
      ], JSON_THROW_ON_ERROR),
      'H5P.Bad-1.0/script.js' => 'window.H5PBad = true;',
      'H5P.Bad-1.0/zz-invalid.svg' => '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>',
    ]);
    try {
      try {
        $this->container->get('aculta_lms_h5p.library_package_installer')->install($package);
        self::fail('Expected the unsafe SVG asset to be rejected.');
      }
      catch (\RuntimeException $exception) {
        self::assertSame('An H5P library image was rejected by aculta_image.', $exception->getMessage());
      }
      self::assertSame(0, (int) $this->container->get('database')->select('h5p_libraries', 'l')->countQuery()->execute()->fetchField());
      self::assertNull($this->container->get('file.repository')->loadByUri('private://h5p-kernel-test/libraries/H5P.Bad-1.0/script.js'));
    }
    finally {
      unlink($package);
    }
  }

  /** Verifies progress endpoints expose only the methods required by H5P. */
  public function testProgressRoutesRestrictHttpMethods(): void {
    $provider = $this->container->get('router.route_provider');
    self::assertSame(['POST'], $provider->getRouteByName('aculta_lms_h5p.ajax.set_finished')->getMethods());
    self::assertSame(['GET', 'POST'], $provider->getRouteByName('aculta_lms_h5p.ajax.content_user_data')->getMethods());
    $upgradeRoute = $provider->getRouteByName('aculta_lms_h5p.admin.content.aculta_lms_h5p.libraries.upgrade');
    self::assertSame(['POST'], $upgradeRoute->getMethods());
    self::assertSame('TRUE', $upgradeRoute->getRequirement('_csrf_token'));
    $rebuildRoute = $provider->getRouteByName('aculta_lms_h5p.admin.content.aculta_lms_h5p.rebuild_cache');
    self::assertSame(['POST'], $rebuildRoute->getMethods());
    self::assertSame('TRUE', $rebuildRoute->getRequirement('_csrf_token'));
    $settings = $this->container->get('class_resolver')->getInstanceFromDefinition(\Drupal\aculta_lms_h5p\Form\H5PContentUpgradeForm::class);
    $missing = $settings->buildForm([], new \Drupal\Core\Form\FormState(), 999);
    self::assertSame('Biblioteca não encontrada.', (string) $missing['#markup']);
  }

  /** Verifies package uploads follow the configured private storage location. */
  public function testLibraryUploadFormUsesConfiguredPrivateDirectory(): void {
    $form = $this->container->get('class_resolver')->getInstanceFromDefinition(\Drupal\aculta_lms_h5p\Form\H5PLibraryUploadForm::class);
    $built = $form->buildForm([], new \Drupal\Core\Form\FormState());
    self::assertSame('private://h5p-kernel-test/packages', $built['package']['#upload_location']);
    self::assertSame(['extensions' => 'h5p'], $built['package']['#upload_validators']['FileExtension']);
  }

  /** Verifies the H5P settings form builds its option fieldset and labels. */
  public function testH5PSettingsFormBuildsTranslatedOptions(): void {
    $form = $this->container->get('class_resolver')->getInstanceFromDefinition(\Drupal\aculta_lms_h5p\Form\H5PAdminSettingsForm::class);
    $built = $form->buildForm([], new \Drupal\Core\Form\FormState());
    self::assertSame('fieldset', $built['h5p_display_options']['#type']);
    self::assertSame('Opções de exibição', (string) $built['h5p_display_options']['#title']);
    self::assertSame('Diretório privado dos arquivos H5P', (string) $built['h5p_default_path']['#title']);
  }

  /** Verifies failed progress requests cannot be cached by browser or proxy. */
  public function testProgressResponsesArePrivateAndNotCacheable(): void {
    $controller = \Drupal\aculta_lms_h5p\Controller\H5PAJAX::create($this->container);
    $finished = $controller->setFinished(1, \Symfony\Component\HttpFoundation\Request::create('/', 'POST'));
    self::assertSame(403, $finished->getStatusCode());
    self::assertStringContainsString('private', $finished->headers->get('Cache-Control'));
    self::assertStringContainsString('no-store', $finished->headers->get('Cache-Control'));
    self::assertSame('nosniff', $finished->headers->get('X-Content-Type-Options'));
    $state = $controller->contentUserData(1, 0, 'state', 0, \Symfony\Component\HttpFoundation\Request::create('/', 'GET'));
    self::assertSame(403, $state->getStatusCode());
    self::assertStringContainsString('private', $state->headers->get('Cache-Control'));
    self::assertStringContainsString('no-store', $state->headers->get('Cache-Control'));
  }

  /** Ensures the routed H5P controllers can be constructed with Drupal 11 DI. */
  public function testDatabaseControllersUseContainerInjection(): void {
    self::assertInstanceOf(\Drupal\aculta_lms_h5p\Controller\H5PContentUpgrade::class, \Drupal\aculta_lms_h5p\Controller\H5PContentUpgrade::create($this->container));
    self::assertInstanceOf(\Drupal\aculta_lms_h5p\Controller\H5PLibraryAdmin::class, \Drupal\aculta_lms_h5p\Controller\H5PLibraryAdmin::create($this->container));
  }

  /** Creates a temporary ZIP package with the requested entries. */
  private function createPackage(array $entries): string {
    $path = $this->siteDirectory . '/library-' . bin2hex(random_bytes(6)) . '.h5p';
    $zip = new \ZipArchive();
    self::assertSame(TRUE, $zip->open($path, \ZipArchive::CREATE | \ZipArchive::EXCL));
    foreach ($entries as $name => $contents) {
      self::assertTrue($zip->addFromString($name, $contents));
    }
    self::assertTrue($zip->close());
    return $path;
  }

}
