## Retificação da subfrente B - experiência Studio completa (2026-10-29)

A sequência OCS-F0-F7 anteriormente descrita foi **revogada** pelo [plano vinculante OCS-F0-F10](PLANO-VISUAL-OPENCULTURAS-NEXT-ACULTA-STUDIO.md). O trabalho da Frente B abrange hub, Marley, GanjaLab, WE Sativa, Conta, Perfil e experiências extras verificadas no registry, com os respectivos donos funcionais preservados. Etapa A1-A4 somente após **OCS-F10 homologada**. A Frente B não modifica controllers/services/rotas/ACL da Frente A. Conflitos ou dependências entre frentes viram `Pede-A`; manter commits isolados e push fast-forward na PR #299, sem branch/PR adicional e sem merge.

## Subfrente visual Studio OCS-F → OCS-A (2026-10-29)

Aprovação posterior à execução B1-B5: [plano detalhado](PLANO-VISUAL-OPENCULTURAS-NEXT-ACULTA-STUDIO.md). Primeiro uma reprodução visual fiel do OpenCulturas **Next** (tema `opcult`); depois uma variante de identidade Aculta usando os mesmos SDCs e contratos. É subfrente da **Frente B**, não altera ownership da tabela acima: B é responsável por tema/SDC/CSS/Twig/stories; novos providers, PHP, rotas, acesso e services exigem handoff à A. Não abrir branch/PR nova.

A execução segue OCS-F0 (baseline), F1 (tokens), F2 (navegação interna), F3 (componentes), F4 (dashboard), F5 (Marley), F6 (Conta/Perfil), F7 (gate de fidelidade); somente depois OCS-A1-A4 (identidade, refino, validação e consolidação). O shell institucional e Domain Header são invioláveis. Portar padrões e CSS com licenças e provenance verificadas; **não** instalar/ativar `opcult`, copiar config YAML/UUID ou inventar funcionalidades. Gates S-R4/S-R5 e de release continuam mandatórios. Microcommits e push fast-forward na PR #299, com paths próprios e sem force/merge.

# Plano de execução — refatoração de código e visual (OpenCulturas)

Branch: `refactor/opencultures-codigo-visual` (checkout local e remoto são a mesma branch).
Origem das ideias: `OPENCULTURAS-COMPARATIVO.md`, `OPENCULTURAS-INCORPORACAO-CODIGO.md` e
`DECISOES-RESPONSAVEL-2026-10-28.md` (decisões D-01 a D-12 valem como premissa).

Duas frentes, **dois agentes independentes, uma só branch**. Cada agente trabalha em microfases; cada microfase
é um commit publicado no remoto antes de começar a próxima.

## 1. Divisão de propriedade (regra que evita conflito)

| Caminho | Dono | Observação |
| --- | --- | --- |
| `web/modules/custom/**/src/**` (exceto `src/Plugin/Block/**`) | **Frente A (código)** | PHP de serviços, entidades, controllers, hooks, formulários |
| `web/modules/custom/**/tests/src/**` | **Frente A** | Testes dos arquivos da frente A. Testes de visual ficam com a B |
| `web/modules/custom/**/*.module`, `*.install` | **Frente A** | |
| `web/modules/custom/**/*.services.yml`, `*.info.yml`, `*.routing.yml`, `*.permissions.yml` | **Frente A** | Arquivos compartilhados. A frente B **não** os altera |
| `web/modules/custom/**/src/Plugin/Block/**`, `**/templates/**`, `**/css/**`, `**/js/**`, `**/*.component.yml` | **Frente B (visual)** | Blocos, Twig, CSS, JS e componentes SDC |
| `web/themes/custom/aculta420/**` | **Frente B** | Tema, SDC, stories, tokens, documentação de tema |
| `config/sync/views.view.*.yml` | **Frente B** | Views. Mudança que exige serviço novo pede à frente A |
| `docs/**` e `*.md` de módulo | **Quem altera o assunto** | Documento de decisão ou de roadmap: a frente que fecha a microfase atualiza |

Regras de convivência:

1. Cada agente faz `git add` **somente** dos caminhos do próprio dono. Antes de cada commit, `git status` confere
   que nada fora do escopo entrou.
