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
  AGPL-3.0. O README versionado também declara que o projeto está sob AGPL-3.0
  desde a versão 4.0.0 e exige que obras derivadas sigam seus termos. A decisão
  conservadora é classificar o código original como AGPL-3.0; o campo Composer é
  metadata divergente, ainda sem correção upstream.
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
| Dashboard/listagem de páginas | PORTAR COMPORTAMENTO | Drupal Forms, Views, rotas e access checks, sem copiar implementação Laravel. |
| Editor de links e itens | REIMPLEMENTAR | Form API/AJAX com fallback, ownership Drupal e revisão definida. |
| Ordenação/ativação | REIMPLEMENTAR | Entity/Form API; JS mínimo e interação acessível por teclado. |
| Preview público | PORTAR COMPORTAMENTO | SDC/Twig via ACULTA420, dados fornecidos pelo módulo. |
| Temas/presets | REIMPLEMENTAR | Presets governados por tokens; não executar tema arbitrário. |
| Blocks/extensões de terceiros | ADIAR | Sem contrato de extensão executável nesta fase. |
| Estatísticas/cliques | REIMPLEMENTAR | Queue/Cron e armazenamento minimizado, após análise LGPD. |
| QR Code | REIMPLEMENTAR | Endroid QR Code 6.1.3 gera SVG da URL canônica da página publicada; código LinkStack e serviço remoto descartados. |
| Slugs/redirect curto | REIMPLEMENTAR | APIs Drupal, política de slugs e DomainPurposeManager; proteger open redirect. |
| Autenticação, instalação, usuários Laravel | DESCARTAR | Drupal User e `aculta_user`. |
| Backup, editor de ambiente, updater, jobs/config Laravel | DESCARTAR | Operação/deploy e Configuration API Drupal. |
| Blade/controllers/Eloquent/rotas Laravel | REIMPLEMENTAR comportamento | Twig/SDC, Controllers, Entity API/Storage e Routing Drupal; sem trechos derivados. |
| CSS/JS/assets | DESCARTAR | Recriar apresentação em ACULTA420 a partir dos contratos e necessidades funcionais, sem reutilizar expressão original. |

## Decisão operacional de licença

O README e o `LICENSE` versionados dão uma classificação conservadora suficiente
para conduzir o desenvolvimento sem cópia: nenhuma implementação ou asset do
LinkStack será incorporado. A auditoria por arquivo de assets permanece
desnecessária para a estratégia vigente, pois CSS, JavaScript, fontes, ícones,
imagens, temas e blocos são descartados. Se o escopo voltar a incluir código
original, será necessária revisão formal de compatibilidade e de cada arquivo,
incluindo dependências e créditos. O aviso de segurança também exclui a
reutilização do fluxo `UserController::saveLink`.
