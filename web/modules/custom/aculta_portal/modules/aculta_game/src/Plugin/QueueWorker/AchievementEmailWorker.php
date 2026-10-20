<?php

declare(strict_types=1);

namespace Drupal\aculta_game\Plugin\QueueWorker;

use Drupal\aculta_mailer\Api\AcultaMailerInterface;
use Drupal\aculta_mailer\Message\MailMessage;
use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\Attribute\QueueWorker;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\user\UserDataInterface;
use Drupal\aculta_user\Badge\BadgeService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/** Delivers opted-in email notices after the award has been committed. */
#[QueueWorker(
  id: 'aculta_game_achievement_email',
  title: new TranslatableMarkup('Avisos de conquistas por e-mail'),
  cron: ['time' => 30],
)]
final class AchievementEmailWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
    private readonly EntityTypeManagerInterface $entities,
    private readonly UserDataInterface $userData,
    private readonly AcultaMailerInterface $mailer,
    private readonly UuidInterface $uuid,
    private readonly DomainPurposeManager $domainPurpose,
    private readonly LockBackendInterface $lock,
    private readonly BadgeService $badges,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static($configuration, $plugin_id, $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('user.data'),
      $container->get('aculta_mailer.mailer'),
      $container->get('uuid'),
      $container->get('aculta_portal.domain_purpose'),
      $container->get('lock.persistent'),
      $container->get('aculta_user.badge_service'),
    );
  }

  public function processItem($data): void {
    $awardId = is_array($data) ? filter_var($data['award_id'] ?? NULL, FILTER_VALIDATE_INT) : FALSE;
    if ($awardId === FALSE || $awardId < 1) {
      return;
    }
    $lockName = 'aculta_game:achievement_email:' . $awardId;
    if (!$this->lock->acquire($lockName, 60.0)) {
      throw new \RuntimeException('Aviso de conquista já está em processamento.');
    }
    try {
      $award = $this->entities->getStorage('aculta_badge_award')->load($awardId);
      if ($award === NULL || !$award->get('revogado_em')->isEmpty()) {
        return;
      }
      $uid = (int) $award->get('uid')->target_id;
      $account = $this->entities->getStorage('user')->load($uid);
      if ($account === NULL || !$account->isActive() || !$account->getEmail() || !$this->emailEnabled($uid)) {
        return;
      }
      $sent = array_map('intval', (array) $this->userData->get('aculta_user', $uid, 'game_achievement_email_sent'));
      if (in_array($awardId, $sent, TRUE)) {
        return;
      }
      $definition = $this->badges->definitions()[(string) $award->get('badge')->value] ?? NULL;
      if ($definition === NULL) {
        return;
      }
      $url = $this->domainPurpose->routeUrl('account', 'aculta_user.atividade', ['aba' => 'conquistas']);
      if ($url === NULL) {
        throw new \RuntimeException('O endereço do ambiente para a conta não está disponível.');
      }
      $message = new MailMessage(
        'achievement_earned',
        (string) $account->getEmail(),
        $account->getPreferredLangcode() ?: 'und',
        ['label' => $definition['label'], 'url' => $url->toString()],
        $this->uuid->generate(),
      );
      if (!$this->mailer->send($message)) {
        throw new \RuntimeException('O sistema de e-mail não confirmou o envio do aviso de conquista.');
      }
      $sent[] = $awardId;
      $this->userData->set('aculta_user', $uid, 'game_achievement_email_sent', array_values(array_unique($sent)));
    }
    finally {
      $this->lock->release($lockName);
    }
  }

  private function emailEnabled(int $uid): bool {
    $preferences = $this->userData->get('aculta_user', $uid, 'game_achievement_display');
    return !is_array($preferences) || !array_key_exists('email_notifications', $preferences) || (bool) $preferences['email_notifications'];
  }

}
