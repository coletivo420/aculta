# ACULTA420 V2 — instruções para agentes

Herde o `AGENTS.md` da raiz do repositório. Estas regras são específicas do tema V2 e complementam a raiz; quando houver conflito, vale a regra mais restritiva.

## 1. Antes de editar

1. Leia [README.md](README.md), [docs/architecture.md](docs/architecture.md) e o ADR-046 em `docs/decisions/`.
2. Confira `git status` e `git fetch`. Trabalhe em `dev`, sem branch nova sem pedido. Não faça force push nem merge em `main` sem pedido.
3. Confirme o estado no código antes de afirmar status. O roadmap pode estar atrás do código.
4. Alteração no tema exige autorização explícita do responsável. Pedido de fase do ADR-046 conta como autorização para a V2. Não altere a V1 (`web/themes/custom/aculta420/`), que é somente referência visual.

## 2. Regras de implementação

- **Sem Bootstrap, Vite, Node, jQuery ou `core/drupal.dialog.ajax`.** Exceção só com ADR.
- **SDC é o mecanismo de componente.** Schema estrito em toda prop. `with_context = false` em includes de componente. Não passe entidade, usuário ou render array de conteúdo inteiro como prop.
- **Tokens semânticos.** Novos componentes usam o token que descreve a função visual (`--color-text-primary`, `--color-surface-raised`). Primitivas de paleta ficam dentro de `src/styles/tokens.css`.
- **Modo de cor só por `data-theme`.** Não crie seletor de dark fora de `tokens.css` e não use `prefers-color-scheme`.
- **Mobile-first.** Base em 320 CSS px, sem rolagem horizontal da página, alvo de toque de 44 px (exceção registrada: controles da barra institucional com 35 px, ADR-047, item 10; ícones da barra com 30 px), zoom de 200% funcional. Antes de mexer em `max-width`, rastreie seletor, cascata, componente, consumidores e estados.
- **Tema de teste:** regra canônica em `AGENTS.md` (raiz, "Arquitetura e validação"). Valide só no tema ACULTA ativo.
- **Acessibilidade.** HTML semântico, foco visível (`:focus-visible`), estado nunca só por cor, texto oculto (`visually-hidden`) quando um controle só com ícone precisa de nome.
- **JavaScript mínimo.** Só onde há interação. Use `Drupal.behaviors` com `core/once`. O menu e os diálogos devem funcionar sem JavaScript quando possível.
- **HTMX:** padrão para interações novas (`core/drupal.htmx`, `data-hx-*`). Drupal AJAX (`core/drupal.ajax`, `#ajax`, `use-ajax`) é legado: não crie componente ou biblioteca V2 com `#ajax` ou `use-ajax`. Preserve links e formulários nativos como fallback. Progresso da migração: ADR-050.
- **Gavetas de mensagens/avisos (exceção registrada em 2026-11-05):** abrem por `<button>` e HTMX; dependem de JavaScript. O fallback sem JavaScript não tem link nativo para a página completa, porque o Portal não fornece `all_href`. Decisão do responsável: manter a exceção documentada, sem converter o gatilho em link nesta fase.
- **Classes mapeadas:** toda classe de `PortalUiHooks::TOKENS` precisa de CSS no tema ou de exceção em `docs/classes-sem-css-excecoes.md`. Nomes Bootstrap (`bi*`, `btn-*`, `breadcrumb*`) são proibidos no mapa. Gate: `scripts/validate-aculta420-mapped-classes.php`.
- **Nada de contexto de entidade no tema.** Não leia `user_picture`, Profile, configuração Social Auth ou regra de domínio. O Portal entrega o dado pronto.
- **Avatar.** Ordem: foto liberada → avatar do acervo → ícone neutro. Nunca letra, inicial ou monograma do nome.

## 3. Fronteiras com o Portal

- O Portal entrega variáveis de página pelo contrato `domain_presentation` e pelas variáveis `aculta_*` (ver [docs/architecture.md](docs/architecture.md)).
- Mudança de contrato do Portal (por exemplo, a chave neutra de purpose) é commit ou PR separado, conforme ADR-046.
- Autenticação, Social Auth, CAPTCHA e formulários de conta pertencem ao `aculta_portal`. O tema apresenta.

## 4. Gates e evidência

- Rode só os gates do escopo: o catálogo por escopo está em `docs/operations/GATES.md` (raiz); os gates específicos do tema estão em [docs/gates.md](docs/gates.md). Cole a saída. Não declare PASS sem execução.
- Se um gate não puder rodar no ambiente, registre como `DEFERRED` com o motivo.
- Captura na aceitação da fase: claro e escuro, em 320, 390 e 1440 CSS px, com zoom de 200% e teste de teclado. Mudança visual pequena dentro de uma fase: 320 e 1440 CSS px, claro. Guarde as capturas em `tests/browser/capturas/<frente>/<AAAA-MM-DD>/` (regra em `docs/operations/NAVEGADOR-TESTES.md`).
- Estados autenticados e leitores de tela são evidência separada. Se não foram testados, diga isso.

