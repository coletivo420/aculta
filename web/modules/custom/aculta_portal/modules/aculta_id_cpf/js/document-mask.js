/**
 * Local progressive masks. PHP remains authoritative for format and check digits.
 */
(function (Drupal, once) {
  'use strict';

  const layouts = { cpf: [3, 3, 3, 2], cnpj: [2, 3, 3, 4, 2] };
  const separators = { cpf: ['.', '.', '-'], cnpj: ['.', '.', '/', '-'] };

  function formatPartial(value, type) {
    if (!Object.hasOwn(layouts, type) || typeof value !== 'string') return value;
    const allowed = type === 'cpf' ? /^[0-9.\-]*$/ : /^[A-Za-z0-9.\/-]*$/;
    // Do not erase illegal characters or truncate input into a valid document.
    if (!allowed.test(value)) return value;
    const body = value.replace(/[.\/-]/g, '').toUpperCase();
    const limit = type === 'cpf' ? 11 : 14;
    if (body.length > limit || (type === 'cnpj' && /[A-Z]/.test(body.slice(12)))) return value;
    let offset = 0;
    return layouts[type].map((length, index) => {
      const part = body.slice(offset, offset + length);
      offset += length;
      return part ? (index ? separators[type][index - 1] : '') + part : '';
    }).join('');
  }

  function caretPosition(value, count) {
    if (count === 0) return 0;
    let seen = 0;
    for (let i = 0; i < value.length; i++) {
      if (/[A-Za-z0-9]/.test(value[i])) seen++;
      if (seen === count) return i + 1;
    }
    return value.length;
  }

  function applyMask(input, type) {
    const original = input.value;
    const formatted = formatPartial(original, type);
    if (original === formatted) return;
    const start = input.selectionStart;
    const end = input.selectionEnd;
    const count = (position) => (original.slice(0, position).match(/[A-Za-z0-9]/g) || []).length;
    input.value = formatted;
    if (start !== null && end !== null) {
      input.setSelectionRange(caretPosition(formatted, count(start)), caretPosition(formatted, count(end)));
    }
  }

  Drupal.behaviors.acultaDocumentMask = {
    attach(context) {
      once('aculta-document-mask', 'input[data-aculta-document]', context).forEach((input) => {
        const type = input.getAttribute('data-aculta-document');
        if (!Object.hasOwn(layouts, type)) return;
        const mask = () => applyMask(input, type);
        input.addEventListener('input', (event) => {
          if (!event.isComposing) mask();
        });
        input.addEventListener('compositionend', mask);
        input.addEventListener('blur', mask);
        mask();
      });
    },
  };
})(Drupal, once);
