((Drupal, once) => {
  // Calculadora de frete: envia o CEP ao simulador (JSON) e apresenta o estado devolvido. Não decide disponibilidade.
  // Todo texto entra com textContent, nunca com innerHTML.

  const apresentar = (resultado, data) => {
    resultado.replaceChildren();
    if (data.state === 'available' && Array.isArray(data.options) && data.options.length > 0) {
      const lista = document.createElement('ul');
      lista.className = 'aculta-shipping-calculator__opcoes list-unstyled';
      data.options.forEach((opcao) => {
        const item = document.createElement('li');
        item.className = 'aculta-shipping-calculator__opcao';
        const servico = document.createElement('span');
        servico.className = 'aculta-shipping-calculator__servico';
        servico.textContent = opcao.name;
        const preco = document.createElement('span');
        preco.className = 'aculta-shipping-calculator__preco';
        preco.textContent = opcao.price;
        item.append(servico, preco);
        if (opcao.days) {
          const prazo = document.createElement('span');
          prazo.className = 'aculta-shipping-calculator__prazo';
          prazo.textContent = `${opcao.days} ${opcao.days === 1 ? 'dia útil' : 'dias úteis'}`;
          item.append(prazo);
        }
        lista.append(item);
      });
      resultado.append(lista);
      return;
    }
    if ((data.state === 'unavailable' || data.state === 'empty') && data.message) {
      const aviso = document.createElement('p');
      aviso.className = `aculta-shipping-calculator__aviso aculta-shipping-calculator__aviso--${data.state}`;
      aviso.textContent = data.message;
      resultado.append(aviso);
    }
  };

  Drupal.behaviors.acultaShippingCalculator = {
    attach(context) {
      once('acultaShippingCalculator', '[data-aculta-shipping-calculator]', context).forEach((raiz) => {
        const formulario = raiz.querySelector('form');
        const campo = formulario.querySelector('input[name="cep"]');
        const resultado = raiz.querySelector('[data-aculta-shipping-resultado]');
        const endpoint = raiz.dataset.endpoint;

        formulario.addEventListener('submit', async (evento) => {
          evento.preventDefault();
          const cep = campo.value.replace(/\D/g, '');
          if (cep.length !== 8) {
            apresentar(resultado, { state: 'unavailable', message: 'Informe um CEP válido, com oito dígitos.' });
            return;
          }
          try {
            const resposta = await fetch(`${endpoint}?cep=${encodeURIComponent(cep)}`, {
              headers: { Accept: 'application/json' },
              credentials: 'same-origin',
            });
            const dados = await resposta.json();
            apresentar(resultado, dados);
          }
          catch (erro) {
            apresentar(resultado, { state: 'unavailable', message: 'Não foi possível calcular o frete agora. Tente novamente.' });
          }
        });
      });
    },
  };
})(Drupal, once);
