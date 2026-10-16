<?php

declare(strict_types=1);

namespace Drupal\aculta_game\Form;

use Drupal\aculta_user\Badge\BadgeService;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\user\UserDataInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/** Avisos pessoais de conquistas dentro do drawer do Messenger. */
final class AchievementNoticesForm extends FormBase {

  public function __construct(
    private readonly BadgeService $badges,
    private readonly UserDataInterface $userData,
    private readonly AccountProxyInterface $account,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('aculta_user.badge_service'), $container->get('user.data'), $container->get('current_user'));
  }

  public function getFormId(): string {
    return 'aculta_game_achievement_notices';
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    if (!$this->account->isAuthenticated()) {
      throw new AccessDeniedHttpException();
    }
    $uid = (int) $this->account->id();
    $definitions = $this->badges->definitions();
    $ids = array_reverse(array_map('intval', (array) $this->userData->get('aculta_user', $uid, 'game_achievement_notice_queue')));
    $form['#tree'] = TRUE;
    $form['title'] = ['#type' => 'container', 'text' => ['#plain_text' => $this->t('Conquistas recentes')]];
    $count = 0;
    foreach ($ids as $awardId) {
      $award = $this->badges->loadAward($awardId);
      if ($award === NULL || (int) $award->get('uid')->target_id !== $uid || !$award->get('revogado_em')->isEmpty()) {
        continue;
      }
      $definition = $definitions[(string) $award->get('badge')->value] ?? NULL;
      if ($definition === NULL) {
        continue;
      }
      $form['notices'][$awardId] = [
        '#type' => 'container',
        'label' => ['#type' => 'container', 'text' => ['#plain_text' => $this->t('Você conquistou: @label', ['@label' => $definition['label']])]],
      ];
      if (!empty($definition['description'])) {
        $form['notices'][$awardId]['description'] = ['#type' => 'container', 'text' => ['#plain_text' => $definition['description']]];
      }
      $form['notices'][$awardId]['dismiss_' . $awardId] = [
        '#type' => 'submit',
        '#value' => $this->t('Dispensar aviso'),
        '#name' => 'dismiss_' . $awardId,
        '#award_id' => $awardId,
        '#limit_validation_errors' => [],
        '#submit' => ['::dismissOne'],
      ];
      $count++;
    }
    if ($count === 0) {
      return [];
    }
    $form['#cache']['contexts'] = ['user'];
    $form['#cache']['max-age'] = 0;
    return $form;
  }

  public function dismissOne(array &$form, FormStateInterface $form_state): void {
    if (!$this->account->isAuthenticated()) {
      throw new AccessDeniedHttpException();
    }
    $awardId = (int) ($form_state->getTriggeringElement()['#award_id'] ?? 0);
    if ($awardId < 1) {
      return;
    }
    $uid = (int) $this->account->id();
    $award = $this->badges->loadAward($awardId);
    if ($award === NULL || (int) $award->get('uid')->target_id !== $uid) {
      throw new AccessDeniedHttpException();
    }
    $pending = array_values(array_diff(array_map('intval', (array) $this->userData->get('aculta_user', $uid, 'game_achievement_notice_queue')), [$awardId]));
    $this->userData->set('aculta_user', $uid, 'game_achievement_notice_queue', $pending);
    $form_state->setRebuild();
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {}

}
