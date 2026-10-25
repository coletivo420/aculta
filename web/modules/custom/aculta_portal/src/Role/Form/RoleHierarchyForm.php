<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Role\Form;

use Drupal\aculta_portal\Role\RoleHierarchy;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Papéis e perfis (painel ACULTA).
 *
 * Cadeia de papéis: tabela arrastável do próprio Drupal (#tabledrag, como na tela de blocos), com setas e peso por
 * linha. A ordem final vem dos pesos. Cada papel herda do papel imediatamente abaixo; o administrador herda de todos.
 * Papel fora da cadeia herda do autenticado. Ao salvar, só a cadeia é gravada (aculta_portal.role_hierarchy).
 *
 * Tipos de perfil: cada tipo corresponde a um papel, então a tela só os lista.
 */
final class RoleHierarchyForm extends ConfigFormBase {

  private const ESTADO = 'aculta_portal.role_hierarchy';

  /** Cadeia de partida quando a configuração ainda não existe (instalação). */
  private const CADEIA_PADRAO = ['gerente_loja', 'content_editor', 'lms_teacher', 'email_confirmed', 'authenticated'];

  public function __construct(
    ConfigFactoryInterface $config_factory,
    TypedConfigManagerInterface $typedConfigManager,
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {
    parent::__construct($config_factory, $typedConfigManager);
  }

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('config.factory'),
      $container->get('config.typed'),
      $container->get('entity_type.manager'),
    );
  }

  public function getFormId(): string {
    return 'aculta_role_hierarchy_form';
  }

  protected function getEditableConfigNames(): array {
    return [self::ESTADO];
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $papeis = $this->papeis();
    $cadeia = $this->cadeiaAtual($papeis);
    $mapa = RoleHierarchy::derivarMapa($cadeia, array_keys($papeis));
    $entidades = $this->entityTypeManager->getStorage('user_role')->loadMultiple();
    $lista = $this->entityTypeManager->getListBuilder('user_role');

    // Menu de operações (dropbutton do Core) precisa do próprio script; a lista do Core anexava isso, a nossa não.
    $form['#attached']['library'][] = 'core/drupal.dropbutton';
    $form['intro'] = [
      '#markup' => '<p>' . $this->t('Cadeia de papéis: quem está mais alto herda do papel imediatamente abaixo, e o administrador herda de todos. Arraste as linhas pelas setas para reordenar.') . '</p>',
    ];
    $form['adicionar'] = [
      '#type' => 'link',
      '#title' => $this->t('Adicionar papel'),
      '#url' => Url::fromRoute('user.role_add'),
    ];

    $form['cadeia'] = [
      '#type' => 'table',
      '#header' => [$this->t('Papel'), $this->t('Herda de (efetivo)'), $this->t('Peso'), $this->t('Operações')],
      '#tabledrag' => [
        ['action' => 'order', 'relationship' => 'sibling', 'group' => 'role-weight'],
      ],
      '#empty' => $this->t('Nenhum papel.'),
    ];
    foreach ($cadeia as $i => $papel) {
      $herdados = array_map(fn (string $h): string => $papeis[$h] ?? $h, RoleHierarchy::efetivos($mapa, $papel));
      $form['cadeia'][$papel] = [
        '#attributes' => ['class' => ['draggable']],
        'papel' => ['#plain_text' => $papeis[$papel]],
        'efetivo' => ['#plain_text' => $herdados === [] ? $this->t('nenhum') : implode(', ', $herdados)],
        'peso' => [
          '#type' => 'weight',
          '#title' => $this->t('Peso de @papel', ['@papel' => $papeis[$papel]]),
          '#title_display' => 'invisible',
          '#default_value' => $i,
          '#delta' => max(20, count($cadeia)),
          '#attributes' => ['class' => ['role-weight']],
        ],
        'operacoes' => $lista->buildOperations($entidades[$papel]),
      ];
    }

    $fora = array_values(array_diff(array_keys($papeis), $cadeia));
    if ($fora !== []) {
      $form['fora'] = [
        '#type' => 'table',
        '#caption' => $this->t('Fora da cadeia (herdam do autenticado, ou nada, no caso do anônimo e do administrador)'),
        '#header' => [$this->t('Papel'), $this->t('Operações')],
        '#empty' => $this->t('Nenhum papel fora da cadeia.'),
      ];
      foreach ($fora as $papel) {
        $form['fora'][$papel] = [
          'papel' => ['#plain_text' => $papeis[$papel]],
          'operacoes' => $lista->buildOperations($entidades[$papel]),
        ];
      }
    }

    $form['perfis'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Tipos de perfil'),
      '#description' => $this->t('Cada tipo de perfil corresponde a um papel.'),
    ];
    $linhas = [];
    foreach ($this->tiposDePerfil() as $id => $rotulo) {
      $linhas[] = [$rotulo, $papeis[$id] ?? $id];
    }
    $form['perfis']['tabela'] = [
      '#type' => 'table',
      '#header' => [$this->t('Tipo de perfil'), $this->t('Função')],
      '#rows' => $linhas,
      '#empty' => $this->t('Nenhum tipo de perfil.'),
    ];

    return parent::buildForm($form, $form_state);
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);
    $papeis = $this->papeis();
    $cadeia = $this->ordemDoEnvio($form_state);
    if (RoleHierarchy::temCiclo(RoleHierarchy::derivarMapa($cadeia, array_keys($papeis)))) {
      $form_state->setErrorByName('cadeia', $this->t('A hierarquia tem ciclo. Corrija antes de salvar.'));
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->config(self::ESTADO)->set('cadeia', $this->ordemDoEnvio($form_state))->save();
    $this->messenger()->addStatus($this->t('Hierarquia de papéis salva.'));
    parent::submitForm($form, $form_state);
  }

  /**
   * Papéis da cadeia na ordem dos pesos enviados (empate: ordem atual).
   *
   * @return string[]
   */
  private function ordemDoEnvio(FormStateInterface $form_state): array {
    $linhas = $form_state->getValue('cadeia') ?? [];
    $ordem = [];
    foreach ($linhas as $papel => $linha) {
      if (is_array($linha) && isset($linha['peso'])) {
        $ordem[] = ['papel' => (string) $papel, 'peso' => (int) $linha['peso']];
      }
    }
    usort($ordem, fn (array $a, array $b): int => $a['peso'] <=> $b['peso']);
    return array_column($ordem, 'papel');
  }

  /**
   * Cadeia salva, coerente com os papéis que existem. Sem configuração, usa a cadeia de instalação.
   *
   * @param array<string, string> $papeis
   *
   * @return string[]
   */
  private function cadeiaAtual(array $papeis): array {
    $salva = $this->config(self::ESTADO)->get('cadeia');
    $cadeia = is_array($salva) ? $salva : self::CADEIA_PADRAO;
    return RoleHierarchy::completar($cadeia, array_values(array_diff(array_keys($papeis), RoleHierarchy::FORA_DA_CADEIA)));
  }

  /**
   * Papéis do site (inclui anônimo, que aparece só como papel pai do administrador).
   *
   * @return array<string, string>
   */
  private function papeis(): array {
    $papeis = [];
    foreach ($this->entityTypeManager->getStorage('user_role')->loadMultiple() as $id => $papel) {
      $papeis[$id] = (string) $papel->label();
    }
    return $papeis;
  }

  /**
   * @return array<string, string>
   */
  private function tiposDePerfil(): array {
    $tipos = [];
    foreach ($this->entityTypeManager->getStorage('profile_type')->loadMultiple() as $id => $tipo) {
      $tipos[$id] = (string) $tipo->label();
    }
    return $tipos;
  }

}