## 5. Versão e entrega

- Classifique a mudança como PATCH, MINOR ou MAJOR. Mudança de shell ou fundações é MINOR (ADR-046).
- Não marque versão em `aculta420_v2.info.yml` antes da validação. O número vai junto da entrega validada, no mesmo commit do `CHANGELOG.md` e do `docs/roadmap.md`.
- Commits atômicos em português, com trailer `Claude-Session` e sem `Co-Authored-By` (regra da raiz). Cada entrega é enviada a `origin/dev` logo após o commit. PR e merge só com pedido explícito.
- Entrega de fase com código não testado no servidor de desenvolvimento termina com `RUNTIME STATUS: DEFERRED` no relatório.

## 6. Documentação obrigatória no mesmo commit

Quem cria ou altera um item do tema documenta **no mesmo commit**, não depois. Documentação atrasada conta como item não entregue.

| Você criou ou alterou | Documente em | Conteúdo mínimo |
| --- | --- | --- |
| Componente SDC (`components/<nome>/`) | `docs/components.md` | nome, papel, props, slots, JavaScript, estado, regra que o componente não viola |
| Estilo de UI Styles (plugin) | `docs/ui-styles.md` (crie o arquivo no primeiro uso) | id do estilo, componente-alvo, opções e classes geradas, versão do módulo `ui_styles` testada, compatibilidade com 11.5 e 12 (ADR-045), arquivo que registra o plugin |
| Biblioteca em `aculta420_v2.libraries.yml` | `docs/architecture.md`, seção 5 (JavaScript) ou 4 (tokens e CSS) | o que carrega, dependências, quando é anexada |
| Comportamento JavaScript | `docs/architecture.md`, seção 5 | arquivo, evento que dispara, fallback sem JavaScript |
| Hook, serviço ou classe em `src/` | `docs/architecture.md`, seção 7 | o que faz, quando dispara, quais variáveis altera |
| Variável de contrato vinda do Portal | `docs/architecture.md`, seção 2 | nome, origem, uso no tema e estado (pendente ou entregue) |
| Gate novo ou alterado | `docs/gates.md` | comando, o que verifica, quando rodar, estado da última execução |
| Mudança entregue | `CHANGELOG.md` e `docs/roadmap.md` | versão, o que mudou, pendências |

Regras para cada entrega:

1. **Story antes da documentação.** Todo SDC novo tem ao menos uma story (`*.default.story.yml`). A entrada em `docs/components.md` cita a story.
2. **Status só com evidência.** Escreva "entregue" apenas com captura ou saída de gate. Caso contrário, escreva "pendente" e o motivo.
3. **Sem entrada fantasma.** Não documente o que não existe no código. Ao remover um componente, estilo ou hook, remova também a entrada.
4. **Estado explícito para pendências.** Descreva o que falta, por exemplo "sem validação autenticada" ou "DEFERRED no Homelab", em vez de deixar a seção sem status.
5. **Versão só na entrega validada.** A documentação pode descrever uma mudança ainda não versionada, mas a palavra "versionado" só aparece quando houver número no `info.yml` e entrada no `CHANGELOG.md`.
6. **Checagem antes do commit.** Confirme que o nome de cada componente, prop, slot e estilo na documentação bate com o arquivo fonte (`*.component.yml` e o PHP).

Antes de abrir a entrega, o agente responde a este checklist no relatório:

- [ ] Componentes novos ou alterados estão em `docs/components.md`, com story citada.
- [ ] Estilos de UI Styles novos ou alterados estão em `docs/ui-styles.md`.
- [ ] Bibliotecas, JavaScript, hooks e variáveis do Portal estão em `docs/architecture.md`.
- [ ] Gates novos ou alterados estão em `docs/gates.md`, com a última execução.
- [ ] `CHANGELOG.md` e `docs/roadmap.md` refletem a entrega.
- [ ] Nenhum item documentado sem correspondência no código.

## 7. Não faça

- Não crie componente só para substituir uma classe utilitária simples sem contrato reutilizável.
- Não adote UI Suite, `drupal/bootstrap_components` ou outra suíte concorrente sem nova decisão arquitetural.
- Não grave segredo, token, caminho de Homelab ou hostname de produção no tema.
- Não apague conteúdo, não altere configuração exportada sem pedido e não escreva no Runtime sem autorização.
- Não declare o tema "12 ready" sem a validação do ADR-045.
