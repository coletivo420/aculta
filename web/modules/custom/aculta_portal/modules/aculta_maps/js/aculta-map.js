/**
 * Draws ACULTA maps with Leaflet and groups nearby markers.
 */
(function (Drupal, drupalSettings, once) {
  'use strict';

  const clusterDistance = 52;
  const popupTemplates = new WeakMap();
  const markerIconTemplates = new WeakMap();

  Drupal.behaviors.acultaMaps = {
    attach(context) {
      document.querySelectorAll('[data-aculta-map="true"]').forEach((element) => {
        if (element.classList.contains('leaflet-container')) {
          return;
        }
        const settings = drupalSettings.acultaMaps && drupalSettings.acultaMaps[element.id];
        if (!settings) {
          return;
        }
        once('acultaMaps', element);
        popupTemplates.set(element, new Map(
          Array.from(element.querySelectorAll('template[data-aculta-map-popup-template]'))
            .map((template) => [template.dataset.popupId, template.content.cloneNode(true)]),
        ));
        markerIconTemplates.set(element, new Map(
          Array.from(element.querySelectorAll('template[data-aculta-map-marker-icon-template]'))
            .map((template) => [template.dataset.iconTemplateId, template.content.cloneNode(true)]),
        ));
        if (settings.provider === 'google' && settings.googleApiKey) {
          loadGoogleMaps(settings.googleApiKey)
            .then(() => drawGoogleMap(element, settings))
            .catch(() => drawFallbackMap(element, settings, true));
        }
        else {
          drawFallbackMap(element, settings);
        }
      });
    },
  };

  function drawMap(element, settings) {
    const map = window.L.map(element, {
      center: [settings.center.lat, settings.center.lng],
      zoom: settings.zoom,
      scrollWheelZoom: false,
      worldCopyJump: true,
    });
    window.L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
      maxZoom: 19,
      attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap contributors</a>',
    }).addTo(map);

    const layer = window.L.layerGroup().addTo(map);
    const renderMarkers = () => {
      layer.clearLayers();
      clusterMarkers(settings.markers || [], map).forEach((group) => {
        if (group.length === 1) {
          addLocationMarker(layer, group[0], element);
        }
        else {
          addClusterMarker(layer, group, map);
        }
      });
    };
    map.on('zoomend moveend', renderMarkers);
    renderMarkers();
    const markers = settings.markers || [];
    if (markers.length === 1) {
      map.setView([markers[0].lat, markers[0].lng], settings.zoomLocation ?? settings.zoom);
    }
    else if (markers.length > 1) {
      map.fitBounds(window.L.latLngBounds(markers.map((marker) => [marker.lat, marker.lng])), {
        padding: [24, 24],
        maxZoom: settings.zoomLocation ?? 15,
      });
    }
    window.requestAnimationFrame(() => map.invalidateSize());
  }

  let googleMapsPromise;
  const googleFallbacks = [];

  window.gm_authFailure = () => {
    googleFallbacks.forEach(({ element, settings }) => drawFallbackMap(element, settings, true));
    googleFallbacks.length = 0;
  };

  function loadGoogleMaps(apiKey) {
    if (window.google && window.google.maps) {
      return Promise.resolve();
    }
    if (!googleMapsPromise) {
      googleMapsPromise = new Promise((resolve, reject) => {
        const callbackName = `acultaMapsGoogleReady${Date.now()}`;
        window[callbackName] = () => {
          delete window[callbackName];
          resolve();
        };
        const script = document.createElement('script');
        script.src = `https://maps.googleapis.com/maps/api/js?key=${encodeURIComponent(apiKey)}&callback=${callbackName}&loading=async`;
        script.async = true;
        script.onerror = () => reject(new Error('Google Maps could not be loaded'));
        window.setTimeout(() => reject(new Error('Google Maps load timed out')), 10000);
        document.head.appendChild(script);
      });
    }
    return googleMapsPromise;
  }

  function drawGoogleMap(element, settings) {
    try {
      googleFallbacks.push({ element, settings });
      const map = new window.google.maps.Map(element, {
        center: settings.center,
        zoom: settings.zoom,
        gestureHandling: 'cooperative',
      });
      const bounds = new window.google.maps.LatLngBounds();
      (settings.markers || []).forEach((marker) => {
        const position = { lat: marker.lat, lng: marker.lng };
        const pin = new window.google.maps.Marker({ map, position, title: marker.label });
        bounds.extend(position);
        const content = getPopupContent(element, marker);
        const info = new window.google.maps.InfoWindow({ content });
        pin.addListener('click', () => info.open({ anchor: pin, map }));
      });
      if (settings.markers.length === 1) {
        map.setCenter(bounds.getCenter());
        map.setZoom(settings.zoomLocation ?? settings.zoom);
      }
      else if (settings.markers.length > 1) {
        map.fitBounds(bounds);
      }
      googleFallbacks.splice(googleFallbacks.findIndex((entry) => entry.element === element), 1);
    }
    catch (error) {
      drawFallbackMap(element, settings, true);
    }
  }

  function drawFallbackMap(element, settings, failed = false) {
    if (failed && settings.showAdminNotice && !element.dataset.acultaMapFallbackNotified) {
      const notice = document.createElement('p');
      notice.className = 'aculta-map-fallback-notice';
      notice.textContent = Drupal.t('Google Maps falhou; o mapa foi substituído pelo OpenStreetMap.');
      element.insertAdjacentElement('afterend', notice);
      element.dataset.acultaMapFallbackNotified = 'true';
    }
    if (window.L) {
      drawMap(element, settings);
    }
  }

  function clusterMarkers(markers, map) {
    const points = markers.map((marker, index) => ({
      marker,
      index,
      point: map.latLngToLayerPoint([marker.lat, marker.lng]),
    }));
    const parents = points.map((point) => point.index);
    const find = (index) => {
      if (parents[index] !== index) {
        parents[index] = find(parents[index]);
      }
      return parents[index];
    };
    const join = (left, right) => {
      const leftRoot = find(left);
      const rightRoot = find(right);
      if (leftRoot !== rightRoot) {
        parents[rightRoot] = leftRoot;
      }
    };
    const buckets = new Map();
    points.forEach((item) => {
      const cellX = Math.floor(item.point.x / clusterDistance);
      const cellY = Math.floor(item.point.y / clusterDistance);
      for (let x = cellX - 1; x <= cellX + 1; x += 1) {
        for (let y = cellY - 1; y <= cellY + 1; y += 1) {
          const neighbors = buckets.get(`${x}:${y}`) || [];
          neighbors.forEach((neighbor) => {
            if (item.point.distanceTo(neighbor.point) <= clusterDistance) {
              join(item.index, neighbor.index);
            }
          });
        }
      }
      const key = `${cellX}:${cellY}`;
      buckets.set(key, [...(buckets.get(key) || []), item]);
    });

    const groups = new Map();
    points.forEach((item) => {
      const root = find(item.index);
      groups.set(root, [...(groups.get(root) || []), item.marker]);
    });
    return Array.from(groups.values());
  }

  function addLocationMarker(layer, marker, element) {
    const iconTemplate = markerIconTemplates.get(element)?.get(marker.iconTemplateId);
    const iconMarkup = iconTemplate?.cloneNode(true) || createFallbackMarkerIcon();
    const icon = createDuotoneMarker(iconMarkup, marker.categoryColorClass);
    const pin = window.L.marker([marker.lat, marker.lng], {
      title: marker.label,
      alt: marker.label,
      keyboard: true,
      icon,
    }).addTo(layer);
    pin.bindPopup(getPopupContent(element, marker));
  }

  /** Builds a Leaflet divIcon around theme-rendered, inline SVG. */
  function createDuotoneMarker(iconMarkup, categoryColorClass = 'primary') {
    const allowedVariants = ['primary', 'highlight', 'neutral'];
    const variant = allowedVariants.includes(categoryColorClass) ? categoryColorClass : 'primary';
    const pin = document.createElement('span');
    pin.className = `aculta-map-pin aculta-map-pin--${variant}`;
    pin.setAttribute('aria-hidden', 'true');
    const inner = document.createElement('span');
    inner.className = 'aculta-map-pin__inner';
    if (iconMarkup instanceof DocumentFragment) {
      inner.appendChild(iconMarkup);
    }
    else if (iconMarkup instanceof Element) {
      inner.appendChild(iconMarkup);
    }
    pin.appendChild(inner);
    const shadow = document.createElement('span');
    shadow.className = 'aculta-map-pin__shadow';
    pin.appendChild(shadow);
    return window.L.divIcon({
      className: 'aculta-leaflet-marker-custom',
      html: pin,
      iconSize: [40, 48],
      iconAnchor: [20, 46],
      popupAnchor: [0, -42],
    });
  }

  function createFallbackMarkerIcon() {
    const icon = document.createElement('span');
    icon.className = 'aculta-icon aculta-icon--duotone aculta-icon--md';
    icon.setAttribute('aria-hidden', 'true');
    icon.textContent = '•';
    return icon;
  }

  /**
   * Clones theme-rendered popup markup when present, with a safe DOM fallback.
   */
  function getPopupContent(element, marker) {
    const templateContent = popupTemplates.get(element)?.get(marker.popupId);
    if (templateContent) {
      const content = document.createElement('div');
      content.appendChild(templateContent.cloneNode(true));
      return content;
    }

    const content = document.createElement('div');
    const title = document.createElement('strong');
    title.textContent = marker.label;
    content.appendChild(title);
    if (marker.description) {
      const description = document.createElement('p');
      description.textContent = marker.description;
      content.appendChild(description);
    }
    if (isSafeUrl(marker.url)) {
      const link = document.createElement('a');
      link.href = marker.url;
      link.textContent = Drupal.t('Ver detalhes');
      content.appendChild(link);
    }
    if (isSafeUrl(marker.routeUrl)) {
      const route = document.createElement('a');
      route.href = marker.routeUrl;
      route.target = '_blank';
      route.rel = 'noopener noreferrer';
      route.textContent = Drupal.t('Ver rota');
      content.appendChild(route);
    }
    return content;
  }

  function addClusterMarker(layer, markers, map) {
    const center = markers.reduce((result, marker) => ({
      lat: result.lat + marker.lat / markers.length,
      lng: result.lng + marker.lng / markers.length,
    }), { lat: 0, lng: 0 });
    const icon = window.L.divIcon({
      className: 'aculta-map__cluster',
      html: `<span>${markers.length}</span>`,
      iconSize: [42, 42],
      iconAnchor: [21, 21],
    });
    const cluster = window.L.marker([center.lat, center.lng], { icon, keyboard: true }).addTo(layer);
    cluster.on('click keypress', (event) => {
      if (event.type === 'keypress' && event.originalEvent.key !== 'Enter' && event.originalEvent.key !== ' ') {
        return;
      }
      const bounds = window.L.latLngBounds(markers.map((marker) => [marker.lat, marker.lng]));
      map.fitBounds(bounds, { padding: [24, 24], maxZoom: 18 });
    });
    cluster.on('add', () => {
      const element = cluster.getElement();
      if (element) {
        element.setAttribute('role', 'button');
        element.setAttribute('aria-label', Drupal.t('@count locais agrupados. Ampliar mapa.', { '@count': markers.length }));
      }
    });
  }

  function isSafeUrl(value) {
    if (!value) {
      return false;
    }
    try {
      const url = new URL(value, window.location.origin);
      return url.protocol === 'https:' || url.origin === window.location.origin;
    }
    catch (error) {
      return false;
    }
  }

})(Drupal, drupalSettings, once);
