<?php

declare(strict_types=1);

namespace Drupal\aculta_mailer\Type;

use Drupal\Core\StringTranslation\TranslationInterface;

/**
 * Alerta de envio para o administrador (falha de envio ou endereço que devolveu a mensagem).
 *
 * O detalhe completo do erro aparece aqui de propósito: o destinatário é o administrador, não o usuário final.
 */
final class AdminAlertMailType implements MailTypeInterface {

  public const ID = 'aculta_admin_alert';

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
      'evento' => [
        'label' => (string) $this->translation->translate('Evento'),
        'sample' => 'Falha de envio',
      ],
      'tipo' => [
        'label' => (string) $this->translation->translate('Tipo de e-mail'),
        'sample' => 'registration_existing',
      ],
      'transporte' => [
        'label' => (string) $this->translation->translate('Transporte'),
        'sample' => 'smtp',
      ],
      'codigo' => [
        'label' => (string) $this->translation->translate('Código'),
        'sample' => 'smtp_554',
      ],
      'correlacao' => [
        'label' => (string) $this->translation->translate('Correlação'),
        'sample' => '3f2504e0-4f89-41d3-9a0c-0305e82c3301',
      ],
      'detalhe' => [
        'label' => (string) $this->translation->translate('Texto completo (só administrador)'),
        'sample' => '554 5.7.1 Recusado',
      ],
      'suprimidos' => [
        'label' => (string) $this->translation->translate('Alertas suprimidos desde o último'),
        'sample' => '0',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function defaultSubject(): string {
    return (string) $this->translation->translate('Alerta de envio de e-mail do ACULTA');
  }

  /**
   * {@inheritdoc}
   */
  public function defaultBody(): string {
    $partes = [
      (string) $this->translation->translate('Um envio de e-mail falhou ou um endereço devolveu a mensagem.'),
      (string) $this->translation->translate('Evento: {{ evento }}') . "\n" . (string) $this->translation->translate('Tipo: {{ tipo }}') . "\n" . (string) $this->translation->translate('Transporte: {{ transporte }}') . "\n" . (string) $this->translation->translate('Código: {{ codigo }}') . "\n" . (string) $this->translation->translate('Correlação: {{ correlacao }}'),
      (string) $this->translation->translate('Texto completo (só administrador): {{ detalhe }}'),
      (string) $this->translation->translate('Alertas suprimidos desde o último envio deste alerta: {{ suprimidos }}. Os alertas se repetem no máximo a cada 5 minutos; o registro completo fica no relatório de falhas.'),
    ];
    return implode("\n\n", $partes);
  }

}
