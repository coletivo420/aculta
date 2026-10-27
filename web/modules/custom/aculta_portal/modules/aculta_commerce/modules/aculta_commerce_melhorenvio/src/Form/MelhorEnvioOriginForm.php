<?php

declare(strict_types=1);

namespace Drupal\aculta_commerce_melhorenvio\Form;

use Drupal\aculta_portal_cep\Lookup\CepLookupInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Origem da expedição, usada pela cotação do Melhor Envio. Vazia por padrão: sem origem o simulador fica indisponível.
 *
 * Só o CEP é obrigatório para a cotação; os demais campos seguem o endereço ACULTA e servem à etiqueta depois.
 */
final class MelhorEnvioOriginForm extends ConfigFormBase {

  private const SETTINGS = 'aculta_commerce_melhorenvio.settings';

  /**
   * Cria o formulário.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    TypedConfigManagerInterface $typed_config_manager,
    private readonly CepLookupInterface $cep,
  ) {
    parent::__construct($config_factory, $typed_config_manager);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('config.factory'),
      $container->get('config.typed'),
      $container->get('aculta_portal_cep.lookup'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'aculta_commerce_melhorenvio_origin_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return [self::SETTINGS];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $origin = (array) $this->config(self::SETTINGS)->get('origin');
    $form['origin'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Origem da expedição'),
      '#description' => $this->t('Endereço de onde os pedidos saem. Sem CEP de origem, o cálculo de frete fica indisponível para o visitante.'),
    ];
    $form['origin']['postal_code'] = [
      '#type' => 'textfield',
      '#title' => $this->t('CEP'),
      '#default_value' => $origin['postal_code'] ?? '',
      '#size' => 10,
      '#maxlength' => 9,
      '#required' => TRUE,
      '#description' => $this->t('Oito dígitos, com ou sem traço.'),
    ];
    $form['origin']['address_line1'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Logradouro'),
      '#default_value' => $origin['address_line1'] ?? '',
      '#maxlength' => 255,
    ];
    $form['origin']['address_line2'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Complemento'),
      '#default_value' => $origin['address_line2'] ?? '',
      '#maxlength' => 255,
    ];
    $form['origin']['dependent_locality'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Bairro'),
      '#default_value' => $origin['dependent_locality'] ?? '',
      '#maxlength' => 255,
    ];
    $form['origin']['locality'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Cidade'),
      '#default_value' => $origin['locality'] ?? '',
      '#maxlength' => 255,
    ];
    $form['origin']['administrative_area'] = [
      '#type' => 'textfield',
      '#title' => $this->t('UF'),
      '#default_value' => $origin['administrative_area'] ?? '',
      '#size' => 2,
      '#maxlength' => 2,
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);
    $postal = (string) $form_state->getValue(['postal_code']);
    if ($this->cep->normalize($postal) === NULL) {
      $form_state->setErrorByName('postal_code', $this->t('Informe um CEP com oito dígitos.'));
    }
    $uf = (string) $form_state->getValue(['administrative_area']);
    if ($uf !== '' && preg_match('/^[A-Za-z]{2}$/', $uf) !== 1) {
      $form_state->setErrorByName('administrative_area', $this->t('UF com duas letras, ou vazia.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $origin = [
      'country_code' => 'BR',
      'postal_code' => (string) $this->cep->normalize((string) $form_state->getValue('postal_code')),
      'address_line1' => trim((string) $form_state->getValue('address_line1')),
      'address_line2' => trim((string) $form_state->getValue('address_line2')),
      'dependent_locality' => trim((string) $form_state->getValue('dependent_locality')),
      'locality' => trim((string) $form_state->getValue('locality')),
      'administrative_area' => strtoupper(trim((string) $form_state->getValue('administrative_area'))),
    ];
    $this->config(self::SETTINGS)->set('origin', $origin)->save();
    parent::submitForm($form, $form_state);
    $this->messenger()->addStatus($this->t('Origem da expedição salva.'));
  }

}
