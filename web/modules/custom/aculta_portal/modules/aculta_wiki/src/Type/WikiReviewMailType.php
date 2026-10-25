<?php

declare(strict_types=1);

namespace Drupal\aculta_wiki\Type;

use Drupal\aculta_mailer\Type\MailTypeInterface;
use Drupal\Core\StringTranslation\TranslationInterface;

/**
 * Aviso de que um verbete entrou em revisão editorial.
 *
 * O contexto traz só o título do verbete, o estado e o link de edição: o nome
 * de quem editou fica de fora de propósito
 * (ver docs/compliance/LGPD-INVENTARIO.md).
 * O tipo não consulta entidades nem rotas; recebe os valores prontos.
 */
final class WikiReviewMailType implements MailTypeInterface {

  public const ID = 'aculta_wiki_review';

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
      'verbete' => [
        'label' => (string) $this->translation->translate('Título do verbete'),
        'sample' => 'Cannabis medicinal no Brasil',
      ],
      'estado' => [
        'label' => (string) $this->translation->translate('Estado do verbete'),
        'sample' => 'Em revisão',
      ],
      'link' => [
        'label' => (string) $this->translation->translate('Link de edição do verbete'),
        'sample' => 'https://wiki420.aculta.org/node/123/edit',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function defaultSubject(): string {
    return (string) $this->translation->translate('Verbete aguardando revisão: {{ verbete }}');
  }

  /**
   * {@inheritdoc}
   */
  public function defaultBody(): string {
    $partes = [
      (string) $this->translation->translate('O verbete "{{ verbete }}" entrou em revisão editorial na Wiki420.'),
      (string) $this->translation->translate("Estado: {{ estado }}\nLink: {{ link }}"),
      (string) $this->translation->translate('Este aviso vai somente às caixas editoriais cadastradas. Ele não traz o nome de quem editou.'),
    ];
    return implode("\n\n", $partes);
  }

}
