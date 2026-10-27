((Drupal, once) => {
  // Calculadora de frete (módulo): cálculo automático por AJAX. Ao digitar um CEP de 8 dígitos, consulta o simulador
  // sem botão. CEP incompleto ao sair do campo mostra "CEP inválido" embaixo da caixa. Todo texto entra com
  // textContent, nunca com innerHTML. Respostas antigas são descartadas (só vale a última consulta).

  const MENSAGEM_CEP_INVALIDO = 'CEP inválido.';
  const MENSAGEM_FALHA = 'Não foi possível calcular o frete agora. Tente novamente.';
  const ATRASO_DIGITACAO = 300;

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
        const campo = raiz.querySelector('input[name="cep"]');
        const resultado = raiz.querySelector('[data-aculta-shipping-resultado]');
        const endpoint = raiz.dataset.endpoint || '/frete/simular';
        let consulta = 0;
        let temporizador = null;

        const digitos = () => campo.value.replace(/\D/g, '');

        const calcular = async (cep) => {
          const minha = ++consulta;
          try {
            const variacao = raiz.dataset.variation && raiz.dataset.variation !== '0' ? `&variation=${encodeURIComponent(raiz.dataset.variation)}` : '';
            const resposta = await fetch(`${endpoint}?cep=${encodeURIComponent(cep)}${variacao}`, {
              headers: { Accept: 'application/json' },
              credentials: 'same-origin',
            });
            const dados = await resposta.json();
            if (minha === consulta) {
              apresentar(resultado, dados);
            }
          }
          catch (erro) {
            if (minha === consulta) {
              apresentar(resultado, { state: 'unavailable', message: MENSAGEM_FALHA });
            }
          }
        };

        const cancelar = () => {
          clearTimeout(temporizador);
          consulta++;
        };

        campo.addEventListener('input', () => {
          cancelar();
          const cep = digitos();
          if (cep.length === 8) {
            temporizador = setTimeout(() => calcular(cep), ATRASO_DIGITACAO);
          }
          else if (cep.length === 0) {
            resultado.replaceChildren();
          }
          // Enquanto digita (menos de 8 dígitos), não mostra erro: a mensagem aparece ao sair do campo.
        });

        campo.addEventListener('blur', () => {
          const cep = digitos();
          if (cep.length > 0 && cep.length !== 8) {
            cancelar();
            apresentar(resultado, { state: 'unavailable', message: MENSAGEM_CEP_INVALIDO });
          }
        });

      });
    },
  };
})(Drupal, once);
