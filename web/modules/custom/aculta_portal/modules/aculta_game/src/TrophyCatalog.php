<?php

declare(strict_types=1);

namespace Drupal\aculta_game;

use Drupal\aculta_user\Badge\BadgeCatalog;

/** Metadados de troféus importados; concessões permanecem no backend User. */
final class TrophyCatalog {

  public static function all(): array {
    return [
      BadgeCatalog::FUNDADORE => [
        'image' => 'fundadore.png',
        'description' => 'Reconhece quem participou da criação da Associação Cultural Antiproibicionista e da construção inicial do Coletivo 420.',
        'source' => 'https://coletivo420.org.br/insignias/fundadore/',
      ],
      BadgeCatalog::ASSOCIADO => [
        'image' => 'associado-antiproibicionista.png',
        'description' => 'Reconhecimento da participação na categoria Membro Antiproibicionista do Coletivo 420.',
        'source' => 'https://coletivo420.org.br/insignias/associado-a-antiproibicionista/',
      ],
      BadgeCatalog::APOIADOR => [
        'image' => 'apoiadore.png',
        'description' => 'Reconhece o apoio e a participação nas atividades e nos ideais do Coletivo 420.',
        'source' => 'https://coletivo420.org.br/insignias/apoiadore/',
      ],
    ];
  }

}
