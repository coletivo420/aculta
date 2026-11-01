# Dados de teste do Runtime local

Status: **ativo no Runtime Homelab**. Documenta conteúdos, usuários e demais
elementos de exemplo criados para teste manual e de QA. Este documento não contém
senhas e não deve ser usado como fonte de credenciais.

## Escopo e limites

- Vale apenas para o **Runtime Homelab** (banco MariaDB `aculta_runtime`, com
  credenciais fora do Git). Nunca produção, Hostinger ou VPS.
- Não altera os Estados (`estados/`), que são backups imutáveis e ficam fora do
  disco local e do Git.
- Não usa `drush cim`, `cex` ou `updb`.
- Todos os e-mails são fictícios, no domínio reservado `@example.invalid`.
- Títulos de conteúdo e de curso começam com `[EXEMPLO]`, para facilitar a remoção.

## Credenciais

Arquivo local, fora do repositório:

```text
~/.config/aculta-homelab/test-credentials.env   (permissão 600)
```

- Senhas aleatórias, geradas uma vez pelo seed.
- Não são credenciais de integração. Por isso não passam pelo Drupal Key nem pelo
  arquivo `secrets/aculta.secrets.env`, que ficam reservados a integrações (ver
  `SECRETS.md`).
- Nunca copiar para `settings*.php`, configuração exportada, issue, PR ou chat.
- Login de teste: `https://aculta.toca.net.br/entrar`.

## Usuários

| Usuário | Papel Drupal | Ativo | Uso de teste |
| --- | --- | --- | --- |
| `qa_membro` | authenticated | sim | conta autenticada comum; sem menu extra |
| `qa_aluno` | authenticated | sim | aluno LMS sem papel de professor |
| `qa_editor` | content_editor | sim | edição de conteúdo editorial |
| `qa_wiki` | wiki_editor | sim | edição da Wiki |
| `qa_professor` | lms_teacher | sim | área de professor no LMS |
| `qa_bloqueado` | authenticated | **não** | login deve ser **negado** |

Contas de pessoas reais (`piradopirata`, `admin`) não foram alteradas.

## Conteúdos

Todos foram criados **despublicados** (`status = 0`). Visitantes não os veem.
Editores e administradores veem pelo painel.

| Tipo | Quantidade | Estados cobertos |
| --- | --- | --- |
| `project` | 3 | status `ativo`, `pausado`, `concluido`; sem imagem; um com título muito longo |
| `activity` | 2 | modalidades `online` e `presencial`; status `agendado` |
| `editorial_highlight` | 2 | pesos 90 e 80, para o carrossel |
| `page` | 1 | página interna simples |
| `wiki_entry` | 1 | verbete simples |

Não foram criados artigos. Eles exigem imagem e categoria editorial, que não existem
no Runtime (`media = 0`, `editorial_category` sem termos). Criar esses dados é uma
decisão separada.

## Curso LMS

- 1 grupo do tipo `lms_course`, despublicado: `[EXEMPLO] Curso de teste: introdução`.
- Não há lições nem matrícula. Matrícula e progresso exigem ação do LMS.
- Já existe um curso anterior no Runtime (`Introdução ao Antiproibicionismo`),
  que não foi alterado.

## Como publicar um item para teste público

Só faça isso quando o teste exigir visitante anônimo. Antes, confirme que não há dado real.

```sh
php vendor/drush/drush/drush.php php:eval '\Drupal::entityTypeManager()->getStorage("node")->load(ID)->setPublished()->save();'
```

## Manifesto e remoção

- Manifesto com os IDs criados: `~/.config/aculta-homelab/qa-seed-manifest.json`
  (permissão 600, sem senhas).
- Scripts guardados fora do repositório: `~/.config/aculta-homelab/seed-examples.php`
  (idempotente: pula usuários e conteúdos já existentes) e
  `~/.config/aculta-homelab/check-pass.php` (confere cada senha sem exibi-la).
- Para remover, use os IDs do manifesto. Confirme antes que cada ID tem o prefixo
  `[EXEMPLO]`; não remova nada sem essa conferência.
- Para trocar senhas: remova as contas de teste e rode o seed de novo, ou use
  `drush user:password` para cada conta.

## Como recriar

```sh
php vendor/drush/drush/drush.php php:script seed-examples --script-path=$HOME/.config/aculta-homelab
php vendor/drush/drush/drush.php php:script check-pass --script-path=$HOME/.config/aculta-homelab
```

## Não versionar

Credenciais, o manifesto, scripts de seed e qualquer export contendo essas contas
ficam fora do Git. Este documento descreve o processo, sem valores.
