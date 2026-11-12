# TODO — aculta_studio

Estado: Dev · versão 0.1.0-dev.53 (`aculta_studio.info.yml`) · status: `docs/operations/status/aculta_studio.yml`.

## Próximo passo

Continuar a refatoração das chamadas estáticas de serviço restantes em classes dos módulos, com testes focais e gates do Portal.
Manter a Identity servida e fechar os DEFERRED de escrita/persistência, duas lojas, pareamento upstream e leitor de tela. Preservar S-R4/S-R5 e a decisão de caminhos de ADR-038. A rota `/conta` e os atributos de apresentação do fallback estão validados; prosseguir na adoção SDC por consumer real, com captura antes/depois.

## Leitura

- Leia só este arquivo, o código alvo e o `CHANGELOG.md` do componente se ele existir, pelo trecho necessário.
- Roadmap completo, grande: `web/modules/custom/aculta_portal/modules/aculta_studio/docs/ROADMAP.md`. Leia só a seção pelo título: `rg -n '^## ' web/modules/custom/aculta_portal/modules/aculta_studio/docs/ROADMAP.md`.

## Ao concluir uma fase

Atualize versão, estado e próximo passo aqui e em `docs/operations/status/aculta_studio.yml` (quando houver), no mesmo commit. Remova o que foi concluído; não acrescente histórico neste arquivo.
