<?php

declare(strict_types=1);

namespace Drupal\aculta_game\Form;

use Drupal\aculta_user\Badge\AchievementDefinitions;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** Criação/edição administrativa de critérios predefinidos, sem código executável. */
final class AchievementDefinitionForm extends FormBase {

  public function __construct(
    private readonly AchievementDefinitions $definitions,
    private readonly LoggerInterface $audit,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('aculta_user.achievement_definitions'), $container->get('logger.factory')->get('aculta_game'));
  }

  public function getFormId(): string {
    return 'aculta_game_achievement_definition';
  }

  public function buildForm(array $form, FormStateInterface $form_state, ?string $conquista = NULL): array {
    $all = $this->definitions->all();
    if ($conquista !== NULL && !isset($all[$conquista])) {
      throw new NotFoundHttpException();
    }
    $definition = $all[$conquista] ?? [];
    $form_state->set('achievement_id', $conquista);
    $form['label'] = ['#type' => 'textfield', '#title' => $this->t('Nome da conquista'), '#required' => TRUE, '#maxlength' => 150, '#default_value' => $definition['label'] ?? ''];
    $form['id'] = ['#type' => 'textfield', '#title' => $this->t('Identificador'), '#description' => $this->t('Use de 3 a 64 letras minúsculas, números ou sublinhado, começando com letra. Depois de criado, o identificador é permanente.'), '#required' => TRUE, '#maxlength' => 64, '#disabled' => $conquista !== NULL, '#default_value' => $conquista ?? ''];
    $form['description'] = ['#type' => 'textarea', '#title' => $this->t('Descrição pública'), '#default_value' => $definition['description'] ?? '', '#maxlength' => 2000];
    $form['enabled'] = ['#type' => 'checkbox', '#title' => $this->t('Disponível para novas concessões'), '#description' => $this->t('Desativar não apaga nem oculta conquistas já concedidas.'), '#default_value' => $definition['enabled'] ?? TRUE];
    $options = [];
    foreach (AchievementDefinitions::TRIGGERS as $id => $label) {
      $options[$id] = $this->t($label);
    }
    $form['triggers'] = ['#type' => 'checkboxes', '#title' => $this->t('Formas de concessão'), '#options' => $options, '#required' => TRUE, '#default_value' => $definition['triggers'] ?? ['manual'], '#description' => $this->t('Basta uma das formas escolhidas. Cada pessoa recebe uma concessão ativa por conquista. Alterar critérios vale para fatos futuros, sem reavaliar o passado.')];
    $form['image'] = ['#type' => 'select', '#title' => $this->t('Imagem'), '#options' => ['' => $this->t('Sem imagem'), 'fundadore' => $this->t('Fundadore'), 'associado' => $this->t('Associado/a Antiproibicionista'), 'apoiador' => $this->t('Apoiadore')], '#default_value' => $definition['image'] ?? ''];
    $form['version'] = ['#plain_text' => $this->t('Versão atual do critério: @version. Mudanças nas formas de concessão avançam essa versão.', ['@version' => $definition['criterion_version'] ?? 1])];
    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = ['#type' => 'submit', '#value' => $this->t('Salvar conquista')];
    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $existing = $form_state->get('achievement_id');
    $id = $existing ?? trim((string) $form_state->getValue('id'));
    if (!preg_match('/^[a-z][a-z0-9_]{2,63}$/D', $id)) {
      $form_state->setErrorByName('id', $this->t('Informe um identificador válido.'));
    }
    if ($existing === NULL && isset($this->definitions->all()[$id])) {
      $form_state->setErrorByName('id', $this->t('Esse identificador já existe.'));
    }
    if (array_filter($form_state->getValue('triggers', [])) === []) {
      $form_state->setErrorByName('triggers', $this->t('Escolha pelo menos uma forma de concessão.'));
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $id = $form_state->get('achievement_id') ?? trim((string) $form_state->getValue('id'));
    $this->definitions->save($id, [
      'label' => $form_state->getValue('label'),
      'description' => $form_state->getValue('description'),
      'enabled' => (bool) $form_state->getValue('enabled'),
      'triggers' => array_values(array_filter($form_state->getValue('triggers', []))),
      'image' => $form_state->getValue('image'),
    ]);
    $this->audit->notice('Definição da conquista @id salva pela conta @actor.', ['@id' => $id, '@actor' => $this->currentUser()->id()]);
    $this->messenger()->addStatus($this->t('Conquista salva.'));
    $form_state->setRedirect('aculta_game.achievements');
  }

}
