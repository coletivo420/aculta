<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Role\Form;

use Drupal\aculta_portal\Role\RoleHierarchy;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Hierarquia de papéis (painel ACULTA). Edita o mapa do role_inheritance: cada papel herda dos papéis marcados.
 *
 * A herança é transitiva no role_inheritance. Por isso o formulário mostra a cadeia efetiva de cada papel e recusa
 * autorreferência e ciclo antes de salvar. Grava só a configuração role_inheritance.settings (não cria nem altera papéis).
 */
final class RoleHierarchyForm extends ConfigFormBase {

  private const CONFIG = 'role_inheritance.settings';

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
    return [self::CONFIG];
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $mapa = $this->mapaAtual();
    $papeis = $this->papeis();
    $form['intro'] = [
      '#markup' => '<p>' . $this->t('Marque, para cada papel, os papéis dos quais ele herda. A herança é transitiva: um papel recebe as permissões de tudo o que está abaixo dele na cadeia. Papel sem marcação não herda nada.') . '</p>',
    ];
    foreach ($papeis as $id => $rotulo) {
      $outros = array_diff_key($papeis, [$id => TRUE]);
      $efetivos = RoleHierarchy::efetivos($mapa, $id);
      $form['papel_' . $id] = [
        '#type' => 'checkboxes',
        '#title' => $this->t('Herda de: @papel', ['@papel' => $rotulo]),
        '#options' => $outros,
        '#default_value' => array_values(array_intersect($mapa[$id] ?? [], array_keys($outros))),
        '#description' => $efetivos === []
          ? $this->t('Cadeia efetiva: nenhuma.')
          : $this->t('Cadeia efetiva: @lista', ['@lista' => implode(', ', array_map(fn (string $e): string => $papeis[$e] ?? $e, $efetivos))]),
      ];
    }
    return parent::buildForm($form, $form_state);
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);
    $novo = $this->mapaDoFormulario($form_state);
    if (RoleHierarchy::temCiclo($novo)) {
      $form_state->setErrorByName('', $this->t('A hierarquia tem ciclo ou um papel herda de si mesmo. Corrija antes de salvar.'));
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->config(self::CONFIG)->set('role_map', $this->mapaDoFormulario($form_state))->save();
    $this->messenger()->addStatus($this->t('Hierarquia de papéis salva.'));
    parent::submitForm($form, $form_state);
  }

  /**
   * Papéis do site, exceto anônimo (que não é atribuído a contas e fica fora do editor).
   *
   * @return array<string, string>
   */
  private function papeis(): array {
    $papeis = [];
    foreach ($this->entityTypeManager->getStorage('user_role')->loadMultiple() as $id => $papel) {
      if ($id !== 'anonymous') {
        $papeis[$id] = (string) $papel->label();
      }
    }
    return $papeis;
  }

  /**
   * @return array<string, string[]>
   */
  private function mapaAtual(): array {
    $mapa = $this->config(self::CONFIG)->get('role_map') ?? [];
    return is_array($mapa) ? $mapa : [];
  }

  /**
   * @return array<string, string[]>
   */
  private function mapaDoFormulario(FormStateInterface $form_state): array {
    $mapa = [];
    foreach (array_keys($this->papeis()) as $id) {
      $marcados = array_values(array_filter((array) $form_state->getValue('papel_' . $id), static fn ($v): bool => $v !== 0 && $v !== '0' && $v !== NULL));
      if ($marcados !== []) {
        $mapa[$id] = $marcados;
      }
    }
    return $mapa;
  }

}
