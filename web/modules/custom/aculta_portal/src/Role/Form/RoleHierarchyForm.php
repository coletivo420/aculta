<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Role\Form;

use Drupal\aculta_portal\Role\RoleHierarchy;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Component\Utility\Html;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Papéis e perfis (painel ACULTA).
 *
 * Cadeia de papéis: lista ordenada pela classificação de cada categoria, do mais alto ao mais baixo; cada papel herda do papel imediatamente abaixo.
 * Move-se um papel por setas (botões, funcionam sem JavaScript) ou por arraste (mouse e toque; módulo
 * js/role-hierarchy.js). Papéis sem categoria ficam fora da cadeia e herdam do autenticado. O administrador herda
 * de todos. O mapa de herança do role_inheritance é derivado da cadeia e dos laterais ao salvar.
 *
 * Tipos de perfil: cada tipo corresponde a um papel (um tipo por função), então a tela só os lista.
 */
final class RoleHierarchyForm extends ConfigFormBase {

  private const HIERARQUIA = 'role_inheritance.settings';

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
    return [self::HIERARQUIA, self::ESTADO];
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $papeis = $this->papeis();
    [$cadeia, $laterais] = $this->estadoAtual($form_state, $papeis);

    $form['#attached']['library'][] = 'aculta_portal/role-hierarchy';
    $form['intro'] = [
      '#markup' => '<p>' . $this->t('Cadeia de papéis: quem está mais alto herda do papel imediatamente abaixo, e o administrador herda de todos. Mova um papel com as setas ou arrastando (mouse ou toque).') . '</p>',
    ];

    $form['ordem'] = ['#type' => 'hidden', '#default_value' => json_encode($cadeia), '#attributes' => ['data-portal-role-order' => '']];
    $form['laterais'] = ['#type' => 'hidden', '#default_value' => json_encode($laterais), '#attributes' => ['data-portal-role-laterals' => '']];

    $form['cadeia'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Cadeia de papéis (do mais alto ao mais baixo)'),
      'lista' => ['#type' => 'container', '#attributes' => ['data-portal-role-list' => '']],
    ];
    $total = count($cadeia);
    foreach ($cadeia as $i => $papel) {
      $form['cadeia']['lista'][$papel] = [
        '#type' => 'container',
        '#attributes' => ['data-portal-role-item' => $papel],
        'rotulo' => ['#markup' => '<strong>' . Html::escape($papeis[$papel]) . '</strong>'],
        'subir' => $this->botao($papel, 'up', $this->t('Subir'), $i === 0),
        'descer' => $this->botao($papel, 'down', $this->t('Descer'), $i === $total - 1),
      ];
    }

