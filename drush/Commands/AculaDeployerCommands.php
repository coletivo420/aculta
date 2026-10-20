<?php

declare(strict_types=1);

namespace Drush\Commands;

use Drush\Attributes as CLI;
use Symfony\Component\Process\Process;

/**
 * Ferramentas Drush do aculta_deployer.
 *
 * Cada comando chama a CLI standalone (`bin/aculta-deployer`) e repassa a saída e o código de saída. A CLI não depende
 * do bootstrap do Drupal, então os comandos funcionam mesmo com o módulo desabilitado no Runtime. A CLI continua sendo
 * a fonte de verdade das verificações: este arquivo só a chama.
 */
final class AculaDeployerCommands extends DrushCommands {

  /**
   * Caminho da CLI, relativo à raiz do projeto.
   */
  private const BIN = 'web/modules/custom/aculta_portal/modules/aculta_deployer/bin/aculta-deployer';

  /**
   * Confere registro, fronteiras e escopo do deploy.
   */
  #[CLI\Command(name: 'aculta-deployer:check')]
  #[CLI\Option(name: 'strict', description: 'Falha com entradas bloqueantes abertas.')]
  public function check(array $options = ['strict' => FALSE]): int {
    return $this->cli(array_merge(['check'], !empty($options['strict']) ? ['--strict'] : []));
  }

  /**
   * Verifica a separação entre o Portal, o tema e a ferramenta.
   */
  #[CLI\Command(name: 'aculta-deployer:boundaries')]
  public function boundaries(): int {
    return $this->cli(['boundaries']);
  }

  /**
   * Confere (somente leitura) os webhooks declarados por ambiente: URL, segredo no contrato e estado do cadastro.
   */
  #[CLI\Command(name: 'aculta-deployer:webhooks')]
  public function webhooks(): int {
    return $this->cli(['webhooks']);
  }

  /**
   * Confere (somente leitura) o cookie de sessão de cada ambiente contra os arquivos de serviços.
   */
  #[CLI\Command(name: 'aculta-deployer:cookies')]
  public function cookies(): int {
    return $this->cli(['cookies']);
  }

  /**
   * Confere (somente leitura) a tabela de domínios de teste para produção contra config/sync.
   */
  #[CLI\Command(name: 'aculta-deployer:domains')]
  public function domains(): int {
    return $this->cli(['domains']);
  }

  /**
   * Confere (GET somente leitura) X-Robots-Tag, caminhos privados e robots.txt de um ambiente.
   */
  #[CLI\Command(name: 'aculta-deployer:robots')]
  #[CLI\Option(name: 'env', description: 'production ou test.')]
  public function robots(array $options = ['env' => NULL]): int {
    return $this->cli(array_merge(['robots'], $this->envOption($options)));
  }

  /**
   * Confere (GET somente leitura) o índice e os filhos de sitemap de um ambiente.
   */
  #[CLI\Command(name: 'aculta-deployer:sitemap')]
  #[CLI\Option(name: 'env', description: 'production ou test.')]
  public function sitemap(array $options = ['env' => NULL]): int {
    return $this->cli(array_merge(['sitemap'], $this->envOption($options)));
  }

  /**
   * Argumento --env da CLI, quando informado.
   */
  private function envOption(array $options): array {
    return is_string($options['env'] ?? NULL) && $options['env'] !== '' ? ['--env=' . $options['env']] : [];
  }

  /**
   * Executa a CLI com os argumentos informados e devolve o código de saída dela.
   */
  private function cli(array $args): int {
    $root = dirname(__DIR__, 2);
    $process = new Process([PHP_BINARY, $root . '/' . self::BIN, ...$args], $root, NULL, NULL, NULL);
    $process->run(function (string $type, string $buffer): void {
      $this->output()->write($buffer);
    });
    return $process->getExitCode() ?? 1;
  }

}
