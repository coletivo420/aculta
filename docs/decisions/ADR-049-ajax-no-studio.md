# ADR-049 — AJAX no ACULTA Studio (V2)

- **Status:** aceito pelo responsável em 2026-11-05.
- **Data:** 2026-11-05.
- **Relaciona-se com:** ADR-044 (decisão 8 e seção 3, mecanismo pendente, agora escolhido), ADR-045 (AJAX isolado em um módulo; preferir página completa sem ganho real), ADR-046 (sem jQuery por padrão), ADR-047 (menu), ADR-048 (layouts e popup de foto), `web/themes/custom/aculta420_v2/docs/studio-estrutura.md`.

## 1. Contexto

- O ADR-044 deixou em aberto qual mecanismo de AJAX a V2 usa: A (formulário sem AJAX), B (Fetch próprio com `<dialog>`), C (HTMX do core) ou D (`core/drupal.ajax`, que depende de jQuery).
- O ADR-045 pede que todo AJAX do tema passe por um único módulo JavaScript, e que páginas completas sejam preferidas quando AJAX não traz ganho real.
- O responsável pediu AJAX em todo o Studio.

## 2. Decisões

1. **Mecanismo: B, Fetch próprio com resposta aplicada em `<dialog>` ou na área da tela.** Sem jQuery e sem `core/drupal.ajax` no tema. Segue o padrão já usado pelo login da V2 (`multidomain-bar.js`).
2. **Módulo único.** Todo o AJAX do Studio fica em um só arquivo JavaScript do tema (`studio-ajax.js`, nome a confirmar na implementação), com `Drupal.behaviors` e `core/once`. Trocar o mecanismo no futuro fica restrito a esse arquivo (ADR-045, item 2).
3. **Escopo: ações, formulários e navegação interna.** Salvar, sortear, remover, enviar foto e os formulários de perfil e de conta são enviados por AJAX. A troca de tela do Studio dentro do mesmo purpose também é feita por AJAX (item 4).
4. **Navegação interna por AJAX (decisão de 2026-11-05, revisa a versão anterior deste item).** Dashboard, Meu Perfil, Minha conta (e suas seções), Marley (e suas abas, seções e subseções) e demais telas do Studio que pertencem ao mesmo purpose trocam só a área de conteúdo. Regras:
   - Cada tela continua com URL própria. Todo link é um `href` real, então sem JavaScript a página completa responde.
   - A URL muda com `history.pushState`. Voltar e avançar do navegador (`popstate`) restauram a tela sem recarregar.
   - A barra institucional, a faixa do domínio e a navegação do Studio ficam no lugar. Só o estado ativo (`aria-current`) é atualizado.
   - Ao trocar de tela: o título da página (`document.title`) muda, o foco vai para o `h1` da nova tela e uma região `aria-live` anuncia a mudança. A rolagem volta ao topo, e a posição é restaurada no `popstate`.
   - Requisição de fragmento: o servidor devolve só a área de conteúdo quando recebe o cabeçalho de AJAX. Resposta privada, com `Cache-Control: private` e `Vary: Cookie`.
   - Falha de rede ou de resposta: o módulo faz navegação normal para o mesmo endereço.
5. **Navegação entre purposes não usa AJAX.** A política cross-domain (`docs/portal/CROSS-DOMAIN-REQUEST-POLICY.md`, seção "AJAX/fetch") exige que link entre purposes faça navegação normal, e proíbe CORS para contornar isso. Por isso, WE Sativa (purpose `wiki`), Ganjalab (`courses`), Green Stash (`shop`), Culturas (`eventos`) e Meus cursos (`courses`) abrem por link comum, com página completa. Ver pendência 1 da seção 6.
6. **Popup de foto usa o mesmo mecanismo.** Envio de arquivo, sorteio e recorte passam pelo módulo do item 2, e não por `Drupal.ajax` dentro do `<dialog>`. Isso corrige a opção que estava como padrão no ADR-048, item 10, e na especificação 4.2.1.
7. **Sem JavaScript, funciona.** Todo formulário envia por POST comum e a página completa responde. O AJAX é melhoria progressiva.
8. **Comandos suportados.** O módulo trata um subconjunto de comandos do protocolo de resposta do Drupal: `insert`, `replaceWith`, `remove`, `redirect`, `update_build_id` e mensagens de status. Comando não suportado não é ignorado em silêncio: o módulo envia o formulário de novo por POST comum.
9. **Estados visíveis.**
   - Carregando: `aria-busy="true"` na área afetada, botão de envio desabilitado. Sem envio duplo.
   - Sucesso: mensagem de status em região `role="status"`. O foco fica na mensagem ou no próximo passo.
   - Erro de validação: foco no primeiro campo inválido, com `aria-invalid` e texto de erro associado.
   - Erro de rede: mensagem em `role="alert"`, e o formulário continua preenchido.
10. **Mesma origem.** Requisições só para o próprio host, como no login. Nenhuma requisição atravessa purpose (PAYMENT-DOMAIN-POLICY e CROSS-DOMAIN-REQUEST-POLICY).
11. **Sem cache de resposta.** Respostas de telas do Studio são privadas e não entram em cache público (ADR-048, 8).

## 3. Lista inicial de fluxos com AJAX (ações)

| Fluxo | Onde | Dono da regra |
| --- | --- | --- |
| Salvar perfil público (apelido, biografia, redes, visibilidade) | Meu Perfil | `aculta_user` |
| Salvar dados cadastrais, endereço, senha, e-mail e preferências | Minha conta | `aculta_portal` e módulos de conta |
| Enviar, sortear, remover e salvar foto | Popup de foto (Meu Perfil e área de conta) | `aculta_user` |
| Recortar foto (aplicar e cancelar) | Popup de recorte | `aculta_user` |
| Abrir e fechar o popup de foto | Avatar | tema (abertura) |

