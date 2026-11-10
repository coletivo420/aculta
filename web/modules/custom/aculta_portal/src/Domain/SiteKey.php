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

}
