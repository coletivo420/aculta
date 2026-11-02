/**
 * @file
 * API SCORM 2004 do ACULTA LMS, usada pelo conteúdo para falar com o LMS.
 *
 * Derivada de conceitos do Opigno SCORM 3.2.7 (js/lib/api-2004.js, GPL-2.0-or-later).
 * Reescrita, não copiada: sem eval, sem depuração, sem testes internos do Opigno,
 * sem manipulação de DOM para trocar de SCO, e com códigos de retorno da
 * especificação (Initialize, Terminate, SetValue e Commit devolvem "false" em erro).
 * Registro de origem: docs/ORIGEM-OPIGNO.md do aculta_lms.
 *
 * Expõe window.API_1484_11, nome que o conteúdo SCORM 2004 procura.
 *
 * Esta API não grava nada por conta própria. Ao receber commit, dispara o evento
 * commit para o player do ACULTA, que liga o evento ao endpoint de progresso.
 * Pedido de navegação (adl.nav.request) é entregue ao player no Terminate, pelo
 * evento navigation; o player decide qual SCO carregar.
 */

(function ($, Drupal, window) {

  'use strict';

  /**
   * Códigos de erro do SCORM 2004 RTE.
   */
  var ERROR_TEXT = {
    '0': 'No Error',
    '101': 'General Exception',
    '102': 'General Initialization Failure',
    '103': 'Already Initialized',
    '104': 'Content Instance Terminated',
    '111': 'General Termination Failure',
    '112': 'Termination Before Initialization',
    '113': 'Termination After Termination',
    '122': 'Retrieve Data Before Initialization',
    '123': 'Retrieve Data After Termination',
    '132': 'Store Data Before Initialization',
    '133': 'Store Data After Termination',
    '142': 'Commit Before Initialization',
    '143': 'Commit After Termination',
    '201': 'General Argument Error',
    '301': 'General Get Failure',
    '351': 'General Set Failure',
    '391': 'General Commit Failure',
    '401': 'Undefined Data Model Element',
    '402': 'Unimplemented Data Model Element',
    '403': 'Data Model Element Value Not Initialized',
    '404': 'Data Model Element Is Read Only',
    '405': 'Data Model Element Is Write Only',
    '406': 'Data Model Element Type Mismatch',
    '407': 'Data Model Element Value Out Of Range',
    '408': 'Data Model Dependency Not Established'
  };

  /**
   * Caminhos CMI válidos no SCORM 2004. Índices de coleção usam "n".
   */
  var VALID_PATHS = [
    'cmi._version', 'cmi.exit', 'cmi.entry', 'cmi.success_status',
    'cmi.completion_status', 'cmi.score.raw', 'cmi.score.min', 'cmi.score.max',
    'cmi.score.scaled', 'cmi.location', 'cmi.objectives', 'cmi.objectives._children',
    'cmi.objectives._count', 'cmi.objectives.n.score', 'cmi.objectives.n.score.scaled',
    'cmi.objectives.n.score.raw', 'cmi.objectives.n.score.min', 'cmi.objectives.n.score.max',
    'cmi.objectives.n.id', 'cmi.objectives.n.success_status',
    'cmi.objectives.n.completion_status', 'cmi.objectives.n.progress_measure',
    'cmi.objectives.n.description', 'cmi.suspend_data', 'cmi.comments_from_learner',
    'cmi.comments_from_learner._children', 'cmi.comments_from_learner._count',
    'cmi.comments_from_learner.n.comment', 'cmi.comments_from_learner.n.location',
    'cmi.comments_from_learner.n.timestamp', 'cmi.learner_id', 'cmi.learner_name',
    'cmi.learner_preference._children', 'cmi.learner_preference.audio_level',
    'cmi.learner_preference.language', 'cmi.learner_preference.delivery_speed',
    'cmi.learner_preference.audio_captioning', 'adl.nav.request', 'cmi.interactions',
    'cmi.interactions._children', 'cmi.interactions._count', 'cmi.interactions.n.id',
    'cmi.interactions.n.type', 'cmi.interactions.n.description', 'cmi.interactions.n.result',
    'cmi.interactions.n.timestamp', 'cmi.interactions.n.learner_response',
    'cmi.interactions.n.correct_responses._count', 'cmi.interactions.n.correct_responses.n.pattern',
    'cmi.interactions.n.objectives._count', 'cmi.interactions.n.objectives.n.id'
  ];

  /**
   * Converte "cmi.objectives.2.id" em "cmi.objectives.n.id".
   */
  function normalizePath(path) {
    return path.replace(/\.[0-9]+(?=\.|$)/g, '.n');
  }

  /**
   * Divide um caminho em partes e converte índices numéricos.
   */
  function splitPath(path) {
    return path.split('.').map(function (part) {
      return /^[0-9]+$/.test(part) ? parseInt(part, 10) : part;
    });
  }

  /**
   * API SCORM 2004.
   *
   * @param {Object} options
   *   scoId: identificador do SCO em execução (usado no commit).
   *
   * @constructor
   */
  function ScormApi2004(options) {
    this.options = options || {};
    this.error = '0';
    this.initialized = false;
    this.terminated = false;
    this.navRequest = '_none_';
    this.registered = [];
    this.readOnly = [];
    this.writeOnly = [];
    this.data = { cmi: { _version: '1.0' } };
    this.registered.push('cmi._version');
    this.readOnly.push('cmi._version');
    this.listeners = {
      initialize: [],
      'pre-setvalue': [],
      'pre-commit': [],
      commit: [],
      'post-commit': [],
      navigation: [],
      terminate: []
    };
  }

  /**
   * Registra caminhos que o conteúdo pode usar, com propriedades opcionais.
   *
   * @param {Object} paths
   *   Mapa caminho → { readOnly: bool } ou { writeOnly: bool }.
   */
  ScormApi2004.prototype.registerCMIPaths = function (paths) {
    var self = this;
    Object.keys(paths).forEach(function (path) {
      var key = normalizePath(path);
      self.registered.push(key);
      if (paths[path].readOnly) {
        self.readOnly.push(key);
      }
      else if (paths[path].writeOnly) {
        self.writeOnly.push(key);
      }
    });
  };

  /**
   * Preenche dados iniciais (por exemplo, o estado salvo da tentativa).
   *
   * Ignora validação de caminho; use só com dados confiáveis do servidor.
   */
  ScormApi2004.prototype.registerCMIData = function (path, value) {
    this.setTree(path, value);
  };

  ScormApi2004.prototype.Initialize = function (argument) {
    if (argument !== '') {
      this.error = '201';
      return 'false';
    }
    if (this.terminated) {
      this.error = '104';
      return 'false';
    }
    if (this.initialized) {
      this.error = '103';
      return 'false';
    }
    this.initialized = true;
    this.trigger('initialize', argument);
    this.error = '0';
    return 'true';
  };

  ScormApi2004.prototype.Terminate = function (argument) {
    if (argument !== '') {
      this.error = '201';
      return 'false';
    }
    if (this.terminated) {
      this.error = '113';
      return 'false';
    }
    if (!this.initialized) {
      this.error = '112';
      return 'false';
    }
    if (this.Commit('') !== 'true') {
      this.error = '391';
      return 'false';
    }
    this.initialized = false;
    this.terminated = true;
    if (this.navRequest !== '_none_') {
      this.trigger('navigation', this.navRequest);
    }
    this.trigger('terminate', this.data);
    this.error = '0';
    return 'true';
  };

  ScormApi2004.prototype.GetValue = function (path) {
    if (this.terminated) {
      this.error = '123';
      return '';
    }
    if (!this.initialized) {
      this.error = '122';
      return '';
    }
    if (typeof path !== 'string' || path === '') {
      this.error = '201';
      return '';
    }
    if (path === 'adl.nav.request') {
      this.error = '0';
      return this.navRequest;
    }
    var key = normalizePath(path);
    if (/^adl\./.test(key) || VALID_PATHS.indexOf(key) === -1) {
      this.error = '401';
      return '';
    }
    if (this.writeOnly.indexOf(key) !== -1) {
      this.error = '405';
      return '';
    }
    if (this.registered.indexOf(key) === -1) {
      this.error = '402';
      return '';
    }
    var value = this.getTree(path);
    if (value === undefined || value === null) {
      if (/\._count$/.test(path)) {
        this.error = '0';
        return '0';
      }
      this.error = '403';
      return '';
    }
    this.error = '0';
    return String(value);
  };

  ScormApi2004.prototype.SetValue = function (path, value) {
    if (this.terminated) {
      this.error = '133';
      return 'false';
    }
    if (!this.initialized) {
      this.error = '132';
      return 'false';
    }
    if (typeof path !== 'string' || path === '') {
      this.error = '201';
      return 'false';
    }
    if (typeof value !== 'string' && typeof value !== 'number') {
      this.error = '406';
      return 'false';
    }
    var key = normalizePath(path);
    if (key === 'adl.nav.request') {
      this.navRequest = String(value);
      this.error = '0';
      return 'true';
    }
    // Palavras-chave _children e _count são somente leitura, mesmo sem registro.
    if (/\._(children|count)$/.test(key)) {
      this.error = '404';
      return 'false';
    }
    if (/^adl\./.test(key) || VALID_PATHS.indexOf(key) === -1) {
      this.error = '401';
      return 'false';
    }
    if (this.registered.indexOf(key) === -1) {
      this.error = '402';
      return 'false';
    }
    if (this.readOnly.indexOf(key) !== -1) {
      this.error = '404';
      return 'false';
    }
    this.trigger('pre-setvalue', path, value);
    if (!this.setTree(path, String(value))) {
      this.error = '351';
      return 'false';
    }
    this.error = '0';
    return 'true';
  };

  ScormApi2004.prototype.Commit = function (argument) {
    if (argument !== '') {
      this.error = '201';
      return 'false';
    }
    if (this.terminated) {
      this.error = '143';
      return 'false';
    }
    if (!this.initialized) {
      this.error = '142';
      return 'false';
    }
    this.trigger('pre-commit', this.data);
    var scoId = this.options.scoId || this.findScoId();
    if (!scoId) {
      this.error = '391';
      return 'false';
    }
    this.trigger('commit', this.data, scoId);
    this.trigger('post-commit', this.data);
    this.error = '0';
    return 'true';
  };

  ScormApi2004.prototype.GetLastError = function () {
    return this.error;
  };

  ScormApi2004.prototype.GetErrorString = function (code) {
    return ERROR_TEXT[String(code)] || '';
  };

  ScormApi2004.prototype.GetDiagnostic = function () {
    return '';
  };

  /**
   * Localiza o SCO pelo iframe do player. Usa jQuery apenas para essa busca.
   */
  ScormApi2004.prototype.findScoId = function () {
    var iframe = $(document).find('.scorm-ui-player-iframe-wrapper iframe')[0];
    if (!iframe || !iframe.src) {
      return null;
    }
    var parts = iframe.src.split('/');
    return parts[parts.length - 1] || null;
  };

  ScormApi2004.prototype.bind = function (event, callback) {
    if (!this.listeners[event]) {
      throw new Error('Evento SCORM desconhecido: ' + event);
    }
    this.listeners[event].push(callback);
  };

  ScormApi2004.prototype.trigger = function (event) {
    var args = Array.prototype.slice.call(arguments, 1);
    if (!this.listeners[event]) {
      throw new Error('Evento SCORM desconhecido: ' + event);
    }
    this.listeners[event].forEach(function (callback) {
      callback.apply(null, args);
    });
  };

  /**
   * Lê um caminho da árvore de dados. Retorna undefined se não existir.
   */
  ScormApi2004.prototype.getTree = function (path) {
    var node = this.data;
    var parts = splitPath(path);
    for (var i = 0; i < parts.length; i++) {
      if (node === null || typeof node !== 'object') {
        return undefined;
      }
      if (parts[i] === '_count' && Array.isArray(node)) {
        return node.length;
      }
      node = node[parts[i]];
    }
    return node;
  };

  /**
   * Grava um caminho na árvore. Índice de coleção só pode ser o próximo
   * elemento do array ou um existente. Índice com lacuna é recusado.
   *
   * @return {boolean} FALSE se o caminho for estruturalmente inválido.
   */
  ScormApi2004.prototype.setTree = function (path, value) {
    var parts = splitPath(path);
    var node = this.data;
    for (var i = 0; i < parts.length - 1; i++) {
      var part = parts[i];
      var next = parts[i + 1];
      if (typeof part === 'number') {
        if (!Array.isArray(node) || part > node.length) {
          return false;
        }
      }
      if (node[part] === undefined || node[part] === null) {
        node[part] = typeof next === 'number' ? [] : {};
      }
      if (typeof next === 'number' && !Array.isArray(node[part])) {
        return false;
      }
      node = node[part];
    }
    var last = parts[parts.length - 1];
    if (typeof last === 'number') {
      if (!Array.isArray(node) || last > node.length) {
        return false;
      }
    }
    node[last] = value;
    return true;
  };

  var api = new ScormApi2004({});

  // Nome exigido pelo conteúdo SCORM 2004. O player define o SCO antes de iniciar.
  window.API_1484_11 = api;

  if (Drupal) {
    if (Drupal.acultaLms === undefined) {
      Drupal.acultaLms = {};
    }
    Drupal.acultaLms.scorm2004 = api;
    Drupal.acultaLms.ScormApi2004 = ScormApi2004;
  }

})(jQuery, window.Drupal, window);
