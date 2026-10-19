<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta420\Kernel;

use Drupal\aculta420\Hook\InputHooks;
use Drupal\aculta420\Hook\PortalUiHooks;
use Drupal\Core\Template\Attribute;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Preprocess de `input`: ícones de botão do Webform e delegação dos tokens do Portal.
 *
 * Usa a URL base e o ExtensionList reais do Core, por isso é Kernel. Roda no banco
 * `aculta_simpletest` (ver docs/operations/BANCO-DE-TESTE.md).
 */
#[Group('aculta420')]
#[RunTestsInSeparateProcesses]
final class InputHooksKernelTest extends KernelTestBase {

  protected static $modules = ['system'];

  private InputHooks $hooks;

  protected function setUp(): void {
    parent::setUp();
    $this->hooks = new InputHooks(new PortalUiHooks(), $this->container->get('extension.list.theme'));
  }

  /**
   * O botão de adicionar do Webform usa o SVG oficial plus-circle e as classes de botão.
   */
  public function testWebformAddButtonUsesOfficialPlusIconAndButtonClasses(): void {
    $variables = $this->imageButton('/modules/contrib/webform/images/icons/plus.svg');

    $this->hooks->preprocess($variables);

    $this->assertStringEndsWith('/assets/icons/bootstrap-icons/icons/plus-circle.svg', (string) $variables['attributes']['src']);
    $this->assertFileExists($this->themeFile('assets/icons/bootstrap-icons/icons/plus-circle.svg'));
    $this->assertTrue($variables['attributes']->hasClass('btn'));
    $this->assertTrue($variables['attributes']->hasClass('btn-outline-primary'));
    $this->assertTrue($variables['attributes']->hasClass('webform-icon-button'));
  }

  /**
   * O botão de remover usa o SVG dash-circle.
   */
  public function testWebformRemoveButtonUsesOfficialDashIcon(): void {
    $variables = $this->imageButton('/modules/contrib/webform/images/icons/minus.svg');

    $this->hooks->preprocess($variables);

    $this->assertStringEndsWith('/assets/icons/bootstrap-icons/icons/dash-circle.svg', (string) $variables['attributes']['src']);
    $this->assertFileExists($this->themeFile('assets/icons/bootstrap-icons/icons/dash-circle.svg'));
  }

  /**
   * Formato array (antes da conversão do núcleo) também recebe o ícone e as classes, sem perder as classes existentes.
   */
  public function testArrayAttributesGetIconAndKeepExistingClasses(): void {
    $variables = [
      'element' => ['#type' => 'image_button'],
      'attributes' => [
        'src' => '/modules/contrib/webform/images/icons/plus.svg',
        'class' => ['image-button'],
      ],
    ];

    $this->hooks->preprocess($variables);

    $this->assertStringEndsWith('/plus-circle.svg', $variables['attributes']['src']);
    $this->assertContains('image-button', $variables['attributes']['class']);
    $this->assertContains('webform-icon-button', $variables['attributes']['class']);
  }

  /**
   * Imagem que não é ícone do Webform não é alterada.
   */
  public function testOtherImageButtonSourcesAreNotChanged(): void {
    $variables = $this->imageButton('/core/misc/icons/outra.svg');

    $this->hooks->preprocess($variables);

    $this->assertSame('/core/misc/icons/outra.svg', (string) $variables['attributes']['src']);
    $this->assertFalse($variables['attributes']->hasClass('webform-icon-button'));
  }

  /**
   * Botão de envio comum não é alterado, mesmo com o mesmo src de ícone.
   */
  public function testSubmitButtonIsNotChanged(): void {
    $variables = [
      'element' => ['#type' => 'submit'],
      'attributes' => new Attribute(['src' => '/modules/contrib/webform/images/icons/plus.svg']),
    ];

    $this->hooks->preprocess($variables);

    $this->assertSame('/modules/contrib/webform/images/icons/plus.svg', (string) $variables['attributes']['src']);
    $this->assertFalse($variables['attributes']->hasClass('webform-icon-button'));
  }

  /**
   * A delegação a PortalUiHooks continua valendo para `input`, junto com o ícone do Webform.
   */
  public function testPortalTokensStillApplyOnInputAfterDelegation(): void {
    $variables = [
      'element' => ['#type' => 'textfield'],
      'attributes' => new Attribute(['data-portal-ui' => 'email-notice']),
    ];

    $this->hooks->preprocess($variables);

    $this->assertTrue($variables['attributes']->hasClass('aculta-email-notice'));
    $this->assertFalse($variables['attributes']->offsetExists('data-portal-ui'));
  }

  /**
   * Botão de imagem do Webform com token do Portal: ícone e classe de token coexistem.
   */
  public function testIconAndPortalTokenCoexistOnTheSameImageButton(): void {
    $variables = $this->imageButton('/modules/contrib/webform/images/icons/minus.svg');
    $variables['attributes']->setAttribute('data-portal-ui', 'email-notice');

    $this->hooks->preprocess($variables);

    $this->assertStringEndsWith('/dash-circle.svg', (string) $variables['attributes']['src']);
    $this->assertTrue($variables['attributes']->hasClass('webform-icon-button'));
    $this->assertTrue($variables['attributes']->hasClass('aculta-email-notice'));
  }

  /**
   * Variáveis de `input` com `image_button` e atributos em objeto Attribute.
   *
   * @return array<string, mixed>
   */
  private function imageButton(string $src): array {
    return [
      'element' => ['#type' => 'image_button'],
      'attributes' => new Attribute(['type' => 'image', 'src' => $src, 'class' => ['image-button']]),
    ];
  }

  /**
   * Caminho absoluto de um arquivo do tema, a partir da raiz do Drupal.
   */
  private function themeFile(string $relative): string {
    return $this->container->getParameter('app.root') . '/themes/custom/aculta420/' . $relative;
  }

}
