# SONI — Status de desenvolvimento

**Data:** 2026-10-15  
**Versão:** 0.7.1 (devolutivas por faixa de acertos)  
**Etapa:** 4 — Teste seus Conhecimentos: devolutivas personalizadas  
**Estado:** v0.7.1 implementada, migrações 002/003 aplicadas e editor de devolutivas validado no Windows pelo responsável («Tudo ok»); testes automatizados de pontuação/faixas aprovados no GitHub Actions 38804274629. A confirmação não discrimina o resultado individual de cada nota simulada no navegador.

## Entregue no código
- [x] Repositório oficial e Paleta 1 Harmonia Técnica.
- [x] Página institucional React/Vite demonstrativa.
- [x] Tela React /admin e link no rodapé do site.
- [x] Cliente HTTP com tratamento de erro e indicação de estado da API.
- [x] Login e consulta de autorização à API Slim (/api/admin/login e /api/admin/me).
- [x] Token mantido somente na memória React, com logout.
- [x] Proxy Vite /api na porta 8282; ajustado para `http://localhost:8282` após diagnóstico de resolução de endereço no Windows.
- [x] Configuração do administrador pelo arquivo api/.env, não versionado.
- [x] Headers adicionais anti-cache e nosniff para a API.
- [x] Instruções de ambiente local e workflow GitHub Actions.
- [x] Biblioteca firebase/php-jwt atualizada da versão 6 para ^7.2.1 para corrigir bloqueio por alerta de segurança.
- [x] CI do commit 8612fb1: npm install/build e Composer install + PHP lint passaram.
- [x] Testes de autenticação com credenciais fictícias executados com sucesso no CI em 27ad41d (login correto, credenciais inválidas e token adulterado).

## Fundação MariaDB/CMS — novos componentes
- [x] Servidor MariaDB 11.4 está em execução e cliente conecta com sucesso (confirmado pelo responsável).
- [x] Código de conexão PDO com configurações DB_* guardadas no api/.env.
- [x] Migração numerada 001_site_institucional.sql para site_settings, site_blocks e site_media; seed inicial de 5 blocos.
- [x] Script php bin/migrate.php com histórico schema_migrations e checksum.
- [x] Script php bin/db-check.php para testar conexão e conteúdo sem expor senhas.
- [x] Endpoint público GET /api/site para recuperar somente blocos visíveis/publicados do banco; corrigida omissão da rota no index.php em 2026-10-15, após erro HTTP 405 informado pelo responsável.
- [x] GET /favicon.ico responde 204, evitando erro 405 no console do navegador.
- [x] Integração HTTP de GET /api/site e favicon validada pelo GitHub Actions com MariaDB 11.4 no commit 748c2c6 (execução 38793328967, todos os jobs aprovados).
- [x] GitHub Actions executou a migração 001 em MariaDB 11.4 temporário, repetiu a migração sem duplicar registros e aprovou o filtro de rascunhos/ocultos (CI do commit ca9eab8).
- [x] Responsável confirmou em 2026-10-15 que /api/site passou a funcionar no ambiente local após atualização; isso indica consulta bem-sucedida ao MariaDB, mas a execução exata dos comandos de migração não foi relatada.
- [x] GET /api/site confirmado pelo responsável no navegador. Teste explícito de php bin/db-check.php não informado.
- [x] Código CRUD de settings e blocos da API protegido por JWT (criar/editar/excluir/ocultar/publicar/reordenar).
- [x] Código React de editor em /admin/site (sessão em memória), acesso a partir do painel /admin.
- [x] Site público React agora renderiza os blocos publicados recebidos de GET /api/site.
- [x] Teste HTTP integrado no CI: login, autorização, rejeição de URL perigosa, criação, publicação, ocultação, ordem, configurações e exclusão; aprovado na execução 38794375321.
- [x] O responsável confirmou em 2026-10-15 que o acesso ao editor e o salvamento/visualização das alterações funcionaram no Windows (retorno: "tudo certo").
- [x] Código v0.5.0: upload autenticado de imagens (JPG/PNG/WebP/GIF), áudio (MP3/WAV/OGG) e vídeo (MP4/WebM), limites e inspeção MIME real, armazenamento em api/storage/site_media (fora de api/public).
- [x] Mídias persistidas na tabela site_media já existente e associadas ao campo site_blocks.media_id, sem nova migração.
- [x] Rota GET /api/site/media/{id} exige bloco publicado/visível; GET /api/admin/site/media/{id} é protegida por JWT e serve prévia de rascunho; suporte HTTP Range.
- [x] Editor React com envio, seleção de mídias e pré-visualização particular de blocos.
- [x] CI aprovado no commit a9ce7fe, execução 38797251656: upload PNG, JWT, leitura pública/privada, HTTP Range, publicação, ocultação e rejeição de extensões/tipos incompatíveis; compilação React e verificação PHP também aprovadas.
- [x] O responsável confirmou no Windows (2026-10-15): "Já testei imagem, audio e vídeo e todos estao funcionando". Uploads e reprodução dos três tipos de mídia validados na prática; não houve relatório separado dos limites extremos do php.ini.
- [ ] Implementar limpeza de mídias órfãs, backup, auditoria e ajustes SEO/acessibilidade.

