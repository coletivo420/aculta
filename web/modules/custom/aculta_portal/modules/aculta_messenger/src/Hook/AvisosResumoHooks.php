<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\aculta_messenger\Aviso\AvisosResumo;
use Drupal\aculta_messenger\Entity\Aviso;

/**
 * Cron e mensagem do resumo diário de avisos (ACULTA Messenger, F5.2).
 */
final class AvisosResumoHooks {

  public function __construct(
    private readonly AvisosResumo $resumo,
  ) {}

  /** Implements hook_cron(). */
  #[Hook('cron')]
  public function cron(): void {
    $this->resumo->enviarResumos();
  }

  /** Implements hook_mail(). Conteúdo do resumo: uma linha por tópico, com a quantidade de novidades. */
  #[Hook('mail')]
  public function mail(string $key, array &$message, array $params): void {
    if ($key !== AvisosResumo::CHAVE_MAIL) {
      return;
    }
    $rotulos = [
      Aviso::TIPO_RESPOSTA => 'Respostas novas',
      Aviso::TIPO_ATUALIZACAO => 'Atualizações',
    ];
    $message['subject'] = t('Resumo de avisos do Fórum');
    $linhas = [];
    foreach ($params['linhas'] ?? [] as $linha) {
      $rotulo = $rotulos[$linha['tipo']] ?? $linha['tipo'];
      $linhas[] = $rotulo . ' em "' . $linha['titulo'] . '": ' . $linha['quantidade'];
    }
    $message['body'][] = t('Você tem novidades nos tópicos que segue:');
    foreach ($linhas as $linha) {
      $message['body'][] = $linha;
    }
    $message['body'][] = t('Para deixar de receber este resumo, altere a opção em Minha conta > Privacidade.');
  }

}
