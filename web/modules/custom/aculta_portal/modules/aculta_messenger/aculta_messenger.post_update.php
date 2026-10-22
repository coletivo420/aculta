<?php
/**
 * @file
 * Post-update functions for ACULTA Messenger.
 */

use Drupal\aculta_messenger\Entity\Aviso;

/**
 * Adds the per-member dismissal Flag for temporary Site Alerts.
 */
function aculta_messenger_post_update_site_alert_dismissal_flag(array &$sandbox): string {
  $storage = \Drupal::entityTypeManager()->getStorage('flag');
  if ($storage->load('aculta_site_alert_dismissed') !== NULL) {
    return 'The Site Alert dismissal flag was already present.';
  }

  $storage->create([
    'id' => 'aculta_site_alert_dismissed',
    'label' => 'Aviso temporário dispensado',
    'bundles' => [],
    'entity_type' => 'site_alert',
    'global' => FALSE,
    'flag_short' => 'Dispensar aviso',
    'flag_long' => 'Dispensar este aviso temporário somente para sua conta.',
    'flag_message' => 'Aviso dispensado para sua conta.',
    'unflag_short' => 'Mostrar aviso novamente',
    'unflag_long' => 'Mostrar este aviso novamente para sua conta.',
    'unflag_message' => 'Aviso restaurado para sua conta.',
    'unflag_denied_text' => '',
    'weight' => 0,
    'flag_type' => 'entity:site_alert',
    'link_type' => 'ajax_link',
    'flagTypeConfig' => [
      'show_in_links' => [],
      'show_as_field' => TRUE,
      'show_on_form' => FALSE,
      'show_contextual_link' => FALSE,
      'extra_permissions' => [],
    ],
    'linkTypeConfig' => [],
  ])->save();

  return 'Added the per-user dismissal Flag for Site Alerts.';
}

/**
 * Adds the per-member read Flag for temporary Site Alerts (rótulo Lido / Não lido).
 */
function aculta_messenger_post_update_site_alert_read_flag(array &$sandbox): string {
  $storage = \Drupal::entityTypeManager()->getStorage('flag');
  if ($storage->load('aculta_site_alert_read') !== NULL) {
    return 'The Site Alert read flag was already present.';
  }

  $storage->create([
    'id' => 'aculta_site_alert_read',
    'label' => 'Aviso temporário lido',
    'bundles' => [],
    'entity_type' => 'site_alert',
    'global' => FALSE,
    'flag_short' => 'Marcar como lido',
    'flag_long' => 'Marcar este aviso temporário como lido somente para sua conta.',
    'flag_message' => 'Aviso marcado como lido para sua conta.',
    'unflag_short' => 'Marcar como não lido',
    'unflag_long' => 'Marcar este aviso temporário como não lido somente para sua conta.',
    'unflag_message' => 'Aviso marcado como não lido para sua conta.',
    'unflag_denied_text' => '',
    'weight' => 0,
    'flag_type' => 'entity:site_alert',
    'link_type' => 'ajax_link',
    'flagTypeConfig' => [
      'show_in_links' => [],
      'show_as_field' => TRUE,
      'show_on_form' => FALSE,
      'show_contextual_link' => FALSE,
      'extra_permissions' => [],
    ],
    'linkTypeConfig' => [],
  ])->save();

  return 'Added the per-user read Flag for Site Alerts.';
}

/**
 * Adds the response-notice watermark for revisionable Forum posts.
 */
function aculta_messenger_post_update_forum_post_watermark(array &$sandbox): string {
  $manager = \Drupal::entityDefinitionUpdateManager();
  if ($manager->getFieldStorageDefinition('ultimo_post', 'aculta_aviso') !== NULL) {
    return 'The revisionable Forum post notice watermark was already installed.';
  }

  $entity_type = \Drupal::entityTypeManager()->getDefinition('aculta_aviso');
  $field = Aviso::baseFieldDefinitions($entity_type)['ultimo_post'];
  $manager->installFieldStorageDefinition(
    'ultimo_post',
    'aculta_aviso',
    'aculta_messenger',
    $field,
  );

  return 'Added the revisionable Forum post notice watermark.';
}
