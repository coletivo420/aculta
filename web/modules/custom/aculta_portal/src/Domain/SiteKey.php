<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Domain;

/**
 * Stable functional key for one of the ACULTA sites.
 *
 * A site key is not a hostname, a Drupal Domain ID, or a visual skin ID.
 */
enum SiteKey: string {

  case Main = 'main';
  case Account = 'account';
  case Support = 'support';
  case Magazine = 'magazine';
  case Wiki = 'wiki';
  case Shop = 'shop';
  case Courses = 'courses';
  case Forum = 'forum';
  case Links = 'links';
  case Eventos = 'eventos';

  /**
   * Sessão do breadcrumb, usada pelo SDC aculta420_v2:breadcrumb.
   *
   * Cada subdomínio público tem o seu ícone de primeiro nível. Main, Account,
   * Support e Links usam a sessão "portal".
   */
  public function breadcrumbSessao(): string {
    return match ($this) {
      self::Magazine => 'revista',
      self::Wiki => 'wiki',
      self::Shop => 'green_stash',
      self::Courses => 'ganjalab',
      self::Forum => 'forum',
      self::Eventos => 'culturas',
      self::Main, self::Account, self::Support, self::Links => 'portal',
    };
  }

}
