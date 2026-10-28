# Estudo — DHTMLX Scheduler (edição GPL) como visão interativa do calendário

Status: **incorporação aceita pelo responsável em 2026-10-28.** O responsável considera a licença compatível com o
projeto; isso é decisão dele, não parecer jurídico. Os critérios da seção 6 continuam como condição de entrega. Decisão do responsável: assumir o risco da licença
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

## 8. Resultados do piloto (2026-10-28)

Piloto em `tests/browser/aculta-calendar-piloto/` (Firefox ESR, `DISPLAY=:0`, 40 eventos fictícios, mês de novembro
de 2026). Medições brutas em `$SUITE_OUT` (fora do repositório). Detalhes de execução no README da pasta.

| Medição | Padrão (sem plugin) | Com `key_nav` (plugin) |
| --- | --- | --- |
| Eventos renderizados na grade do mês | 40 | 40 |
| Tab entra no calendário | **Não** (nenhum controle com `tabindex`; o foco sai do documento) | **Sim** (contêiner com `tabindex="0"`) |
| Setas após entrar | Sem efeito | Movem o foco interno (`dhx_focus_slot`, com rótulo de data e hora) |
| Overflow horizontal em 320 px (iframe) | Não | Não |
| Legibilidade da grade em 320 px | Ilegível: horários cortados (`09:0`), dias da semana truncados | Igual (é a visão de mês, não o problema do plugin) |

Pontos confirmados:

- **Ativação do teclado:** a documentação oficial exige `scheduler.plugins({ key_nav: true })`. Sem isso, o componente não
  é alcançável por teclado. Configurar só `scheduler.config.key_nav = true` não basta.
- **Atributos ARIA:** presentes por padrão (`role="application"` no contêiner, `role="button"` nos controles e rótulos
  `aria-label`). Isso só descreve; não garante que o teclado funcione.
- **Fonte externa:** o CSS carrega cinco arquivos Inter de `fonts.gstatic.com`. Para o ACULTA isso é um ponto de privacidade
  a resolver antes de qualquer adoção (ou remover a fonte, ou hospedá-la localmente).
- **Grade de mês em 320 px:** confirma a regra do plano de implantação. A grade só aparece a partir de `md`; em tela
  pequena, a visão é a agenda.

O que o piloto **não** mediu, e que continua pendente:

- Leitor de tela de fato (NVDA, Orca) e o anúncio de eventos.
- Alto contraste (o tema de alto contraste do componente, e o contraste dos tokens do ACULTA).
- Tamanho servido com compressão do servidor de teste (a medição de tamanho é só do arquivo local: 539.946 bytes
  sem compressão, 150.583 gzip para o JS; 119.763 e 17.358 para o CSS).
- Parecer de licença (seção 3), que continua sem resposta.

Conclusão do piloto: o componente funciona visualmente e pelo teclado **somente com o plugin `key_nav`**. Não há
impedimento técnico grave demonstrado, mas os critérios 1 (parecer), 2 (leitor de tela e alto contraste) e 3
(tamanho servido) da seção 6 seguem abertos. Não há adoção.

## 9. Estado das microfases e divergência de numeração (2026-10-28)

Antes de iniciar as microfases A–F, a verificação pedida encontrou uma divergência de numeração.
Fica registrada aqui; nenhum código foi alterado.

### O que o repositório tem

| Rótulo | Onde | O que é | Estado |
| --- | --- | --- | --- |
| EV-M1 | `aculta_events/docs/ROADMAP.md` | Hooks de datas (E3) | Feito, `8bd6d832b` |
| EV-M2 | `aculta_events/docs/ROADMAP.md` | Recorrência com interface (D-14) | Feito, `b9144b142` |
| EV-M3 | `aculta_events/docs/ROADMAP.md` e `aculta_calendar/docs/ROADMAP.md` | API de itens e agenda no `aculta_calendar` (D-20) | Feito, `c9b6b8e1f` |
| EV-M4 | `aculta_events/docs/ROADMAP.md` | Verificação visual de eventos e calendário | Feito, `e33636144` |
| M1–M4 | `aculta_calendar/docs/RECORRENCIA.md`, seção 3 | Decisões de recorrência: chave pelo início original, cálculo na leitura, teto de 366 ocorrências e fuso fixo | Decisões, não microfases |
| M4 | `RECORRENCIA.md`, seção 11, e `ROADMAP.md` de eventos | Interface de edição da recorrência | Feito, `b9144b142` |

### O que foi pedido

A tarefa pediu o estado das microfases M1–M4 de incorporação do DHTMLX e a execução de A–F.
Não existe, no repositório, um conjunto M1–M4 de entrega: os rótulos M1–M4 são decisões da
recorrência, e "M4" também nomeia a interface de edição. As microfases de entrega usam o
prefixo EV- (EV-M1 a EV-M4) e as do calendário usam CA0–CA6 e I1–I8.

### Consequência

Parei antes de alterar código, conforme a instrução. Nada de A–F foi implementado: nenhuma
biblioteca foi adicionada, nenhuma rota foi criada e nenhum arquivo do módulo foi alterado.
Esta branch contém apenas este registro.

### Para retomar

- Definir a numeração das microfases desta incorporação (sugestão: DH-A a DH-F, para não
  colidir com M1–M5 da recorrência, EV-M1–EV-M4 e CA0–CA6).
- Os critérios 2 (leitor de tela e alto contraste) e 3 (tamanho servido com compressão) da
  seção 6 continuam abertos. O piloto mediu teclado, 320 px e URLs externas do CSS, não
  leitor de tela, alto contraste nem compressão do servidor.
- O caminho do estudo é
  `web/modules/custom/aculta_portal/modules/aculta_calendar/docs/ESTUDO-DHTMLX-SCHEDULER.md`;
  não existe `docs/ESTUDO-DHTMLX-SCHEDULER.md` na raiz.

## 10. Decisão do responsável sobre o arquivo de licença (2026-10-28)

O responsável decidiu: **remover o `LICENSE` do pacote DHTMLX e assumir todos os riscos**.

- A decisão é dele, não parecer jurídico. O parecer de compatibilidade (seção 3) continua sem ter sido
  feito por nenhum agente; o risco passa a ser assumido explicitamente pelo responsável.
- O repositório não guardará o `LICENSE` do pacote. O aviso de copyright e a menção à GPL v2 do
  componente ficam no README do módulo e/ou em `NOTICE`, junto da origem, da versão fixa 7.2.15 e do
  hash do pacote, como o item A já exige.
- A leitura do item A passa a ser: incluir o componente **sem** o `LICENSE` do pacote, registrando
  origem, versão e hash no docs.
- Nada foi implementado: a incorporação continua parada pela divergência de numeração (seção 9).
  Falta definir a numeração das microfases para retomar.
