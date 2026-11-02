/**
 * @file
 * Lógica do player SCORM do ACULTA LMS: ordem dos SCOs, navegação pedida pelo
 * conteúdo e envio do progresso ao servidor.
 *
 * Só lógica, sem DOM. Renderizar a árvore e o iframe fica para a camada de
 * apresentação, depois que o endpoint de progresso existir. Deriva de conceitos
 * do Opigno SCORM 3.2.7 (GPL-2.0-or-later), reescritos sem copiar código.
 * Registro de origem: docs/ORIGEM-OPIGNO.md do aculta_lms.
 */

(function (window, Drupal) {

  'use strict';

  /**
   * Lista os SCOs lançáveis, na ordem do manifesto.
   *
   * @param {Array<{identifier: string, href: string, title?: string}>} items
   *
   * @return {Array<{id: string, href: string, title: string}>}
   */
  function orderedScos(items) {
    return (items || []).filter(function (item) {
      return item && item.identifier && item.href;
    }).map(function (item) {
      return { id: item.identifier, href: item.href, title: item.title || item.identifier };
    });
  }

  /**
   * Retorna o SCO vizinho do atual, ou null se não houver.
   *
   * @param {Array} scos
   * @param {string} currentId
   * @param {number} direction 1 para o próximo, -1 para o anterior.
   */
  function neighbour(scos, currentId, direction) {
    var index = scos.findIndex(function (sco) { return sco.id === currentId; });
    if (index === -1) {
      return null;
    }
    var target = scos[index + direction];
    return target ? target.id : null;
  }

  /**
   * Converte o adl.nav.request do conteúdo em um SCO de destino.
   *
   * Aceita "continue", "previous" e "{target=ID}choice". Qualquer outro valor,
   * ou um alvo que não existe na lista, devolve null: o player não navega.
   *
   * @param {string} request
   * @param {Array} scos
   * @param {string} currentId
   *
   * @return {string|null}
   */
  function navigationTarget(request, scos, currentId) {
    if (request === 'continue') {
      return neighbour(scos, currentId, 1);
    }
    if (request === 'previous') {
      return neighbour(scos, currentId, -1);
    }
    var match = /^\{target=([^}]+)\}choice$/.exec(request || '');
    if (match) {
      var target = match[1];
      var exists = scos.some(function (sco) { return sco.id === target; });
      return exists ? target : null;
    }
    return null;
  }

  /**
   * Liga a API SCORM ativa ao player. Ao commit, envia o cmi ao servidor.
   *
   * O envio é feito por fetch no mesmo domínio, com o identificador do SCO e a
   * versão. O servidor é quem valida e sanitiza o cmi (ScormProgressSnapshot).
   *
   * @param {object} api
   *   API SCORM 1.2 (window.API) ou 2004 (window.API_1484_11).
   * @param {object} options
   *   version ('1.2' ou '2004'), scoId, progressUrl, fetchFn (opcional, para testes).
   */
  function bindApi(api, options) {
    var fetchFn = options.fetchFn || (typeof fetch === 'function' ? fetch : null);
    api.options = api.options || {};
    api.options.scoId = options.scoId;

    // Cada versão tem o próprio nome de evento: 1.2 usa commit12, 2004 usa commit.
    var commitEvent = options.version === '1.2' ? 'commit12' : 'commit';
    api.bind(commitEvent, function (data, scoId) {
      send(options, fetchFn, scoId, data);
    });

    // Navegação só existe no 2004. O player decide o destino com navigationTarget.
    if (options.version === '2004' && typeof options.onNavigate === 'function') {
      api.bind('navigation', function (request) {
        var target = navigationTarget(request, options.scos || [], options.scoId);
        if (target) {
          options.onNavigate(target);
        }
      });
    }
  }

  /**
   * Envia o progresso. Falhas não lançam exceção para o conteúdo; o conteúdo
   * continua funcionando e o próximo commit tenta de novo.
   */
  function send(options, fetchFn, scoId, data) {
    if (!fetchFn || !options.progressUrl) {
      return null;
    }
    var body = JSON.stringify({ version: options.version, scoId: scoId, cmi: data.cmi || {} });
    return fetchFn(options.progressUrl, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json' },
      body: body
    }).catch(function () {
      return null;
    });
  }

  var player = {
    orderedScos: orderedScos,
    neighbour: neighbour,
    navigationTarget: navigationTarget,
    bindApi: bindApi
  };

  if (Drupal) {
    if (Drupal.acultaLms === undefined) {
      Drupal.acultaLms = {};
    }
    Drupal.acultaLms.scormPlayer = player;
  }
  if (window) {
    window.acultaScormPlayer = player;
  }

})(window, window.Drupal);
