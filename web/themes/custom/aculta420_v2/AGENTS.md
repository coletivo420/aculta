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
- **Mobile-first.** Base em 320 CSS px, sem rolagem horizontal da página, alvo de toque de 44 px, zoom de 200% funcional. Antes de mexer em `max-width`, rastreie seletor, cascata, componente, consumidores e estados.
- **Acessibilidade.** HTML semântico, foco visível (`:focus-visible`), estado nunca só por cor, texto oculto (`visually-hidden`) quando um controle só com ícone precisa de nome.
- **JavaScript mínimo.** Só onde há interação. Use `Drupal.behaviors` com `core/once`. O menu e os diálogos devem funcionar sem JavaScript quando possível.
- **Nada de contexto de entidade no tema.** Não leia `user_picture`, Profile, configuração Social Auth ou regra de domínio. O Portal entrega o dado pronto.
- **Avatar.** Ordem: foto liberada → avatar do acervo → ícone neutro. Nunca letra, inicial ou monograma do nome.

## 3. Fronteiras com o Portal

- O Portal entrega variáveis de página pelo contrato `domain_presentation` e pelas variáveis `aculta_*` (ver [docs/architecture.md](docs/architecture.md)).
- Mudança de contrato do Portal (por exemplo, a chave neutra de purpose) é commit ou PR separado, conforme ADR-046.
- Autenticação, Social Auth, CAPTCHA e formulários de conta pertencem ao `aculta_portal`. O tema apresenta.

## 4. Gates e evidência

- Rode os gates aplicáveis listados em [docs/gates.md](docs/gates.md) e cole a saída. Não declare PASS sem execução.
- Se um gate não puder rodar no ambiente, registre como `DEFERRED` com o motivo.
- Mudança visual exige captura em claro e escuro, em 320, 390 e 1440 CSS px, com zoom de 200% e teste de teclado. Guarde as capturas em `$SUITE_OUT`, fora do repositório.
- Estados autenticados e leitores de tela são evidência separada. Se não foram testados, diga isso.

## 5. Versão e entrega

- Classifique a mudança como PATCH, MINOR ou MAJOR. Mudança de shell ou fundações é MINOR (ADR-046).
- Não marque versão em `aculta420_v2.info.yml` antes da validação. O número vai junto da entrega validada, no mesmo commit do `CHANGELOG.md` e do `docs/roadmap.md`.
- Commits atômicos em português, com o trailer `Co-Authored-By`. Commit e push só quando pedido ou autorizado.
- Toda fase termina em PR própria, com `RUNTIME STATUS: DEFERRED` quando houver código não testado no servidor de desenvolvimento.

## 6. Não faça

- Não crie componente só para substituir uma classe utilitária simples sem contrato reutilizável.
- Não adote UI Suite, `drupal/bootstrap_components` ou outra suíte concorrente sem nova decisão arquitetural.
- Não grave segredo, token, caminho de Homelab ou hostname de produção no tema.
- Não apague conteúdo, não altere configuração exportada sem pedido e não escreva no Runtime sem autorização.
- Não declare o tema "12 ready" sem a validação do ADR-045.
