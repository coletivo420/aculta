<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_studio\Unit;

use Drupal\aculta_portal\Account\AccountDocumentInterface;
use Drupal\aculta_portal\Appearance\AppearanceForm;
use Drupal\aculta_portal\Controller\PortalController;
use Drupal\aculta_portal\Support\Controller\SupportController;
use Drupal\aculta_studio\Controller\StudioAccountSectionController;
use Drupal\Core\DependencyInjection\ClassResolverInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Verifies Portal Form API composition without nesting the account shell.
 */
#[CoversClass(StudioAccountSectionController::class)]
#[Group('aculta_studio')]
final class StudioAccountSectionControllerTest extends UnitTestCase {

  /**
   * TranslatableMarkup do controlador precisa de um container com tradução.
   */
  protected function setUp(): void {
    parent::setUp();
    $container = new \Symfony\Component\DependencyInjection\ContainerBuilder();
    $container->set('string_translation', $this->getStringTranslationStub());
    \Drupal::setContainer($container);
  }

  /**
   * Authorization is checked before resolving an owner controller.
   */
  public function testVisitorCannotLoadAccountSections(): void {
    $resolver = $this->createMock(ClassResolverInterface::class);
    $resolver->expects(self::never())->method('getInstanceFromDefinition');
    $this->expectException(AccessDeniedHttpException::class);
    (new StudioAccountSectionController($resolver, $this->account(FALSE, TRUE)))->address();
  }

  /**
   * Portal permission is checked even for authenticated members.
   */
  public function testRestrictedMemberDenied(): void {
    $resolver = $this->createMock(ClassResolverInterface::class);
    $resolver->expects(self::never())->method('getInstanceFromDefinition');
    $this->expectException(AccessDeniedHttpException::class);
    (new StudioAccountSectionController($resolver, $this->account(TRUE, FALSE)))->security();
  }

  /**
   * Each permitted operation delegates to the original owner exactly once.
   */
  public function testDelegatesAndReplacesOnlyLegacyNavigation(): void {
    $methods = [
      'data' => 'myData',
      'address' => 'address',
      'documents' => 'otherData',
      'security' => 'security',
      'password' => 'securityPassword',
      'connections' => 'connections',
      'orders' => 'orders',
    ];

    foreach ($methods as $studioMethod => $ownerMethod) {
      $owner = new class {
        public array $called = [];

        public function myData(): array {
          $this->called[] = 'myData';
          return $this->build('myData');
        }

        public function address(): array {
          $this->called[] = 'address';
          return $this->build('address');
        }

        public function otherData(): array {
          $this->called[] = 'otherData';
          return $this->build('documents');
        }

        public function security(): array {
          $this->called[] = 'security';
          return $this->build('security');
        }

        public function securityPassword(): array {
          $this->called[] = 'securityPassword';
          return $this->build('password');
        }

        public function connections(): array {
          $this->called[] = 'connections';
          return $this->build('connections');
        }

        public function orders(): array {
          $this->called[] = 'orders';
          return $this->build('orders');
        }

        private function build(string $name): array {
          return [
            'content' => [
              'tabs' => ['#type' => 'container', '#legacy' => TRUE],
              'section' => ['#owner' => $name, 'form' => ['#type' => 'form']],
            ],
            '#cache' => ['max-age' => 0],
          ];
        }
      };

      $resolver = $this->createMock(ClassResolverInterface::class);
      $resolver->expects(self::once())->method('getInstanceFromDefinition')
        ->with(PortalController::class)->willReturn($owner);

      $document = $this->createMock(AccountDocumentInterface::class);
      $document->method('isAvailable')->willReturn(TRUE);
      $page = (new StudioAccountSectionController($resolver, $this->account(TRUE, TRUE), $document))->{$studioMethod}();

      self::assertSame([$ownerMethod], $owner->called);
      self::assertSame('studio-account-section', $page['#attributes']['data-portal-ui']);
      self::assertSame($studioMethod === 'password' ? 'password' : ($studioMethod === 'documents' ? 'documents' : $ownerMethod),
        $page['content']['content']['section']['#owner']);
      self::assertSame(['#type' => 'form'],
        $page['content']['content']['section']['form']);
      self::assertArrayNotHasKey('tabs', $page['content']['content']);
      self::assertSame('item_list', $page['navigation']['#theme']);
      self::assertCount(9, $page['navigation']['#items']);
      $routes = array_map(static fn (array $link) => $link['#url']->getRouteName(),
        $page['navigation']['#items']);
      self::assertSame([
        'aculta_studio.account_data',
        'aculta_studio.account_address',
        'aculta_studio.account_documents',
        'aculta_studio.account_security',
        'aculta_studio.account_password',
        'aculta_studio.account_connections',
        'aculta_studio.account_orders',
        'aculta_studio.account_support',
        'aculta_studio.account_preferences',
      ], $routes);
      self::assertSame(0, $page['#cache']['max-age']);
      self::assertContains('user', $page['#cache']['contexts']);
    }
  }