2. Se a frente B precisar de um serviço, de uma permissão ou de uma rota nova, ela **não altera** o arquivo
   compartilhado. Registra o pedido na mensagem do commit (`Pede-A: …`) e segue com o que não depende dele.
   A frente A atende o pedido em microfase própria.
3. Antes de publicar, `git pull --rebase origin refactor/opencultures-codigo-visual`. Conflito de arquivo fora
   do próprio escopo **interrompe** o agente, que reporta em vez de resolver.
4. Publicação sempre por fast-forward (`git push origin HEAD:refactor/opencultures-codigo-visual`). Sem force.

## 2. Frente A — refatoração de código

Objetivo: reduzir funções longas e resíduos de padrão, sem mudar comportamento. Cada microfase mantém a saída
idêntica (verificada) e os testes da área verdes.

| Microfase | Alvo | Ação | Critério de aceite |
| --- | --- | --- | --- |
| A1 | `aculta_document_viewer/src/Pdf/PdfViewerBuilder.php::buildFile` (357 linhas) | Dividir em etapas com nome: resolver URL e acesso, mesclar configurações, montar o viewer, montar barra e controles | Saída do array de render idêntica para as mesmas entradas (ver §4); `PdfViewerBuilderTest` e teste Kernel de instalação verdes |
| A2 | `aculta_messenger/src/Aviso/AvisosResumo.php::enviarParaComLock` (313 linhas) | Dividir: obter trava, montar envio, registrar resultado, liberar trava | Testes Unit e Kernel do messenger de avisos verdes; nenhum envio duplicado com a mesma trava |
| A3 | `aculta_commerce/src/Entity/Shipment.php` (2 chamadas `\Drupal::`) | Trocar por serviço injetado, onde a entidade permitir; onde não permitir, documentar o motivo | Testes do motor de frete verdes (`ShippingEngineKernelTest`, 7 testes; suíte Kernel do módulo: 14) |
| A4 | `aculta_lms_h5p/src/aculta_H5PDrupal/aculta_H5PDrupal.php` (4 métodos-stub, linhas 1557–1581) | Antes de mudar: confirmar se o H5P chama os métodos. Se chamar, implementar mínimo coerente ou lançar exceção explícita. Se não, documentar | Teste Unit do comportamento escolhido; nenhum método retorna `null` em silêncio |
| A5 | `aculta_lms_h5p` (11 TODOs em código de produção) | Triar: cada TODO vira item em `DEBT-REGISTER.md` ou é resolvido. Sem mudança de código além do necessário | Lista de TODOs zerada no código ou registrada |
| A6 | Blocos `catch` genéricos (heurística não conclusiva) | Revisão manual: cada `catch (Exception|Throwable)` precisa registrar ou relançar. Os que engolem erro ganham log | Nenhum `catch` genérico que engole o erro sem registro, confirmado por leitura |

Ordem: A1 → A2 → A3 → A4 → A5 → A6. A1 e A2 são as de maior ganho e servem de modelo para o método das demais.

## 3. Frente B — visual, views, CSS e blocos

Objetivo: entregar as melhorias visuais aprovadas, como componentes SDC conformes, sem cópia de código do OpenCulturas
(regra da revisão de 2026-10-22: template e bloco legados são referência, nunca cópia).

| Microfase | Alvo | Ação | Critério de aceite |
| --- | --- | --- | --- |
| B1 | I-10: variações de cartão | **Reusar** `patterns/card` (já tem `default`, `horizontal`, `overlay`, `vertical`). Adicionar a variante `compact` ao mesmo componente, com story | Story de cada variante; consumidor real em pelo menos uma view |
| B2 | I-09 e D-12: cores de seção | Tokens de cor para a lista fechada (vermelho, verde, amarelo, verde-escuro) e classes de modificador. Sem cor livre | Contraste AA em claro e escuro; nenhum uso de cor como único sinal (texto obrigatório) |
| B3 | Candidatos SDC (gate de elegibilidade) | Para cada um: verificar se algum SDC existente atende; se não, criar com `.component.yml`, Twig, story e schema. Ordem: `feedback/stat-counter` (I-29), `content/accessibility-badges` (I-28), `content/press-mention` (I-26), `content/sponsor-logos` (I-27), `patterns/bookmark-toggle` (I-31) | Cada componente com story, props/slots explícitos, WCAG 2.2 AA, light/dark. Sem consumidor real, fica como candidato e não entra no catálogo como aprovado |
| B4 | I-21: página de evento em cinco regiões | Verificar se UI Patterns Layouts já está disponível no tema. Se sim, criar o layout. Se não, documentar a dependência e parar | Layout com cinco regiões e ordem de tela pequena definida; sem Layout Builder paralelo |
| B5 | I-19: lint de CSS e JS do tema | Configurar `stylelint` e `eslint` para o tema, com script no `package.json` do tema | `lint:css` e `lint:js` rodam sem erro no tema |
| B6 | Documentação do catálogo | Atualizar `component-catalog.md` e `visual-cross-product-roadmap.md` com o que foi aprovado, o que ficou candidato e o motivo | Catálogo reflete o estado real dos componentes |

