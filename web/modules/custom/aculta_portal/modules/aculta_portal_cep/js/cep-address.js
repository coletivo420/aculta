(function (Drupal, drupalSettings, once) {
  'use strict';

  const labels = {
    postal_code: Drupal.t('CEP'),
    address_line1: Drupal.t('Endereço / Logradouro'),
    address_line2: Drupal.t('Complemento'),
    dependent_locality: Drupal.t('Bairro'),
    locality: Drupal.t('Cidade'),
    administrative_area: Drupal.t('Estado'),
    country_code: Drupal.t('País'),
    sorting_code: Drupal.t('Código postal'),
  };

  const digits = (value) => (value || '').replace(/\D+/g, '').slice(0, 8);
  // Máscara 00000-000 (o envio e a consulta usam só os dígitos).
  const formatCep = (value) => {
    const cep = digits(value);
    return cep.length > 5 ? `${cep.slice(0, 5)}-${cep.slice(5)}` : cep;
  };

  function getField(prefix, key, container) {
    const expected = `${prefix}[address][${key}]`;
    return Array.from(container.querySelectorAll('[name]')).find((field) => field.name === expected) || null;
  }

  function setLabel(field, text) {
    if (!field?.id) return;
    const label = document.querySelector(`label[for="${CSS.escape(field.id)}"]`);
    if (!label) return;
    const textNode = Array.from(label.childNodes).find((node) => node.nodeType === Node.TEXT_NODE);
    if (textNode) textNode.nodeValue = `${text} `;
    else label.insertBefore(document.createTextNode(`${text} `), label.firstChild);
  }

  function statusFor(postal) {
    const wrapper = postal.closest('.form-item, .js-form-item') || postal.parentElement;
    let status = wrapper?.querySelector('[data-aculta-cep-status]');
    if (!status && wrapper) {
      status = document.createElement('div');
      status.className = 'aculta-cep-status';
      status.dataset.acultaCepStatus = 'true';
      status.setAttribute('role', 'status');
      status.setAttribute('aria-live', 'polite');
      status.setAttribute('aria-atomic', 'true');
      wrapper.append(status);
    }
    return status;
  }

  function announce(status, text, error = false) {
    if (!status) return;
    status.textContent = text;
    status.setAttribute('role', error ? 'alert' : 'status');
  }

  async function lookup(cep) {
    const base = drupalSettings?.path?.baseUrl || '/';
    const prefix = drupalSettings?.path?.pathPrefix || '';
    const response = await fetch(`${base}${prefix}aculta_portal_cep/${cep}`, {
      credentials: 'same-origin',
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    });
    if (!response.ok) throw new Error('lookup-failed');
    const result = await response.json();
    if (!result?.ok || !result?.data) throw new Error('lookup-failed');
    return result;
  }

  function dispatchChange(field) {
    if (!field) return;
    field.dispatchEvent(new Event('input', { bubbles: true }));
    field.dispatchEvent(new Event('change', { bubbles: true }));
  }

  // Preenchimento do CEP. O Estado dispara um rebuild AJAX do endereço, que substitui os campos do markup. Por isso
  // os campos vêm primeiro, e o Estado por último. O preenchimento fica guardado e é reaplicado nos campos vazios
  // do markup novo (attach), sem disparar outro rebuild.
  const pendingFill = new Map();
  // CEP já resolvido por endereço (prefixo do campo). Fica no módulo, e não no elemento: o AJAX troca o elemento.
  const resolvedCep = new Map();
  // Campos do endereço que vieram do CEP (banco) ficam bloqueados para edição, mas visíveis. Número, S/N e complemento
  // nunca são bloqueados. Campos que a busca não preencheu continuam editáveis.
  const LOCKABLE = ['address_line1', 'dependent_locality', 'locality', 'administrative_area'];
  const lockedValues = new Map();

  function applyLocks(prefix, container) {
    const locks = lockedValues.get(prefix) || {};
    LOCKABLE.forEach((key) => {
      const field = getField(prefix, key, container);
      if (!field) return;
      const locked = Object.prototype.hasOwnProperty.call(locks, key);
      field.classList.toggle('aculta-cep-locked', locked);
      if (field.tagName === 'SELECT') {
        if (locked) field.setAttribute('aria-disabled', 'true');
        else field.removeAttribute('aria-disabled');
        // Select não tem readonly: a mudança é desfeita, e o valor enviado continua o do CEP.
        if (!field.dataset.acultaLockBound) {
          field.dataset.acultaLockBound = 'true';
          field.addEventListener('change', () => {
            const current = lockedValues.get(prefix) || {};
            if (Object.prototype.hasOwnProperty.call(current, key) && field.value !== current[key]) {
              field.value = current[key];
            }
          });
        }
      }
      else {
        field.readOnly = locked;
        if (locked) field.setAttribute('aria-readonly', 'true');
        else field.removeAttribute('aria-readonly');
      }
    });
  }

  function clearLocks(prefix, container) {
    lockedValues.delete(prefix);
    applyLocks(prefix, container);
  }

  // Campos que a busca deve escrever: a troca de CEP escreve o endereço inteiro; o primeiro preenchimento só os vazios.
  function keysToWrite(prefix, container, values, replace) {
    if (replace) return values;
    const out = {};
    Object.keys(values).forEach((key) => {
      const field = getField(prefix, key, container);
      if (field && field.value === '' && values[key] !== undefined && values[key] !== null) out[key] = values[key];
    });
    return out;
  }

  function applyFill(prefix, container, values, { onlyEmpty = false } = {}) {
    Object.keys(values).filter((key) => key !== 'administrative_area').forEach((key) => {
      const field = getField(prefix, key, container);
      const value = values[key];
      if (field && value !== undefined && value !== null && (!onlyEmpty || field.value === '')) {
        field.value = value;
      }
    });
    const state = getField(prefix, 'administrative_area', container);
    if (state && values.administrative_area && (!onlyEmpty || state.value === '')) {
      state.value = values.administrative_area;
      dispatchChange(state);
    }
  }

  /**
   * Comportamento do Número e da marca S/N: só dígitos, até 10, e número desabilitado e vazio com S/N marcado.
   * A posição e as colunas do bloco (CEP primeiro, Número/S/N/Complemento na mesma linha) são do tema do site
   * (js/address-layout.js). A marca S/N e o número são subcampos do endereço (StreetNumberField).
   */
  function bindStreetNumber(numInput, snInput) {
    numInput.setAttribute('maxlength', '10');
    numInput.setAttribute('inputmode', 'numeric');
    numInput.setAttribute('pattern', '[0-9]{1,10}');
    if (!numInput.dataset.acultaNumberBound) {
      numInput.dataset.acultaNumberBound = 'true';
      numInput.addEventListener('input', () => {
        numInput.value = numInput.value.replace(/\D/g, '').slice(0, 10);
      });
    }
    if (snInput && !snInput.dataset.acultaSnBound) {
      snInput.dataset.acultaSnBound = 'true';
      snInput.addEventListener('change', () => syncStreetNumber(numInput, snInput));
    }
    syncStreetNumber(numInput, snInput);
  }

  function syncStreetNumber(numInput, snInput) {
    if (!snInput) return;
    if (snInput.checked) {
      numInput.value = '';
      numInput.disabled = true;
      numInput.required = false;
    }
    else {
      numInput.disabled = false;
      numInput.required = true;
    }
  }

  function attach(context) {
    // Reivindica os campos com o mesmo id do contrib (data-once): o behavior do contrib não os processa de novo.
    const postalFields = once('aculta-portal-cep', 'input[name$="[address][postal_code]"], input[name$="[postal_code]"]', context);
    postalFields.forEach((postal) => {
      const name = postal.name || '';
      const marker = '[address][';
      const markerIndex = name.lastIndexOf(marker);
      if (markerIndex < 0) return;

      const prefix = name.slice(0, markerIndex);
      const container = postal.closest('form') || document;
      const status = statusFor(postal);
      const numInput = container.querySelector('input[name$="[street_number]"]');
      const snInput = container.querySelector('input[name$="[street_number_sn]"]');
      if (numInput) bindStreetNumber(numInput, snInput);

      Object.entries(labels).forEach(([key, label]) => setLabel(getField(prefix, key, container), label));
      // CEP já salvo no formulário é a base da comparação: digitar outro CEP troca o endereço inteiro.
      if (!resolvedCep.has(prefix) && digits(postal.value).length === 8) {
        resolvedCep.set(prefix, digits(postal.value));
      }
      const pending = pendingFill.get(prefix);
      if (pending) {
        applyFill(prefix, container, pending, { onlyEmpty: false });
        // Vale só para o rebuild que a própria busca disparou: depois disso, o que a pessoa editar fica como está.
        pendingFill.delete(prefix);
        // O rebuild apaga o aviso de status: anuncia de novo, para leitor de tela.
        announce(statusFor(postal), Drupal.t('Endereço localizado. Confira os dados e complete o número manualmente.'));
      }
      applyLocks(prefix, container);
      const complement = getField(prefix, 'address_line2', container);
      if (complement) complement.setAttribute('placeholder', Drupal.t('Complemento'));

      let timer;
      let activeCep = '';
      postal.addEventListener('input', () => {
        window.clearTimeout(timer);
        const cep = digits(postal.value);
        if (cep.length !== 8 || cep === resolvedCep.get(prefix) || cep === activeCep) return;
        timer = window.setTimeout(async () => {
          const requestedCep = digits(postal.value);
          if (requestedCep.length !== 8 || requestedCep === resolvedCep.get(prefix)) return;
          activeCep = requestedCep;
          clearLocks(prefix, container);
          announce(status, Drupal.t('Consultando o CEP.'));
          try {
            const result = await lookup(requestedCep);
            if (digits(postal.value) !== requestedCep) return;
            const data = result.data;
            // Campos do Commerce/Address: bairro em dependent_locality. O complemento do ViaCEP não é usado.
            const values = {
              administrative_area: data.administrative_area,
              locality: data.locality,
              address_line1: data.address_line1,
              dependent_locality: data.dependent_locality || '',
            };
            // Primeiro CEP: só preenche campos vazios, sem apagar o que a pessoa já digitou. Se o CEP mudou depois de um
            // preenchimento, o endereço é do novo CEP: rua, bairro, cidade e estado são substituídos. Número, S/N e
            // complemento não fazem parte de values e nunca são alterados.
            const previous = resolvedCep.get(prefix) || '';
            const replace = previous !== '' && previous !== requestedCep;
            const toWrite = keysToWrite(prefix, container, values, replace);
            pendingFill.set(prefix, toWrite);
            applyFill(prefix, container, toWrite, { onlyEmpty: false });
            resolvedCep.set(prefix, requestedCep);
            const locks = {};
            Object.keys(toWrite).forEach((key) => {
              if (LOCKABLE.includes(key) && toWrite[key]) locks[key] = toWrite[key];
            });
            lockedValues.set(prefix, locks);
            applyLocks(prefix, container);
            postal.value = formatCep(requestedCep);
            postal.dataset.acultaCepResolved = requestedCep;
            announce(status, Drupal.t('Endereço localizado. Confira os dados e complete o número manualmente.'));
            const street = getField(prefix, 'address_line1', container);
            if (street) street.focus();
          }
          catch (error) {
            if (digits(postal.value) === requestedCep) {
              announce(status, Drupal.t('Não foi possível localizar este CEP. Confira o número ou preencha o endereço manualmente.'), true);
            }
          }
          finally {
            activeCep = '';
          }
        }, 250);
      });

      postal.addEventListener('change', () => {
        if (postal.value !== formatCep(postal.value)) {
          postal.value = formatCep(postal.value);
          postal.dispatchEvent(new Event('input', { bubbles: true }));
        }
      });
      // Máscara enquanto digita (o cursor vai para o fim, que é onde o CEP é digitado).
      postal.addEventListener('input', () => {
        const formatted = formatCep(postal.value);
        if (postal.value !== formatted && digits(postal.value).length > 0) {
          postal.value = formatted;
        }
      });
    });
  }

  // Nome próprio (não colide com o behavior cepAutocomplete do módulo contrib). Esta biblioteca é dependência
  // do contrib, então executa antes dele e reivindica os campos (ver once acima).
  Drupal.behaviors.acultaCepAddress = { attach };
})(Drupal, drupalSettings, once);
