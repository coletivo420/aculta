# Regras específicas — consulta sob demanda

Estas regras foram realocadas do arquivo histórico de instruções; aplique somente no escopo correspondente. O `AGENTS.md` raiz continua obrigatório.

## Identidade institucional

- Dados legais da Associação (razão social, CNPJ, endereço, telefone e canal de privacidade `faleconosco@aculta.org`): `docs/institution/IDENTIDADE-LEGAL.md`. Use esses dados, não o e-mail antigo `4e20coletivo@gmail.com`.
- Tratamento de dados pessoais (LGPD), por módulo e por prestador: `docs/compliance/LGPD-INVENTARIO.md`. Antes de criar campo com dado pessoal, registre-o nesse inventário.
- O Portal não processa pagamentos: todos são processados pelo Mercado Pago. CPF e CNPJ ficam cifrados no site, e só vão ao Mercado Pago quando o pagamento exige.

Nome:

ACULTA - Associação Cultural Antiproibicionista

A ACULTA é uma associação cultural antiproibicionista.

O site deve comunicar de forma clara:

- identidade institucional
- finalidade da associação
- atuação cultural
- defesa dos direitos humanos
- debate público sobre políticas de drogas
- projetos e atividades
- transparência institucional
- formas de contato

Evitar linguagem que faça o site parecer uma loja, dispensário ou plataforma comercial de cannabis.

## Conteúdo institucional do site

### Home

Apresentação clara da ACULTA e de sua atuação.

### Institucional

- Quem somos
- Missão e objetivos
- Organização

### Atividades

Apresentação das atividades realizadas pela associação.

### Projetos

Projetos culturais e sociais da ACULTA.

### Notícias

Notícias, artigos e atualizações institucionais.

### Transparência

Área destinada a documentos institucionais, incluindo quando disponíveis:

- Estatuto
- CNPJ
- Atas
- Relatórios
- documentos institucionais

### Contato

Informações oficiais de contato.

### Política de Privacidade

Página com política de privacidade do site.

## Google for Nonprofits

O site deve identificar a associação e comunicar missão, atividades, projetos,
transparência e contato conforme a seção “Identidade institucional”. Não inventar
informações institucionais ou jurídicas (CNPJ, endereço, dirigentes, datas,
registros, parceiros, financiadores ou certificações). Se um dado necessário não
foi fornecido, use placeholder claramente identificado ou solicite-o. Não trate
produtos Google for Nonprofits como ativos antes de sua ativação real.

## Wiki (regra permanente)

- **Estado atual (2026-10-22, branch `docs/aculta-forum-0.1.0-dev.1`):** o conteúdo continua no domínio `wiki420` (`wiki420.aculta.org` em produção), com o tipo `wiki_entry` e o fluxo editorial `wiki_editorial`. O submódulo `aculta_wiki` já existe em código (`web/modules/custom/aculta_portal/modules/aculta_wiki/`) e concentra o controlador, o formulário de busca e as rotas `/wiki`, `/wiki/busca` e `/wiki/busca/previa`, com os mesmos nomes de máquina `aculta_portal.wiki_*`. **Ele está declarado em `config/sync/core.extension.yml` desde 2026-10-22** (decisão do responsável); as rotas da Wiki passam a ser registradas quando o ambiente importar essa configuração. Nenhuma operação foi feita no Runtime por esta sessão. O plano e o estado de cada tarefa ficam em `docs/modules/WIKI-SUBMODULO.md`.
- **Não reimplementar a Wiki no Fórum.** O Fórum (`aculta_forum`, Growroom420) não cria verbetes nem fluxo editorial da Wiki.
- **Comentários e contexto:** comentários em verbete ficam associados ao verbete de origem. Verbetes não geram tópicos públicos automáticos no Fórum.
- **Dados pessoais:** a autoria e o conteúdo de verbete são públicos quando publicados. Quem contribui consente na publicação (art. 7º, I da LGPD). A base legal e o uso estão na política de privacidade (seção "Bases legais por uso do site") e no inventário `docs/compliance/LGPD-INVENTARIO.md`.
- **Busca e notificações (2026-10-22, Fase 5, item 4):** o `wiki_entry` entrou no índice `aculta_conteudo`, que indexa somente conteúdo publicado e reaplica Entity Access no resultado; a busca própria da Wiki e as três rotas não mudaram. O aviso de revisão usa a fila do `aculta_mailer` e vai **somente** às caixas editoriais cadastradas em `/painel-administrativo/aculta/wiki`, com título, estado e link de edição — **sem o nome de quem editou**. Vem desligado por padrão, sem destinatários. Não transforme o aviso em newsletter, lista de marketing ou notificação a quem contribui, e não acrescente autoria ao contexto.
- **Mudança de escopo:** o módulo já existe em código. A habilitação foi registrada em 2026-10-22 junto do inventário LGPD; mudar retenção ou adicionar integração (novas notificações, perfis, novos bundles de busca) exige atualizar o inventário LGPD, a política de privacidade e este `AGENTS.md` no mesmo commit.
- **Domínio:** `wiki420` é um purpose de Domain. Mudanças de host seguem `docs/architecture/multidomain.md` e o `aculta_deployer`; nada fixo no código.