Ordem: B1 → B2 → B3 (em sequência, um componente por microfase) → B4 → B5 → B6.

**Limite de execução visual:** mudança de Twig e de cache de template só aparece no servidor de testes após
`drush cr`, que precisa de autorização do responsável. A frente B não roda `drush cr` por conta própria. Testes
visuais no Firefox ESR (`DISPLAY=:0`) só depois dessa autorização.

## 4. Verificação de equivalência (A1 e A2)

Para refatoração sem mudança de comportamento, a prova é a saída:

1. Antes de mudar a função, gerar uma **referência**: o array de saída (ou o resultado observável) para um conjunto
   fixo de entradas (PDF público, PDF privado, arquivo não PDF, acesso negado, configuração diferente).
2. Guardar a referência como fixture em `tests/fixtures/` do módulo, **versionada**.
3. Teste de regressão compara a saída atual com a fixture. Após a refatoração, o teste passa sem alterar a fixture.

Não criar arquivos temporários de teste no checkout. Uma tentativa anterior falhou por nome de método final do
PHPUnit (`run`); o nome de método de teste deve evitar nomes já usados pelo framework.

## 5. Microfase: protocolo obrigatório

Cada microfase:

1. **Escopo:** ler só o necessário. Nada de mudança fora da microfase.
2. **Mudança:** uma intenção por commit.
3. **Gates da área:** `php -l` em arquivos PHP alterados; PHPUnit da área (Unit sempre; Kernel quando a área tem);
   para tema, lint quando existir. Registrar o resultado no corpo do commit: `Testes: <N> ok, <M> deprecações`.
4. **Commit:** mensagem `refactor(<área>): <microfase> — <resumo>`, com corpo curto e o trailer
   `Claude-Session: https://claude.ai/code/session_019PQCLXg47kwM5MKggXPaYo`.
5. **Publicação:** `git pull --rebase origin refactor/opencultures-codigo-visual` e depois
   `git push origin HEAD:refactor/opencultures-codigo-visual`. Só fast-forward.
6. **Próxima microfase** só depois da publicação.

Parar e reportar (sem contornar) quando:

- um teste que não tem relação com a microfase falha;
- o rebase traz conflito de arquivo de outra frente;
- uma tarefa exige `drush cr`, `updatedb`, `config:import`, escrita no Runtime ou deploy;
- uma decisão não está registrada em `DECISOES-RESPONSAVEL-2026-10-28.md`.

## 6. Ambiente de testes

- Kernel: banco `aculta_simpletest`, carregado de `secrets/simpletest-db.env` (ver `BANCO-DE-TESTE.md`).
  Nunca `aculta_runtime`.
- Unit: sem banco.
- Nenhum deploy. Nenhuma alteração de produção.

## 7. Definição de pronto

- Todas as microfases A1–A6 e B1–B6 concluídas, ou registradas como bloqueadas com motivo.
- Cada microfase tem commit próprio, publicado, com os gates no corpo.
- Catálogo e roadmaps atualizados (B6 e A5).
- PR com corpo descrevendo as duas frentes, as microfases e os bloqueios, com o trailer no final.

## 8. Integração

As duas frentes trabalham na mesma branch e se integram continuamente pelo rebase de cada publicação. Não há
merge entre branches de frente. A PR é revisada no conjunto.
