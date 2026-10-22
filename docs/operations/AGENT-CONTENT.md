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

- **Estado atual (2026-10-20, Runtime de teste):** a Wiki funciona como conteúdo no domínio `wiki420` (`wiki420.aculta.org` em produção), com o tipo de conteúdo `wiki_entry` e o fluxo editorial `wiki_editorial`. Havia 7 verbetes, 6 publicados. Não existe um módulo de Wiki habilitado: a implementação é de conteúdo e configuração.
- **Não reimplementar a Wiki no Fórum.** O Fórum (`aculta_forum`, Growroom420) não cria verbetes nem fluxo editorial da Wiki.
- **Comentários e contexto:** comentários em verbete ficam associados ao verbete de origem. Verbetes não geram tópicos públicos automáticos no Fórum.
- **Dados pessoais:** a autoria e o conteúdo de verbete são públicos quando publicados. Quem contribui consente na publicação (art. 7º, I da LGPD). A base legal e o uso estão na política de privacidade (seção "Bases legais por uso do site") e no inventário `docs/compliance/LGPD-INVENTARIO.md`.
- **Mudança de escopo:** se a Wiki ganhar módulo próprio, retenção específica ou integração (busca, notificações, perfis), atualize o inventário LGPD, a política de privacidade e este `AGENTS.md` no mesmo commit.
- **Domínio:** `wiki420` é um purpose de Domain. Mudanças de host seguem `docs/architecture/multidomain.md` e o `aculta_deployer`; nada fixo no código.
