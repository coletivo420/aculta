<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Mapper;

use Drupal\Core\Database\Query\SelectInterface;
use Drupal\private_message\Mapper\PrivateMessageMapper;

/**
 * The Private Message mapper with the ACULTA rule for blocks: blocking a member does not hide the conversation.
 *
 * The contrib mapper removes from every list the threads in which the current member has blocked another member,
 * so the blocker could no longer open the conversation to unblock. ACULTA keeps those threads listed for the
 * blocker. Sending is what the block stops: MessengerPrivateMessageValidator rejects the new messages, and
 * unblocking is the "Opções" action of the conversation.
 *
 * Replaces the contrib class through AcultaMessengerServiceProvider (the only change is the class name).
 */
class MessengerPrivateMessageMapper extends PrivateMessageMapper {

  /**
   * A subquery that selects no threads, so no listed thread is excluded because of a block by the current member.
   */
  protected function getBannedThreads(int $user_id): SelectInterface {
    $query = $this->database->select('private_message_thread__members', 'thread_member');
    $query->addField('thread_member', 'entity_id');
    $query->condition('thread_member.entity_id', 0);
    return $query;
  }

}
