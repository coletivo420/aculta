# Integração visual com ACULTA420

ACULTA420 é dono da apresentação; o módulo fornece conteúdo, URLs, acesso e
cacheabilidade. O SDC existente `social-links-page` já apresenta a landing em
coluna mobile-first com tokens semânticos e modo de cor. Seu contrato deve
suportar micropáginas sem mover política de acesso ou dados para Twig/JS.

Para o editor inspirado no LinkStack, portar fluxos e hierarquia avaliados
arquivo a arquivo, não copiar componentes Laravel em bloco. Usar o Bootstrap
Component Design System, tokens, SDCs e bibliotecas Drupal existentes. Ordenação
precisa ser acessível por teclado e ter fallback sem JavaScript. Preview não
pode expor campos privados do `aculta_user`.

Antes de mudança visual, ler `docs/portal/COMPONENT-DESIGN-SYSTEM.md`,
`web/themes/custom/aculta420/docs/design-system.md` e regras de versão do tema;
validar a fronteira Portal–tema. Assets externos exigem licença individual.


A rota pública e a prévia usam o SDC existente `social-links-page` pelo template de micropágina do ACULTA420. A entidade guarda somente uma chave entre os presets permitidos `classic`, `soft` e `outline`; o SDC mapeia essa chave para tokens semânticos sem aceitar CSS/HTML arbitrário. O template de micropágina passa `show_brand: false`, tornando visível o título autoral e evitando exibir a logo institucional em páginas de terceiros; landing e Node institucional mantêm o comportamento default `show_brand: true`. A identidade do proprietário Drupal, bio, avatar e redes não são herdados até integração explícita com a API pública de `aculta_user`. Revisão responsiva, claro/escuro, teclado e leitor de tela segue pendente antes de RC.
