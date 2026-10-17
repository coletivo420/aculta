<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Role\Form;

use Drupal\aculta_portal\Role\RoleHierarchy;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Papéis e perfis (painel ACULTA).
 *
 * Cadeia de papéis: tabela arrastável do próprio Drupal (#tabledrag, como na tela de blocos), com setas e peso por
 * linha. A ordem final vem dos pesos. Cada papel herda do papel imediatamente abaixo; papéis listados junto de uma
 * categoria entram nela. O administrador herda de todos. O mapa do role_inheritance é derivado ao salvar.
 *
 * Tipos de perfil: cada tipo corresponde a um papel (um tipo por função), então a tela só os lista.
 */
final class RoleHierarchyForm extends ConfigFormBase {

  private const ESTADO = 'aculta_portal.role_hierarchy';

  /** Papéis que não entram na hierarquia editável. */
  private const FORA = ['anonymous', 'administrator'];

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
    [$cadeia, $laterais] = $this->estadoAtual($papeis);

    $form['intro'] = [
      '#markup' => '<p>' . $this->t('Cadeia de papéis: quem está mais alto herda do papel imediatamente abaixo, e o administrador herda de todos. Papéis listados junto de uma categoria entram nela e herdam o que ela herda. Arraste as linhas pelas setas para reordenar.') . '</p>',
    ];

    $membrosDe = [];
    foreach ($laterais as $papel => $categoria) {
      $membrosDe[$categoria][] = $papel;
    }

    $form['cadeia'] = [
      '#type' => 'table',
      '#header' => [$this->t('Papel'), $this->t('Entram nesta categoria'), $this->t('Herda de (efetivo)'), $this->t('Peso')],
      '#tabledrag' => [
        ['action' => 'order', 'relationship' => 'sibling', 'group' => 'role-weight'],
      ],
      '#empty' => $this->t('Nenhum papel.'),
    ];
    $mapa = RoleHierarchy::derivarMapa($cadeia, $laterais, array_keys($papeis));
    foreach ($cadeia as $i => $papel) {
      $nomesMembros = array_map(fn (string $m): string => $papeis[$m], $membrosDe[$papel] ?? []);
      $herdados = array_map(fn (string $h): string => $papeis[$h] ?? $h, RoleHierarchy::efetivos($mapa, $papel));
      $form['cadeia'][$papel] = [
        '#attributes' => ['class' => ['draggable']],
        'papel' => ['#plain_text' => $papeis[$papel]],
        'membros' => ['#plain_text' => implode(', ', $nomesMembros)],
        'efetivo' => ['#plain_text' => $herdados === [] ? $this->t('nenhum') : implode(', ', $herdados)],
        'peso' => [
          '#type' => 'weight',
          '#title' => $this->t('Peso de @papel', ['@papel' => $papeis[$papel]]),
          '#title_display' => 'invisible',
          '#default_value' => $i,
          '#delta' => max(20, count($cadeia)),
          '#attributes' => ['class' => ['role-weight']],
        ],
      ];
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

    $form['telas'] = [
      '#type' => 'details',
      '#title' => $this->t('Telas do núcleo'),
      '#open' => FALSE,
      'lista' => [
        '#theme' => 'item_list',
        '#items' => [
          Link::fromTextAndUrl($this->t('Papéis (criar, editar e excluir papéis)'), Url::fromRoute('entity.user_role.collection'))->toRenderable(),
          Link::fromTextAndUrl($this->t('Permissões por papel'), Url::fromRoute('user.admin_permissions'))->toRenderable(),
          Link::fromTextAndUrl($this->t('Tipos de perfil (campos e exibição)'), Url::fromRoute('entity.profile_type.collection'))->toRenderable(),
        ],
      ],
    ];
    return parent::buildForm($form, $form_state);
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);
    $papeis = $this->papeis();
    $cadeia = $this->ordemDoEnvio($form_state);
    $laterais = $this->laterais($papeis, $cadeia);
    if (RoleHierarchy::temCiclo(RoleHierarchy::derivarMapa($cadeia, $laterais, array_keys($papeis)))) {
      $form_state->setErrorByName('cadeia', $this->t('A hierarquia tem ciclo. Corrija antes de salvar.'));
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $papeis = $this->papeis();
    $cadeia = $this->ordemDoEnvio($form_state);
    $laterais = $this->laterais($papeis, $cadeia);
    $this->config(self::ESTADO)->set('cadeia', $cadeia)->set('laterais', $laterais)->save();
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
   * Papéis fora da cadeia e a categoria em que entram (somente os que pertencem à cadeia enviada).
   *
   * @param array<string, string> $papeis
   * @param string[] $cadeia
   *
   * @return array<string, string>
   */
  private function laterais(array $papeis, array $cadeia): array {
    $laterais = (array) $this->config(self::ESTADO)->get('laterais');
    return RoleHierarchy::completar($cadeia, $laterais, array_values(array_diff(array_keys($papeis), self::FORA)))['laterais'];
  }

  /**
   * Cadeia e laterais atuais: os do envio (ao mover), senão os salvos, senão o padrão de instalação.
   *
   * @param array<string, string> $papeis
   *
   * @return array{0: string[], 1: array<string, string>}
   */
  private function estadoAtual(array $papeis): array {
    $configuracao = $this->config(self::ESTADO);
    $cadeia = (array) ($configuracao->get('cadeia') ?? ['gerente_loja', 'content_editor', 'lms_teacher', 'associado', 'estudante', 'email_confirmed', 'authenticated']);
    $laterais = (array) ($configuracao->get('laterais') ?? ['wiki_contributor' => 'email_confirmed', 'lms_admin' => 'lms_teacher']);
    $completo = RoleHierarchy::completar($cadeia, $laterais, array_values(array_diff(array_keys($papeis), self::FORA)));
    return [$completo['cadeia'], $completo['laterais']];
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
