<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Avatar;

use Drupal\aculta_image\ImageRenderer;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\user\UserInterface;

/**
 * Avatar público único do site, na mesma ordem de Conta/perfil.
 *
 * 1. foto da conta, quando o chamador informa que a política pública a libera;
 * 2. avatar ilustrativo estável do acervo (AvatarPool), que é o fallback final
 *    e obrigatório para quem não tem foto.
 *
 * Não decide visibilidade de campos: recebe $showPhoto já calculado pela policy.
 * O AvatarPool é opcional só para testes unitários; em produção é sempre injetado.
 * O NULL abaixo é contrato defensivo do teste sem pool: o site não tem estado
 * "sem acervo" e a inicial do nome nunca é usada.
 */
final class AvatarResolver {

  public function __construct(
    private readonly ImageRenderer $imageRenderer,
    private readonly ?AvatarPool $avatarPool = NULL,
  ) {}

  /**
   * Monta o render array do avatar, ou NULL quando não há imagem a mostrar.
   *
   * @param array<string, string> $attributes
   *   Atributos extras do avatar ilustrativo (por exemplo, data-portal-ui).
   */
  public function build(?UserInterface $account, string $name, bool $showPhoto, CacheableMetadata $cacheability, array $attributes = []): ?array {
    if ($showPhoto && $account !== NULL && $account->hasField('user_picture') && !$account->get('user_picture')->isEmpty()) {
      $file = $account->get('user_picture')->entity;
      if ($file !== NULL) {
        $cacheability->addCacheableDependency($file);
        return $this->imageRenderer->build('avatar', $file->getFileUri(), $name, ['loading' => 'lazy']);
      }
    }

    if ($this->avatarPool === NULL) {
      return NULL;
    }
    $entry = $this->avatarPool->fallbackEntry((int) $account?->id());
    if ($entry === NULL) {
      return NULL;
    }
    return [
      '#type' => 'html_tag',
      '#tag' => 'img',
      '#attributes' => $attributes + [
        'src' => $this->avatarPool->previewUrl($entry),
        'alt' => (string) new TranslatableMarkup('Avatar ilustrativo para @name', ['@name' => $name]),
        'loading' => 'lazy',
      ],
      '#cache' => ['contexts' => ['languages:language_interface']],
    ];
  }

}
