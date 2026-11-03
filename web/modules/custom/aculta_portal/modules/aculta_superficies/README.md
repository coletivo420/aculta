# ACULTA Superfícies

Submódulo opt-in do `aculta_portal`, para controlar cada superfície pública do site (Principal, Conta, Apoio, Revista, Wiki, Loja, Cursos, Fórum, Redes sociais, Eventos):

- ativar ou desativar a superfície;
- escolher o modo: `subdominio`, `diretorio` ou `desativado`;
- definir o slug em português, usado no modo `diretorio`.

**Status:** planejamento. `0.1.0-dev.1` é apenas o esqueleto desta pasta. Nenhum código PHP foi escrito. Testes: **DEFERRED** (sem Runtime).

## O que este submódulo faz

- Formulário administrativo em `/painel-administrativo`, exclusivo do purpose `main`.
- Validações: slug segue `FRIENDLY-PORTUGUESE-SLUGS`; dois purposes não podem ocupar o mesmo par host + caminho; superfícies protegidas não podem ser desativadas.
- Sincronização dos registros do módulo Domain a partir da configuração.
- Aviso de impacto antes de salvar.

## O que este submódulo NÃO faz

- **Não resolve rotas.** O resolvedor de superfície fica no núcleo (`aculta_portal`), para que o site funcione com este submódulo desligado, usando os padrões atuais.
- **Não guarda hosts.** Os hosts vêm do módulo Domain e de variável de ambiente via Drupal Key.
- **Não decide permissões de acesso.** Essas regras continuam em `DomainRoutePolicy` e no subscriber do núcleo.

## Superfícies protegidas

Estas superfícies não podem ser desativadas nem ter o modo alterado para `desativado`:

- `main` (principal, painel administrativo, carrinho, checkout e pagamento);

## Riscos que o formulário precisa mostrar

- **Sessão compartilhada.** Todos os subdomínios da mesma raiz compartilham o cookie de sessão. Mudar o modo de uma superfície altera o limite de confiança entre elas.
- **Slug antes da versão estável.** Trocar o slug faz o caminho antigo responder 404, sem redirect, como decidido pelo responsável.
- **Conteúdo existente.** Desativar uma superfície deixa seu conteúdo fora do ar. Nenhum dado é apagado.

## Dependências

- `aculta_portal` (núcleo: resolvedor, configuração padrão e mapas de purpose).
- `domain` (hosts, aliases de ambiente e registros de domínio).

## Ordem de instalação (quando houver implementação)

1. Núcleo com o resolvedor lendo a configuração e os padrões atuais (Fase 3.1 do plano).
2. Este submódulo, com o formulário e a sincronização.

Sem o passo 1, este submódulo não tem o que controlar.

## Documentos de referência

- `docs/PLANO-FINAL.md` (nesta pasta): plano consolidado, decisões e etapas.
- ADR de superfícies: `docs/decisions/ADR-043-superficies-dominio.md` e anexo `docs/decisions/ADR-043-anexo-decisoes-fixas.md`.
- `CHANGELOG.md` deste submódulo.
