<?php

declare(strict_types=1);

namespace Drupal\aculta_game\Form;

use Drupal\aculta_game\MissionProgress;
use Drupal\aculta_game\AchievementPresentation;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/** Controles iniciais do Game; não altera concessões nem inventa regras. */
final class MissionSettingsForm extends ConfigFormBase {

  public function __construct(
    ConfigFactoryInterface $configFactory,
    TypedConfigManagerInterface $typedConfigManager,
    private readonly MissionProgress $missions,
    private readonly AchievementPresentation $presentation,
  ) {
    parent::__construct($configFactory, $typedConfigManager);
  }

  public static function create(ContainerInterface $container): static {
    return new static($container->get('config.factory'), $container->get('config.typed'), $container->get('aculta_game.mission_progress'), $container->get('aculta_game.achievement_presentation'));
  }

  public function getFormId(): string {
    return 'aculta_game_mission_settings';
  }

  protected function getEditableConfigNames(): array {
    return ['aculta_game.missions'];
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['intro'] = ['#plain_text' => $this->t('Ative as missões oferecidas às pessoas participantes. Desativar uma missão não apaga conquistas ou altera seu histórico. Os critérios desta versão são fixos.')];
    $form['missions'] = ['#type' => 'container', '#tree' => TRUE];
    $form['catalog'] = $this->presentation->trophyCatalog();
    $form['catalog']['#weight'] = 10;
    foreach ($this->missions->definitions(FALSE) as $id => $definition) {
      $form['missions'][$id] = [
        '#type' => 'checkbox',
        '#title' => $this->t($definition['label']),
        '#description' => $this->t('@criterio Versão do critério: @versao.', ['@criterio' => $definition['description'], '@versao' => $definition['criterion_version']]),
        '#default_value' => !empty($definition['enabled']),
      ];
    }
    return parent::buildForm($form, $form_state);
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $config = $this->config('aculta_game.missions');
    foreach ($this->missions->definitions(FALSE) as $id => $definition) {
      $config->set('missions.' . $id . '.enabled', (bool) $form_state->getValue(['missions', $id]));
    }
    $config->save();
    parent::submitForm($form, $form_state);
  }

}
