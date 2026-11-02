/**
 * @file
 * API SCORM 1.2 do ACULTA LMS, usada pelo conteúdo para falar com o LMS.
 *
 * Derivada de conceitos do Opigno SCORM 3.2.7 (js/lib/api-1.2.js, GPL-2.0-or-later).
 * Reescrita, não copiada: sem eval, sem console de depuração, sem referência
 * cruzada à API 2004, e com códigos de retorno conforme a especificação
 * (LMSInitialize, LMSSetValue e LMSCommit devolvem "false" em erro).
 * Registro de origem: docs/ORIGEM-OPIGNO.md do aculta_lms.
 *
 * Esta API não grava nada por conta própria. Ao receber um commit, dispara o
 * evento commit12, que o player do ACULTA liga ao endpoint de progresso. Onde o
 * progresso fica guardado é decisão do ACULTA LMS (tentativa da atividade).
 */

(function ($, Drupal, window) {

  'use strict';

  /**
   * Códigos de erro do SCORM 1.2.
   */
  var ERROR_TEXT = {
    '0': 'No error',
    '101': 'General exception',
    '201': 'Invalid argument error',
    '202': 'Element cannot have children',
    '203': 'Element not an array - cannot have count',
    '301': 'Not initialized',
    '401': 'Not implemented error',
    '402': 'Invalid set value, element is a keyword',
    '403': 'Element is read only',
    '404': 'Element is write only',
    '405': 'Incorrect data type'
  };

  /**
   * Caminhos CMI válidos no SCORM 1.2. Índices de coleção usam "n".
   */
  var VALID_PATHS = [
    'cmi.core._children', 'cmi.core.student_id', 'cmi.core.student_name',
    'cmi.core.lesson_location', 'cmi.core.credit', 'cmi.core.lesson_status',
    'cmi.core.entry', 'cmi.core.score_children', 'cmi.core.score.raw',
    'cmi.core.score.max', 'cmi.core.score.min', 'cmi.core.score._children',
    'cmi.core.total_time', 'cmi.core.exit', 'cmi.core.session_time',
    'cmi.suspend_data', 'cmi.launch_data', 'cmi.comments',
    'cmi.comments_from_lms', 'cmi.objectives._children', 'cmi.objectives._count',
    'cmi.objectives.n.id', 'cmi.objectives.n.score._children',
    'cmi.objectives.n.score.raw', 'cmi.objectives.n.score.max',
    'cmi.objectives.n.score.min', 'cmi.objectives.n.status',
    'cmi.student_data._children', 'cmi.student_data.mastery_score',
    'cmi.student_data.max_time_allowed', 'cmi.student_data.time_limit_action',
    'cmi.student_preference._children', 'cmi.student_preference.audio',
    'cmi.student_preference.language', 'cmi.student_preference.speed',
    'cmi.student_preference.text', 'adl.nav.request', 'cmi.interactions._children',
    'cmi.interactions._count', 'cmi.interactions.n.id', 'cmi.interactions.n.type',
    'cmi.interactions.n.result', 'cmi.interactions.n.timestamp',
    'cmi.interactions.n.student_response', 'cmi.interactions.n.correct_responses._count',
    'cmi.interactions.n.correct_responses.n.pattern',
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
   * API SCORM 1.2.
   *
   * @param {Object} options
   *   scoId: identificador do SCO que está em execução (usado no commit).
   *
   * @constructor
   */
  function ScormApi12(options) {
    this.options = options || {};
    this.error = '0';
    this.initialized = false;
    this.finished = false;
    this.registered = [];
    this.readOnly = [];
    this.writeOnly = [];
    this.data = { cmi: {} };
    this.listeners = {
      initialize12: [],
      'pre-setvalue12': [],
      'pre-commit12': [],
      commit12: [],
      'post-commit12': [],
      terminate12: []
    };
  }

  /**
   * Registra caminhos que o conteúdo pode usar, com propriedades opcionais.
   *
   * @param {Object} paths
   *   Mapa caminho → { readOnly: bool } ou { writeOnly: bool }.
   */
  ScormApi12.prototype.registerCMIPaths = function (paths) {
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
  ScormApi12.prototype.registerCMIData = function (path, value) {
    this.setTree(path, value);
  };

  ScormApi12.prototype.LMSInitialize = function (argument) {
    if (argument !== '') {
      this.error = '201';
      return 'false';
    }
    if (this.initialized || this.finished) {
      this.error = '101';
      return 'false';
    }
    this.initialized = true;
    this.trigger('initialize12', argument);
    this.error = '0';
    return 'true';
  };

  ScormApi12.prototype.LMSFinish = function (argument) {
    if (argument !== '') {
      this.error = '201';
      return 'false';
    }
    if (!this.initialized) {
      this.error = '301';
      return 'false';
    }
    if (this.finished) {
      this.error = '101';
      return 'false';
    }
    var committed = this.commit();
    if (committed !== 'true') {
      return 'false';
    }
    this.finished = true;
    this.trigger('terminate12', this.data);
    this.error = '0';
    return 'true';
  };

  ScormApi12.prototype.LMSGetValue = function (path) {
    if (!this.initialized) {
      this.error = '301';
      return '';
    }
    if (this.finished) {
      this.error = '101';
      return '';
    }
    if (typeof path !== 'string' || path === '') {
      this.error = '201';
      return '';
    }
    var key = normalizePath(path);
    if (VALID_PATHS.indexOf(key) === -1) {
      this.error = '201';
      return '';
    }
    if (this.registered.indexOf(key) === -1) {
      this.error = '401';
      return '';
    }
    if (this.writeOnly.indexOf(key) !== -1) {
      this.error = '404';
      return '';
    }
    var value = this.getTree(path);
    if (value === undefined || value === null) {
      // Valor ainda não definido: SCORM 1.2 devolve vazio sem erro.
      this.error = '0';
      return '';
    }
    this.error = '0';
    return String(value);
  };

  ScormApi12.prototype.LMSSetValue = function (path, value) {
    if (!this.initialized) {
      this.error = '301';
      return 'false';
    }
    if (this.finished) {
      this.error = '101';
      return 'false';
    }
    if (typeof path !== 'string' || path === '') {
      this.error = '201';
      return 'false';
    }
    if (typeof value !== 'string' && typeof value !== 'number') {
      this.error = '405';
      return 'false';
    }
    var key = normalizePath(path);
    // Palavras-chave _children e _count são somente leitura, mesmo sem registro.
    if (/\._(children|count)$/.test(key)) {
      this.error = '403';
      return 'false';
    }
    if (VALID_PATHS.indexOf(key) === -1) {
      this.error = '201';
      return 'false';
    }
    if (this.registered.indexOf(key) === -1) {
      this.error = '401';
      return 'false';
    }
    if (this.readOnly.indexOf(key) !== -1) {
      this.error = '403';
      return 'false';
    }
    this.trigger('pre-setvalue12', path, value);
    if (!this.setTree(path, String(value))) {
      this.error = '201';
      return 'false';
    }
    this.error = '0';
    return 'true';
  };

  /**
   * Dispara o commit. O player liga o evento commit12 ao endpoint de progresso.
   */
  ScormApi12.prototype.LMSCommit = function (argument) {
    return this.commit(argument);
  };

  ScormApi12.prototype.commit = function (argument) {
    if (argument !== undefined && argument !== '') {
      this.error = '201';
      return 'false';
    }
    if (!this.initialized) {
      this.error = '301';
      return 'false';
    }
    if (this.finished) {
      this.error = '101';
      return 'false';
    }
    this.trigger('pre-commit12', this.data);
    var scoId = this.options.scoId || this.findScoId();
    if (!scoId) {
      this.error = '101';
      return 'false';
    }
    this.trigger('commit12', this.data, scoId);
    this.trigger('post-commit12', this.data);
    this.error = '0';
    return 'true';
  };

  ScormApi12.prototype.LMSGetLastError = function () {
    return this.error;
  };

  ScormApi12.prototype.LMSGetErrorString = function (code) {
    return ERROR_TEXT[String(code)] || '';
  };

  ScormApi12.prototype.LMSGetDiagnostic = function () {
    return '';
  };

  /**
   * Localiza o SCO pelo iframe do player, como o Opigno fazia.
   *
   * Usa jQuery apenas para essa busca. Retorna NULL se o iframe não existir.
   */
  ScormApi12.prototype.findScoId = function () {
    var iframe = $(document).find('.scorm-ui-player-iframe-wrapper iframe')[0];
    if (!iframe || !iframe.src) {
      return null;
    }
    var parts = iframe.src.split('/');
    return parts[parts.length - 1] || null;
  };

  ScormApi12.prototype.bind = function (event, callback) {
    if (!this.listeners[event]) {
      throw new Error('Evento SCORM desconhecido: ' + event);
    }
    this.listeners[event].push(callback);
  };

  ScormApi12.prototype.trigger = function (event) {
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
  ScormApi12.prototype.getTree = function (path) {
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
   * Grava um caminho na árvore. Cria objetos e arrays conforme o caminho.
   *
   * Índice de coleção só pode ser igual ao tamanho atual (novo elemento) ou
   * menor (elemento existente). Índice acima disso é recusado.
   *
   * @return {boolean} FALSE se o caminho for estruturalmente inválido.
   */
  ScormApi12.prototype.setTree = function (path, value) {
    var parts = splitPath(path);
    var node = this.data;
    for (var i = 0; i < parts.length - 1; i++) {
      var part = parts[i];
      var next = parts[i + 1];
      if (typeof part === 'number') {
        // Índice: só existe ou é o próximo elemento do array.
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

  var api = new ScormApi12({});

  // Ponto de entrada para o conteúdo. O player define o SCO antes de iniciar.
  window.API = api;

  if (Drupal && Drupal.acultaLms === undefined) {
    Drupal.acultaLms = {};
  }
  if (Drupal) {
    Drupal.acultaLms.scorm12 = api;
    Drupal.acultaLms.ScormApi12 = ScormApi12;
  }

})(jQuery, window.Drupal, window);
