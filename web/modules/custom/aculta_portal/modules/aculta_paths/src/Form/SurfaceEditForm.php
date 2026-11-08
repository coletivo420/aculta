<?php

declare(strict_types=1);

namespace Drupal\aculta_paths\Form;

use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\aculta_portal\Domain\DomainSurfaceMap;
use Drupal\aculta_portal\Domain\SurfaceConfigReader;
use Drupal\aculta_paths\Validation\EnderecoValidator;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Htmx\Htmx;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Edição de uma superfície dentro de modal (Fase 3.6).
 *
 * O campo de caminho depende do modo, por AJAX: em diretório aparece o slug
 * editável; em subdomínio aparece o endereço vindo do Domain (somente leitura);
 * em desativado não aparece nenhum campo de caminho.
 */
final class SurfaceEditForm extends FormBase {

  public function __construct(
    protected ConfigFactoryInterface $configFactoryService,
    protected SurfaceConfigReader $reader,
    protected DomainPurposeManager $purposes,
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('config.factory'),
      $container->get('aculta_portal.surface_config_reader'),
      $container->get('aculta_portal.domain_purpose'),
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'aculta_paths_editar_form_v2';
  }

  /**
   * {@inheritdoc}
   *
   * @param array $form
   *   Estrutura do formulário a construir.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Estado atual do formulário.
   * @param string $purpose
   *   Purpose da superfície (validado pelo controlador).
   */
  public function buildForm(array $form, FormStateInterface $form_state, string $purpose = ''): array {
    $linha = $this->reader->superficies()[$purpose];
    $ehMain = $purpose === DomainSurfaceMap::MAIN;
    // Durante o rebuild do AJAX, o valor vem do estado; na primeira abertura, da configuração.
    $modo = (string) ($form_state->getValue('modo') ?? $linha['modo']);

    $form['#purpose'] = $purpose;
    // Wrapper do fragmento devolvido ao envio HTMX; é o mesmo bloco que o
    // callback AJAX de salvar reabria em caso de erro.
    $form['#prefix'] = '<div id="aculta-paths-surface-edit">';
    $form['#suffix'] = '</div>';
    $form['titulo'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Nome do Site'),
      '#description' => $this->t('Nome exibido para esta superfície. Corresponde ao site do subdomínio.'),
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
        '#description' => $this->t('Letras sem acento, números e hífen. Maiúsculas viram minúsculas. Ex.: loja-sativa.'),
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
          '#description' => $this->t('Ex.: wiki.exemplo.org. Sem https://, sem barra final. Maiúsculas viram minúsculas. O endereço é gravado no Domain desta superfície.'),
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
    else {
      // Mantém o wrapper renderizável no estado desativado; um container
      // completamente vazio pode gerar resposta AJAX inválida no Drupal.
      $form['caminho']['vazio'] = ['#markup' => ''];
    }

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Salvar'),
      '#button_type' => 'primary',
    ];
    // O envio devolve o formulário (erro de validação) ou redireciona para a
    // lista (sucesso). A URL é a própria rota do formulário, com o purpose.
    (new Htmx())
      ->post(Url::fromRoute('aculta_paths.editar', ['purpose' => $purpose]))
      ->select('#aculta-paths-surface-edit')
      ->target('#aculta-paths-surface-edit')
      ->swap('innerHTML')
      ->onlyMainContent()
      ->applyTo($form['actions']['submit']);
    return $form;
  }

