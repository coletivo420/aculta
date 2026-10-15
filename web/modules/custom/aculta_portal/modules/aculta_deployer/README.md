# ACULTA Deployer

Submódulo do `aculta_portal`. Ferramenta de deploy que troca os hosts de teste
(`*.aculta.toca.net.br`) pelos hosts de produção (`*.aculta.org`) no build de
produção, e mantém o registro das correções de deploy que o código não pode
resolver sozinho (canonicals e sitemap).

- Versão: 0.1.21 (`VERSION`, `Cli::VERSION` e `CHANGELOG.md`). O `aculta_deployer.info.yml` ainda marca 0.1.10; divergência registrada em DT-O12.
- Papel: complemento opcional do `aculta_portal`. O Portal não depende dele. O módulo fica desabilitado por padrão; a CLI roda sem Drupal e o painel do Portal lê o relatório `var/deployer/status.json`, mesmo com o módulo desabilitado.
- Estado no Drupal: descoberto, **não habilitado**. A ferramenta não precisa de
  Drupal para rodar; a CLI é standalone.
- CLI: `bin/aculta-deployer`. Não usa Drush, serviços Drupal ou vendor.

## Início rápido

```
php web/modules/custom/aculta_portal/modules/aculta_deployer/bin/aculta-deployer check
php web/modules/custom/aculta_portal/modules/aculta_deployer/bin/aculta-deployer build --out=/caminho/fora/do/repo
php web/modules/custom/aculta_portal/modules/aculta_deployer/tests/run.php
```

## Documentação

- [ARQUITETURA.md](docs/ARQUITETURA.md): componentes e barreiras de separação.
- [USO.md](docs/USO.md): comandos e procedimento de deploy.
- [GUARDRAILS.md](docs/GUARDRAILS.md): regras obrigatórias.
- [REGISTRO.md](docs/REGISTRO.md): formato e ciclo de vida das entradas do registro.
- [PLANEJAMENTO.md](docs/PLANEJAMENTO.md): fases de construção e gates de segurança.
- [CHANGELOG.md](CHANGELOG.md): histórico de versões.
