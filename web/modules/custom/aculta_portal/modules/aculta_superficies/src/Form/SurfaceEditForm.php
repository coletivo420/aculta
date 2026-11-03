<?php

declare(strict_types=1);

namespace Drupal\aculta_superficies\Form;

use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\aculta_portal\Domain\DomainSurfaceMap;
use Drupal\aculta_portal\Domain\SurfaceConfigReader;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\Core\Ajax\RedirectCommand;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Edição de uma superfície dentro de modal (Fase 3.6).
 *
 * O campo de caminho depende do modo, por AJAX: em diretório aparece o slug
 * editável; em subdomínio aparece o endereço vindo do Domain (somente leitura);
 * em desativado não aparece nenhum campo de caminho.
 */
final class SurfaceEditForm extends FormBase {

  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
    private readonly SurfaceConfigReader $reader,
    private readonly DomainPurposeManager $purposes,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('config.factory'),
      $container->get('aculta_portal.surface_config_reader'),
      $container->get('aculta_portal.domain_purpose'),
    );
  }

  public function getFormId(): string {
    return 'aculta_superficies_editar_form';
  }

  /**
   * {@inheritdoc}
   *
   * @param string $purpose
   *   Purpose da superfície (validado pelo controlador).
   */
  public function buildForm(array $form, FormStateInterface $form_state, string $purpose = ''): array {
    $linha = $this->reader->superficies()[$purpose];
    $ehMain = $purpose === DomainSurfaceMap::MAIN;
    // Durante o rebuild do AJAX, o valor vem do estado; na primeira abertura, da configuração.
    $modo = (string) ($form_state->getValue('modo') ?? $linha['modo']);

    $form['#purpose'] = $purpose;
    $form['titulo'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Título'),
      '#default_value' => $linha['titulo'],
      '#maxlength' => 80,
      '#required' => TRUE,
    ];
    $form['ativa'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Ativa'),
      '#default_value' => $ehMain ? TRUE : $linha['ativa'],
      '#disabled' => $ehMain,
      '#description' => $ehMain ? $this->t('O principal não pode ser desativado.') : NULL,
    ];
    $form['modo'] = [
      '#type' => 'select',
      '#title' => $this->t('Modo'),
      '#options' => [
        'subdominio' => $this->t('Subdomínio'),
        'diretorio' => $this->t('Diretório'),
        'desativado' => $this->t('Desativado'),
      ],
      '#default_value' => $ehMain ? 'subdominio' : $linha['modo'],
      '#disabled' => $ehMain,
      '#ajax' => [
        'callback' => '::ajaxAtualizaCaminho',
        'wrapper' => 'superficie-caminho',
        'event' => 'change',
      ],
    ];
    $form['caminho'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'superficie-caminho'],
    ];
    if ($modo === 'diretorio') {
      $form['caminho']['slug'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Diretório (slug)'),
        '#description' => $this->t('Minúsculas, números e hífen. Ex.: loja-sativa.'),
        '#default_value' => $linha['slug'],
        '#maxlength' => 64,
        '#required' => TRUE,
      ];
    }
    elseif ($modo === 'subdominio') {
      $dominio = $this->purposes->getDomain($purpose);
      $form['caminho']['subdominio'] = [
        '#type' => 'item',
        '#title' => $this->t('Subdomínio'),
        '#markup' => $dominio !== NULL ? $dominio->getHostname() : $this->t('(sem Domain)'),
        '#description' => $this->t('Vem do Domain configurado. Não é editado aqui.'),
      ];
    }

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Salvar'),
      '#button_type' => 'primary',
      '#ajax' => ['callback' => '::ajaxSalvar'],
    ];
    return $form;
  }

  /**
   * Callback do seletor de modo: devolve só o bloco do caminho.
   */
  public function ajaxAtualizaCaminho(array &$form, FormStateInterface $form_state): array {
    return $form['caminho'];
  }

  /**
   * Callback do botão Salvar: erro reabre o modal; sucesso fecha e volta à lista.
   */
  public function ajaxSalvar(array &$form, FormStateInterface $form_state): array|AjaxResponse {
    if ($form_state->hasAnyErrors()) {
      return $form;
    }
    $resposta = new AjaxResponse();
    $resposta->addCommand(new CloseModalDialogCommand());
    $resposta->addCommand(new RedirectCommand(Url::fromRoute('aculta_superficies.form')->toString()));
    return $resposta;
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $purpose = (string) $form['#purpose'];
    $superficies = $this->reader->superficies();
    $superficies[$purpose] = $this->valoresDoFormulario($purpose, $form_state);
    $erros = SurfaceConfigReader::validar($superficies);
    if (isset($erros[$purpose])) {
      $campo = $form_state->getValue('modo') === 'diretorio' ? 'caminho][slug' : 'modo';
      $form_state->setErrorByName($campo, $erros[$purpose]);
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $purpose = (string) $form['#purpose'];
    $antes = $this->reader->superficies()[$purpose];
    $depois = $this->valoresDoFormulario($purpose, $form_state);

    $superficies = $this->reader->superficies();
    $superficies[$purpose] = $depois;
    $this->configFactory->getEditable(SurfaceConfigReader::CONFIG_NAME)
      ->set('superficies', $superficies)
      ->save();

    $this->messenger()->addStatus($this->t('@titulo salvo.', ['@titulo' => $depois['titulo']]));
    // O Portal não grava no registro de deploy; a correção é registrada pela CLI.
    if ($depois['modo'] !== $antes['modo'] || $depois['slug'] !== $antes['slug']) {
      $this->messenger()->addWarning($this->t('@titulo mudou de modo ou slug. Registre a correção de deploy (canonical e sitemap) na ferramenta de deploy.', [
        '@titulo' => $depois['titulo'],
      ]));
    }
  }

  /**
   * Valores do formulário no formato da configuração, para um purpose.
   *
   * @return array{modo: string, ativa: bool, slug: string, titulo: string}
   */
  private function valoresDoFormulario(string $purpose, FormStateInterface $form_state): array {
    $modo = (string) $form_state->getValue('modo');
    $ehMain = $purpose === DomainSurfaceMap::MAIN;
    return [
      'modo' => $ehMain ? 'subdominio' : $modo,
      'ativa' => $ehMain ? TRUE : (bool) $form_state->getValue('ativa'),
      'slug' => $modo === 'diretorio' ? trim((string) $form_state->getValue(['caminho', 'slug'])) : '',
      'titulo' => trim((string) $form_state->getValue('titulo')),
    ];
  }

}
