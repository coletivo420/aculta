<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_messenger\Unit;

use Drupal\aculta_messenger\Aviso\AvisoPreferencias;
use Drupal\aculta_messenger\Form\CommunicationSettingsForm;
use Drupal\aculta_portal\Appearance\SettingsTabs;
use Drupal\Core\GeneratedUrl;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Routing\UrlGeneratorInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\user\UserDataInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests member controls for privacy-minimal private-message email delivery.
 */
#[CoversClass(CommunicationSettingsForm::class)]
#[Group('aculta_messenger')]
final class CommunicationSettingsFormTest extends UnitTestCase {

  /**
   * Container that was active before the test installed its Drupal services.
   */
  private ?ContainerInterface $previousContainer = NULL;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->previousContainer = \Drupal::hasContainer() ? \Drupal::getContainer() : NULL;
    $container = new ContainerBuilder();
    $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
    $urlGenerator->method('generateFromRoute')->willReturnCallback(
      static fn(string $route, array $parameters = [], array $options = [], bool $collectMetadata = FALSE): string|GeneratedUrl =>
        $collectMetadata
          ? (new GeneratedUrl())->setGeneratedUrl('/configuracoes/mensagens')
          : '/configuracoes/mensagens',
    );
    $container->set('url_generator', $urlGenerator);
    $container->set('messenger', $this->createMock(MessengerInterface::class));
    $container->set('string_translation', $this->getStringTranslationStub());
    \Drupal::setContainer($container);
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    if ($this->previousContainer instanceof ContainerInterface) {
      \Drupal::setContainer($this->previousContainer);
    }
    else {
      \Drupal::unsetContainer();
    }
    parent::tearDown();
  }

  /**
   * Offers direct, weekly digest, and site-only choices without message text.
   */
  public function testBuildOffersSupportedModesAndExplainsPrivacy(): void {
    $form = $this->form(new AvisoPreferencias($this->createMock(UserDataInterface::class)));
    $build = $form->buildForm([], new FormState());

    self::assertSame(
      [AvisoPreferencias::EMAIL_OFF, AvisoPreferencias::EMAIL_IMMEDIATE, AvisoPreferencias::EMAIL_DIGEST],
      array_keys($build['receive_notification']['#options']),
    );
    self::assertStringContainsString('nunca é incluído', $build['intro']['#plain_text']->getUntranslatedString());
    self::assertSame(0, $build['#cache']['max-age']);
  }

  /**
   * An administrator can remove member choices from the form.
   */
  public function testBuildRespectsAdministrativeModeRestrictions(): void {
    $preferences = new AvisoPreferencias(
      $this->createMock(UserDataInterface::class),
      $this->getConfigFactoryStub([
        'aculta_messenger.settings' => [
          'email_modes' => [AvisoPreferencias::EMAIL_PRIVATE_MESSAGES => ['digest' => TRUE, 'immediate' => FALSE]],
        ],
      ]),
    );
    $build = $this->form($preferences)->buildForm([], new FormState());

    self::assertSame(
      [AvisoPreferencias::EMAIL_OFF, AvisoPreferencias::EMAIL_DIGEST],
      array_keys($build['receive_notification']['#options']),
    );
  }

  /**
   * Saves only the selected Messenger-owned preference.
   */
  public function testSubmitSavesSelectedMode(): void {
    $userData = $this->createMock(UserDataInterface::class);
    $userData->expects(self::once())->method('set')->with(
      AvisoPreferencias::MODULO,
      27,
      'avisos_email_mode_' . AvisoPreferencias::EMAIL_PRIVATE_MESSAGES,
      AvisoPreferencias::EMAIL_DIGEST,
    );
    $form = $this->form(new AvisoPreferencias($userData));
    $form->setStringTranslation($this->getStringTranslationStub());
    $formState = new FormState();
    $formState->setValue('receive_notification', AvisoPreferencias::EMAIL_DIGEST);

    $formArray = [];
    $form->submitForm($formArray, $formState);

    self::assertSame('aculta_messenger.communication_settings', $formState->getRedirect()->getRouteName());
  }

  /**
   * Creates the form with a test account and Portal settings navigation.
   */
  private function form(AvisoPreferencias $preferences): CommunicationSettingsForm {
    $account = $this->createMock(AccountProxyInterface::class);
    $account->method('id')->willReturn(27);
    $tabs = new SettingsTabs($this->createMock(ModuleHandlerInterface::class));
    $form = new CommunicationSettingsForm($account, $preferences, $tabs);
    $form->setStringTranslation($this->getStringTranslationStub());
    return $form;
  }

}
