<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_portal\Unit;

use Drupal\aculta_portal\Account\AccountAddressForm;
use Drupal\aculta_portal\Account\StreetNumberField;
use Drupal\address\Element\Address;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests entity mappings and variants of the site's shared address widget.
 */
#[Group('aculta_portal')]
final class AccountAddressFormTest extends UnitTestCase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $container = new ContainerBuilder();
    $container->set('string_translation', $this->getStringTranslationStub());
    \Drupal::setContainer($container);
  }

  /**
   * Tests Event Location field mapping.
   */
  public function testEventLocationUsesTheSharedAddressField(): void {
    $entity = $this->createMock(EntityInterface::class);
    $entity->method('getEntityTypeId')->willReturn('aculta_event_location');

    $this->assertSame('field_endereco', AccountAddressForm::fieldOf($entity));
  }

  /**
   * Tests the shared CEP widget adapter for external address formats.
   */
  public function testExternalAdapterCanUseSharedCepWidgetWithoutAccountNumberRule(): void {
    $tree = [
      'review' => [
        'address' => [
          '#type' => 'address',
          '#default_value' => [],
        ],
      ],
    ];

    $this->assertTrue(AccountAddressForm::apply($tree, '', FALSE, FALSE));
    $element = $tree['review']['address'];

    $this->assertSame('BR', $element['#default_value']['country_code']);
    $this->assertContains([Address::class, 'processAddress'], $element['#process']);
    $this->assertContains([Address::class, 'processGroup'], $element['#process']);
    $this->assertContains([AccountAddressForm::class, 'finish'], $element['#process']);
    $this->assertNotContains([StreetNumberField::class, 'processStreetNumber'], $element['#process']);
  }

  /**
   * Tests that Conta retains its number and S/N controls.
   */
  public function testAccountAddressVariantRetainsSharedNumberRule(): void {
    $element = ['#type' => 'address', '#default_value' => []];

    $this->assertTrue(AccountAddressForm::apply($element));
    $this->assertContains([StreetNumberField::class, 'processStreetNumber'], $element['#process']);
    $this->assertContains([AccountAddressForm::class, 'validateAddressElement'], $element['#element_validate']);
    $this->assertContains(AccountAddressForm::LIBRARY, $element['#attached']['library']);
    $this->assertSame(['BR'], $element['#available_countries']);
  }

  /**
   * Tests that validateCountryCode normalizes empty input to BR.
   */
  public function testValidateCountryCodeNormalizesEmptyToBrazil(): void {
    $element = ['#parents' => ['address', 'country_code']];
    $formState = $this->createMock(FormStateInterface::class);
    $formState->method('getValue')->with(['address', 'country_code'])->willReturn('');
    $formState->expects($this->once())
      ->method('setValue')
      ->with(['address', 'country_code'], 'BR');

    $completeForm = [];
    AccountAddressForm::validateCountryCode($element, $formState, $completeForm);
  }

  /**
   * Tests that validateCountryCode rejects non-BR values.
   */
  public function testValidateCountryCodeRejectsNonBrazil(): void {
    $element = ['#parents' => ['address', 'country_code']];
    $formState = $this->createMock(FormStateInterface::class);
    $formState->method('getValue')->with(['address', 'country_code'])->willReturn('US');
    $formState->expects($this->once())
      ->method('setError')
      ->with($element, $this->anything());

    $completeForm = [];
    AccountAddressForm::validateCountryCode($element, $formState, $completeForm);
  }

  /**
   * Tests validateAddressElement normalizes missing country and rejects other.
   */
  public function testValidateAddressElementNormalizesAndRejects(): void {
    $element = ['#parents' => ['field_address', 0, 'address']];

    // Empty country -> normalizes to BR.
    $formStateNormalize = $this->createMock(FormStateInterface::class);
    $formStateNormalize->method('getValue')
      ->with(['field_address', 0, 'address'])
      ->willReturn(['country_code' => '']);
    $formStateNormalize->expects($this->once())
      ->method('setValue')
      ->with(['field_address', 0, 'address', 'country_code'], 'BR');

    $completeForm = [];
    AccountAddressForm::validateAddressElement($element, $formStateNormalize, $completeForm);

    // Foreign country -> rejects.
    $formStateReject = $this->createMock(FormStateInterface::class);
    $formStateReject->method('getValue')
      ->with(['field_address', 0, 'address'])
      ->willReturn(['country_code' => 'FR']);
    $formStateReject->expects($this->once())
      ->method('setError')
      ->with($element, $this->anything());

    AccountAddressForm::validateAddressElement($element, $formStateReject, $completeForm);
  }

  /**
   * Tests finish callback sets hidden country input and server validator.
   */
  public function testFinishSetsHiddenCountryAndValidator(): void {
    $element = [
      'country_code' => [
        '#type' => 'address_country',
        '#options' => ['BR' => 'Brazil', 'US' => 'United States'],
        '#ajax' => ['callback' => 'foo'],
        '#empty_value' => '',
      ],
      'address_line1' => ['#title' => 'Address line 1'],
    ];

    $finished = AccountAddressForm::finish($element);

    $this->assertSame('hidden', $finished['country_code']['#type']);
    $this->assertSame('BR', $finished['country_code']['#value']);
    $this->assertSame('BR', $finished['country_code']['#default_value']);
    $this->assertArrayNotHasKey('#options', $finished['country_code']);
    $this->assertArrayNotHasKey('#ajax', $finished['country_code']);
    $this->assertContains([AccountAddressForm::class, 'validateCountryCode'], $finished['country_code']['#element_validate']);
  }

  /**
   * Elemento avulso address_country é convertido para hidden fixo em BR.
   */
  public function testFinishCountryElementSetsHiddenAndValidator(): void {
    $element = [
      '#type' => 'address_country',
      '#options' => ['BR' => 'Brazil', 'AR' => 'Argentina'],
      '#ajax' => ['callback' => 'foo'],
      '#empty_value' => '',
    ];
    $formState = new FormState();
    $completeForm = [];

    $finished = AccountAddressForm::finishCountryElement($element, $formState, $completeForm);

    $this->assertSame('hidden', $finished['#type']);
    $this->assertSame('BR', $finished['#value']);
    $this->assertSame('BR', $finished['#default_value']);
    $this->assertArrayNotHasKey('#options', $finished);
    $this->assertArrayNotHasKey('#ajax', $finished);
    $this->assertContains([AccountAddressForm::class, 'validateCountryCode'], $finished['#element_validate']);
  }

  /**
   * Tests finish callback sets postal_code as the first visible element.
   */
  public function testFinishOrdersPostalCodeFirst(): void {
    $element = [
      'country_code' => ['#type' => 'hidden'],
      'address_line1' => ['#title' => 'Rua'],
      'street_number' => ['#title' => 'Número'],
      'street_number_sn' => ['#title' => 'S/N'],
      'address_line2' => ['#title' => 'Complemento'],
      'dependent_locality' => ['#title' => 'Bairro'],
      'postal_code' => ['#title' => 'CEP'],
      'container6' => ['#type' => 'container'],
    ];

    $finished = AccountAddressForm::finish($element);

    $this->assertLessThan($finished['address_line1']['#weight'], $finished['postal_code']['#weight']);
    $this->assertLessThan($finished['street_number']['#weight'], $finished['address_line1']['#weight']);
    $this->assertLessThan($finished['address_line2']['#weight'], $finished['street_number']['#weight']);
    $this->assertLessThan($finished['dependent_locality']['#weight'], $finished['address_line2']['#weight']);
    $this->assertLessThan($finished['container6']['#weight'], $finished['dependent_locality']['#weight']);
  }

  /**
   * Tests finish callback hides personal name and redundant complement fields.
   */
  public function testFinishSuppressesPersonalNameAndRedundantComplementLine(): void {
    $element = [
      'given_name' => ['#title' => 'Nome'],
      'family_name' => ['#title' => 'Sobrenome'],
      'additional_name' => ['#title' => 'Nome do meio'],
      'organization' => ['#title' => 'Organização'],
      'address_line1' => ['#title' => 'Endereço'],
      'address_line2' => ['#title' => 'Complemento'],
      'address_line3' => ['#title' => 'Logradouro, linha 3'],
    ];

    $finished = AccountAddressForm::finish($element);

    $this->assertFalse($finished['given_name']['#access']);
    $this->assertFalse($finished['family_name']['#access']);
    $this->assertFalse($finished['additional_name']['#access']);
    $this->assertFalse($finished['organization']['#access']);
    $this->assertFalse($finished['address_line3']['#access']);
    $this->assertTrue($finished['address_line2']['#access'] ?? TRUE);
  }

}