    if ($laterais !== []) {
      // Papéis sem categoria na cadeia: herdam do autenticado, sem controle na tela.
      $form['sem_categoria'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Sem categoria'),
        '#description' => $this->t('Estes papéis não estão na cadeia.'),
      ];
      foreach (array_keys($laterais) as $papel) {
        $form['sem_categoria'][$papel] = ['#markup' => '<p><strong>' . Html::escape($papeis[$papel]) . '</strong></p>'];
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
    $cadeia = $this->lerLista($form_state->getValue('ordem'));
    $laterais = $this->lerMapa($form_state->getValue('laterais'));
    $esperados = array_values(array_diff(array_keys($papeis), self::FORA, array_keys($laterais)));
    if ($cadeia === NULL || $laterais === NULL || array_diff($cadeia, $esperados) !== [] || array_diff($esperados, $cadeia) !== []) {
      $form_state->setErrorByName('ordem', $this->t('A cadeia de papéis está incompleta. Recarregue a página e tente de novo.'));
      return;
    }
    if (RoleHierarchy::temCiclo(RoleHierarchy::derivarMapa($cadeia, $laterais, array_keys($papeis)))) {
      $form_state->setErrorByName('ordem', $this->t('A hierarquia tem ciclo. Corrija antes de salvar.'));
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $papeis = $this->papeis();
    $cadeia = $this->lerLista($form_state->getValue('ordem')) ?? [];
    $laterais = $this->lerMapa($form_state->getValue('laterais')) ?? [];
    $mapa = RoleHierarchy::derivarMapa($cadeia, $laterais, array_keys($papeis));
    $this->config(self::ESTADO)->set('cadeia', $cadeia)->set('laterais', $laterais)->save();
    $this->config(self::HIERARQUIA)->set('role_map', $mapa)->save();
    $this->messenger()->addStatus($this->t('Hierarquia de papéis salva.'));
    parent::submitForm($form, $form_state);
  }

  /**
   * Botões de setas: troca o papel com o vizinho e refaz a tela com a nova ordem (sem salvar).
   */
  public function alterarEstado(array &$form, FormStateInterface $form_state): void {
    $nome = (string) ($form_state->getTriggeringElement()['#name'] ?? '');
    $partes = explode('__', $nome);
    $entrada = $form_state->getUserInput();
    if (($partes[0] ?? '') === 'cadeia' && count($partes) === 3) {
      $cadeia = $this->lerLista($entrada['ordem'] ?? NULL) ?? [];
      $i = array_search($partes[1], $cadeia, TRUE);
      $j = $partes[2] === 'up' ? $i - 1 : $i + 1;
      if ($i !== FALSE && isset($cadeia[$j])) {
        [$cadeia[$i], $cadeia[$j]] = [$cadeia[$j], $cadeia[$i]];
        $entrada['ordem'] = json_encode(array_values($cadeia));
      }
    }
    $form_state->setUserInput($entrada);
    $form_state->setRebuild();
  }

  /**
   * Botão de seta. Sem JavaScript, o clique envia o formulário e a ordem é refeita pelo servidor.
   *
   * @return array<string, mixed>
   */
  private function botao(string $papel, string $direcao, mixed $rotulo, bool $desabilitado): array {
    return [
      '#type' => 'submit',
      '#name' => 'cadeia__' . $papel . '__' . $direcao,
      '#value' => $rotulo,
      '#limit_validation_errors' => [],
      '#submit' => ['::alterarEstado'],
      '#disabled' => $desabilitado,
      '#attributes' => ['aria-label' => (string) $rotulo . ' ' . $papel],
    ];
  }

  /**
   * Cadeia e laterais atuais: os do envio (ao mover), senão os salvos, senão o padrão de instalação.
   *
   * @param array<string, string> $papeis
   *
   * @return array{0: string[], 1: array<string, string>}
   */
  private function estadoAtual(FormStateInterface $form_state, array $papeis): array {
    $entrada = $form_state->getUserInput();
    $cadeia = isset($entrada['ordem']) ? $this->lerLista($entrada['ordem']) : NULL;
    $laterais = isset($entrada['laterais']) ? $this->lerMapa($entrada['laterais']) : NULL;
    if ($cadeia === NULL || $laterais === NULL) {
      $cadeia = $this->config(self::ESTADO)->get('cadeia');
      $laterais = $this->config(self::ESTADO)->get('laterais');
    }
    $existentes = array_values(array_diff(array_keys($papeis), self::FORA));
    if (!is_array($cadeia) || !is_array($laterais)) {
      $cadeia = ['gerente_loja', 'content_editor', 'lms_teacher', 'associado', 'estudante', 'email_confirmed', 'authenticated'];
      $laterais = ['wiki_contributor' => 'authenticated', 'lms_admin' => 'authenticated'];
    }
    // Papéis do site que ainda não estão em lugar nenhum entram como laterais presos ao autenticado.
    $cadeia = array_values(array_filter($cadeia, fn (string $p): bool => in_array($p, $existentes, TRUE) && !isset($laterais[$p])));
    foreach ($existentes as $papel) {
      if (!in_array($papel, $cadeia, TRUE) && !isset($laterais[$papel])) {
        $laterais[$papel] = 'authenticated';
      }
    }
    $laterais = array_filter($laterais, fn (string $pai, string $papel): bool => in_array($papel, $existentes, TRUE) && !in_array($papel, $cadeia, TRUE), ARRAY_FILTER_USE_BOTH);
    return [$cadeia, $laterais];
  }

  /**
   * @return string[]|null
   */
  private function lerLista(mixed $valor): ?array {
    if (is_array($valor)) {
      return array_values(array_map('strval', $valor));
    }
    $dados = is_string($valor) ? json_decode($valor, TRUE) : NULL;
    return is_array($dados) ? array_values(array_map('strval', $dados)) : NULL;
  }

  /**
   * @return array<string, string>|null
   */
  private function lerMapa(mixed $valor): ?array {
    if (is_array($valor)) {
      return array_map('strval', $valor);
    }
    $dados = is_string($valor) ? json_decode($valor, TRUE) : NULL;
    return is_array($dados) ? array_map('strval', $dados) : NULL;
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
