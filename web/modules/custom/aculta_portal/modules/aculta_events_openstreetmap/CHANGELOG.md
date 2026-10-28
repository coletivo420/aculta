# Changelog — aculta_events_openstreetmap

## Não lançado — seleção explícita da API ativa a partir do teste (D-45)

- O ambiente de teste continua usando a API de desenvolvimento por padrão e pode selecionar a API ativa somente com a configuração local explícita `aculta_openstreetmap_environment=production`.
- O contrato do `SecretsManager` permite no checkout de teste apenas as chaves da aplicação OAuth da API ativa; os valores e a chave de cifra ficam em `secrets/`, fora do Git.
- O assistente mantém revisão e confirmação explícita para cada envio. Nesta etapa nenhuma autenticação OAuth ou edição real do mapa foi feita.
- Kernel `OpenStreetMapOAuthSafetyKernelTest`: 4 testes, 53 assertions, 12 deprecações.
