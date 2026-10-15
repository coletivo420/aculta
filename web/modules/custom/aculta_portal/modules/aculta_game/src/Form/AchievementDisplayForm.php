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

/** Escolhas da própria pessoa dentro de Minha Conta > Atividade > Conquistas. */
final class AchievementDisplayForm extends FormBase {

  public function __construct(
    private readonly AchievementPresentation $presentation,
    private readonly AccountProxyInterface $account,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('aculta_game.achievement_presentation'), $container->get('current_user'));
  }

  public function getFormId(): string {
    return 'aculta_game_achievement_display';
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    if (!$this->account->isAuthenticated()) {
      throw new AccessDeniedHttpException();
    }
    $preferences = $this->presentation->preferences((int) $this->account->id());
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
    foreach ($this->presentation->definitions() as $id => $definition) {
      $choice = $preferences['items'][$id] ?? [];
      $form['items'][$id] = ['#type' => 'details', '#title' => (string) $definition['label'], '#open' => TRUE];
      $form['items'][$id]['image'] = $this->presentation->imageForDefinition($definition);
      $form['items'][$id]['status'] = ['#type' => 'container', 'text' => ['#plain_text' => isset($earned[$id]) ? $this->t('Conquistada') : $this->t('Ainda não conquistada')]];
      if (!empty($definition['description'])) {
        $form['items'][$id]['description'] = ['#type' => 'container', 'text' => ['#plain_text' => $definition['description']]];
      }
      $criteria = array_map(static fn (string $trigger): string => AchievementDefinitions::TRIGGERS[$trigger] ?? $trigger, $definition['triggers'] ?? []);
      $form['items'][$id]['criteria'] = ['#type' => 'container', 'label' => ['#plain_text' => $this->t('Como conquistar')], 'text' => ['#plain_text' => implode(' · ', $criteria)]];
      if (empty($definition['enabled'])) {
        $form['items'][$id]['unavailable'] = ['#type' => 'container', 'text' => ['#plain_text' => $this->t('Indisponível para novas concessões')]];
      }
      $form['items'][$id]['show'] = ['#type' => 'checkbox', '#title' => $this->t('Exibir quando conquistada'), '#default_value' => empty($choice['hidden'])];
      $form['items'][$id]['highlight'] = ['#type' => 'checkbox', '#title' => $this->t('Exibir em destaque'), '#default_value' => !empty($choice['highlight'])];
      $form['items'][$id]['weight'] = ['#type' => 'number', '#title' => $this->t('Ordem de exibição'), '#description' => $this->t('Números menores aparecem primeiro. Os destaques vêm antes dos demais.'), '#min' => -50, '#max' => 50, '#step' => 1, '#default_value' => (int) ($choice['weight'] ?? 0)];
    }
    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = ['#type' => 'submit', '#value' => $this->t('Salvar exibição das conquistas')];
    $form['#cache']['contexts'] = ['user'];
    $form['#cache']['max-age'] = 0;
    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    if (!$this->account->isAuthenticated()) {
      throw new AccessDeniedHttpException();
    }
    $items = [];
    foreach ($this->presentation->definitions() as $id => $definition) {
      $row = $form_state->getValue(['items', $id], []);
      $items[$id] = ['hidden' => empty($row['show']), 'highlight' => !empty($row['highlight']), 'weight' => (int) ($row['weight'] ?? 0)];
    }
    $this->presentation->savePreferences((int) $this->account->id(), ['hide_all' => (bool) $form_state->getValue('hide_all'), 'email_notifications' => (bool) $form_state->getValue('email_notifications'), 'items' => $items]);
    $this->messenger()->addStatus($this->t('Exibição das conquistas salva.'));
    $form_state->setRedirect('aculta_user.atividade', ['aba' => 'conquistas']);
  }

}
