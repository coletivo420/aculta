# Changelog — ACULTA Links

## 0.1.0-dev.1 — fundação e auditoria inicial — 2026-10-20

- Inventariada a implementação atual do purpose `links`, seus consumidores,
  configuração de conteúdo e apresentação no ACULTA420.
- Criado o submódulo documental, sem habilitá-lo e sem alterar a implementação
  atualmente servida.
- Registradas arquiteturas propostas, inventário Linky/LinkStack, matriz de
  portabilidade, integrações, segurança e pendências de licença.
- LinkStack estudado na tag `v4.8.0` (`ae6837e8c0cc2cd0e77f26952ceca97d0eebec35`);
  Linky estudado na tag `2.0.0-beta1`
  (`2d70712a854065428faf0228aef96066bd6812f2`). Nenhum código foi copiado.
- Validação desta fundação: manifesto YAML parseado pelo Symfony YAML e links
  locais Markdown verificados em 2026-10-20; diff-check PASS. Runtime:
  DEFERRED; módulo não instalado nem habilitado.
- O gate do índice revelou que sua comparação histórica tratava a primeira linha
  de um componente novo como inalterada. Uma correção do validador entrou
  acidentalmente no commit de outra frente (`fb1e678b`); a verificação posterior
  continua falhando na comparação desse commit. Histórico não foi reescrito.

## 0.2.0-dev.1 — entidades base de links e micropáginas — 2026-10-20

- Criadas as entidades revisionáveis `aculta_link`, `aculta_link_page` e
  `aculta_link_item`, separando destino, página e ordem/apresentação.
- A página inicia como rascunho; item referencia página e link por Entity
  Reference. O acesso ao item revalida a propriedade de ambos.
- Acesso de usuário fica privado; administração global exige permissão restrita.
- O módulo continua sem rotas públicas e desabilitado; a página institucional
  `links.aculta.org/` permanece sem migração nesta etapa.
- Unit de controle de acesso: 1 teste, 7 asserções; Kernel de storage/acesso:
  1 teste, 20 asserções. Ambos PASS em PHP 8.4.26. PHP 8.5 e Runtime DEFERRED.
  PHPUnit Unit emitiu aviso de diretório HTML não gravável e duas deprecações.
- A validação do mapa módulo-tema identificou também módulos concorrentes ainda
  não documentados. O registro de Links foi incluído; o gate de status permanece
  bloqueado pelo commit histórico misto e por alterações concorrentes.
