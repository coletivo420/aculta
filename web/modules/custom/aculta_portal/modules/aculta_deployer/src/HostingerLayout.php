<?php

declare(strict_types=1);

namespace AcultaDeployer;

/** Safely points a Hostinger domain's public_html at the Drupal web directory. */
final class HostingerLayout {

  /** @return array{domain_root: string, installation_root: string, public_root: string, status: string} */
  public function inspect(string $domainRoot): array {
    $root = realpath($domainRoot);
    if ($root === false || !is_dir($root) || $root === DIRECTORY_SEPARATOR) {
      throw new \RuntimeException('informe --domain-root apontando para uma pasta de domínio existente');
    }
    foreach ([
      'composer.json',
      'composer.lock',
      'web/index.php',
      'web/sites/default/settings.php',
    ] as $required) {
      if (!is_file($root . DIRECTORY_SEPARATOR . $required)) {
        throw new \RuntimeException("instalação incompleta: arquivo obrigatório ausente ($required)");
      }
    }
    $composerAutoload = $root . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
    if (!is_file($composerAutoload)) {
      throw new \RuntimeException('instalação incompleta: dependências Composer não instaladas');
    }
    $webRoot = $root . DIRECTORY_SEPARATOR . 'web';
    if (is_link($webRoot) || !is_dir($webRoot)) {
      throw new \RuntimeException('web/ precisa ser uma pasta física na raiz do projeto');
    }

    $publicRoot = $root . DIRECTORY_SEPARATOR . 'public_html';
    $existing = file_exists($publicRoot) || is_link($publicRoot);
    if ($existing && !is_dir($publicRoot) && !is_link($publicRoot)) {
      throw new \RuntimeException('public_html existe, mas não é pasta nem symlink; nenhuma alteração foi feita');
    }
    $status = 'absent';
    if ($existing && is_link($publicRoot) && readlink($publicRoot) === 'web') {
      $status = 'already_linked';
    } elseif ($existing) {
      $status = is_link($publicRoot) ? 'symlink' : 'directory';
    }

    return [
      'domain_root' => $root,
      'installation_root' => $root,
      'public_root' => $publicRoot,
      'status' => $status,
    ];
  }

  /** @param array{domain_root: string, installation_root: string, public_root: string, status: string} $plan */
  public function activate(array $plan): string {
    if ($plan['status'] === 'already_linked') {
      return 'public_html já aponta para a raiz web da instalação';
    }
    if (!function_exists('symlink')) {
      throw new \RuntimeException('a função symlink do PHP está desabilitada; habilite-a na configuração PHP do hPanel e tente novamente');
    }
    $lock = fopen($plan['domain_root'] . DIRECTORY_SEPARATOR . '.aculta-deployer-layout.lock', 'c');
    if ($lock === false) {
      throw new \RuntimeException('não foi possível abrir o bloqueio da operação na pasta do domínio');
    }
    try {
      if (!flock($lock, LOCK_EX | LOCK_NB)) {
        throw new \RuntimeException('outra operação de layout está em andamento para este domínio');
      }
      // Reinspect after taking the lock so a stale plan cannot move a changed destination.
      $current = $this->inspect($plan['domain_root']);
      if ($current['status'] === 'already_linked') {
        return 'public_html já aponta para a raiz web da instalação';
      }
      $suffix = bin2hex(random_bytes(6));
      $temporary = $current['domain_root'] . DIRECTORY_SEPARATOR . '.public_html.aculta-deployer-link-' . $suffix;
      $previous = $current['domain_root'] . DIRECTORY_SEPARATOR . '.public_html.aculta-deployer-old-' . $suffix;
      $moved = false;
      if ($current['status'] !== 'absent') {
        if (!rename($current['public_root'], $previous)) {
          throw new \RuntimeException('não foi possível mover o public_html anterior para substituí-lo');
        }
        $moved = true;
      }
      try {
        if (!symlink('web', $temporary)) {
          throw new \RuntimeException('não foi possível criar o symlink temporário para web/');
        }
        if (!rename($temporary, $current['public_root'])) {
          throw new \RuntimeException('não foi possível ativar public_html como symlink');
        }
      } catch (\Throwable $e) {
        if (is_link($temporary)) {
          unlink($temporary);
        }
        if ($moved && !file_exists($current['public_root']) && !is_link($current['public_root'])) {
          rename($previous, $current['public_root']);
        }
        throw $e;
      }
      if ($moved) {
        $this->removePath($previous);
        return 'public_html anterior excluído e substituído pelo symlink relativo para web/';
      }
      return 'public_html criado como symlink relativo para web/ na raiz do projeto';
    } finally {
      flock($lock, LOCK_UN);
      fclose($lock);
    }
  }

  private function removePath(string $path): void {
    if (is_link($path) || is_file($path)) {
      if (!unlink($path)) {
        throw new \RuntimeException('public_html foi substituído, mas não foi possível excluir o item anterior');
      }
      return;
    }
    if (!is_dir($path)) {
      return;
    }
    $items = new \RecursiveIteratorIterator(
      new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
      \RecursiveIteratorIterator::CHILD_FIRST,
    );
    foreach ($items as $item) {
      $itemPath = $item->getPathname();
      $removed = $item->isLink() || !$item->isDir()
        ? unlink($itemPath)
        : rmdir($itemPath);
      if (!$removed) {
        throw new \RuntimeException('public_html foi substituído, mas não foi possível excluir todo o conteúdo anterior');
      }
    }
    if (!rmdir($path)) {
      throw new \RuntimeException('public_html foi substituído, mas não foi possível excluir a pasta anterior');
    }
  }

}
