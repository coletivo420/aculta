/**
 * @file
 * Applies the History read receipt to revisionable Forum posts.
 */

(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.acultaForumPostHistory = {
    attach(context) {
      once(
        'aculta-forum-post-history',
        '[data-aculta-discussion-thread][data-history-node-id]',
        context,
      ).forEach((thread) => {
        const nodeId = thread.getAttribute('data-history-node-id');
        const posts = Array.from(
          thread.querySelectorAll(
            'article.aculta-forum-post[data-aculta-forum-post-timestamp]',
          ),
        );
        const unreadCandidates = posts.filter((post) => {
          const timestamp = Number.parseInt(post.getAttribute('data-aculta-forum-post-timestamp'), 10);
          return Number.isFinite(timestamp) && Drupal.history.needsServerCheck(nodeId, timestamp);
        });

        if (unreadCandidates.length === 0) {
          return;
        }

        Drupal.history.fetchTimestamps([nodeId], () => {
          const lastRead = Drupal.history.getLastRead(nodeId);
          unreadCandidates.forEach((post) => {
            const timestamp = Number.parseInt(post.getAttribute('data-aculta-forum-post-timestamp'), 10);
            if (timestamp > lastRead) {
              post.classList.add('new');
            }
          });
        });
      });
    },
  };
})(Drupal, once);
