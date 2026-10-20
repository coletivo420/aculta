# Arquitetura proposta

## Fronteiras

`aculta_links` é um módulo Drupal nativo no diretório de submódulos do Portal.
Drupal User e
`aculta_user` continuam responsáveis por identidade, sessão, autenticação e
perfil. Drupal Core e módulos existentes são donos de entidades e APIs que já
oferecem conteúdo, Domains, Views, arquivos, cache, Forms e permissões. O
ACULTA420 permanece responsável pela apresentação, tokens e SDC.

O módulo administra micropáginas e a composição de itens. Não será uma aplicação
Laravel embutida nem terá usuários, sessão, banco, sistema de arquivo, URLs ou
resolução de domínio paralelos.

## Responsabilidades separadas

1. **Destino/link reutilizável (`aculta_link`):** campo Link do Core (URI interna
   ou externa), título e publicação; revisões e proprietário Drupal.
2. **Micropágina (`aculta_link_page`):** proprietário Drupal, slug, título,
   apresentação textual e publicação em rascunho por padrão.
3. **Item de página (`aculta_link_item`):** referências tipadas à página e ao
   destino, posição, rótulo, ativação e agendamento futuro.
4. **Evento estatístico:** evento minimizado, retenção e processamento
   assíncrono quando volume e confiabilidade justificarem.

Não fundir essas responsabilidades por conveniência. O Linky serviu como
referência para entidade/revisões; sua dependência Dynamic Entity Reference não
foi adicionada. O campo Link Core atende o destino URI sem uma dependência.

## Caminho de implantação

- 0.1.0: auditoria e decisão, sem código upstream copiado ou mudança de Runtime.
- 0.2.0: entidades base iniciadas sem ativar o módulo. Antes de habilitar ou
  publicar páginas, transferir a landing e sua configuração ao submódulo,
  mantendo endpoint e dados atuais, paridade e rollback documentados.
- Fases seguintes: editor, apresentação, métricas e ferramentas.

Cada substituição será por fluxo, com paridade e reversão documentadas. Conteúdo
ou configuração ativos não serão apagados como atalho.
