/**
 * @file
 * Synchronizes visible forum reaction totals after an authoritative HTMX swap.
 *
 * The POST response carries fresh counters from DiscussionReactionManager;
 * this script does not predict scores or issue an additional database request.
 */
((Drupal) => {
  'use strict';

  const isCount = (value) => /^\d+$/.test(String(value));

  const updateText = (element, value) => {
    if (element && isCount(value)) {
      element.textContent = String(value);
    }
  };

  const updateUserTotal = (uid, value, name) => {
    if (!isCount(uid) || Number(uid) <= 0 || !isCount(value)) {
      return;
    }
    document.querySelectorAll('[data-forum-user-reactions-' + name + ']').forEach((element) => {
      if (element.dataset.userUid !== String(uid)) {
        return;
      }
      const label = element.dataset.statLabel;
      element.textContent = label ? label + ' ' + value : String(value);
    });
  };

  const updateAuthorCards = (uid, value) => {
    if (!isCount(uid) || Number(uid) <= 0 || !isCount(value)) {
      return;
    }
    document.querySelectorAll('[data-discussion-reaction-bar]').forEach((bar) => {
      if (bar.dataset.authorUid !== String(uid)) {
        return;
      }
      // Both opening posts and classic/mirrored ForumPost/Comment cards.
      const card = bar.closest('.forum-post-card');
      updateText(card?.querySelector('[data-forum-author-reactions-received]'), value);
    });
  };

  const syncMessageCopies = (source) => {
    const entityType = source.dataset.entityType;
    const entityId = source.dataset.entityId;
    const total = source.dataset.reactionTotal;
    if (!entityType || !isCount(entityId) || !isCount(total)) {
      return;
    }

    // Update independent read-only summaries in comment headers as well.
    document.querySelectorAll('[data-discussion-reactions-received]').forEach((summary) => {
      if (summary.dataset.entityType === entityType && summary.dataset.entityId === entityId) {
        updateText(summary.querySelector('.discussion-reactions-received__net .discussion-reactions-received__count'), total);
      }
    });

    document.querySelectorAll('[data-discussion-reaction-bar]').forEach((bar) => {
      if (bar === source || bar.dataset.entityType !== entityType || bar.dataset.entityId !== entityId) {
        return;
      }
      bar.dataset.reactionTotal = total;
      bar.dataset.userReaction = source.dataset.userReaction || '';
      updateText(bar.querySelector('.discussion-reactions-received__net .discussion-reactions-received__count'), total);

      // Keep active vote/Undo button state consistent for duplicate cards.
      bar.querySelectorAll('form input[name="reaction"]').forEach((input) => {
        const button = input.form?.querySelector('button');
        if (!button) {
          return;
        }
        const selected = input.value === (source.dataset.userReaction || '');
        if (input.value === 'undo') {
          button.disabled = !source.dataset.userReaction;
        }
        else {
          if (button.hasAttribute('aria-pressed')) {
            button.setAttribute('aria-pressed', selected ? 'true' : 'false');
          }
          button.classList.toggle('is-active', selected);
          button.classList.toggle('is-selected', selected);
        }
      });
    });
  };

  const sync = (bar) => {
    if (!bar?.matches?.('[data-discussion-reaction-bar]')) {
      return;
    }
    updateAuthorCards(bar.dataset.authorUid, bar.dataset.authorReactionsReceived);
    updateUserTotal(bar.dataset.authorUid, bar.dataset.authorReactionsReceived, 'received');
    updateUserTotal(bar.dataset.viewerUid, bar.dataset.viewerReactionsGiven, 'given');
    syncMessageCopies(bar);
  };

  Drupal.behaviors.acultaForumReactionCounterSync = {
    attach(context) {
      // Initial render, plus bars inserted by HTMX and Drupal AJAX.
      if (context.matches?.('[data-discussion-reaction-bar]')) {
        sync(context);
      }
      context.querySelectorAll?.('[data-discussion-reaction-bar]').forEach(sync);

      if (document.documentElement.dataset.forumReactionSyncBound === 'true') {
        return;
      }
      document.documentElement.dataset.forumReactionSyncBound = 'true';
      document.body.addEventListener('htmx:afterSwap', (event) => {
        const changed = event.detail?.elt || event.detail?.target || event.target;
        const bar = changed?.matches?.('[data-discussion-reaction-bar]')
          ? changed
          : changed?.querySelector?.('[data-discussion-reaction-bar]');
        if (bar) {
          sync(bar);
        }
      });
    },
  };
})(Drupal);
