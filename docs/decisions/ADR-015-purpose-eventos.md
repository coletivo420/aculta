# ADR-015 — Purpose `eventos` e subdomínio de eventos

**Status:** aceito em 2026-10-28, pelo responsável (decisões D1, D2, D3, D6 e D9 de
`docs/architecture/DECISOES-RESPONSAVEL-2026-10-28.md` e do plano de `aculta_events`).
**Escopo:** novo purpose de Domain para eventos, calendário e locais (`aculta_events`, `aculta_calendar`).
**Não altera** os purposes existentes nem o código: a implementação é a fase F1 do roadmap de eventos.

## Contexto

O ACULTA serve cada área pública em um subdomínio, com um purpose estável que decide rotas, conteúdo e
presentação (`docs/architecture/multidomain.md`). Eventos, calendário e locais precisam de um host próprio,
sem misturar a rota de evento com os demais purposes.

## Decisão

1. **Nome interno do purpose: `eventos`** (D1). Os purposes atuais usam nomes em inglês (`main`, `shop`,
   `courses`). `eventos` é a exceção aceita pelo responsável. As URLs públicas seguem o padrão de slugs em
   português (`docs/portal/FRIENDLY-PORTUGUESE-SLUGS.md`).
2. **Domain ID: `eventos_aculta_org`** (D2), no padrão do Domain 3.0.1 gerado a partir do host de produção.
3. **Hosts:**
   - Produção: `eventos.aculta.org`.
   - Teste: `eventos.aculta.toca.net.br`.
   O host de teste exige registro DNS no Cloudflare (ainda não resolvido em 2026-10-28) e certificado.
4. **Dois submódulos** (D3): `aculta_events` (dados, catálogo, locais e mapa) e `aculta_calendar`
   (agenda, `.ics` e incorporação por iframe). Ambos declaram o purpose `eventos`, por rota.
5. **Calendário também no `main`** (D6). A versão do `main` usa as mesmas rotas de agenda.
6. **Canonical do calendário** (D-09): a versão do `main` aponta canonical para
   `eventos.aculta.org/calendario`, evitando conteúdo duplicado nos buscadores.
7. **Fuso horário:** o do site, `America/Sao_Paulo` (D9). Recorrência fica para depois da v1.

## Consequências

- **Vitrine:** a home do `main` lista a raiz de cada purpose com Domain ativo (seção "Vitrine do MAIN"
  em `multidomain.md`). O purpose `eventos` aparece nessa vitrine automaticamente quando seu Domain estiver
  ativo, e o item fica fora dela até lá.
- **Deployer:** cada host novo entra no mapeamento de `aculta_deployer` (`deploy.json`), em
  `domain_transition`, `robots_policy` e `sitemap`. A regra `aculta.toca.net.br` → `aculta.org` já preserva o
  prefixo do subdomínio, então `eventos.*` não exige regra extra.
- **Indexação:** teste com `noindex, nofollow, noarchive`; produção indexável, conforme a política do projeto.
- **Acesso:** rotas declaram `_aculta_domain_purpose = eventos`; o `DomainRoutePolicy` bloqueia o host errado.
- **Mapa:** o provedor e o carregamento seguem o ADR-014, não este documento.

## Alternativas consideradas

| Alternativa | Por que não |
| --- | --- |
| Eventos só no `main`, sem subdomínio | Mistura a agenda com o site institucional; não permite marca e navegação próprias. |
| Nome `agenda` para o purpose | O responsável escolheu `eventos`. |
| Calendário só no subdomínio | Descartado pelo responsável (D6): o calendário aparece também no `main`. |

## Pendências para a fase F1

- Criar o Domain `eventos_aculta_org` e incluir `eventos` no `DomainPurposeManager`.
- Registrar o host no deployer e no `config/sync` (`domain.record`, ação de domínio, aliases de teste).
- Resolver o DNS de teste (`eventos.aculta.toca.net.br`) e emitir o certificado.
- Atualizar `docs/architecture/ROUTES-SITEMAP.md` com o identificador `eventos.aculta`.
