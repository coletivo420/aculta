<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Avatar;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ExtensionList;
use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Drupal\user\UserDataInterface;
use Drupal\user\UserInterface;

/**
 * Sorteia um avatar do acervo do Portal para quem não tem foto (decisão 12: o arquivo vai para user_picture).
 *
 * Fonte da verdade: assets/avatars/meta/avatars.json, com entradas ativas e arquivo existente. A escolha fica em
 * user.data (aculta_user, avatar_id), para que a rotação futura saiba o que já foi sorteado.
 */
final class AvatarPool {

  public const DIRECTORY = 'public://pictures/acervo';

  public function __construct(
    #[Autowire(service: 'extension.list.module')]
    private readonly ExtensionList $modules,
    #[Autowire(service: 'file_system')]
    private readonly FileSystemInterface $fileSystem,
    #[Autowire(service: 'entity_type.manager')]
    private readonly EntityTypeManagerInterface $entityTypeManager,
    #[Autowire(service: 'user.data')]
    private readonly UserDataInterface $userData,
    #[Autowire('%app.root%')]
    private readonly string $appRoot,
  ) {}

  /**
   * Atribui um avatar do acervo à conta, se ela não tiver foto. Retorna TRUE se atribuiu.
   */
  public function assignIfMissing(UserInterface $account): bool {
    if (!$account->hasField('user_picture') || !$account->get('user_picture')->isEmpty() || $account->id() <= 0) {
      return FALSE;
    }
    $entry = $this->pick();
    if ($entry === NULL) {
      return FALSE;
    }
    $ok = $this->assignEntry($account, $entry);
    if ($ok) {
      $account->save();
    }
    return $ok;
  }

  /** Copia a imagem da entrada para o acervo público e a grava em user_picture. */
  private function assignEntry(UserInterface $account, array $entry): bool {
    $root = $this->appRoot . '/' . $this->modules->getPath('aculta_user') . '/assets/avatars/';
    $basename = basename($entry['file']);
    // Cópia pelo caminho real (o stream public:// do Drupal recusava o diretório no contexto web). O registro do
    // arquivo é criado depois, com o uri público.
    $real = $this->fileSystem->realpath(self::DIRECTORY);
    if ($real === FALSE || !is_dir($real) && !mkdir($real, 0775, TRUE)) {
      return FALSE;
    }
    $target = $real . '/' . $basename;
    $stem = pathinfo($basename, PATHINFO_FILENAME);
    $ext = pathinfo($basename, PATHINFO_EXTENSION);
    for ($i = 2; file_exists($target) && $i < 100; $i++) {
      $target = $real . '/' . $stem . '-' . $i . '.' . $ext;
    }
    if (!copy($root . $entry['file'], $target)) {
      return FALSE;
    }
    $uri = self::DIRECTORY . '/' . basename($target);

    $file = $this->entityTypeManager->getStorage('file')->create([
      'uri' => $uri,
      'uid' => $account->id(),
      'status' => 1,
      'filename' => $basename,
      'filemime' => 'image/png',
    ]);
    $file->save();

    $account->set('user_picture', ['target_id' => $file->id(), 'alt' => $entry['alt'] ?? '']);
    if ((int) $account->id() > 0) {
      $this->userData->set('aculta_user', (int) $account->id(), 'avatar_id', (string) $entry['id']);
    }
    return TRUE;
  }

  /**
   * Atribui um avatar do acervo dentro de user_presave, sem salvar a conta (salvar dentro de hook de usuário
   * quebra outros módulos, como group, porque o original fica nulo).
   */
  public function assignPresave(UserInterface $account): bool {
    if (!$account->hasField('user_picture') || !$account->get('user_picture')->isEmpty()) {
      return FALSE;
    }
    $entry = $this->pick();
    return $entry !== NULL && $this->assignEntry($account, $entry);
  }

  /**
   * Sorteia outro avatar do acervo para a conta (botão "sortear outro" do popup de foto).
   *
   * Substitui a foto atual. A cópia anterior do acervo é apagada quando nenhuma outra conta a usa; fotos enviadas
   * pela pessoa não são tocadas aqui (ver PictureReplacementHooks). Retorna o uri da nova foto, ou NULL.
   */
  public function reroll(UserInterface $account): ?string {
    $current = (string) $this->userData->get('aculta_user', (int) $account->id(), 'avatar_id');
    $entry = $this->pick($current);
    if ($entry === NULL) {
      return NULL;
    }
    $previous = (int) ($account->get('user_picture')->target_id ?? 0);
    $account->set('user_picture', NULL);
    if (!$this->assignEntry($account, $entry)) {
      return NULL;
    }
    $account->save();
    if ($previous > 0) {
      $old = $this->entityTypeManager->getStorage('file')->load($previous);
      if ($old !== NULL && str_starts_with($old->getFileUri(), self::DIRECTORY . '/') && !$this->isUsedByOthers($previous, (int) $account->id())) {
        $old->delete();
      }
    }
    return (string) $this->entityTypeManager->getStorage('file')->load((int) $account->get('user_picture')->target_id)?->getFileUri();
  }

  /** Verdadeiro se outra conta ainda usa a foto. */
  private function isUsedByOthers(int $fid, int $uid): bool {
    return $this->entityTypeManager->getStorage('user')->getQuery()
      ->accessCheck(FALSE)->condition('user_picture.target_id', $fid)->condition('uid', $uid, '<>')->range(0, 1)->count()->execute() > 0;
  }

  /** Sorteia uma entrada ativa cujo arquivo existe, diferente da atual quando possível. */
  private function pick(string $exclude = ''): ?array {
    $catalog = json_decode((string) file_get_contents($this->appRoot . '/' . $this->modules->getPath('aculta_user') . '/assets/avatars/meta/avatars.json'), TRUE);
    $root = $this->appRoot . '/' . $this->modules->getPath('aculta_user') . '/assets/avatars/';
    $available = array_values(array_filter(
      is_array($catalog) ? $catalog : [],
      static fn (array $item): bool => !empty($item['active']) && is_file($root . ($item['file'] ?? '')),
    ));
    $others = array_values(array_filter($available, static fn (array $item): bool => (string) ($item['id'] ?? '') !== $exclude));
    $pool = $others !== [] ? $others : $available;
    return $pool === [] ? NULL : $pool[random_int(0, count($pool) - 1)];
  }

}