  /**
   * If CPF/CNPJ is unavailable, all other account sections remain usable.
   */
  public function testOptionalDocumentsAreAbsentFromContextualMenu(): void {
    $owner = new class {
      public function address(): array {
        return ['content' => ['form' => ['#type' => 'form']]];
      }
    };
    $resolver = $this->createMock(ClassResolverInterface::class);
    $resolver->method('getInstanceFromDefinition')->willReturn($owner);
    $page = (new StudioAccountSectionController($resolver, $this->account(TRUE, TRUE)))->address();
    $routes = array_map(static fn (array $link): string => $link['#url']->getRouteName(),
      $page['navigation']['#items']);
    self::assertCount(8, $routes);
    self::assertNotContains('aculta_studio.account_documents', $routes);
  }

  /**
   * Direct access fails with 404 if the optional document owner is absent.
   */
  public function testDocumentsWithoutOwnerAreNotFound(): void {
    $resolver = $this->createMock(ClassResolverInterface::class);
    $resolver->expects(self::never())->method('getInstanceFromDefinition');
    $this->expectException(NotFoundHttpException::class);
    (new StudioAccountSectionController($resolver, $this->account(TRUE, TRUE)))->documents();
  }

  /**
   * A denied member cannot inspect optional document availability.
   */
  public function testDocumentsRequirePortalPermissionBeforeOwnerResolution(): void {
    $resolver = $this->createMock(ClassResolverInterface::class);
    $resolver->expects(self::never())->method('getInstanceFromDefinition');
    $this->expectException(AccessDeniedHttpException::class);
    (new StudioAccountSectionController($resolver, $this->account(TRUE, FALSE)))->documents();
  }

  /**
   * An installed but temporarily unavailable owner retains its own message.
   */
  public function testInstalledUnavailableDocumentOwnerCanRenderExplanation(): void {
    $owner = new class {
      public function otherData(): array {
        return ['content' => ['form' => ['unavailable' => ['#plain_text' => 'Indisponível']]]];
      }
    };
    $resolver = $this->createMock(ClassResolverInterface::class);
    $resolver->expects(self::once())->method('getInstanceFromDefinition')
      ->with(PortalController::class)->willReturn($owner);
    $document = $this->createMock(AccountDocumentInterface::class);
    $document->method('isAvailable')->willReturn(FALSE);
    $page = (new StudioAccountSectionController($resolver, $this->account(TRUE, TRUE), $document))->documents();
    self::assertSame('Indisponível', $page['content']['content']['form']['unavailable']['#plain_text']);
    $routes = array_map(static fn (array $item): string => $item['#url']->getRouteName(), $page['navigation']['#items']);
    self::assertNotContains('aculta_studio.account_documents', $routes);
  }

