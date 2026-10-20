# Auditoria de portabilidade — LinkStack

## Referência reproduzível

- Repositório: [LinkStackOrg/LinkStack](https://github.com/LinkStackOrg/LinkStack).
- Referência inicial auditada: tag `v4.8.0`, commit `ae6837e8c0cc2cd0e77f26952ceca97d0eebec35`.
- Release mais recente consultada em 2026-10-20: tag `v4.8.6`, commit
  `dca586e8b8946a779cb212d21791cff6308fa7c0`. A auditoria arquivo a arquivo
  dessa release ainda está pendente.
- Árvore consultada: controllers Laravel, `resources/views`, CSS/JS,
  `themes` e `blocks` na tag.
- `composer.json` declara `GPL-3.0-or-later`; o `LICENSE` dessa mesma tag contém
  AGPL-3.0. Isso é um conflito de licença a resolver por arquivo/upstream.
- O GitHub Advisory Database lista `GHSA-mg7g-g2wv-6mhr` para LinkStack até
  `v4.8.6`: possível bypass de autorização em `UserController::saveLink`. Não
  portar o fluxo nem sua autorização; usar Entity Access e ownership Drupal.
- A árvore da tag contém `app/Models/{Page,Button,Link}.php`,
  `app/Http/Controllers/{Home,User}Controller.php`, rotas Laravel,
  `resources/views/studio/*`, `resources/views/panel/*`,
  `resources/views/linkstack/*`, `themes/*` e `blocks/*`. A amostragem identifica
  recursos, mas não substitui auditoria de licença por arquivo.

## Componentes avaliados

| Componente | Decisão | Destino previsto |
| --- | --- | --- |
| Dashboard/listagem de páginas | PORTAR | Drupal Forms, Views, rotas e access checks. |
| Editor de links e itens | PORTAR | Form API/AJAX com fallback, ownership Drupal e revisão definida. |
| Ordenação/ativação | REIMPLEMENTAR | Entity/Form API; JS mínimo e interação acessível por teclado. |
| Preview público | PORTAR | SDC/Twig via ACULTA420, dados fornecidos pelo módulo. |
| Temas/presets | PORTAR/REIMPLEMENTAR | Presets governados por tokens; não executar tema arbitrário. |
| Blocks/extensões de terceiros | ADIAR | Sem contrato de extensão executável nesta fase. |
| Estatísticas/cliques | REIMPLEMENTAR | Queue/Cron e armazenamento minimizado, após análise LGPD. |
| QR Code | ADIAR | Avaliar dependência Drupal/PHP quando a fase justificar. |
| Slugs/redirect curto | REIMPLEMENTAR | APIs Drupal, política de slugs e DomainPurposeManager; proteger open redirect. |
| Autenticação, instalação, usuários Laravel | DESCARTAR | Drupal User e `aculta_user`. |
| Backup, editor de ambiente, updater, jobs/config Laravel | DESCARTAR | Operação/deploy e Configuration API Drupal. |
| Blade/controllers/Eloquent/rotas Laravel | PORTAR comportamento | Twig/SDC, Controllers, Entity API/Storage e Routing Drupal. |
| CSS/JS/assets | NÃO COPIAR AINDA | Revisão de licença/origem e adaptação ACULTA420 necessárias. |

## Bloqueio de licença

A divergência entre metadado Composer e arquivo LICENSE impede definir a licença
dos componentes pela raiz do repositório. Antes de cópia, consultar upstream e
inventariar licenças de código, CSS, JS, fontes, ícones, imagens, temas, blocos e
dependências. A AGPL tem obrigação de fonte correspondente para interação de
usuários por rede em versão modificada, além de avisos e condições de interface
interativa. Compatibilidade e fronteira de obra combinada com Drupal não são
inferidas nesta auditoria. Nenhum código foi copiado. O aviso de segurança
também reforça que autorização upstream não deve ser reutilizada.