## Manutenção e backup v0.6.0
- [x] Implementado `php bin/backup.php`: exportação SQL MariaDB com senha fora dos argumentos, cópia das mídias e manifesto SHA-256, armazenamento fora do webroot.
- [x] Implementada verificação de integridade do snapshot; cópias incompletas não são promovidas a backup concluído.
- [x] Implementado `php bin/media-audit.php`: análise sem modificação por padrão; exclui da lista mídias usadas em blocos publicados, ocultos, rascunhos e logotipo.
- [x] Implementada quarentena opcional (movimentação recuperável, não remoção permanente), restrita a mídias sem vínculo mais antigas que 30 dias, dependente de backup íntegro criado nas últimas 12 horas.
- [x] GitHub Actions 38799724661 aprovou exportação SQL, integridade SHA-256, identificação de órfãos, preservação de mídia em uso e quarentena no MariaDB descartável.
- [ ] Executar primeiro os comandos seguros de backup e auditoria no Windows do responsável.
- [ ] Testar restauração completa em banco separado, testar backup externo/off-site e definir retenção/quota.


## Confirmação local da v0.6.0 — 2026-10-15
- [x] O responsável executou \`php bin/backup.php\`: "Backup concluído e verificado", snapshot \`20261015-201352-540b7e04\` (diretório privado, não versionado).
- [x] O responsável executou \`php bin/media-audit.php\`: 0 mídias sem referência com mais de 30 dias; simulação sem alterações.
- [ ] Confirmar cópia do backup para outro disco/destino seguro e ensaio de restauração isolada; a verificação SHA-256 não é restauração.

## Primeira versão dos questionários — v0.7.0
- [x] Código da migração \`002_quizzes.sql\`: categorias, testes, questões, alternativas, tentativas e respostas, com mídias opcionais de imagem e áudio.
- [x] API administrativa JWT para criar/editar categorias e testes e gerenciar questões e alternativas; publicação sujeita à validação de todas as questões.
- [x] API pública de catálogo e aplicação, sem revelar gabarito/explicação antes de completar a tentativa; correção e persistência do resultado no servidor.
- [x] Formulário do aluno com seleção de uma alternativa por questão e explicações após envio; painel do administrador com cadastro de mídia via biblioteca existente.
- [x] Mídias associadas aos testes publicados podem ser reproduzidas via rota pública; auditoria de mídias protege vínculos em questões e alternativas, inclusive rascunhos.
- [x] Teste HTTP integrado ao CI para rascunhos, publicação, alternativas, gabarito e persistência de tentativas.
- [ ] Verificar CI da v0.7.0 e testar no Windows. Uma nova migração SQL precisa ser aplicada com \`php bin/migrate.php\`.
- [ ] Para produção: rate limiting das tentativas anônimas, prevenção de abuso, controles de sessão, questões aleatórias, versões avançadas e relatórios de desempenho.

## Devolutivas por faixas — v0.7.1
- [x] Criada a migração 003_quiz_feedback.sql com faixas por teste e cópia histórica da devolutiva por tentativa; testes anteriores preservados.
- [x] Novos testes recebem cinco faixas padrão; a migração oferece as mesmas faixas aos testes existentes, sem sobrepor personalizações.
- [x] Endpoint JWT PUT /api/admin/quizzes/{id}/feedback-ranges com validação de cobertura 0–100%, exatos centésimos, sem lacunas ou sobreposições; salvamento transacional.
- [x] O editor React permite ajustar porcentagens, classificação, mensagem e recomendação; adicionar/remover faixas.
- [x] A resposta do POST de finalização contém performance_feedback com a faixa aplicada e registro persistente histórico em quiz_attempt_feedback.
- [x] Página pública apresenta classificação, devolutiva e recomendação somente depois da correção.
- [x] GitHub Actions 38804274629 aprovado: migrações 001–003, build React, PHP, validações de faixas, notas 0%/50%/100%, proteção de dados públicos e imutabilidade do histórico.
- [x] Responsável confirmou no Windows: 001 já aplicada, 002 e 003 aplicadas; «Migrações concluídas.»
- [x] Responsável confirmou «Tudo ok» após roteiro de validação das faixas e salvamento de mensagens no navegador (2026-10-15).
- [ ] Não foi recebido relatório separado para cada resultado de nota 0%, 50% e 100% no Windows; esses casos passaram nos testes automáticos.
- [ ] Cópia externa de backup e teste isolado de restauração ainda pendentes.

## Problema de comunicação local identificado em 2026-10-15
- [x] Teste direto via `curl.exe -i http://localhost:8282/api/health` retornou HTTP 200 e JSON válido (informado pelo responsável).
- [x] Teste via `127.0.0.1:8282` apresentou erro (informado pelo responsável).
- [x] O Vite estava configurado para encaminhar `/api` à interface IPv4 `127.0.0.1`, onde não foi possível estabelecer conexão.
- [x] Atualizado `frontend/vite.config.js` para usar o endereço `localhost:8282`, consistente com a API acessível.
- [x] Após `git pull` e reinicialização do Vite, o responsável confirmou que o indicador `API: conectada` funcionou na página `/admin` (2026-10-15).

