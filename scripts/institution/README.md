# Scripts institucionais

Este diretório contém scripts e artefatos auxiliares de provisionamento e
migração. Ele **não é fonte de verdade documental** da arquitetura atual.

Documentação canônica:

- [Mapa da documentação](../../docs/README.md)
- [Deployment](../../docs/operations/DEPLOYMENT.md)
- [Camadas anti-regressão](../../docs/ANTI-REGRESSION.md)
- [Módulos Drupal](../../docs/modules/README.md)

## Scripts mantidos

### `provision-commerce-store.php`

Provisiona de forma idempotente a Store Commerce canônica.

Executar somente quando o deployment exigir a criação da Store e depois de
confirmar os dados institucionais usados pelo script.

### `provision-lms-pilot.php`

Compatibilidade com o caminho histórico de execução do curso piloto. O script
delega ao loader de `scripts/content/`, cuja fonte editorial canônica é
`scripts/content/courses/introducao-antiproibicionismo.json`.

Não mantém conteúdo próprio. Não executar em produção por rotina; usar apenas
em ambiente/aplicação em que o curso piloto foi explicitamente aprovado.

### `phase6-account-email-test.php`

Checker local de integração Email Confirmer / Change Mail.

É ferramenta de teste, não mecanismo de produção nem fonte de verdade do fluxo
de e-mail.

## Dados e manifests

Arquivos JSON deste diretório são artefatos auxiliares/históricos de
provisionamento e inventário.

Regras:

- não tratá-los como substitutos de `config/sync`;
- não tratá-los como documentação atual;
- confirmar consumidor e validade antes de alterar/usar;
- manifests marcados como superseded permanecem históricos;
- não adicionar novos “final reports” em JSON/Markdown como rotina.

Uma limpeza futura de artefatos pode remover manifests sem consumidor após
verificação específica.

## Configuração de produção

`MULTIDOMAIN-SETTINGS-PRODUCTION.example.php` é exemplo sem segredos.

Settings reais e credenciais pertencem ao ambiente privado.

## Histórico

Os antigos relatórios Markdown de Fase 7/8/9, reviews locais, implementação,
carousel e integrações foram removidos do `main` durante a reorganização
documental. Permanecem recuperáveis pelo histórico Git.

Não recriá-los. Registre resultados novos no PR/issue/release correspondente.
