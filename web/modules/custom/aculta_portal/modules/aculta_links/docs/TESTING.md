# Testes e critérios de migração

## Fundação 0.1.0 (histórico)

- Validar sintaxe do manifesto YAML.
- Executar `php scripts/validate-project-status-index.php`.
- Executar `git diff --check`.
- O módulo está habilitado no Runtime de teste desde 2026-10-20; a configuração versionada lista a extensão. Não importar globalmente a configuração enquanto existirem diferenças de outras frentes.

## Entidades 0.2.0 em desenvolvimento

- Unit: permissão do proprietário, isolamento de outra conta e administração.
- Kernel em `aculta_simpletest`: armazenamento de destino/página/item,
  referências, rascunho padrão, acesso por proprietário e revisão do destino.
- Validar lint/PHPCS, dependências Core e gates de mapa/documentação do projeto.

## Migração da landing e UI

Antes de mover a landing, testar configuração e URL, render/cache, formulário,
fallback sem JavaScript e autorização administrativa. Comparar HTTP e apresentação
no purpose `links`; conferir que `/` e o caminho interno `/redes-sociais` não
perdem conteúdo. Criar mecanismo de reversão antes de qualquer migração de dados.

Para entidades: Unit/Kernel/Functional em MariaDB dedicado conforme
`docs/operations/BANCO-DE-TESTE.md`, cobrindo ownership, revisões, unicidade,
URLs inválidas, isolamento Domain, access, cache e publicação. Runtime é DEFERRED
para produção; evidências parciais do Runtime de teste e seus limites são
registrados abaixo.

## Interface pública e segurança

- Kernel valida unicidade/gramática do slug, constraint do destino, access publicado e path/purpose declarado.
- Functional/browser valida `/pagina/{slug}` em links e 404 em outros purposes; inspeciona SDC em tamanhos móveis/desktop, claro/escuro, teclado, contraste e sem overflow.
- Cache precisa invalidar ao editar/despublicar página, item ou link e ao mudar alias de Domain.

Update de entidade `aculta_links_update_10001` aplicado ao Runtime de teste em
2026-10-20 com zero micropáginas existentes; o schema recebeu o campo
revisionável `theme_preset`. Nenhum update de outro módulo foi executado.

Em 2026-10-20, Kernel passou com 1 teste/49 asserções incluindo prévia privada
do proprietário, preset persistido e `max-age: 0` (1 teste/52 asserções); o
route provider do Runtime confirmou `/pagina/{slug}/previa` no purpose `links`;
HTTP anônimo sem entidade e HTTP no purpose `main` retornaram 404. Firefox
headless a 390 × 844 CSS px apresentou o preset `soft` com HTTP 200; o cabeçalho
vazio de projetos foi removido. Os fixtures de três presets foram apagados após
verificação de identidade. Claro/escuro completo, desktop, teclado, leitor de
tela e prévia autenticada continuam pendentes.