## Validação pendente
- [x] Responsável informou que `composer install` e `npm install` terminaram sem erros no Windows (2026-10-15).
- [x] Responsável confirmou login bem-sucedido no painel React conectado ao Slim com credenciais configuradas localmente no api/.env (2026-10-15).
- [ ] Conferir erros de autenticação, desconexão e saída no navegador.
- [ ] Definir rate limit e configurações finais de HTTPS/CORS antes de produção.

## Nova decisão aprovada — site institucional editável
- [x] Analisada como referência a implementação em `kriale-dot/mapa-percepcao-relacional`: editor por blocos com criação, alteração, remoção, ordem, visibilidade, título do site e upload de imagens.
- [x] Registrado requisito definitivo de CMS institucional na SONI; documentação técnica em `docs/SONI_CMS_INSTITUCIONAL.md`.
- [x] Implementada primeira versão do CMS na API e React em `/admin/site`, com edição textual, links e estados de publicação. Upload e prévia de rascunhos pendentes.
- [x] Fluxo de edição/salvamento/visualização pública validado pelo responsável em 2026-10-15; testes específicos de exclusão/ordem/ocultação no Windows não foram discriminados.
- [ ] Editor não vai alterar a paleta oficial, apenas conteúdo configurável.

## Ainda não desenvolvido
- [x] Interface real para gerenciar configurações e blocos textuais; gestão de mídia/testes/documentos/vídeos ainda não implementada.
- [x] Estrutura de migrations e conexão PDO/MariaDB incluída no código; execução local e conexão definitiva pendentes.
- [ ] Motor de provas, cadastro e aplicação com texto/imagem/áudio.
- [ ] Biblioteca PDF/PNG com upload seguro.
- [ ] Biblioteca de vídeos por YouTube e upload.
- [ ] Testes completos, backup e deploy.

## Próxima ação
Iniciar o módulo F5 — Biblioteca de Documentos (PDF/PNG), incluindo upload seguro, catálogo público, organização por categorias e visualização. Antes do deploy, tratar rate limiting das tentativas de quizzes, segurança, restauração isolada e backup externo. Não executar scripts de teste CI em banco real.

**Limite da verificação:** CI consegue compilar/validar o código, mas não substitui a validação visual/end-to-end no ambiente do responsável.
