<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Mail;

use Drupal\aculta_messenger\Aviso\AvisosResumo;
use Drupal\aculta_messenger\Entity\Aviso;
use Drupal\aculta_mailer\Type\MailTypeInterface;
use Drupal\Core\StringTranslation\TranslationInterface;

/**
 * Resumo diário de avisos do Fórum por e-mail (ACULTA Messenger, F5.2).
 *
 * Recebe `linhas`: uma linha por tópico, com `tipo`, `titulo` e `quantidade` de novidades.
 */
final class AvisosResumoMailType implements MailTypeInterface {

  public function __construct(
    private readonly TranslationInterface $translation,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function id(): string {
    return AvisosResumo::CHAVE_MAIL;
  }

  /**
   * {@inheritdoc}
   */
  public function subject(array $context): string {
    return (string) $this->translation->translate('Resumo de avisos do Fórum');
  }

  /**
   * {@inheritdoc}
   */
  public function body(array $context): string {
    $rotulos = [
      Aviso::TIPO_RESPOSTA => 'Respostas novas',
      Aviso::TIPO_ATUALIZACAO => 'Atualizações',
    ];
    $partes = [(string) $this->translation->translate('Você tem novidades nos tópicos que segue:')];
    foreach ($context['linhas'] ?? [] as $linha) {
      $tipo = (string) ($linha['tipo'] ?? '');
      $partes[] = ($rotulos[$tipo] ?? $tipo) . ' em "' . (string) ($linha['titulo'] ?? '') . '": ' . (int) ($linha['quantidade'] ?? 0);
    }
    $partes[] = (string) $this->translation->translate('Para deixar de receber este resumo, altere a opção em Minha conta > Privacidade.');
    return implode("\n\n", $partes);
  }

}
