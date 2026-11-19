<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_portal\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Proves Core HTMX behavior with an authenticated Drupal form in a dialog.
 */
#[Group('aculta_htmx_f0')]
#[RunTestsInSeparateProcesses]
final class HtmxF0DialogTest extends WebDriverTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'aculta_htmx_f0_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Verifies modal load, valid form POST, and rejection of an invalid token.
   */
  public function testDialogFormAndCsrf(): void {
    $account = $this->drupalCreateUser(['access content']);
    $this->drupalLogin($account);

    $this->openFormInDialog();
    $this->assertSession()->elementExists('css', 'dialog#htmx-f0-dialog[open]');
    $tokenField = $this->getSession()->getPage()->find('css', 'dialog#htmx-f0-dialog input[name="form_token"]');
    $this->assertNotNull($tokenField, 'Authenticated HTMX dialog form contains the Form API CSRF token field.');
    $token = $tokenField->getValue();
    $this->assertNotEmpty($token);

    $this->getSession()->getPage()->fillField('message', 'prova válida');
    $this->getSession()->getPage()->pressButton('Enviar formulário');
    $this->assertSession()->waitForText('Formulário enviado via HTMX.');
    $this->assertSession()->elementExists('css', 'dialog#htmx-f0-dialog[open] #htmx-f0-result[role="status"]');

    $this->openFormInDialog();
    $this->getSession()->executeScript("document.querySelector('input[name=\"form_token\"]').value = 'invalid-csrf-token';");
    $this->getSession()->getPage()->fillField('message', 'não deve ser processada');
    $this->getSession()->getPage()->pressButton('Enviar formulário');
    $this->assertSession()->waitForText('The form has become outdated.');
    $this->assertSession()->elementNotExists('css', '#htmx-f0-result');
  }

  /**
   * Opens the dialog and waits for the HTMX form fragment.
   */
  private function openFormInDialog(): void {
    $this->drupalGet('/htmx-f0');
    $this->getSession()->getPage()->clickLink('Abrir formulário HTMX');
    $loaded = $this->getSession()->wait(10000, "document.querySelector('dialog#htmx-f0-dialog[open] form') !== null");
    if (!$loaded) {
      $debug = $this->getSession()->evaluateScript("JSON.stringify({htmx: window.htmx?.version, resources: performance.getEntriesByType('resource').filter((entry) => entry.name.includes('/htmx-f0')).map((entry) => ({name: entry.name, status: entry.responseStatus}))})");
      $this->fail('HTMX form fragment did not appear in the open dialog. Browser evidence: ' . $debug);
    }
  }

}
