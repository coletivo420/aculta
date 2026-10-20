<?php

declare(strict_types=1);

namespace Drupal\aculta_mailer\Policy;

/**
 * Política efetiva de um envio: transporte, remetente de resposta, prioridade, suspensão e cabeçalhos extras.
 *
 * Imutável: cada ajuste devolve uma política nova.
 */
final readonly class MailPolicy {

  /**
   * Política com os valores informados.
   *
   * @param string|null $transport
   *   ID do transporte, ou NULL para o padrão.
   * @param string|null $replyTo
   *   Endereço de resposta, ou NULL.
   * @param int|null $priority
   *   Prioridade de 1 (alta) a 5 (baixa), ou NULL.
   * @param bool $skip
   *   TRUE para não enviar (suspensão pela política).
   */
  public function __construct(
    public ?string $transport = NULL,
    public ?string $replyTo = NULL,
    public ?int $priority = NULL,
    public bool $skip = FALSE,
  ) {}

  /**
   * Cópia com o transporte definido.
   */
  public function withTransport(?string $transport): self {
    return new self($transport, $this->replyTo, $this->priority, $this->skip);
  }

  /**
   * Cópia com o endereço de resposta definido.
   */
  public function withReplyTo(?string $replyTo): self {
    return new self($this->transport, $replyTo, $this->priority, $this->skip);
  }

  /**
   * Cópia com a prioridade definida.
   */
  public function withPriority(?int $priority): self {
    return new self($this->transport, $this->replyTo, $priority, $this->skip);
  }

  /**
   * Cópia com a suspensão definida.
   */
  public function withSkip(bool $skip): self {
    return new self($this->transport, $this->replyTo, $this->priority, $skip);
  }

}