  /**
   * Support delegates to the original owner and preserves subscription actions.
   */
  public function testSupportDelegatesToOriginalOwner(): void {
    $action = ['#type' => 'link', '#title' => 'Pausar assinatura'];
    $owner = new class($action) {
      public int $calls = 0;

      public function __construct(private readonly array $action) {}

      public function mySupport(): array {
        $this->calls++;
        return [
          'table' => ['#type' => 'table', '#rows' => [['data' => $this->action]]],
          '#cache' => ['contexts' => ['user'], 'max-age' => 0],
        ];
      }
    };
    $resolver = $this->createMock(ClassResolverInterface::class);
    $resolver->expects(self::once())->method('getInstanceFromDefinition')
      ->with(SupportController::class)->willReturn($owner);
    $page = (new StudioAccountSectionController($resolver, $this->account(TRUE, TRUE)))->support();
    self::assertSame(1, $owner->calls);
    self::assertSame($action, $page['content']['table']['#rows'][0]['data']);
    self::assertSame(['user'], $page['content']['#cache']['contexts']);
    self::assertSame('aculta_studio.account_support', $page['navigation']['#items'][7]['#url']->getRouteName());
    self::assertSame('page', $page['navigation']['#items'][7]['#attributes']['aria-current']);
  }

  /**
   * Auth checks precede the support owner lookup.
   */
  public function testSupportAccessDeniedBeforeOwnerResolution(): void {
    $resolver = $this->createMock(ClassResolverInterface::class);
    $resolver->expects(self::never())->method('getInstanceFromDefinition');
    $this->expectException(AccessDeniedHttpException::class);
    (new StudioAccountSectionController($resolver, $this->account(TRUE, FALSE)))->support();
  }

  /**
   * Core builds the original appearance form, without stripping owner tabs.
   */
  public function testPreferencesPreserveFormApiAndOwnerSettingsTabs(): void {
    $resolver = $this->createMock(ClassResolverInterface::class);
    $resolver->expects(self::never())->method('getInstanceFromDefinition');
    $forms = $this->createMock(FormBuilderInterface::class);
    $original = [
      '#type' => 'form',
      '#form_id' => 'aculta_portal_appearance_form',
      'tabs' => ['#type' => 'container', 'owner_only' => TRUE],
      'color_mode' => ['#type' => 'radios', '#required' => TRUE],
    ];
    $forms->expects(self::once())->method('getForm')->with(AppearanceForm::class)->willReturn($original);
    $page = (new StudioAccountSectionController($resolver, $this->account(TRUE, TRUE), NULL, $forms))->preferences();
    self::assertSame($original, $page['content']);
    self::assertSame('aculta_studio.account_preferences', $page['navigation']['#items'][8]['#url']->getRouteName());
    self::assertSame('page', $page['navigation']['#items'][8]['#attributes']['aria-current']);
    self::assertSame(0, $page['#cache']['max-age']);
  }

  /**
   * Anonymous visitors are rejected before the preference form is built.
   */
  public function testPreferencesDenyAnonymousBeforeBuildingForm(): void {
    $forms = $this->createMock(FormBuilderInterface::class);
    $forms->expects(self::never())->method('getForm');
    $resolver = $this->createMock(ClassResolverInterface::class);
    $resolver->expects(self::never())->method('getInstanceFromDefinition');
    $this->expectException(AccessDeniedHttpException::class);
    (new StudioAccountSectionController($resolver, $this->account(FALSE, TRUE), NULL, $forms))->preferences();
  }

  /**
   * A misconfigured Studio cannot bypass the original Form API.
   */
  public function testPreferencesFailClosedWithoutFormBuilder(): void {
    $resolver = $this->createMock(ClassResolverInterface::class);
    $resolver->expects(self::never())->method('getInstanceFromDefinition');
    $this->expectException(\LogicException::class);
    (new StudioAccountSectionController($resolver, $this->account(TRUE, TRUE)))->preferences();
  }

  /**
   * Creates a user account with controlled authentication/permission.
   */
  private function account(bool $authenticated, bool $permitted): AccountInterface {
    $account = $this->createMock(AccountInterface::class);
    $account->method('isAuthenticated')->willReturn($authenticated);
    $account->method('hasPermission')->with('access aculta portal')->willReturn($permitted);
    return $account;
  }

}
