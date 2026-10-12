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
      status.className = 'description aculta-cep-status';
      status.dataset.acultaCepStatus = 'true';
      status.setAttribute('role', 'status');
      status.setAttribute('aria-live', 'polite');
      status.setAttribute('aria-atomic', 'true');
      wrapper.append(status);
    }
    return status;
  }

  function movePostalFirst(postal) {
    const row = postal.closest('.form-item, .js-form-item');
    const parent = row?.parentElement;
    if (row && parent && !postal.dataset.acultaCepMoved) {
      parent.insertBefore(row, parent.firstChild);
      postal.dataset.acultaCepMoved = 'true';
    }
  }

  function announce(status, text, error = false) {
    if (!status) return;
    status.textContent = text;
    status.setAttribute('role', error ? 'alert' : 'status');
  }

  async function lookup(cep) {
    const base = drupalSettings?.path?.baseUrl || '/';
    const prefix = drupalSettings?.path?.pathPrefix || '';
    const response = await fetch(`${base}${prefix}cep-autocomplete/viacep/${cep}`, {
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
   * Número, S/N e Complemento na mesma linha, logo depois da rua. Número: só dígitos, até 10. Com S/N marcado, o
   * número fica desabilitado e vazio. A marca S/N e o número são subcampos do endereço (StreetNumberField).
   */
  function layoutStreetNumber(prefix, container) {
    const numInput = container.querySelector('input[name$="[street_number]"]');
    const snInput = container.querySelector('input[name$="[street_number_sn]"]');
    if (!numInput) return;
    const rowOf = (el) => el?.closest('.form-item, .js-form-item, .form-check');
    const numberRow = rowOf(numInput);
    const snRow = snInput ? rowOf(snInput) : null;
    const streetRow = rowOf(getField(prefix, 'address_line1', container));
    const complementRow = rowOf(getField(prefix, 'address_line2', container));
    if (numberRow && streetRow && numberRow !== streetRow) {
      streetRow.after(numberRow);
    }
    if (numberRow && complementRow && numberRow.parentElement === complementRow.parentElement && !numberRow.closest('.portal-address-number-row')) {
      const row = document.createElement('div');
      row.className = 'row g-2 align-items-end portal-address-number-row';
      // Colunas de largura do conteúdo: número e S/N encolhem para caber; o complemento ocupa o resto da linha.
      const numCol = document.createElement('div');
      numCol.className = 'col-auto';
      const snCol = document.createElement('div');
      snCol.className = 'col-auto pb-2';
      const compCol = document.createElement('div');
      compCol.className = 'col';
      numberRow.parentElement.insertBefore(row, numberRow);
      numCol.append(numberRow);
      if (snRow) snCol.append(snRow);
      compCol.append(complementRow);
      row.append(numCol, snCol, compCol);
    }
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
    const postalFields = once('cep-autocomplete', 'input[name$="[address][postal_code]"], input[name$="[postal_code]"]', context);
    postalFields.forEach((postal) => {
      const name = postal.name || '';
      const marker = '[address][';
      const markerIndex = name.lastIndexOf(marker);
      if (markerIndex < 0) return;

      const prefix = name.slice(0, markerIndex);
      const container = postal.closest('form') || document;
      const status = statusFor(postal);
      movePostalFirst(postal);
      layoutStreetNumber(prefix, container);

      Object.entries(labels).forEach(([key, label]) => setLabel(getField(prefix, key, container), label));
      const pending = pendingFill.get(prefix);
      if (pending) {
        applyFill(prefix, container, pending, { onlyEmpty: true });
        // O rebuild apaga o aviso de status: anuncia de novo, para leitor de tela.
        announce(statusFor(postal), Drupal.t('Endereço localizado. Confira os dados e complete o número manualmente.'));
      }
      const complement = getField(prefix, 'address_line2', container);
      if (complement) complement.setAttribute('placeholder', Drupal.t('Complemento'));

      let timer;
      let activeCep = '';
      postal.addEventListener('input', () => {
        window.clearTimeout(timer);
        const cep = digits(postal.value);
        if (cep.length !== 8 || cep === postal.dataset.acultaCepResolved || cep === activeCep) return;
        timer = window.setTimeout(async () => {
          const requestedCep = digits(postal.value);
          if (requestedCep.length !== 8 || requestedCep === postal.dataset.acultaCepResolved) return;
          activeCep = requestedCep;
          announce(status, Drupal.t('Consultando o CEP.'));
          try {
            const result = await lookup(requestedCep);
            if (digits(postal.value) !== requestedCep) return;
            const data = result.data;
            const raw = result.raw || {};
            const values = {
              administrative_area: data.administrative_area,
              locality: data.locality,
              address_line1: data.address_line1,
              dependent_locality: raw.bairro || '',
            };
            pendingFill.set(prefix, values);
            applyFill(prefix, container, values);
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
