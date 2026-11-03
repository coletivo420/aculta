<?php

declare(strict_types=1);

namespace Drupal\aculta_superficies\Form;

use Drupal\aculta_portal\Domain\DomainSurfaceMap;
use Drupal\aculta_portal\Domain\SurfaceConfigReader;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Administra modo, slug, título e ativação de cada superfície (Fase 3.6).
 *
 * Só existe no MAIN (rota com _aculta_domain_purpose main). Não altera
 * roteamento: a aplicação da configuração às rotas é a Fase 3.4 completa.
 */
final class SuperficiesForm extends ConfigFormBase {

  public function __construct(
    ConfigFactoryInterface $config_factory,
    TypedConfigManagerInterface $typed_config_manager,
    private readonly SurfaceConfigReader $reader,
  ) {
    parent::__construct($config_factory, $typed_config_manager);
  }

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('config.factory'),
      $container->get('config.typed'),
      $container->get('aculta_portal.surface_config_reader'),
    );
  }

  public function getFormId(): string {
    return 'aculta_superficies_form';
  }

  protected function getEditableConfigNames(): array {
    return [SurfaceConfigReader::CONFIG_NAME];
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildForm($form, $form_state);
    $form['aviso'] = [
      '#markup' => '<p>' . $this->t('Mudar o modo ou o slug não altera as rotas nesta versão. Antes de aplicar, confira o impacto na Conta e na sessão compartilhada.') . '</p>',
    ];
    foreach ($this->reader->superficies() as $purpose => $linha) {
      $form[$purpose] = [
        '#type' => 'details',
        '#title' => SurfaceConfigReader::TITULOS[$purpose] ?? $purpose,
        '#open' => TRUE,
      ];
      $form[$purpose]['ativa'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Ativa'),
        '#default_value' => $linha['ativa'],
        '#disabled' => $purpose === DomainSurfaceMap::MAIN,
      ];
      $form[$purpose]['modo'] = [
        '#type' => 'select',
        '#title' => $this->t('Modo'),
        '#options' => [
          'subdominio' => $this->t('Subdomínio'),
          'diretorio' => $this->t('Diretório'),
          'desativado' => $this->t('Desativado'),
        ],
        '#default_value' => $linha['modo'],
      ];
      $form[$purpose]['slug'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Slug (só para diretório)'),
        '#default_value' => $linha['slug'],
        '#maxlength' => 64,
      ];
      $form[$purpose]['titulo'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Título'),
        '#default_value' => $linha['titulo'],
        '#maxlength' => 80,
        '#required' => TRUE,
      ];
    }
    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);
    foreach (SurfaceConfigReader::validar($this->valoresSubmetidos($form_state)) as $purpose => $mensagem) {
      $form_state->setErrorByName($purpose . '][slug', $mensagem);
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->config(SurfaceConfigReader::CONFIG_NAME)
      ->set('superficies', $this->valoresSubmetidos($form_state))
      ->save();
    parent::submitForm($form, $form_state);
    $this->messenger()->addStatus($this->t('Superfícies salvas.'));
  }

  /**
   * @return array<string, array{modo: string, ativa: bool, slug: string, titulo: string}>
   */
  private function valoresSubmetidos(FormStateInterface $form_state): array {
    $saida = [];
    foreach (array_keys(DomainSurfaceMap::DOMAIN_IDS) as $purpose) {
      $saida[$purpose] = [
        'modo' => (string) $form_state->getValue([$purpose, 'modo']),
        'ativa' => $purpose === DomainSurfaceMap::MAIN ? TRUE : (bool) $form_state->getValue([$purpose, 'ativa']),
        'slug' => trim((string) $form_state->getValue([$purpose, 'slug'])),
        'titulo' => trim((string) $form_state->getValue([$purpose, 'titulo'])),
      ];
    }
    return $saida;
  }

}
