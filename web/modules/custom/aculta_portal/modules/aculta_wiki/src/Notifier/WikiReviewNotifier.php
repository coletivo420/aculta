<?php

declare(strict_types=1);

namespace Drupal\aculta_wiki\Notifier;

use Drupal\aculta_mailer\Message\MailMessage;
use Drupal\aculta_wiki\Type\WikiReviewMailType;
use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\node\NodeInterface;

/**
 * Avisa as caixas editoriais quando um verbete entra em revisão.
 *
 * O canal é a fila do `aculta_mailer`. O módulo não depende dele em
 * `*.info.yml`: a fila chega como serviço opcional (`@?aculta_mailer.queue`)
 * e, sem o mailer, o aviso simplesmente não é enviado. Os tipos de parâmetro
 * são `?object` porque, com o mailer desabilitado, nem a classe da fila é
 * carregada.
 *
 * O contexto do e-mail leva só o título, o estado e o link de edição. O autor
 * da edição não vai no aviso: não há tratamento novo de autoria aqui.
 */
final class WikiReviewNotifier {

  use StringTranslationTrait;

  /**
   * Configuração da Wiki, gravada pela tela administrativa.
   *
   * Não há arquivo em `config/sync` nem em `config/install`: os padrões vivem
   * no código até alguém salvar a tela.
   */
  public const CONFIG = 'aculta_wiki.settings';

  /**
   * Chave que liga os avisos de revisão.
   */
  public const ENABLED_KEY = 'review_notifications';

  /**
   * Chave com os endereços editoriais.
   *
   * Separe por vírgula, ponto e vírgula ou espaço.
   */
  public const RECIPIENTS_KEY = 'review_recipients';

  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
    TranslationInterface $stringTranslation,
    private readonly UuidInterface $uuid,
    private readonly ?object $queue = NULL,
    private readonly ?object $publicUrl = NULL,
  ) {
    $this->stringTranslation = $stringTranslation;
  }

  /**
   * Se a configuração manda avisar as caixas editoriais.
   */
  public function isEnabled(): bool {
    return (bool) $this->configFactory->get(self::CONFIG)->get(self::ENABLED_KEY);
  }

  /**
   * Endereços editoriais válidos, em minúsculas e sem repetição.
   *
   * @param string $raw
   *   Texto do formulário, com um ou mais endereços.
   *
   * @return list<string>
   *   Endereços válidos, na ordem em que aparecem.
   */
  public static function parseRecipients(string $raw): array {
    $valid = [];
    foreach (preg_split('/[\s,;]+/', $raw, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $candidate) {
      $address = strtolower(trim($candidate));
      if (filter_var($address, FILTER_VALIDATE_EMAIL) === FALSE) {
        continue;
      }
      $valid[$address] = $address;
    }
    return array_values($valid);
  }

  /**
   * Enfileira o aviso de revisão para cada caixa editorial.
   *
   * Desligado, sem fila (mailer desabilitado) ou sem destinatário, não faz
   * nada.
   *
   * @return int
   *   Quantidade de avisos enfileirados.
   */
  public function notifyReview(NodeInterface $verbete): int {
    if (!$this->isEnabled() || $this->queue === NULL || !method_exists($this->queue, 'enqueue')) {
      return 0;
    }
    $recipients = self::parseRecipients((string) $this->configFactory->get(self::CONFIG)->get(self::RECIPIENTS_KEY));
    if ($recipients === []) {
      return 0;
    }
    $context = $this->context($verbete);
    $sent = 0;
    foreach ($recipients as $recipient) {
      // Autoload da fila só aqui: sem o aculta_mailer, este trecho não roda.
      $this->queue->enqueue(new MailMessage(
        WikiReviewMailType::ID,
        $recipient,
        'pt-br',
        $context,
        $this->uuid->generate(),
      ));
      $sent++;
    }
    return $sent;
  }

  /**
   * Valores do modelo: título, estado e link de edição. Sem autoria.
   *
   * @return array<string, string>
   *   Contexto do e-mail.
   */
  private function context(NodeInterface $verbete): array {
    return [
      'verbete' => $verbete->label() ?? '',
      'estado' => (string) $this->t('Em revisão'),
      'link' => $this->editUrl($verbete),
    ];
  }

  /**
   * URL absoluta do formulário de edição, ou vazio se o site não souber o host.
   */
  private function editUrl(NodeInterface $verbete): string {
    if ($this->publicUrl === NULL || !method_exists($this->publicUrl, 'absolute')) {
      return '';
    }
    return (string) $this->publicUrl->absolute('/node/' . $verbete->id() . '/edit');
  }

}
