# Componentes próprios: caixa, nome e barreiras

Status: **vigente desde 2026-10-18** (Portal 0.4.0-dev.128).

## 1. O que é um componente próprio

Todo módulo ou submódulo do projeto que está em `web/modules/custom/`, em qualquer profundidade. Hoje é o
`aculta_portal` e seus submódulos (`web/modules/custom/aculta_portal/modules/**`). Módulos contrib e Core não são
componentes próprios e ficam fora desta regra.

Componentes de apresentação do tema (`web/themes/custom/aculta420/components/`) seguem
`docs/portal/COMPONENT-DESIGN-SYSTEM.md` e o mapa `docs/portal/MAPA-MODULO-TEMA.md`. Esta orientação trata do
rótulo de pacote dos módulos.

## 2. Regra: uma só caixa

Todo módulo próprio declara, no `.info.yml`:

```yaml
package: 'ACULTA Portal'
```

- A caixa é a única usada em `/painel-administrativo/modulos` para o que é do projeto.
- A grafia é exata: `ACULTA Portal`. Variações (`Aculta Portal`, `ACULTA portal`, `Portal ACULTA`) criam uma caixa
  separada e não são aceitas.
- Não usar `Associação Cultural Antiproibicionista` em módulo próprio. Esse nome identifica a associação no texto do
  site, não agrupa código. Módulos contrib usam o próprio rótulo do mantenedor.
- Não há exceção para o módulo pai. Desde 0.4.0-dev.128 o `aculta_portal` também usa `ACULTA Portal`.

**Motivo:** o painel agrupa os módulos pelo texto de `package`. Um rótulo diferente divide a lista e dificulta a
conferência do que é do projeto. A regra é de navegação administrativa; nenhum código ou configuração lê o rótulo.

## 3. Como criar um componente próprio

1. Criar o diretório dentro de `web/modules/custom/aculta_portal/modules/<nome>/` (submódulo) ou, quando for
   realmente independente do Portal, em `web/modules/custom/<nome>/` com a mesma regra de rótulo.
2. Declarar `package: 'ACULTA Portal'` no `.info.yml`.
3. Registrar o caminho em `docs/portal/MAPA-MODULO-TEMA.md` (exigência já existente do gate de mapa).
4. Atualizar a versão e o CHANGELOG do `aculta_portal` no mesmo commit, conforme `web/themes/custom/aculta420/docs/versioning.md`.
5. Rodar os gates da seção 4.

## 4. Barreiras

| Gate | O que impede | Comando |
|---|---|---|
| Rótulo de pacote | Módulo próprio com rótulo diferente de `ACULTA Portal` | `php scripts/validate-own-module-packages.php` |
| Mapa módulo x tema | Módulo sem registro no mapa | `php scripts/validate-module-theme-map.php` |
| Gate Drupal 11+ do Portal | Regressões de arquitetura | `php scripts/validate-aculta-portal-drupal11.php` |

O gate de rótulo é estático: lê os `.info.yml` com o parser YAML, não inicia o Drupal e roda em qualquer checkout.
Ele foi testado nos dois sentidos: passa com a configuração atual e falha quando um submódulo recebe o rótulo
`Associação Cultural Antiproibicionista`.

Após mudar um rótulo no Runtime de teste, limpar o cache (`php vendor/drush/drush/drush.php cr`) e conferir com
`extension.list.module`. O painel só reflete a mudança depois do cache.

## 5. O que esta orientação não cobre

- Nome técnico (machine name) e nome exibido (`name:`) dos módulos. Continuam sob as regras de slugs e de
  `docs/portal/FRONTEIRA-MODULO-TEMA.md`.
- Agrupamento de estilos de componentes do tema: `docs/portal/COMPONENTES-AGRUPAMENTO.md`.
