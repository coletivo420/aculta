# Changelog — aculta_events_openstreetmap

## 0.1.0-dev.2 — escrita fictícia validada e limpa (2026-10-29)

- Ensaio no OSM de produção, autorizado e revisado: nó fictício `14331309082`
  criado no Atlântico Sul (changeset `191197897`), atualizado pelo caminho real
  do módulo (`changeset 191197899`, versão 2) e removido
  (changeset `191197900`). A verificação posterior confirmou a ausência do nó.
- Nenhum token, segredo ou corpo sensível foi impresso; os changesets levam
  comentário de teste.
- Kernel `OpenStreetMapOAuthSafetyKernelTest`: 4 testes, 53 assertions, 12
  depreciações.

## Não lançado — seleção explícita da API ativa a partir do teste (D-45)

- O ambiente de teste continua usando a API de desenvolvimento por padrão e pode selecionar a API ativa somente com a configuração local explícita `aculta_openstreetmap_environment=production`.
- O contrato do `SecretsManager` permite no checkout de teste apenas as chaves da aplicação OAuth da API ativa; os valores e a chave de cifra ficam em `secrets/`, fora do Git.
- O assistente mantém revisão e confirmação explícita para cada envio. Nesta etapa nenhuma autenticação OAuth ou edição real do mapa foi feita.
- Kernel `OpenStreetMapOAuthSafetyKernelTest`: 4 testes, 53 assertions, 12 deprecações.
