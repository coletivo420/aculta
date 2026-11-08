/**
 * Suporte a preview do componente SDC de mapa no UI Components Library.
 */
(function (Drupal, drupalSettings, once) {
  'use strict';

  Drupal.behaviors.acultaMapsStory = {
    attach(context) {
      once('acultaMapsStory', '[data-portal-ui="map-widget"]:not(.leaflet-container)', context).forEach((element) => {
        if (!window.L) {
          return;
        }
        if (!drupalSettings.acultaMaps) {
          drupalSettings.acultaMaps = {};
        }
        if (!drupalSettings.acultaMaps[element.id]) {
          drupalSettings.acultaMaps[element.id] = {
            center: { lat: -16.671981, lng: -49.217406 },
            zoom: 15,
            zoomLocation: 15,
            provider: 'openstreetmap',
            showAdminNotice: false,
            markers: [
              {
                lat: -16.671981,
                lng: -49.217406,
                label: 'Roots Green Tabacaria e Headshop',
                description: 'Av. Cristóvão Colombo, Quadra 205 - Lote 27 - Jardim Novo Mundo, Goiânia - GO, 74705-130',
                url: '/locais/4',
                routeUrl: 'https://www.google.com/maps/dir/?api=1&destination=Av.+Crist%C3%B3v%C3%A3o+Colombo%2C+Quadra+205+-+Lote+27+-+Jardim+Novo+Mundo%2C+Goi%C3%A2nia+-+GO%2C+74705-130',
                categoryColorClass: 'primary',
              }
            ]
          };
          // Re-executa o behavior principal para desenhar o mapa Leaflet
          if (Drupal.behaviors.acultaMaps) {
            Drupal.behaviors.acultaMaps.attach(element.parentElement || document);
          }
        }
      });
    }
  };
})(Drupal, drupalSettings, once);
