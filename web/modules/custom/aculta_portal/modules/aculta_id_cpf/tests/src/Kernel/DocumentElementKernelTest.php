<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_id_cpf\Kernel;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Exercises plugin discovery and real Form API service callback resolution.
 */
#[Group('aculta_id_cpf')]
final class DocumentElementKernelTest extends KernelTestBase {

  // Kernel module lists isolate the element layer; this does not homologate
  // the full aculta_portal dependency graph or the Homelab account forms.
  protected static $modules = ['system', 'aculta_id_cpf'];

  public function testFormElementsAreDiscovered(): void {
    $manager = $this->container->get('plugin.manager.element_info');
    $this->assertSame('cpf', $manager->getInfo('aculta_cpf')['#aculta_document_type']);
    $this->assertSame('cnpj', $manager->getInfo('aculta_cnpj')['#aculta_document_type']);
  }

  public function testNestedInputIsCanonicalizedByFormApi(): void {
    $state = (new FormState())->setValues(['party' => ['cpf' => '012.345.678-90', 'cnpj' => '12.abc.345/01de-35']]);
    $this->container->get('form_builder')->submitForm(DocumentElementTestForm::class, $state);
    $this->assertSame([], $state->getErrors());
    $this->assertSame('01234567890', $state->getValue(['party', 'cpf']));
    $this->assertSame('12ABC34501DE35', $state->getValue(['party', 'cnpj']));
  }

  public function testControlCharactersAreNotRemovedBeforeValidation(): void {
    $state = (new FormState())->setValues(['party' => ['cpf' => "01234567890\n", 'cnpj' => '']]);
    $this->container->get('form_builder')->submitForm(DocumentElementTestForm::class, $state);
    $this->assertArrayHasKey('party][cpf', $state->getErrors());
  }

  public function testEmptyOptionalDocumentsAreAllowed(): void {
    $state = (new FormState())->setValues(['party' => ['cpf' => '', 'cnpj' => '']]);
    $this->container->get('form_builder')->submitForm(DocumentElementTestForm::class, $state);
    $this->assertSame([], $state->getErrors());
  }

  public function testMalformedArrayInputIsRejectedEvenWhenOptional(): void {
    $state = (new FormState())->setValues(['party' => ['cpf' => ['01234567890'], 'cnpj' => '']]);
    $this->container->get('form_builder')->submitForm(DocumentElementTestForm::class, $state);
    $this->assertArrayHasKey('party][cpf', $state->getErrors());
  }

}

/**
 * Test-only form without a public route or persistence callback.
 */
final class DocumentElementTestForm extends FormBase {

  public function getFormId() {
    return 'aculta_document_element_test';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['party'] = ['#type' => 'container', '#tree' => TRUE];
    $form['party']['cpf'] = ['#type' => 'aculta_cpf', '#title' => 'CPF'];
    $form['party']['cnpj'] = ['#type' => 'aculta_cnpj', '#title' => 'CNPJ'];
    $form['submit'] = ['#type' => 'submit', '#value' => 'Test'];
    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {}

}
