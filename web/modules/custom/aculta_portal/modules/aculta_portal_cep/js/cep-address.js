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

  function dispatchInput(field) {
    if (!field) return;
    field.dispatchEvent(new Event('input', { bubbles: true }));
    field.dispatchEvent(new Event('change', { bubbles: true }));
  }

  // Preenchimento do CEP. Quando o Estado muda, o Drupal Address dispara um rebuild AJAX do endereço,
  // substituindo os elementos no DOM. O preenchimento pendente é guardado aqui e aplicado aos novos
  // campos no attach seguinte, sem re-disparar o rebuild.
  const pendingFill = new Map();
  // CEP já resolvido por prefixo do campo.
  const resolvedCep = new Map();

  /**
   * Aplica valores aos campos de endereço (rua, bairro e cidade).
   * Todos os campos continuam editáveis pelo usuário (inclusive em CEP geral onde rua e bairro vêm vazios).
   */
  function applyFieldValues(prefix, container, values, isReplacement = false) {
    ['address_line1', 'dependent_locality'].forEach((key) => {
      const field = getField(prefix, key, container);
      if (!field) return;
      const val = values[key];
      if (val !== undefined && val !== null && val !== '') {
        field.value = val;
        dispatchInput(field);
      }
      else if (isReplacement) {
        field.value = '';
        dispatchInput(field);
      }
    });

    const city = getField(prefix, 'locality', container);
    if (city && values.locality) {
      if (city.tagName === 'SELECT') {
        city.value = values.locality;
        if (city.value !== values.locality) {
          const norm = (s) => (s || '').trim().toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
          const target = norm(values.locality);
          const match = Array.from(city.options).find(
            (opt) => norm(opt.value) === target || norm(opt.text) === target
          );
          if (match) {
            city.value = match.value;
          }
        }
        dispatchInput(city);
      }
      else {
        city.value = values.locality;
        dispatchInput(city);
      }
    }
    else if (city && isReplacement && !values.locality) {
      city.value = '';
      dispatchInput(city);
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
      snInput.addEventListener('click', () => syncStreetNumber(numInput, snInput));
      snInput.addEventListener('input', () => syncStreetNumber(numInput, snInput));
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
        pendingFill.delete(prefix);
        applyFieldValues(prefix, container, pending.values, pending.isReplacement);
        if (pending.number && numInput && !numInput.value && !numInput.disabled) {
          numInput.value = pending.number;
          dispatchInput(numInput);
        }
        announce(statusFor(postal), Drupal.t('Endereço localizado. Confira os dados e complete o número manualmente.'));
      }
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
          announce(status, Drupal.t('Consultando o CEP.'));
          try {
            const result = await lookup(requestedCep);
            if (digits(postal.value) !== requestedCep) return;
            const data = result.data;
            // Campos do Commerce/Address: bairro em dependent_locality. O complemento do ViaCEP não é usado.
            const values = {
              administrative_area: data.administrative_area || '',
              locality: data.locality || '',
              address_line1: data.address_line1 || '',
              dependent_locality: data.dependent_locality || '',
            };
            const previous = resolvedCep.get(prefix) || '';
            const isReplacement = previous !== '' && previous !== requestedCep;
            resolvedCep.set(prefix, requestedCep);

            const state = getField(prefix, 'administrative_area', container);
            const needsStateChange = state && values.administrative_area && state.value !== values.administrative_area;

            applyFieldValues(prefix, container, values, isReplacement);

            if (needsStateChange) {
              const currentNumber = numInput && !numInput.disabled ? numInput.value : '';
              pendingFill.set(prefix, { values, isReplacement, number: currentNumber });
              state.value = values.administrative_area;
              dispatchInput(state);
            }

            postal.value = formatCep(requestedCep);
            postal.dataset.acultaCepResolved = requestedCep;
            announce(status, Drupal.t('Endereço localizado. Confira os dados e complete o número manualmente.'));

            const street = getField(prefix, 'address_line1', container);
            if (values.address_line1 && numInput && !numInput.disabled) {
              numInput.focus();
            }
            else if (street) {
              street.focus();
            }
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
