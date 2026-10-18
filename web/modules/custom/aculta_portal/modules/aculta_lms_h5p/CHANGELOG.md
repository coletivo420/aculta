## 0.4.0-dev.144 — instalação privada de bibliotecas H5P — 2026-10-18

- Adiciona formulário administrativo para importar pacotes `.h5p` locais, com validação oficial H5P, limite/extensão fornecidos pelo Core, conteúdo ignorado e Hub remoto desligado.
- Bibliotecas e assets são gravados em armazenamento privado por arquivos Drupal gerenciados; imagens raster/SVG passam por `aculta_image`. Remoção administrativa limpa usos e arquivos por File API; cópias de conteúdo exigem arquivos gerenciados. A importação legada do editor falha fechada, e substituição silenciosa de assets instalados é recusada.
- Unit H5P: 8 testes, 25 assertions; Image Unit: 20 testes, 71 assertions; Kernel descoberta de hooks/serviços: 1 teste, 5 assertions. Teste com pacote real, browser e Runtime: DEFERRED; módulo permanece desabilitado.
- RUNTIME STATUS: DEFERRED.
## Continuação — instalação de bibliotecas H5P

- Corrige o schema de configuração e os nomes usados pelo adapter H5P para compartilhar o prefixo `aculta_lms_h5p_` definido na configuração de instalação.
- Migra a entidade e o field type H5P para PHP Attributes do Drupal 11; troca a descoberta de bibliotecas pelo serviço atual do Core.
- Instalação real via Kernel passou com biblioteca, asset JavaScript privado e SVG gerenciado pelo `aculta_image`; pacote sem biblioteca é rejeitado sem escrita no banco.
- Kernel H5P: 3 testes, 40 assertions; Unit H5P: 11 testes, 46 assertions. Runtime e navegador: DEFERRED.
- Restringe endpoints de progresso aos métodos HTTP usados pelo H5P e marca respostas com dados pessoais como `private, no-store`; rota validada em Kernel.
- Corrige leituras e resets de configuração que ainda usavam nomes do módulo contrib; teste instala em diretório privado customizado e o Unit do upload confere o destino configurado.
- Valida entradas do ZIP antes do extrator H5P para bloquear caminhos absolutos, traversal, caminhos malformados e links simbólicos; upload de bibliotecas agora usa o diretório privado configurado e o limite de tamanho nativo do PHP/Core.
- Verificação atual: Kernel H5P 5 testes/60 assertions; Unit H5P 12 testes/52 assertions.
- A revisão dos controllers remove service locators substituíveis por DI e testa sua criação no container; cobertura Kernel atual: 7 testes/85 assertions.
- Pré-validação integral dos arquivos de todas as bibliotecas antes do savePackage; um pacote com SVG ativo no último arquivo não grava nem a biblioteca nem o JS anterior. Kernel atual: 8 testes/101 assertions; Unit H5P: 12/52.
- Migra os quatro plugins anotados para Attributes Drupal 11 e troca `*.views.inc` depreciado por hook OOP com DI; declara `views` como dependência. Descoberta Kernel sem depreciações: 9 testes/119 assertions.
- Completa traduções da interface administrativa/permissões, verifica via descoberta Kernel a condição de curso do CTools, corrige tokens da ação de restrição e aplica CSRF do Drupal às operações POST de upgrade/reconstrução; entradas inválidas retornam erros HTTP apropriados e IDs ignorados são normalizados.
- Migra os controllers administrativos restantes de atalhos do ControllerBase para injeção explícita de serviços. Corrige instanciação DI dos formulários de manutenção e trata a biblioteca inexistente sem acesso a índice indefinido. Kernel H5P: 9 testes/124 assertions; Unit H5P: 12 testes/52 assertions; gates Portal, imagem, pacotes e slugs aprovados.
- RUNTIME STATUS: DEFERRED.

- Atualiza a orientação do painel do educador para refletir o fluxo já protegido de edição H5P na aba Editar da sala do curso.
- Adiciona Kernel com curso Group real, campo H5P e permissões reais: estudante matriculado e educador com `edit group` acessam somente conteúdo do curso; pessoa externa, referência inexistente e matrícula revogada são negadas. A condição CTools é avaliada sobre o mesmo curso. Os endpoints reais gravam/leem estado privado e salvam pontuação para o estudante, negando acesso externo. O salvamento de estado usa `merge()` pela chave primária para evitar corrida entre leitura e gravação. Kernel total: 10 testes/168 assertions; essa integração expõe 40 depreciações provenientes de Core/Group/LMS/Views instalados.
- Extrai a resolução do diretório privado H5P para serviço DI e injeta esse contrato no controller de arquivos e instalador de bibliotecas; o helper estático permanece somente como ponte para o framework H5P legado.
- Corrige a configuração exportada do campo H5P: Drupal 11 exige que `field.storage.*` contenha `settings` como array, mesmo quando o campo não define ajustes próprios. A validação de instalação real no Runtime encontrou o dado ausente.
