((Drupal, once) => {
  // Carrossel das experiências do painel de conta: três caixas visíveis no desktop, uma no celular,
  // rolagem automática que pausa com mouse ou foco e respeita prefers-reduced-motion.
  const INTERVAL_MS = 5000;
  const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

  Drupal.behaviors.acultaAccountCarousel = {
    attach(context) {
      once('aculta-account-carousel', '.aculta-xp-grid', context).forEach((list) => {
        const items = Array.from(list.children);
        const strips = items.filter((li) => li.querySelector('.aculta-xp-strip'));
        const cards = items.filter((li) => !strips.includes(li));
        if (cards.length < 2) return;

        const wrap = document.createElement('div');
        wrap.className = 'aculta-xp-carousel';
        const viewport = document.createElement('div');
        viewport.className = 'aculta-xp-carousel__viewport';
        const controls = document.createElement('div');
        controls.className = 'aculta-xp-carousel__controls';
        const prev = document.createElement('button');
        prev.type = 'button';
        prev.className = 'aculta-xp-carousel__button';
        prev.setAttribute('aria-label', 'Experiência anterior');
        prev.textContent = '‹';
        const next = document.createElement('button');
        next.type = 'button';
        next.className = 'aculta-xp-carousel__button';
        next.setAttribute('aria-label', 'Próxima experiência');
        next.textContent = '›';

        list.parentNode.insertBefore(wrap, list);
        viewport.appendChild(list);
        wrap.append(viewport, controls);
        controls.append(prev, next);
        list.classList.add('aculta-xp-carousel__track');

        // Faixa de largura total (Green Stash) fica fora do carrossel, logo abaixo.
        if (strips.length) {
          const stripList = document.createElement('ul');
          stripList.className = 'aculta-xp-grid aculta-xp-grid--strip';
          strips.forEach((li) => stripList.appendChild(li));
          wrap.after(stripList);
        }

        let index = 0;
        const step = () => {
          const first = list.children[0];
          const gap = parseFloat(getComputedStyle(list).columnGap) || 0;
          return first ? first.getBoundingClientRect().width + gap : 0;
        };
        const visible = () => Math.max(1, Math.round(viewport.clientWidth / (step() || 1)));
        const maxIndex = () => Math.max(0, cards.length - visible());
        const go = (to) => {
          index = Math.min(Math.max(to, 0), maxIndex());
          if (index > maxIndex()) index = 0;
          viewport.scrollTo({ left: index * step(), behavior: reduceMotion.matches ? 'auto' : 'smooth' });
        };
        const advance = () => go(index >= maxIndex() ? 0 : index + 1);

        prev.addEventListener('click', () => go(index <= 0 ? maxIndex() : index - 1));
        next.addEventListener('click', advance);

        let timer = null;
        let paused = false;
        const start = () => {
          if (reduceMotion.matches || timer || paused) return;
          timer = window.setInterval(advance, INTERVAL_MS);
        };
        const stop = () => {
          if (timer) window.clearInterval(timer);
          timer = null;
        };
        wrap.addEventListener('mouseenter', () => { paused = true; stop(); });
        wrap.addEventListener('mouseleave', () => { paused = false; start(); });
        wrap.addEventListener('focusin', () => { paused = true; stop(); });
        wrap.addEventListener('focusout', () => { paused = false; start(); });
        window.addEventListener('resize', () => go(index));
        start();
      });
    },
  };
})(Drupal, once);
