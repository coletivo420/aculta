(function (Drupal, drupalSettings, once) {
  'use strict';

  const settings = drupalSettings.acultaStatistics && drupalSettings.acultaStatistics.consent;
  if (!settings) {
    return;
  }

  const cookieName = `${settings.cookieName}_v${settings.version}`;
  const maxAgeSeconds = 60 * 60 * 24 * 180;

  // Consent Mode v2: fila de comandos antes de qualquer tag carregar. Anúncios ficam negados sempre.
  window.dataLayer = window.dataLayer || [];
  window.gtag = window.gtag || function gtag() {
    window.dataLayer.push(arguments);
  };

  /**
   * Lê a decisão gravada: 'granted', 'denied' ou NULL quando ainda não há.
   */
  function readDecision() {
    const match = document.cookie.match(new RegExp(`(?:^|; )${cookieName}=([^;]*)`));
    return match && (match[1] === 'granted' || match[1] === 'denied') ? match[1] : null;
  }

  /**
   * Grava a decisão e atualiza o Consent Mode. Cookie de decisão, sem dado pessoal.
   */
  function saveDecision(value) {
    const secure = window.location.protocol === 'https:' ? '; Secure' : '';
    document.cookie = `${cookieName}=${value}; Max-Age=${maxAgeSeconds}; Path=/; SameSite=Lax${secure}`;
    window.gtag('consent', 'update', {
      analytics_storage: value,
      ad_storage: 'denied',
      ad_user_data: 'denied',
      ad_personalization: 'denied',
    });
  }

  // Padrão negado até a decisão, antes de qualquer tag. Com o Google Tag em Consent Mode,
  // ele já define o padrão; repetir aqui poderia sobrescrever a decisão gravada.
  const tagOwnsDefault = drupalSettings.gtag && drupalSettings.gtag.consentMode === true;
  if (!tagOwnsDefault) {
    const initial = readDecision();
    window.gtag('consent', 'default', {
      analytics_storage: initial || 'denied',
      ad_storage: 'denied',
      ad_user_data: 'denied',
      ad_personalization: 'denied',
    });
  }

  if (tagOwnsDefault && readDecision() !== null) {
    window.gtag('consent', 'update', {
      analytics_storage: readDecision(),
      ad_storage: 'denied',
      ad_user_data: 'denied',
      ad_personalization: 'denied',
    });
  }

  Drupal.behaviors.acultaConsentBanner = {
    attach(context) {
      once('aculta-consent-banner', '[data-portal-ui="consent-banner"]', context).forEach((banner) => {
        const privacy = banner.querySelector('[data-consent-privacy]');
        if (privacy && settings.privacyUrl) {
          privacy.setAttribute('href', settings.privacyUrl);
        }
        if (readDecision() !== null) {
          return;
        }
        banner.removeAttribute('hidden');
        banner.querySelectorAll('[data-consent-action]').forEach((button) => {
          button.addEventListener('click', () => {
            saveDecision(button.dataset.consentAction === 'accept' ? 'granted' : 'denied');
            banner.setAttribute('hidden', '');
          });
        });
      });
    },
  };

})(Drupal, drupalSettings, once);
