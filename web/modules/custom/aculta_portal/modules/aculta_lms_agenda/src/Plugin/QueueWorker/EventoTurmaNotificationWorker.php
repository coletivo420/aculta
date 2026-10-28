<?php

declare(strict_types=1);

namespace Drupal\aculta_lms_agenda\Plugin\QueueWorker;

use Drupal\aculta_mailer\Api\AcultaMailerInterface;
use Drupal\aculta_mailer\Message\MailMessage;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\Attribute\QueueWorker;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\group\Entity\GroupMembership;
use Drupal\user\UserDataInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Envia o aviso "novo evento na turma" aos matriculados, uma única vez por evento e por pessoa.
 *
 * Item da fila: ['event_id' => int]. Mesma regra de matrícula do painel: membro da turma publicada.
 */
#[QueueWorker(
  id: 'aculta_lms_agenda_evento_turma',
  title: new TranslatableMarkup('Avisos de eventos de turma por e-mail'),
  cron: ['time' => 30],
)]
final class EventoTurmaNotificationWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * Construtor.
   */
  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
    private readonly EntityTypeManagerInterface $entidades,
    private readonly UserDataInterface $dadosUsuario,
    private readonly AcultaMailerInterface $mailer,
    private readonly UuidInterface $uuid,
    private readonly DomainPurposeManager $purpose,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('user.data'),
      $container->get('aculta_mailer.mailer'),
      $container->get('uuid'),
      $container->get('aculta_portal.domain_purpose'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data): void {
    $evento = $this->entidades->getStorage('aculta_event')->load((int) ($data['event_id'] ?? 0));
    $turma = $evento?->get('field_turma')->entity;
    if ($evento === NULL || $turma === NULL || (string) $evento->get('field_status')->value !== 'publicado') {
      return;
    }
    $url = $this->purpose->pathUrl('eventos', '/eventos/' . (string) $evento->get('field_slug')->value);
    if ($url === NULL) {
      throw new \RuntimeException('O endereço do ambiente para os eventos não está disponível.');
    }
    foreach ($this->matriculados($turma) as $aluno) {
      $enviados = array_map('intval', (array) $this->dadosUsuario->get('aculta_lms_agenda', (int) $aluno->id(), 'evento_turma_enviado'));
      if (in_array((int) $evento->id(), $enviados, TRUE) || $aluno->getEmail() === NULL || $aluno->getEmail() === '') {
        continue;
      }
      $mensagem = new MailMessage(
        'aculta_evento_turma',
        (string) $aluno->getEmail(),
        $aluno->getPreferredLangcode() ?: 'und',
        $this->contexto((string) $evento->label(), (string) $turma->label(), $url->toString()),
        $this->uuid->generate(),
      );
      if (!$this->mailer->send($mensagem)) {
        throw new \RuntimeException('O sistema de e-mail não confirmou o aviso de evento da turma.');
      }
      $enviados[] = (int) $evento->id();
      $this->dadosUsuario->set('aculta_lms_agenda', (int) $aluno->id(), 'evento_turma_enviado', array_values(array_unique($enviados)));
    }
  }

  /**
   * Contexto do modelo: só as três variáveis declaradas no tipo.
   *
   * @return array<string, string>
   */
  public static function contexto(string $titulo, string $turma, string $url): array {
    return ['titulo' => $titulo, 'turma' => $turma, 'url' => $url];
  }

  /**
   * Usuários ativos matriculados na turma (membros, com conta ativa).
   *
   * @return \Drupal\user\UserInterface[]
   */
  private function matriculados(object $turma): array {
    $contas = [];
    foreach (GroupMembership::loadByGroup($turma) as $membro) {
      $conta = $membro->getEntity();
      if ($conta instanceof \Drupal\user\UserInterface && $conta->isActive()) {
        $contas[$conta->id()] = $conta;
      }
    }
    return array_values($contas);
  }

}
