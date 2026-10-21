<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Mail;

use Drupal\aculta_messenger\Aviso\AvisosResumo;
use Drupal\aculta_messenger\Entity\Aviso;
use Drupal\aculta_mailer\Type\MailTypeInterface;
use Drupal\Core\StringTranslation\TranslationInterface;

/**
 * Resumo diário do site por e-mail (ACULTA Messenger, F5.2).
 *
 * O tipo declara o texto; o consumidor monta a variável `resumo`.
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
  public function variables(): array {
    return [
      'resumo' => [
        'label' => (string) $this->translation->translate('Linhas do resumo diário de avisos'),
        'sample' => 'Respostas novas em "Tópico de exemplo": 2',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function defaultSubject(): string {
    return (string) $this->translation->translate('Resumo diário de avisos');
  }

  /**
   * {@inheritdoc}
   */
  public function defaultBody(): string {
    $partes = [
      (string) $this->translation->translate('Você tem novidades nos seus avisos:'),
      '{{ resumo }}',
      (string) $this->translation->translate('Para deixar de receber este resumo, altere a opção em Minha conta > Privacidade.'),
    ];
    return implode("\n\n", $partes);
  }

  /**
   * Formata linhas do resumo, seja tópico ou aviso de outro módulo.
   *
   * @param array $linhas
   *   Linhas do resumo.
   */
  public static function formatLinhas(array $linhas): string {
    $rotulos = [
      Aviso::TIPO_RESPOSTA => 'Respostas novas',
      Aviso::TIPO_ATUALIZACAO => 'Atualizações',
    ];
    $texto = [];
    foreach ($linhas as $linha) {
      $tipo = (string) ($linha['tipo'] ?? '');
      if ($tipo === 'aprendizagem') {
        $texto[] = (string) ($linha['titulo'] ?? '');
        continue;
      }
      $texto[] = ($rotulos[$tipo] ?? $tipo) . ' em "' . (string) ($linha['titulo'] ?? '') . '": ' . (int) ($linha['quantidade'] ?? 0);
    }
    return implode("\n", $texto);
  }

}
