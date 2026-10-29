<?php

declare(strict_types=1);

namespace Drupal\aculta_ugc\Mail;

use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\aculta_mailer\Type\MailTypeInterface;

/**
 * Aviso de nova denúncia aos moderadores do purpose (ARQ-06B).
 *
 * Privacidade (D-03): nenhuma variável identifica o denunciante. O aviso traz
 * só o purpose, a referência do conteúdo, o motivo padronizado e a referência
 * interna da denúncia. Requer o submódulo opt-in `aculta_mailer`; sem ele o
 * serviço não é instanciado.
 */
final class UgcReportNoticeMailType implements MailTypeInterface {

  public const ID = 'aculta_ugc_report_notice';

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
      'purpose' => [
        'label' => (string) $this->translation->translate('Área (purpose)'),
        'sample' => 'forum',
      ],
      'tipo_entidade' => [
        'label' => (string) $this->translation->translate('Tipo do conteúdo denunciado'),
        'sample' => 'node',
      ],
      'id_entidade' => [
        'label' => (string) $this->translation->translate('Identificador do conteúdo denunciado'),
        'sample' => '42',
      ],
      'motivo' => [
        'label' => (string) $this->translation->translate('Motivo informado'),
        'sample' => 'spam',
      ],
      'referencia' => [
        'label' => (string) $this->translation->translate('Referência interna da denúncia'),
        'sample' => '3f2504e0-4f89-41d3-9a0c-0305e82c3301',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function defaultSubject(): string {
    return (string) $this->translation->translate('Nova denúncia em {{ purpose }}');
  }

  /**
   * {@inheritdoc}
   */
  public function defaultBody(): string {
    return implode("\n\n", [
      (string) $this->translation->translate('Uma nova denúncia aguarda análise da moderação.'),
      (string) $this->translation->translate('Área: {{ purpose }}') . "\n"
      . (string) $this->translation->translate('Conteúdo: {{ tipo_entidade }} {{ id_entidade }}') . "\n"
      . (string) $this->translation->translate('Motivo: {{ motivo }}') . "\n"
      . (string) $this->translation->translate('Referência: {{ referencia }}'),
      (string) $this->translation->translate('Abra a fila de moderação para decidir. A identidade de quem denunciou não é informada.'),
    ]);
  }

}