Fora desta lista, o formulário continua por POST comum até ser incluído por
decisão.

## 4. O que precisa mudar fora do tema

- **`aculta_user`:** os formulários de foto e de perfil público devolvem resposta
  de AJAX (comandos da lista do item 7) quando chegam com `_wrapper_format=drupal_ajax`
  ou com o cabeçalho de AJAX. Esse é o mesmo contrato que o login já usa.
- **`aculta_portal`:** formulários de conta seguem o mesmo contrato, por PR própria.
- **Gate:** o `validate-aculta-user-photo.php` passa a cobrir o fluxo com Fetch.
  A mudança é PR própria, com gate.

## 5. Consequências

- Nenhum jQuery novo entra no tema. O jQuery que o Core traz nas páginas do
  Portal continua sendo dívida registrada (ADR-045, item 6), sem mudança aqui.
- Há código próprio a manter: o protocolo mínimo do item 7. O risco de divergência
  com o Core é aceito e fica limitado ao módulo do item 2.
- O popup de foto deixa de depender de `core/drupal.dialog.ajax`, em linha com
  o item 10 do ADR-048.
- Em caso de migração para HTMX do core (ADR-044, opção C) ou para `Drupal.ajax`
  sem jQuery, o ponto de troca é o módulo do item 2.

## 6. Pendências e não-decisões

1. **Telas de outros purposes.** Política cross-domain exige navegação normal. Escolha entre manter a navegação normal (recomendado) ou mudar o purpose da suíte, o que altera ADR-038.
2. **Rota `/` do purpose de conta:** resolvida em 2026-11-05. Studio em `/`, Conta em `/conta`, retificando o ADR-038.
- Não define o nome final do arquivo JavaScript.
- Não define a lista de comandos além do item 7; ampliar exige teste com o
  formulário que o usa.
- Não decide o Drupal 12 (ADR-045).

## 7. Validação

- Teste de cada fluxo da seção 3 e da navegação do item 4 com JavaScript ligado e desligado.
- Voltar e avançar do navegador restauram a tela e a posição de rolagem.
- Teclado: foco vai para a mensagem após sucesso e para o primeiro erro.
- Leitor de tela: `role="status"` e `role="alert"` anunciados.
- Comando não suportado cai no POST comum, sem perda de dados.
- Gates no Homelab. Capturas dos estados de carregamento, sucesso e erro em 320,
  390 e 1440 px, claro e escuro, fora do repositório (`$SUITE_OUT`).
- Status: **DEFERRED** no Homelab, sem execução nesta sessão.

## 8. Alternativa htmx (em avaliação, não decidida)

Esta seção registra a opção C do ADR-044 como caminho concreto. A decisão 1 (mecanismo B) continua valendo até o responsável decidir de outra forma.

**O que é.** htmx é uma biblioteca pequena que troca partes da página por fragmentos HTML vindos do servidor. O elemento declara o que faz (requisição, alvo, troca), e o servidor devolve HTML, sem JSON nem protocolo de comandos.

**Estado no Drupal.** O Core 11.3 incluiu suporte nativo a htmx, como parte da iniciativa "Replace AJAX API with HTMX" (fonte: página do módulo contrib `htmx` em drupal.org). O módulo contrib `htmx` 2.x cobre 11.3 a 11.5, e a versão 3.x é a linha do Drupal 12. Pela iniciativa, htmx 4 chega com o Drupal 12. Não há versão definida para descontinuar a API atual de AJAX; a própria iniciativa prevê uma transição de várias versões maiores.

**Como ficaria no Studio (conceito).**

- Navegação interna: o link tem `href` real e atributos htmx (`hx-get`, `hx-target`, `hx-push-url`). Sem JavaScript, o link vai para a página completa. Com JavaScript, troca só a área de conteúdo e atualiza a URL, sem o módulo próprio de `history.pushState`.
- Formulários: `hx-post` com o alvo do bloco. O Drupal responde só o fragmento da área (no artigo consultado, por `onlyMainContent()`).
- Popup de foto e login: o conteúdo do `<dialog>` é trazido por `hx-get` para o alvo do dialog; o envio usa `hx-post`.
- Comandos de resposta: a troca usa o próprio htmx. O módulo de comandos próprio (ADR-049, item 8) deixaria de existir.

**Vantagens.**

- Menos código próprio: o subconjunto de comandos e o controle de estado saem do tema.
- O Core é o dono do protocolo e da resposta de fragmento.
- Sem jQuery, e com um arquivo pequeno de dependência.

**Desvantagens e riscos.**

- A API do Core para htmx está em transição (Drupal 12 traz htmx 4). Uma mudança de versão pode exigir reescrever atributos.
- O comportamento com o `Htmx` do Core (nomes de atributos e cabeçalhos) não foi confirmado nesta análise: a página consultada era do módulo contrib, não a documentação do Core. Confirmar no `core/lib` do 11.5 no Homelab antes de decidir.
- Mudanças de estado com foco, `aria-live` e `document.title` continuam no tema; htmx não faz isso sozinho.
- A regra de mesma origem e de navegação normal entre purposes (item 5) vale do mesmo jeito.

**Recomendação.** Manter B agora, por dois motivos: o protocolo atual é pequeno e está no mesmo módulo, e o Drupal 12 vai mudar o htmx. Abrir uma decisão de migração para C quando (a) a API htmx do Core 11.5 estiver confirmada no Homelab, e (b) a primeira tela de Studio com AJAX estiver pronta e medida. Nesse ponto, o módulo do item 2 é o único arquivo a trocar.
