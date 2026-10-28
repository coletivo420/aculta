# Estudo — DHTMLX Scheduler (edição GPL) como visão interativa do calendário

Status: **estudo, sem decisão.** Nenhum código foi adotado. Decisão do responsável: assumir o risco da licença
(registrado aqui). Critérios de adoção estão na seção 6.

## 1. O que está confirmado

| Ponto | Situação | Fonte |
| --- | --- | --- |
| Licença da edição gratuita | "GPL v2.0". A página **não diz** se é "v2 somente" ou "v2 ou posterior". | [Licenças DHTMLX](http://dhtmlx.com/docs/products/licenses.shtml) |
| Condição de uso gratuito | "Você pode usar estes produtos gratuitamente em projetos de código aberto licenciados sob uma licença compatível com GPLv2." | Mesma página |
| Recursos da edição gratuita | Não inclui recursos PRO, como múltiplas instâncias do Scheduler na mesma página. | Mesma página |
| Suporte da edição gratuita | Sem suporte técnico oficial. Ajuda só pelo fórum da comunidade. | Mesma página |
| Pacote npm | Traz a edição Standard (GPL). A edição PRO vem de registro privado e exige licença comercial ativa. | [Instalação](https://docs.dhtmlx.com/scheduler/llms-md/en/guides/installation.md) |
| Acessibilidade | Atributos WAI-ARIA ativados por padrão (desligáveis por configuração); navegação por teclado com atalhos predefinidos e extensíveis; temas de alto contraste (Branco e Preto). | [Acessibilidade](https://docs.dhtmlx.com/scheduler/accessibility.html), [versão 4.4](https://dhtmlx.com/blog/scheduler-4-4-released-accessibility-support-minor-improvements/) |
| Acessibilidade na edição gratuita | O anúncio da 4.4 diz que o suporte de acessibilidade está nas edições Standard e PRO. | Mesma fonte |

## 2. O que NÃO está confirmado

- **Nível WCAG.** A DHTMLX não declara nível de conformidade (WCAG 2.2 AA ou outro). Só o nome dos recursos.
- **Teste de contraste.** O anúncio diz que os temas de alto contraste "passaram em testes", sem citar o teste nem o padrão.
- **Tamanho do pacote.** Não encontrei a página do `dhtmlx-scheduler` no npm com tamanho atual. A versão 5.1.0 que apareceu é de um espelho de terceiros e está desatualizada.
- **Recorrência.** Não confirmei se a edição gratuita tem eventos recorrentes. Sem isso, o motor do `aculta_calendar` continua sendo o único.
- **Versão atual.** Não confirmei o número da versão publicada hoje.
- **Compatibilidade com o Drupal.** Não há integração pronta. Teríamos de montar o componente como ativo local do módulo.

## 3. O risco de licença, de forma objetiva

O texto da DHTMLX fala em "licença compatível com GPLv2". Isso depende de dois fatos do nosso lado:

1. **Licença declarada do projeto.** O `composer.json` do ACULTA declara `GPL-2.0-or-later`. O arquivo `LICENSE` da raiz é a GPLv3.
   O `LICENSE.txt` é a GPLv2. Os dois convivem no repositório, o que precisa de parecer.
2. **Se o projeto for "GPLv2 ou posterior"**, ele é compatível com a GPLv2 e pode usar o componente gratuito.
   Se a parte do projeto que usa o componente estiver só sob GPLv2 "somente" ou sob GPLv3 sem "ou posterior", a combinação pode não ser permitida.

Consequências práticas, se adotado:
- o componente é código de terceiros sob copyleft: o código que o usa precisa seguir a mesma licença;
- sem suporte oficial: correções de defeito dependem de fórum ou de nós mesmos;
- sem PRO: se precisarmos de múltiplas instâncias ou de recursos pagos, a edição gratuita não serve.

Você assumiu esses riscos. Este estudo registra que o parecer de compatibilidade **continua pendente**, e não foi
feito por nenhum agente.

## 4. Encaixe com o que já existe

- Os dados vêm do `aculta_calendar` (API de itens, ADR-031). O componente seria só a apresentação, recebendo JSON.
- A grade em HTML semântico continua sendo o caminho padrão e o de acessibilidade de referência.
- O componente só seria carregado nas páginas que usam a visão interativa (CA4 em diante).
- Não substitui o motor de recorrência, o `.ics` nem a regra de acesso.

## 5. Comparação com as alternativas já levantadas

| Critério | DHTMLX Scheduler (GPL) | FullCalendar núcleo (MIT) | Schedule-X (MIT) |
| --- | --- | --- | --- |
| Licença | GPL v2 (compatível com GPLv2 apenas por condição de uso) | MIT | MIT |
| Declaração de acessibilidade | ARIA, teclado e alto contraste; sem nível WCAG | Não verificado | Não verificado |
| Suporte oficial na versão gratuita | Não | Documentação e comunidade | Documentação e comunidade |
| Recursos PRO | Recursos avançados exigem licença comercial | Premium à parte | Não verificado |
| Riscos legais | Copyleft e compatibilidade a confirmar | Nenhum relevante | Nenhum relevante |

A comparação mostra que a DHTMLX é a única com acessibilidade declarada **e** licença copyleft. As outras duas têm
licença permissiva, o que simplifica o parecer. A escolha entre elas depende de a acessibilidade da DHTMLX passar
no nosso teste, e não só no que é declarado.

## 6. Critérios de adoção (todos obrigatórios)

1. **Parecer de compatibilidade** da licença GPL do componente com a licença efetiva do projeto, por escrito.
2. **Teste de acessibilidade próprio** no Firefox ESR, com teclado somente e leitor de tela, em desktop e 320 px, com modo
   de alto contraste. Sem isso, não adota.
3. **Medição do tamanho** do pacote que realmente será servido (minificado e comprimido), e o custo por página.
4. **Recorrência**: confirmar se a edição gratuita a suporta. Se não suportar, o motor do `aculta_calendar` continua sendo a fonte.
5. **Piloto isolado**: uma página de teste, sem efeito no resto do site, com retirada simples.
6. **Plano de saída**: se a licença ou o suporte mudarem, a visão volta à grade em HTML sem perda de dados.

## 7. Recomendação

Estudo concluído, sem adoção ainda. Ordem sugerida, se você quiser seguir:

1. Parecer de licença (item 1 da seção 6): é o que mais pesa, e não depende de código.
2. Piloto de acessibilidade e de tamanho (itens 2 e 3), em uma página de teste isolada.
3. Decisão final com base nos resultados, registrada como D-21 ou D-22.

Se o parecer for negativo, a alternativa permissiva (FullCalendar núcleo ou Schedule-X) passa a ser a candidata,
com o mesmo teste de acessibilidade.
