/**
 * Layout do bloco de endereço (tema aculta420).
 *
 * Só reposiciona e agrupa campos que o módulo aculta_portal já renderizou: o CEP fica primeiro e Número, S/N e
 * Complemento ficam na mesma linha, logo depois do Logradouro. O comportamento (busca do CEP, preenchimento,
 * validação do número e bloqueio com S/N) fica no módulo, em js/cep-address.js. Sem este arquivo o formulário
 * funciona, só com a ordem e as colunas padrão do Address.
 */
(function (Drupal, once) {
  'use strict';

  const rowOf = (el) => el?.closest('.form-item, .js-form-item, .form-check') || null;

  // Procura o campo pelo nome final (ex.: [address_line1]), dentro do mesmo formulário ou bloco de endereço.
  const fieldIn = (container, key) => container.querySelector(`input[name$="[${key}]"], select[name$="[${key}]"]`);

  function movePostalFirst(postal) {
    const row = rowOf(postal);
    if (row?.parentElement) {
      row.parentElement.insertBefore(row, row.parentElement.firstChild);
    }
  }

  function groupStreetNumber(numInput) {
    const container = numInput.closest('form') || document;
    const snInput = fieldIn(container, 'street_number_sn');
    const numberRow = rowOf(numInput);
    const streetRow = rowOf(fieldIn(container, 'address_line1'));
    const complementRow = rowOf(fieldIn(container, 'address_line2'));
    const snRow = snInput ? rowOf(snInput) : null;
    if (numberRow && streetRow && numberRow !== streetRow) {
      streetRow.after(numberRow);
    }
    if (!numberRow || !complementRow || numberRow.parentElement !== complementRow.parentElement) {
      return;
    }
    if (numberRow.closest('.portal-address-number-row')) {
      return;
    }
    // Colunas: número e S/N encolhem para caber; o complemento ocupa o resto da linha.
    const row = document.createElement('div');
    row.className = 'row g-2 align-items-end portal-address-number-row';
    const numCol = document.createElement('div');
    numCol.className = 'col-auto';
    const snCol = document.createElement('div');
    snCol.className = 'col-auto pb-2';
    const compCol = document.createElement('div');
    compCol.className = 'col';
    numberRow.parentElement.insertBefore(row, numberRow);
    numCol.append(numberRow);
    if (snRow) {
      snCol.append(snRow);
    }
    compCol.append(complementRow);
    row.append(numCol, snCol, compCol);
  }

  Drupal.behaviors.acultaAddressLayout = {
    attach(context) {
      once('aculta-address-postal', 'input[name$="[postal_code]"]', context).forEach(movePostalFirst);
      once('aculta-address-number', 'input[data-aculta-number]', context).forEach(groupStreetNumber);
    },
  };
})(Drupal, once);
