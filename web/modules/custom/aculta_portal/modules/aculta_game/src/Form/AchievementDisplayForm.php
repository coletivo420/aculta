<?php

declare(strict_types=1);

namespace Drupal\aculta_game\Form;

use Drupal\aculta_game\AchievementPresentation;
use Drupal\aculta_user\Badge\AchievementDefinitions;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Escolhas da própria pessoa dentro de Minha Conta > Atividade > Conquistas.
 */
final class AchievementDisplayForm extends FormBase {

  public function __construct(
    protected readonly AchievementPresentation $presentation,
    protected readonly AccountProxyInterface $account,
  ) {}

  /**
   * Create the display preferences form from Drupal services.
   */
  public static function create(ContainerInterface $container): static {
    return new static($container->get('aculta_game.achievement_presentation'), $container->get('current_user'));
  }

  /**
   * Return the stable form identifier.
   */
  public function getFormId(): string {
    return 'aculta_game_achievement_display';
  }

  /**
   * Build the current account achievement display preferences.
   *
   * @param array<string, mixed> $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array<string, mixed>
   *   The form structure.
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    if (!$this->account->isAuthenticated()) {
      throw new AccessDeniedHttpException();
    }
    /** @var array{hide_all?: bool, email_notifications?: bool, items?: array<string, array{hidden?: bool, highlight?: bool, weight?: int}>} $preferences */
    $preferences = $this->presentation->preferences((int) $this->account->id());
    /** @var array<string, bool> $earned */
    $earned = $this->presentation->earnedIds((int) $this->account->id());
    $form['intro'] = ['#plain_text' => $this->t('Suas conquistas são públicas por padrão. Escolha quais mostrar e destacar. Um perfil privado mantém todas privadas. Os detalhes de cursos e pedidos continuam privados.')];
    $form['hide_all'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Ocultar todas as minhas conquistas em público'),
      '#default_value' => !empty($preferences['hide_all']),
    ];
    $form['email_notifications'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Receber um e-mail quando conquistar algo'),
      '#description' => $this->t('O aviso dentro do portal continua disponível mesmo se você desativar o e-mail.'),
      '#default_value' => !empty($preferences['email_notifications']),
    ];
    $form['items'] = ['#type' => 'container', '#tree' => TRUE];
    /** @var array<string, array{label: string|\Drupal\Core\StringTranslation\TranslatableMarkup, description?: string, triggers?: array<int|string, string>, enabled?: bool, image_uuid?: string, image?: string}> $definitions */
    $definitions = $this->presentation->definitions();
    foreach ($definitions as $id => $definition) {
      $idStr = (string) $id;
      $choice = $preferences['items'][$idStr] ?? [];
      $form['items'][$idStr] = [
        '#type' => 'details',
        '#title' => (string) $definition['label'],
        '#open' => TRUE,
      ];
      $form['items'][$idStr]['image'] = $this->presentation->imageForDefinition($definition, TRUE, $idStr);
      $form['items'][$idStr]['status'] = [
        '#type' => 'container',
        'text' => [
          '#plain_text' => isset($earned[$idStr])
            ? $this->t('Conquistada')
            : $this->t('Ainda não conquistada'),
        ],
      ];
      if (!empty($definition['description'])) {
        $form['items'][$idStr]['description'] = [
          '#type' => 'container',
          'text' => ['#plain_text' => (string) $definition['description']],
        ];
      }
      $triggers = is_array($definition['triggers'] ?? NULL) ? $definition['triggers'] : [];
      $criteria = array_map(static fn (string $trigger): string => AchievementDefinitions::TRIGGERS[$trigger] ?? $trigger, $triggers);
      $form['items'][$idStr]['criteria'] = [
        '#type' => 'container',
        'label' => ['#plain_text' => $this->t('Como conquistar')],
        'text' => ['#plain_text' => implode(' · ', $criteria)],
      ];
      if (empty($definition['enabled'])) {
        $form['items'][$idStr]['unavailable'] = [
          '#type' => 'container',
          'text' => ['#plain_text' => $this->t('Indisponível para novas concessões')],
        ];
      }
      $form['items'][$idStr]['show'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Exibir quando conquistada'),
        '#default_value' => empty($choice['hidden']),
      ];
      $form['items'][$idStr]['highlight'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Exibir em destaque'),
        '#default_value' => !empty($choice['highlight']),
      ];
      $form['items'][$idStr]['weight'] = [
        '#type' => 'number',
        '#title' => $this->t('Ordem de exibição'),
        '#description' => $this->t('Números menores aparecem primeiro. Os destaques vêm antes dos demais.'),
        '#min' => -50,
        '#max' => 50,
        '#step' => 1,
        '#default_value' => (int) ($choice['weight'] ?? 0),
      ];
    }
    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = ['#type' => 'submit', '#value' => $this->t('Salvar exibição das conquistas')];
    if (!isset($form['#cache']) || !is_array($form['#cache'])) {
      $form['#cache'] = [];
    }
    $form['#cache']['contexts'] = ['user'];
    $form['#cache']['max-age'] = 0;
    return $form;
  }

  /**
   * Save the current account achievement display preferences.
   *
   * @param array<array-key, mixed> $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    if (!$this->account->isAuthenticated()) {
      throw new AccessDeniedHttpException();
    }
    $items = [];
    /** @var array<string, mixed> $definitions */
    $definitions = $this->presentation->definitions();
    foreach ($definitions as $id => $definition) {
      $idStr = (string) $id;
      $rawValue = $form_state->getValue(['items', $idStr], []);
      $row = is_array($rawValue) ? $rawValue : [];
      $items[$idStr] = [
        'hidden' => empty($row['show']),
        'highlight' => !empty($row['highlight']),
        'weight' => isset($row['weight']) && is_numeric($row['weight']) ? (int) $row['weight'] : 0,
      ];
    }
    $this->presentation->savePreferences((int) $this->account->id(), [
      'hide_all' => (bool) $form_state->getValue('hide_all'),
      'email_notifications' => (bool) $form_state->getValue('email_notifications'),
      'items' => $items,
    ]);
    $this->messenger()->addStatus($this->t('Exibição das conquistas salva.'));
    $form_state->setRedirect('aculta_user.atividade', ['aba' => 'conquistas']);
  }

}
