/**
 * Mapa de locais do ACULTA: Maps JavaScript API, com agrupamento quando a biblioteca estiver disponível.
 *
 * Carregado só quando há chave (MapController). Sem o agrupador, os marcadores aparecem sem agrupar.
 */
(function (Drupal, drupalSettings) {
  'use strict';

  Drupal.behaviors.acultaMapsMapa = {
    attach(context) {
      const elemento = context.querySelector ? context.querySelector('#aculta-map') : null;
      const config = drupalSettings.acultaMaps;
      if (!elemento || !config || elemento.dataset.iniciado === '1') {
        return;
      }
      elemento.dataset.iniciado = '1';
      carregarMapsApi(config.mapsKey).then(() => desenhar(elemento, config.markers || []));
    },
  };

  function carregarMapsApi(chave) {
    return new Promise((resolve, reject) => {
      if (window.google && window.google.maps) {
        resolve();
        return;
      }
      const script = document.createElement('script');
      script.src = 'https://maps.googleapis.com/maps/api/js?key=' + encodeURIComponent(chave) + '&language=pt-BR&region=BR';
      script.async = true;
      script.onload = resolve;
      script.onerror = reject;
      document.head.appendChild(script);
    });
  }

  function desenhar(elemento, marcadores) {
    if (!marcadores.length) {
      elemento.textContent = 'Nenhum local com localização no mapa.';
      return;
    }
    const mapa = new google.maps.Map(elemento, { center: { lat: marcadores[0].lat, lng: marcadores[0].lng }, zoom: 11 });
    const limites = new google.maps.LatLngBounds();
    const pinos = marcadores.map((m) => {
      const posicao = { lat: m.lat, lng: m.lng };
      limites.extend(posicao);
      const pino = new google.maps.Marker({ position: posicao, title: m.nome });
      if (m.endereco) {
        const janela = new google.maps.InfoWindow({ content: document.createTextNode(m.nome + ' — ' + m.endereco) });
        pino.addListener('click', () => janela.open({ anchor: pino, map: mapa }));
      }
      return pino;
    });
    mapa.fitBounds(limites);
    if (window.markerClusterer && window.markerClusterer.MarkerClusterer) {
      new window.markerClusterer.MarkerClusterer({ map: mapa, markers: pinos });
    }
    else {
      pinos.forEach((pino) => pino.setMap(mapa));
    }
  }

})(Drupal, drupalSettings);
