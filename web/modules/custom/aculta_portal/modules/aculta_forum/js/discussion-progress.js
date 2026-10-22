/**
 * Adds accessible progress navigation for the comments rendered on this page.
 */
(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.acultaDiscussionProgress = {
    attach(context) {
      once('aculta-discussion-progress', '[data-aculta-discussion-thread]', context).forEach((thread) => {
        const messages = Array.from(
          thread.querySelectorAll(
            'article.js-comment[id], article.aculta-forum-post[id]',
          ),
        );
        if (messages.length < 2) {
          return;
        }

        const navigation = document.createElement('nav');
        navigation.setAttribute('aria-label', Drupal.t('Navegação das mensagens nesta página'));
        navigation.setAttribute('data-aculta-discussion-progress', '');

        const first = document.createElement('button');
        first.type = 'button';
        first.textContent = Drupal.t('Início');
        first.setAttribute('aria-label', Drupal.t('Ir à primeira mensagem'));
        first.setAttribute('data-aculta-discussion-first', '');

        const previous = document.createElement('button');
        previous.type = 'button';
        previous.textContent = Drupal.t('Anterior');
        previous.setAttribute('aria-label', Drupal.t('Ir à mensagem anterior'));
        previous.setAttribute('data-aculta-discussion-previous', '');

        const range = document.createElement('input');
        range.type = 'range';
        range.min = '0';
        range.max = String(messages.length - 1);
        range.value = '0';
        range.setAttribute('aria-label', Drupal.t('Posição nas mensagens desta página'));
        range.setAttribute('data-aculta-discussion-range', '');

        const status = document.createElement('span');
        status.setAttribute('data-aculta-discussion-status', '');

        const announcement = document.createElement('span');
        announcement.setAttribute('role', 'status');
        announcement.setAttribute('aria-live', 'off');
        announcement.setAttribute('aria-atomic', 'true');
        announcement.setAttribute('data-aculta-discussion-announcement', '');

        const firstUnread = document.createElement('button');
        firstUnread.type = 'button';
        firstUnread.textContent = Drupal.t('Não lidas');
        firstUnread.setAttribute('aria-label', Drupal.t('Ir à primeira mensagem não lida'));
        firstUnread.setAttribute('data-aculta-discussion-unread', '');
        firstUnread.hidden = true;

        const next = document.createElement('button');
        next.type = 'button';
        next.textContent = Drupal.t('Próxima');
        next.setAttribute('aria-label', Drupal.t('Ir à próxima mensagem'));
        next.setAttribute('data-aculta-discussion-next', '');

        const last = document.createElement('button');
        last.type = 'button';
        last.textContent = Drupal.t('Fim');
        last.setAttribute('aria-label', Drupal.t('Ir à última mensagem'));
        last.setAttribute('data-aculta-discussion-last', '');

        navigation.append(first, previous, range, status, announcement, firstUnread, next, last);
        thread.insertBefore(navigation, thread.firstChild);

        let activeIndex = 0;
        let announcementTimeout;
        let announcementUntil = 0;
        const update = (index, announce = false) => {
          activeIndex = Math.max(0, Math.min(index, messages.length - 1));
          range.value = String(activeIndex);
          const message = Drupal.t('Mensagem @current de @total nesta página', {
            '@current': String(activeIndex + 1),
            '@total': String(messages.length),
          });
          range.setAttribute('aria-valuetext', message);
          status.textContent = message;
          if (announce) {
            window.clearTimeout(announcementTimeout);
            announcementUntil = Date.now() + 1200;
            announcement.setAttribute('aria-live', 'polite');
            announcement.textContent = message;
            announcementTimeout = window.setTimeout(() => {
              announcementUntil = 0;
              announcement.setAttribute('aria-live', 'off');
              announcement.textContent = status.textContent;
            }, 1200);
          }
          else if (Date.now() >= announcementUntil) {
            announcement.setAttribute('aria-live', 'off');
            announcement.textContent = message;
          }
          previous.disabled = activeIndex === 0;
          first.disabled = activeIndex === 0;
          next.disabled = activeIndex === messages.length - 1;
          last.disabled = activeIndex === messages.length - 1;
        };
        const goTo = (index, announce = true) => {
          update(index, announce);
          messages[activeIndex].scrollIntoView({
            block: 'start',
            behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth',
          });
        };

        const syncFirstUnreadAction = () => {
          firstUnread.hidden = !messages.some((message) => message.classList.contains('new'));
        };

        firstUnread.addEventListener('click', () => {
          const index = messages.findIndex((message) => message.classList.contains('new'));
          if (index !== -1) {
            goTo(index);
          }
        });

        if ('MutationObserver' in window) {
          const unreadObserver = new MutationObserver(syncFirstUnreadAction);
          messages.forEach((message) => {
            unreadObserver.observe(message, { attributes: true, attributeFilter: ['class'] });
          });
        }
        syncFirstUnreadAction();

        first.addEventListener('click', () => goTo(0));
        previous.addEventListener('click', () => goTo(activeIndex - 1));
        next.addEventListener('click', () => goTo(activeIndex + 1));
        last.addEventListener('click', () => goTo(messages.length - 1));
        range.addEventListener('input', () => goTo(Number(range.value), false));

        if ('IntersectionObserver' in window) {
          const observer = new IntersectionObserver((entries) => {
            const visible = entries.filter((entry) => entry.isIntersecting);
            if (visible.length === 0) {
              return;
            }
            const center = window.innerHeight / 2;
            visible.sort((a, b) =>
              Math.abs(a.boundingClientRect.top + a.boundingClientRect.height / 2 - center)
              - Math.abs(b.boundingClientRect.top + b.boundingClientRect.height / 2 - center));
            update(messages.indexOf(visible[0].target), false);
          }, { rootMargin: '-40% 0px -40% 0px' });
          messages.forEach((message) => observer.observe(message));
        }

        update(0);
      });
    },
  };
})(Drupal, once);
