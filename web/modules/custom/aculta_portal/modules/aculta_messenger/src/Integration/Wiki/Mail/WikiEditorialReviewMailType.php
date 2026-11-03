<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Integration\Wiki\Mail;

use Drupal\aculta_mailer\Type\MailTypeInterface;
use Drupal\Core\StringTranslation\TranslationInterface;

/**
 * Administrative email for Wiki editorials when an entry enters review.
 *
 * The public type ID is the historical `aculta_wiki_review`, kept so existing
 * templates and delivery history continue to resolve after the Wiki stopped
 * owning the mail type.
 */
final class WikiEditorialReviewMailType implements MailTypeInterface {

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
        'sample' => 'https://example.com/node/123/edit',
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
