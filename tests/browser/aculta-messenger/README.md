# Suíte de navegador do ACULTA Messenger

Suíte de ponta a ponta com cliques e digitação reais no Firefox ESR visível na sessão X11 local (`DISPLAY=:0`), pelo geckodriver e o protocolo W3C WebDriver. Duas contas de teste passam pelos estados de conversa; uma terceira participa do cenário em grupo.
Sem dependências npm: usa o Node 22 (`fetch` e `child_process`) e o Drush do projeto.

## Onde roda

Somente no **servidor de teste** (`https://aculta.toca.net.br`, runtime `aculta_runtime`). Nunca em produção.
A preparação cria contas e fixtures de conversa no banco de teste. As senhas são aleatórias e não são mostradas.

## Pré-requisitos

- Firefox (ESR) e `geckodriver` em execução: `geckodriver --port 4444`.
- Node 22 ou superior.
- Acesso local ao Drush do projeto (`vendor/drush/drush/drush.php`).

## Execução

```sh
DISPLAY=:0 geckodriver --port 4444 &
DISPLAY=:0 node tests/browser/aculta-messenger/suite.mjs
DISPLAY=:0 node tests/browser/aculta-messenger/central-empty-state.mjs
DISPLAY=:0 node tests/browser/aculta-messenger/drawer-notice-filters.mjs
DISPLAY=:0 SUITE_WIDTH=390 node tests/browser/aculta-messenger/drawer-notice-filters.mjs
```

O script recusa qualquer display diferente de `:0` e só aceita `SUITE_BASE`/`SUITE_LOGIN_URI` nos hosts HTTPS do servidor
de teste. A sessão usa Firefox ESR visível; o modo headless é proibido pelas regras locais.

Variáveis opcionais:

| Variável | Padrão | Uso |
| --- | --- | --- |
| `SUITE_BASE` | `https://aculta.toca.net.br` | Site de teste |
| `SUITE_LOGIN_URI` | `https://conta.aculta.toca.net.br` | URI do Drush para o link de login |
| `SUITE_OUT` | `.suite-out/` (fora do Git) | Capturas e `results.json` |
| `SUITE_WIDTH` | `1280` | Largura da janela solicitada para `drawer-notice-filters.mjs`; o relatório inclui a largura efetiva (Firefox pode limitá-la) |
| `GECKODRIVER` | `http://127.0.0.1:4444` | Endereço do geckodriver |

O script termina com código 1 se alguma verificação falhar, e grava `results.json` em `SUITE_OUT`.

## Preparação (`setup-accounts.php`)

Roda sozinha no início da suíte e é idempotente:

- cria `teste.b`, `teste.c` e `teste.d` se faltarem (e-mail `@teste.invalid`, papel `email_confirmed`);
- recria a conversa em grupo de `teste.comum`, `teste.b` e `teste.c` com uma mensagem.
- remove somente a thread `teste.b`/`teste.c`/`teste.d`, para o teste criar esse grupo pela interface.

Para rodar só a preparação: `php vendor/drush/drush/drush.php scr tests/browser/aculta-messenger/setup-accounts.php`.

A conversa um-a-um entre `teste.c` (conta comum) e `teste.b` é zerada pela própria suíte (preferências de mensagem de
`teste.b`, amizade, bloqueio, contadores de envio e mensagens), para que cada execução parta do mesmo estado.
`teste.comum` tem o papel `content_editor` no Runtime e, por isso, não serve para verificar a restrição de mensagens
“Somente amigos”; continua como terceiro participante da conversa em grupo.
O grupo criado pela interface usa `teste.b` e `teste.d` como destinatários e `teste.c` como remetente.

## O que cobre

| Fase | Assunto |
| --- | --- |
| 1 | "Iniciar nova conversa", busca, nova conversa, botões "Cancelar" e "Voltar para o início" (classe e ordem); cria pela interface um grupo com dois destinatários e envia a primeira mensagem |
| 2 | Barra da conversa, menu da thread, "Limpar histórico" em duas etapas, pedido de amizade |
| 3 | Aceite pelo destinatário |
| 4 | "Desfazer amizade" em janela de confirmação; preferência "só amigos" não bloqueia resposta em thread existente |
| 5 | Aceite, "Bloquear" em janela de confirmação, avisos de quem bloqueou e de quem foi bloqueado |
| 6 | Desbloqueio pelo menu |
| 7 | Escape fecha só a janela de bloqueio e mantém a gaveta aberta; bloqueio e desbloqueio pelo aviso |
| 8 | Resposta depois do desbloqueio |
| 9 | Layout em 390 px: alinhamento, rótulo oculto, sem rolagem horizontal, menu |
| 10 | "Limpar histórico" só para quem confirma, e a outra parte continua vendo o histórico |
| 11 | Caixa "Opções" do perfil `/u/teste-b`: sem "Limpar histórico", "Iniciar conversa" abre a gaveta na nova conversa |
| 12 | Conversa em grupo: menu só com "Limpar histórico", limpeza só para quem confirma |
| 13 | Estado visual e anunciado de carregamento e erro recuperável na gaveta |

`central-empty-state.mjs` é uma verificação focada da aba Avisos no host de Conta. Ela verifica filtros combinados ativos,
estado vazio dentro da lista da conta (sem confundi-lo com a gaveta do cabeçalho), anúncio `role="status"` e espaçamento
visual do tema.

`drawer-notice-filters.mjs` verifica a gaveta multidomínio no host do Portal: grupos de origem/leitura, seções de avisos
com cabeçalhos/separadores por origem, preservação de filtros combinados após navegação AJAX e URLs de fallback da Conta.
É somente leitura e não cria notificações; a verificação de seções depende de haver avisos visíveis para a conta de teste.
Na sessão de 2026-10-24, o Firefox ESR limitou a janela solicitada de 390 px a um viewport efetivo de 500 px; o relatório
e a captura guardam a dimensão real, sem declarar uma validação de 390 px.

## Limites

- Não substitui o teste em dispositivo de toque real nem com leitor de tela. Os 390 px são uma aproximação de layout.
- Os textos das fases são os da interface em português. Se um texto mudar, a verificação correspondente precisa ser atualizada.

## Execução registrada

Em 2026-10-24, a suíte completa passou com **76/76 verificações** no Runtime de teste com Firefox ESR visível (`DISPLAY=:0`), incluindo estados loading/error e a preferência friends-only em conversa existente.
O teste usa o diálogo do Drupal/jQuery UI para confirmar amizade e bloqueio, e comprova que Escape fecha o modal sem fechar a gaveta.

Também em 2026-10-24, `central-empty-state.mjs` passou com **4/4 verificações** no host de Conta, confirmando os filtros ativos, o texto não vazio com `role="status"` na lista própria da central e o espaçamento visual.

Na mesma data, `drawer-notice-filters.mjs` passou **6/6 verificações** com viewport de 1280 px e **6/6** com janela solicitada de 390 px no host do Portal. O Firefox reportou viewport efetivo de 500 px na segunda execução; não se declara teste real a 390 px. A navegação apenas abriu a gaveta e consultou filtros, sem alterar avisos ou preferências.

Em 2026-10-25, `drawer-notice-filters.mjs` passou **8/8 verificações** no Firefox ESR visível (`DISPLAY=:0`), host do Portal e viewport efetivo de 1270 px. A rodada confirmou seções com cabeçalho/separador por origem, navegação combinada Wiki + Não lidos e ausência de overflow. Quatro seções estavam disponíveis no estado da conta de teste; nenhum aviso ou preferência foi alterado.
