# Suíte de navegador do ACULTA Messenger

Suíte de ponta a ponta com cliques e digitação reais no Firefox ESR visível na sessão X11 local (`DISPLAY=:0`), pelo geckodriver e o protocolo W3C WebDriver. Duas contas de teste, mais uma terceira para a conversa em grupo, passam pelos dois lados de cada estado.
Sem dependências npm: usa o Node 22 (`fetch` e `child_process`) e o Drush do projeto.

## Onde roda

Somente no **servidor de teste** (`https://aculta.toca.net.br`, runtime `aculta_runtime`). Nunca em produção.
A preparação cria contas e uma conversa em grupo no banco de teste. As senhas são aleatórias e não são mostradas.

## Pré-requisitos

- Firefox (ESR) e `geckodriver` em execução: `geckodriver --port 4444`.
- Node 22 ou superior.
- Acesso local ao Drush do projeto (`vendor/drush/drush/drush.php`).

## Execução

```sh
geckodriver --port 4444 &
node tests/browser/aculta-messenger/suite.mjs
```

Variáveis opcionais:

| Variável | Padrão | Uso |
| --- | --- | --- |
| `SUITE_BASE` | `https://aculta.toca.net.br` | Site de teste |
| `SUITE_LOGIN_URI` | `https://conta.aculta.toca.net.br` | URI do Drush para o link de login |
| `SUITE_OUT` | `.suite-out/` (fora do Git) | Capturas e `results.json` |
| `GECKODRIVER` | `http://127.0.0.1:4444` | Endereço do geckodriver |

O script termina com código 1 se alguma verificação falhar, e grava `results.json` em `SUITE_OUT`.

## Preparação (`setup-accounts.php`)

Roda sozinha no início da suíte e é idempotente:

- cria `teste.b` e `teste.c` se faltarem (e-mail `@teste.invalid`, papel `email_confirmed`);
- recria a conversa em grupo de `teste.comum`, `teste.b` e `teste.c` com uma mensagem.

Para rodar só a preparação: `php vendor/drush/drush/drush.php scr tests/browser/aculta-messenger/setup-accounts.php`.

A conversa um-a-um entre `teste.comum` e `teste.b` é zerada pela própria suíte (amizade, bloqueio, contadores de envio
e mensagens), para que cada execução parta do mesmo estado.

## O que cobre

| Fase | Assunto |
| --- | --- |
| 1 | "Iniciar nova conversa", busca, nova conversa, botões "Cancelar" e "Voltar para o início" (classe e ordem) |
| 2 | Barra da conversa, menu "Opções" (ordem dos itens), "Limpar histórico" em duas etapas, pedido de amizade |
| 3 | Aceite pelo destinatário |
| 4 | "Desfazer amizade" em janela de confirmação (cancelar e confirmar), aviso "só amigos" |
| 5 | Aceite, "Bloquear" em janela de confirmação, avisos de quem bloqueou e de quem foi bloqueado |
| 6 | Desbloqueio pelo menu |
| 7 | Bloqueio pela janela e desbloqueio pelo aviso |
| 8 | Resposta depois do desbloqueio |
| 9 | Layout em 390 px: alinhamento, rótulo oculto, sem rolagem horizontal, menu |
| 10 | "Limpar histórico" só para quem confirma, e a outra parte continua vendo o histórico |
| 11 | Caixa "Opções" do perfil `/u/teste-b`: sem "Limpar histórico", "Iniciar conversa" abre a gaveta na nova conversa |
| 12 | Conversa em grupo: menu só com "Limpar histórico", limpeza só para quem confirma |

## Limites

- Não substitui o teste em dispositivo de toque real nem com leitor de tela. Os 390 px são uma aproximação de layout.
- Os textos das fases são os da interface em português. Se um texto mudar, a verificação correspondente precisa ser atualizada.
