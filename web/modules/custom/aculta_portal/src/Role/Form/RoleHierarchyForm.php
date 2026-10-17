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
 * Papéis e perfis (painel ACULTA). Reúne, em uma tela, a hierarquia de papéis e o vínculo de cada tipo de perfil aos papéis.
 *
 * - Hierarquia: edita o mapa do role_inheritance (cada papel herda dos papéis marcados; a herança é transitiva).
 *   Mostra a cadeia efetiva e recusa ciclo ou autorreferência antes de salvar.
 * - Tipos de perfil: cada tipo declara quais papéis o usam (aculta_portal.profile_roles). Não altera dados já
 *   armazenados nem o tipo 'customer', usado pelo checkout.
 * - Links: papéis, permissões, tipos de perfil e herança do contrib continuam nas telas do núcleo; daqui se chega a elas.
 */
final class RoleHierarchyForm extends ConfigFormBase {

  private const HIERARQUIA = 'role_inheritance.settings';

  private const PERFIS = 'aculta_portal.profile_roles';

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
    return [self::HIERARQUIA, self::PERFIS];
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['links'] = [
      '#type' => 'details',
      '#title' => $this->t('Telas do núcleo'),
      '#open' => FALSE,
      'lista' => [
        '#theme' => 'item_list',
        '#items' => [
          Link::fromTextAndUrl($this->t('Papéis (criar, editar e excluir papéis)'), Url::fromRoute('entity.user_role.collection'))->toRenderable(),
          Link::fromTextAndUrl($this->t('Permissões por papel'), Url::fromRoute('user.admin_permissions'))->toRenderable(),
          Link::fromTextAndUrl($this->t('Tipos de perfil (campos e exibição)'), Url::fromRoute('entity.profile_type.collection'))->toRenderable(),
          Link::fromTextAndUrl($this->t('Herança de papéis (tela do módulo)'), Url::fromRoute('role_inheritance.config_role_inheritance'))->toRenderable(),
        ],
      ],
    ];

    $mapa = $this->mapaHierarquia();
    $papeis = $this->papeis(TRUE);
    $form['hierarquia'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Hierarquia de papéis'),
      '#description' => $this->t('Marque, para cada papel, os papéis dos quais ele herda. A herança é transitiva: um papel recebe as permissões de tudo o que está abaixo dele. Papel sem marcação não herda nada.'),
    ];
    foreach ($papeis as $id => $rotulo) {
      $outros = array_diff_key($papeis, [$id => TRUE]);
      $efetivos = RoleHierarchy::efetivos($mapa, $id);
      $form['hierarquia']['papel_' . $id] = [
        '#type' => 'checkboxes',
        '#title' => $this->t('@papel herda de', ['@papel' => $rotulo]),
        '#options' => $outros,
        '#default_value' => array_values(array_intersect($mapa[$id] ?? [], array_keys($outros))),
        '#description' => $efetivos === []
          ? $this->t('Cadeia efetiva: nenhuma.')
          : $this->t('Cadeia efetiva: @lista', ['@lista' => implode(', ', array_map(fn (string $e): string => $papeis[$e] ?? $e, $efetivos))]),
      ];
    }

    $vinculos = $this->vinculosPerfis();
    $form['perfis'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Tipos de perfil e papéis'),
      '#description' => $this->t('Cada tipo de perfil indica quais papéis o usam. O vínculo é registrado para a administração; não move nem apaga dados de perfis existentes.'),
    ];
    foreach ($this->tiposDePerfil() as $id => $rotulo) {
      $form['perfis']['perfil_' . $id] = [
        '#type' => 'checkboxes',
        '#title' => $this->t('@perfil é usado pelos papéis', ['@perfil' => $rotulo]),
        '#options' => $this->papeis(FALSE),
        '#default_value' => array_values(array_intersect($vinculos[$id] ?? [], array_keys($this->papeis(FALSE)))),
      ];
    }
    return parent::buildForm($form, $form_state);
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);
    if (RoleHierarchy::temCiclo($this->mapaDoFormulario($form_state))) {
      $form_state->setErrorByName('hierarquia', $this->t('A hierarquia tem ciclo ou um papel herda de si mesmo. Corrija antes de salvar.'));
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->config(self::HIERARQUIA)->set('role_map', $this->mapaDoFormulario($form_state))->save();
    $this->config(self::PERFIS)->set('vinculos', $this->vinculosDoFormulario($form_state))->save();
    $this->messenger()->addStatus($this->t('Papéis e perfis salvos.'));
    parent::submitForm($form, $form_state);
  }

  /**
   * Papéis do site. Anônimo entra só na hierarquia (é papel pai de administrator); não é atribuído a perfis.
   *
   * @return array<string, string>
   */
  private function papeis(bool $comAnonimo): array {
    $papeis = [];
    foreach ($this->entityTypeManager->getStorage('user_role')->loadMultiple() as $id => $papel) {
      if ($id !== 'anonymous' || $comAnonimo) {
        $papeis[$id] = (string) $papel->label();
      }
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

  /**
   * @return array<string, string[]>
   */
  private function mapaHierarquia(): array {
    $mapa = $this->config(self::HIERARQUIA)->get('role_map') ?? [];
    return is_array($mapa) ? $mapa : [];
  }

  /**
   * @return array<string, string[]>
   */
  private function vinculosPerfis(): array {
    $vinculos = $this->config(self::PERFIS)->get('vinculos') ?? [];
    return is_array($vinculos) ? $vinculos : [];
  }

  /**
   * @return array<string, string[]>
   */
  private function mapaDoFormulario(FormStateInterface $form_state): array {
    $mapa = [];
    foreach (array_keys($this->papeis(TRUE)) as $id) {
      $marcados = $this->marcados($form_state->getValue('papel_' . $id));
      if ($marcados !== []) {
        $mapa[$id] = $marcados;
      }
    }
    return $mapa;
  }

  /**
   * @return array<string, string[]>
   */
  private function vinculosDoFormulario(FormStateInterface $form_state): array {
    $vinculos = [];
    foreach (array_keys($this->tiposDePerfil()) as $id) {
      $marcados = $this->marcados($form_state->getValue('perfil_' . $id));
      if ($marcados !== []) {
        $vinculos[$id] = $marcados;
      }
    }
    return $vinculos;
  }

  /**
   * Valores marcados de um conjunto de checkboxes (valor não marcado vem como 0).
   *
   * @return string[]
   */
  private function marcados(mixed $valores): array {
    return array_values(array_filter((array) $valores, static fn ($v): bool => is_string($v) && $v !== ''));
  }

}
