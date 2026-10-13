<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Avatar;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ExtensionList;
use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\DependencyInjection\Attribute\Autowire;
use Drupal\user\UserDataInterface;
use Drupal\user\UserInterface;

/**
 * Sorteia um avatar do acervo do Portal para quem não tem foto (decisão 12: o arquivo vai para user_picture).
 *
 * Fonte da verdade: assets/avatars/meta/avatars.json, com entradas ativas e arquivo existente. A escolha fica em
 * user.data (aculta_user, avatar_id), para que a rotação futura saiba o que já foi sorteado.
 */
final class AvatarPool {

  private const DIRECTORY = 'public://pictures/acervo';

  public function __construct(
    #[Autowire(service: 'extension.list.module')]
    private readonly ExtensionList $modules,
    #[Autowire(service: 'file_system')]
    private readonly FileSystemInterface $fileSystem,
    #[Autowire(service: 'entity_type.manager')]
    private readonly EntityTypeManagerInterface $entityTypeManager,
    #[Autowire(service: 'user.data')]
    private readonly UserDataInterface $userData,
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
    $root = $this->modules->getPath('aculta_portal') . '/assets/avatars/';
    $basename = basename($entry['file']);
    $this->fileSystem->prepareDirectory(self::DIRECTORY, FileSystemInterface::CREATE_DIRECTORY);
    $uri = $this->fileSystem->copy($root . $entry['file'], self::DIRECTORY . '/' . $basename, FileExists::Rename);

    $file = $this->entityTypeManager->getStorage('file')->create([
      'uri' => $uri,
      'uid' => $account->id(),
      'status' => 1,
      'filename' => $basename,
      'filemime' => 'image/png',
    ]);
    $file->save();

    $account->set('user_picture', ['target_id' => $file->id(), 'alt' => $entry['alt'] ?? '']);
    $account->save();
    $this->userData->set('aculta_user', (int) $account->id(), 'avatar_id', $entry['id']);
    return TRUE;
  }

  /** Sorteia uma entrada ativa cujo arquivo existe. */
  private function pick(): ?array {
    $catalog = json_decode((string) file_get_contents($this->modules->getPath('aculta_portal') . '/assets/avatars/meta/avatars.json'), TRUE);
    $root = $this->modules->getPath('aculta_portal') . '/assets/avatars/';
    $available = array_values(array_filter(
      is_array($catalog) ? $catalog : [],
      static fn (array $item): bool => !empty($item['active']) && is_file($root . ($item['file'] ?? '')),
    ));
    return $available === [] ? NULL : $available[random_int(0, count($available) - 1)];
  }

}
