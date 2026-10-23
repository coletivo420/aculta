<?php

declare(strict_types=1);

namespace Drupal\aculta_wiki\Type;

use Drupal\aculta_mailer\Type\MailTypeInterface;
use Drupal\Core\StringTranslation\TranslationInterface;

/**
 * Email type for a private proposal decision sent to its confirmed author.
 */
final class WikiProposalOutcomeMailType implements MailTypeInterface {

  public const ID = 'aculta_wiki_proposal_outcome';

  public function __construct(
    private readonly TranslationInterface $translation,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function id(): string {
    return self::ID;
  }

  /**
   * {@inheritdoc}
   */
  public function variables(): array {
    return [
      'subject' => [
        'label' => (string) $this->translation->translate('Resumo da proposta'),
        'sample' => 'Correção sobre cultivo',
      ],
      'state' => [
        'label' => (string) $this->translation->translate('Resultado editorial'),
        'sample' => 'Precisa de ajustes',
      ],
      'decision_note' => [
        'label' => (string) $this->translation->translate('Retorno editorial'),
        'sample' => 'Inclua uma fonte independente para este dado.',
      ],
      'proposal_url' => [
        'label' => (string) $this->translation->translate('Acesso privado à proposta'),
        'sample' => 'https://wiki420.aculta.org/wiki/propostas/123',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function defaultSubject(): string {
    return (string) $this->translation->translate('Atualização da sua proposta para a Wiki420');
  }

  /**
   * {@inheritdoc}
   */
  public function defaultBody(): string {
    return implode("\n\n", [
      (string) $this->translation->translate('A proposta "{{ subject }}" recebeu um retorno editorial: {{ state }}.'),
      (string) $this->translation->translate('Retorno: {{ decision_note }}'),
      (string) $this->translation->translate('Acesse sua proposta em segurança depois de entrar na conta: {{ proposal_url }}'),
      (string) $this->translation->translate('Este e-mail não inclui o conteúdo sugerido nem informações privadas do verbete.'),
    ]);
  }

}
