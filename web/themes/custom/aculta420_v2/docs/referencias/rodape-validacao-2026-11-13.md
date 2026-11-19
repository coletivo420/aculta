# Validação visual do rodapé (2026-11-13)

Modelo de referência: `rodape-modelo-2026-11-13.png`.

**Método:** Playwright 1.56.1 com Firefox (`tests/browser`), página estática de teste com as folhas do tema (`base.css`, `tokens.css` e `site-footer.css` do repositório) e a marcação que o SDC `site-footer` gera para `brand`, `nav` e `legal`. Capturas de 1440 px e 390 px, modos claro e escuro. A fonte do site não carrega nessa página, por isso a tipografia das capturas usa a fonte do sistema.

| Caso | Fundo medido | Esperado (modelo) | Estouro horizontal |
| --- | --- | --- | --- |
| Desktop claro | rgb(104, 148, 39) = #689427 | #689427 | não |
| Desktop escuro | rgb(154, 193, 90) = #9AC15A | #9AC15A | não |
| Celular claro | rgb(104, 148, 39) | #689427 | não |
| Celular escuro | rgb(154, 193, 90) | #9AC15A | não |

Texto e links medidos: rgb(23, 21, 19) = #171513 nos dois modos.

**Diferenças conhecidas em relação ao modelo**

- Tipografia: a fonte do site não carrega na página de teste. Não é diferença do componente.
- Celular: o modelo mostra só marca, divisor e linha legal. O componente mantém a navegação empilhada e põe o divisor abaixo dela. Decisão pendente: esconder a navegação no celular ou manter.
- Desktop: a linha superior e a navegação batem com o modelo em posição e separadores.