  /**
   * Callback do seletor de modo: devolve só o bloco do caminho.
   */
  public function ajaxAtualizaCaminho(array &$form, FormStateInterface $form_state): array {
    return $form['caminho'];
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    // O seletor AJAX produz estados intermediários (por exemplo, modo
    // desativado ainda com ativa marcada). Valide a combinação completa só no
    // envio do formulário, nunca durante a reconstrução do bloco de caminho.
    $gatilho = $form_state->getTriggeringElement();
    if (($gatilho['#type'] ?? '') !== 'submit') {
      return;
    }
    $purpose = (string) $form['#purpose'];
    $modo = (string) $form_state->getValue('modo');

    // Caracteres e formato: a mensagem diz o que foi recusado e por quê.
    if ($purpose !== DomainSurfaceMap::MAIN && $modo === 'diretorio') {
      $motivo = $this->motivoEndereco($this->slugSubmetido($form_state), FALSE);
      if ($motivo !== NULL) {
        $form_state->setErrorByName('caminho][slug', $motivo);
        return;
      }
    }
    if ($purpose !== DomainSurfaceMap::MAIN && $modo === 'subdominio') {
      $motivo = $this->motivoEndereco($this->hostSubmetido($form_state), TRUE);
      if ($motivo !== NULL) {
        $form_state->setErrorByName('caminho][hostname', $motivo);
        return;
      }
    }

    $superficies = $this->reader->superficies();
    $superficies[$purpose] = $this->valoresDoFormulario($purpose, $form_state);
    $erros = SurfaceConfigReader::validar($superficies);
    if (isset($erros[$purpose])) {
      $campo = $modo === 'diretorio' ? 'caminho][slug' : 'modo';
      $form_state->setErrorByName($campo, $erros[$purpose]);
    }

    if ($purpose === DomainSurfaceMap::MAIN || $modo !== 'subdominio') {
      return;
    }
    $host = $this->hostSubmetido($form_state);
    $dominio = $this->purposes->getDomain($purpose);
    if ($dominio === NULL || $host === $dominio->getHostname()) {
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

  /**
   * {@inheritdoc}
   */
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

    // HTMX: navega para a lista (fecha o diálogo); sem HTMX o Form API
    // redireciona normalmente.
    if ($this->isHtmxRequest()) {
      $form_state->setResponse(new Response('', Response::HTTP_OK, [
        'HX-Redirect' => Url::fromRoute('aculta_paths.form')->toString(),
      ]));
      return;
    }
    $form_state->setRedirect('aculta_paths.form');
  }

  /**
   * Motivo da recusa de um endereço ou slug, traduzido, ou NULL se estiver certo.
   */
  private function motivoEndereco(string $valor, bool $host): ?string {
    $motivo = EnderecoValidator::motivo($valor, $host);
    if ($motivo === NULL) {
      return NULL;
    }
    $a = $motivo['args'];
    return match ($motivo['codigo']) {
      'vazio' => (string) $this->t('Campo vazio. Digite um valor.'),
      'caracteres' => (string) $this->t('Caracteres não permitidos: @lista. Use só @uso.', [
        '@lista' => $a['lista'],
        '@uso' => $a['host'] === '1'
          ? (string) $this->t('letras sem acento, números, hífen e ponto')
          : (string) $this->t('letras sem acento, números e hífen'),
      ]),
      'hifen_slug' => (string) $this->t('Não pode começar nem terminar com hífen, nem ter dois hífens seguidos.'),
      'tamanho' => (string) $this->t('Endereço maior que 253 caracteres.'),
      'sem_ponto' => (string) $this->t('Falta o domínio. Ex.: subdominio.exemplo.org.'),
      'ponto_sobrando' => (string) $this->t('Há um ponto sobrando, sem texto entre dois pontos.'),
      'trecho_longo' => (string) $this->t('Um trecho entre pontos passa de 63 caracteres.'),
      'trecho_hifen' => (string) $this->t('Um trecho entre pontos começa ou termina com hífen.'),
      default => (string) $this->t('O final do endereço precisa ser letras (ex.: .org).'),
    };
  }

  /**
   * Slug digitado, sempre em minúsculas e sem espaços nas bordas.
   */
  private function slugSubmetido(FormStateInterface $form_state): string {
    return strtolower(trim((string) $form_state->getValue(['caminho', 'slug'])));
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
   * @param string $purpose
   *   Purpose da superfície editada.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Estado atual do formulário.
   *
   * @return array{modo: string, ativa: bool, slug: string, titulo: string}
   *   Valores normalizados da superfície.
   */
  private function valoresDoFormulario(string $purpose, FormStateInterface $form_state): array {
    $modo = (string) $form_state->getValue('modo');
    $ehMain = $purpose === DomainSurfaceMap::MAIN;
    return [
      'modo' => $ehMain ? 'subdominio' : $modo,
      'ativa' => $ehMain ? TRUE : (bool) $form_state->getValue('ativa'),
      'slug' => $modo === 'diretorio' ? $this->slugSubmetido($form_state) : '',
      'titulo' => trim((string) $form_state->getValue('titulo')),
    ];
  }

}
