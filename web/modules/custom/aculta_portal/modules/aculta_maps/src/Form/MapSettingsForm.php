<?php

declare(strict_types=1);

namespace Drupal\aculta_maps\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\key\KeyRepositoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Administrative options for maps, excluding external credentials.
 */
final class MapSettingsForm extends ConfigFormBase {

  public function __construct(
    ConfigFactoryInterface $configFactory,
    TypedConfigManagerInterface $typedConfigManager,
    private readonly KeyRepositoryInterface $keys,
  ) {
    parent::__construct($configFactory, $typedConfigManager);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('config.factory'),
      $container->get('config.typed'),
      $container->get('key.repository'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'aculta_maps_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['aculta_maps.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $settings = $this->config('aculta_maps.settings');
    $form['engine'] = [
      '#type' => 'select',
      '#title' => $this->t('Provedor do mapa'),
      '#options' => ['openstreetmap' => $this->t('OpenStreetMap'), 'google' => $this->t('Google Maps')],
      '#default_value' => $settings->get('engine') ?? 'openstreetmap',
      '#required' => TRUE,
    ];
    $form['center_lat'] = [
      '#type' => 'number',
      '#title' => $this->t('Latitude do centro inicial'),
      '#default_value' => $settings->get('center_lat') ?? -14.235,
      '#min' => -90,
      '#max' => 90,
      '#step' => 'any',
      '#required' => TRUE,
    ];
    $form['center_lng'] = [
      '#type' => 'number',
      '#title' => $this->t('Longitude do centro inicial'),
      '#default_value' => $settings->get('center_lng') ?? -51.9253,
      '#min' => -180,
      '#max' => 180,
      '#step' => 'any',
      '#required' => TRUE,
    ];
    $form['zoom_initial'] = [
      '#type' => 'number',
      '#title' => $this->t('Zoom inicial'),
      '#default_value' => $settings->get('zoom_initial') ?? 4,
      '#min' => 0,
      '#max' => 20,
      '#required' => TRUE,
    ];
    $form['zoom_location'] = [
      '#type' => 'number',
      '#title' => $this->t('Zoom ao mostrar um local'),
      '#default_value' => $settings->get('zoom_location') ?? 15,
      '#min' => 0,
      '#max' => 20,
      '#required' => TRUE,
    ];
    $form['route_url_template'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Modelo do link para rota'),
      '#description' => $this->t('Use {destino} como marcador do destino. O link abre apenas quando a pessoa escolhe ver a rota.'),
      '#default_value' => $settings->get('route_url_template') ?? 'https://www.google.com/maps/dir/?api=1&destination={destino}',
      '#maxlength' => 255,
      '#required' => TRUE,
    ];
    $key = $this->keys->getKey('google_maps_api_key');
    $keyValue = $key?->getKeyValue();
    $form['credential_status'] = [
      '#type' => 'item',
      '#title' => $this->t('Chave do Google Maps'),
      '#markup' => is_string($keyValue) && $keyValue !== '' ? $this->t('Configurada') : $this->t('Ausente'),
      '#description' => $this->t('A chave fica no Secrets Manager. Este formulário não exibe nem altera credenciais.'),
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);
    $template = trim((string) $form_state->getValue('route_url_template'));
    $parts = parse_url(str_replace('{destino}', 'destination', $template));
    $validHost = in_array($parts['host'] ?? '', ['www.google.com', 'maps.google.com'], TRUE);
    if (!str_contains($template, '{destino}')
      || ($parts['scheme'] ?? '') !== 'https'
      || !$validHost
      || !str_starts_with($parts['path'] ?? '', '/maps/dir')) {
      $form_state->setErrorByName('route_url_template', $this->t('Informe um link HTTPS de rota do Google Maps que contenha {destino}.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $settings = $this->configFactory()->getEditable('aculta_maps.settings');
    foreach (['engine', 'center_lat', 'center_lng', 'zoom_initial', 'zoom_location', 'route_url_template'] as $key) {
      $settings->set($key, $form_state->getValue($key));
    }
    $settings->save();
    parent::submitForm($form, $form_state);
  }

}
