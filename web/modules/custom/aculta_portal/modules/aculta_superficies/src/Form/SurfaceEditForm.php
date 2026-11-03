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
use Drupal\Core\Entity\EntityTypeManagerInterface;
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
    private readonly ConfigFactoryInterface $configFactoryService,
    private readonly SurfaceConfigReader $reader,
    private readonly DomainPurposeManager $purposes,
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('config.factory'),
      $container->get('aculta_portal.surface_config_reader'),
      $container->get('aculta_portal.domain_purpose'),
      $container->get('entity_type.manager'),
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
      // A troca de modo só deve reconstruir o bloco de caminho. Os campos
      // condicionais não podem impedir essa reconstrução por estarem vazios;
      // a validação completa ocorre no botão Salvar.
      '#limit_validation_errors' => [],
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
      $hostAtual = $dominio !== NULL ? (string) $dominio->getHostname() : '';
      if ($ehMain) {
        // O principal define o host de administração e a origem de todos os
        // links; trocá-lo aqui não é permitido.
        $form['caminho']['subdominio'] = [
          '#type' => 'item',
          '#title' => $this->t('Subdomínio'),
          '#markup' => $hostAtual !== '' ? $hostAtual : $this->t('(sem Domain)'),
          '#description' => $this->t('O principal não tem endereço editável aqui.'),
        ];
      }
      else {
        $form['caminho']['hostname'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Subdomínio (endereço completo)'),
          '#description' => $this->t('Ex.: wiki.exemplo.org. Sem https://, sem barra final. O endereço é gravado no Domain desta superfície.'),
          '#default_value' => $hostAtual,
          '#maxlength' => 253,
          '#required' => TRUE,
        ];
        $form['caminho']['confirmar_host'] = [
          '#type' => 'checkbox',
          '#title' => $this->t('Entendo que o endereço muda nas rotas desta superfície'),
          '#description' => $this->t('Marque só ao trocar o endereço. Sem essa confirmação, a troca não é gravada.'),
        ];
      }
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
    // O seletor AJAX produz estados intermediários (por exemplo, modo
    // desativado ainda com ativa marcada). Valide a combinação completa só no
    // envio do formulário, nunca durante a reconstrução do bloco de caminho.
    $gatilho = $form_state->getTriggeringElement();
    if (($gatilho['#type'] ?? '') !== 'submit') {
      return;
    }
    $purpose = (string) $form['#purpose'];
    $superficies = $this->reader->superficies();
    $superficies[$purpose] = $this->valoresDoFormulario($purpose, $form_state);
    $erros = SurfaceConfigReader::validar($superficies);
    if (isset($erros[$purpose])) {
      $campo = $form_state->getValue('modo') === 'diretorio' ? 'caminho][slug' : 'modo';
      $form_state->setErrorByName($campo, $erros[$purpose]);
    }

    if ($purpose === DomainSurfaceMap::MAIN || $form_state->getValue('modo') !== 'subdominio') {
      return;
    }
    $host = $this->hostSubmetido($form_state);
    $dominio = $this->purposes->getDomain($purpose);
    if ($dominio === NULL || $host === $dominio->getHostname()) {
      return;
    }
    if (!preg_match('/^(?=.{1,253}$)([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/', $host)) {
      $form_state->setErrorByName('caminho][hostname', $this->t('Endereço inválido. Use só o domínio, em minúsculas, sem https:// e sem barra.'));
      return;
    }
    if (!$form_state->getValue(['caminho', 'confirmar_host'])) {
      $form_state->setErrorByName('caminho][confirmar_host', $this->t('Confirme a troca de endereço para gravar.'));
    }
    $ocupados = $this->entityTypeManager->getStorage('domain')->loadByProperties(['hostname' => $host]);
    foreach ($ocupados as $ocupado) {
      if ($ocupado->id() !== $dominio->id()) {
        $form_state->setErrorByName('caminho][hostname', $this->t('Este endereço já é usado por outra superfície.'));
        return;
      }
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $purpose = (string) $form['#purpose'];
    $antes = $this->reader->superficies()[$purpose];
    $depois = $this->valoresDoFormulario($purpose, $form_state);

    $superficies = $this->reader->superficies();
    $superficies[$purpose] = $depois;
    $this->configFactoryService->getEditable(SurfaceConfigReader::CONFIG_NAME)
      ->set('superficies', $superficies)
      ->save();

    $dominio = $this->purposes->getDomain($purpose);
    if ($purpose !== DomainSurfaceMap::MAIN && $depois['modo'] === 'subdominio' && $dominio !== NULL) {
      $host = $this->hostSubmetido($form_state);
      if ($host !== $dominio->getHostname()) {
        $dominio->set('hostname', $host)->save();
        $this->messenger()->addWarning($this->t('Endereço de @titulo alterado para @host. Revise canonical e sitemap.', [
          '@titulo' => $depois['titulo'],
          '@host' => $host,
        ]));
      }
    }

    $this->messenger()->addStatus($this->t('@titulo salvo.', ['@titulo' => $depois['titulo']]));
    // O Portal não grava no registro de deploy; a correção é registrada pela CLI.
    if ($depois['modo'] !== $antes['modo'] || $depois['slug'] !== $antes['slug']) {
      $this->messenger()->addWarning($this->t('@titulo mudou de modo ou slug. Registre a correção de deploy (canonical e sitemap) na ferramenta de deploy.', [
        '@titulo' => $depois['titulo'],
      ]));
    }
  }

  /**
   * Host digitado, normalizado (minúsculas, sem espaços).
   */
  private function hostSubmetido(FormStateInterface $form_state): string {
    return strtolower(trim((string) $form_state->getValue(['caminho', 'hostname'])));
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
