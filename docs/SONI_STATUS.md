# SONI — Status de desenvolvimento

**Data:** 2026-10-16  
**Versão:** 0.9.0 — MVP funcional congelado (próxima meta: 1.0.0)  
**Etapa:** 7/8 — Preparação para produção, segurança e implantação  
**Estado:** MVP v0.9.0 aprovado pelo responsável em 2026-10-15 após confirmação de testes locais; CI React/PHP/MariaDB aprovado (38817558203). Migrações 001–005 aplicadas no Windows. **Implantação técnica inicial disponível via HTTPS (confirmação do responsável em 2026-10-16), mas lançamento v1.0.0 ainda NÃO autorizado:** faltam checklist P0, cópia externa e restauração de backup, limites de abuso, homologação e monitoramento.


## Preparação de produção — escolha do servidor (2026-10-16)
- [x] Responsável confirmou reutilizar o Droplet DigitalOcean com Ubuntu 24.04 LTS como destino da SONI.
- [x] Diagnóstico parcial: Ubuntu 24.04.4, Apache 2.4.58, PHP CLI 8.3.6 + fileinfo/pdo_mysql, cliente MySQL 8.0.46, diversos VirtualHosts 80/443, disco com 68 GB livres.
- [x] Confirmados RAM, módulos Apache e mod_php, MySQL 8.0.46 ativo e domínios finais.
- [x] Layout de deploy escolhido: frontend em `/var/www/somdaigreja.com.br/frontend/dist` para raiz e www; API em `/var/www/somdaigreja.com.br/api/public` para `api.somdaigreja.com.br`. Painel integrado em `/admin`.
- [x] Domínios anteriores `somdaigreja.com.br`, `api.somdaigreja.com.br` e `admin.somdaigreja.com.br` tiveram VirtualHosts desativados pelo responsável; arquivos/dados/DNS/certificados preservados.
- [x] Ajuste de código para URLs de mídias cross-origin, CORS e incorporação segura de PDF; CI React/PHP/MariaDB concluída com sucesso no commit `464831e`.
- [x] Responsável confirmou que `/var/www/somdaigreja.com.br` existe e está vazio e que `frontend` e `api` ainda não existem.
- [x] Apache possui `dir_module`, `headers_module`, `php_module`, `rewrite_module`, `ssl_module`; PHP operando com mod_php (não apareceu serviço PHP-FPM).
- [x] Serviço MySQL ativo; RAM total 7,8 GiB, disponível 4,2 GiB; sem swap configurada.
- [x] Responsável clonou `main` em `/var/www/somdaigreja.com.br`, diretórios `frontend` e `api` presentes; Git limpo.
- [x] Composer 2.10.1, Node 24.21.0 e npm 11.19.0 disponíveis. `composer install`, `npm ci` e build React sem mensagens de erro conforme relato do responsável; saída de `dist/index.html` ainda não enviada.
- [x] MySQL 8.0.46, banco `plataforma_soni` inicialmente vazio, usuário `soni_app` com concessões restritas a esse banco; conexão por senha e API PDO confirmadas pelo responsável.
- [x] Migrações 001–005 concluídas e consideradas corretas pelo responsável.
- [x] VirtualHosts específicos frontend e API criados/habilitados, TLS existentes (frontend/raiz/www e API) e `apache2ctl configtest` = Syntax OK.
- [x] Apache recarregado e quatro checagens HTTP aprovadas pelo responsável: frontend raiz, www e `/admin`, API `/api/health` e `/api/site` (descritos coletivamente como quatro verificações).
- [x] `.env` confirmado `640 root:www-data`; mídia em `api/storage/site_media` recebeu permissão restrita de escrita para `www-data`, com teste real de escrita aprovado pelo responsável.
- [x] CORS corrigido no `.env` e frontend conseguiu comunicar-se com API; upload/prévia de imagem de 3–5 MB testados com sucesso.
- [x] Limites de upload ajustados no VirtualHost HTTPS da API (40 MB arquivo/48 MB POST); teste de imagem acima de 2 MB aprovado.
- [x] Biblioteca de documentos PDF e PNG: responsável confirmou upload, visualização pública, publicação e ocultação funcionando.
- [x] Biblioteca de vídeos YouTube/MP4-WebM validada pelo responsável: prévia privada, publicação, reprodução e ocultação aprovadas.
- [x] Primeiro backup real da SONI gerado e verificado pelo script PHP em `api/storage/backups/20261016-110803-d28cea70` (confirmação do responsável em 2026-10-16).
- [ ] **NÃO PRIORIZAR por decisão do responsável em 2026-10-16:** não deseja mais procedimentos de backup, pois conserva código e arquivos de desenvolvimento no GitHub/Google Drive. Primeiro backup local verificado; não há confirmação de cópia externa do banco MySQL de produção e das mídias enviadas. Registrar o risco residual de perda de dados do servidor antes do aceite de lançamento 1.0.0, sem bloquear a continuidade dos testes.
- [ ] Testar proteção de rotas, monitoramento e homologação E2E.
- [ ] **Rate limiting não implementado/validado — ADIADO por decisão do responsável em 2026-10-16.** Login administrativo continua exposto a tentativas automatizadas; manter como risco residual e pendência de segurança do lançamento.
- [x] Código de backup adaptado para localizar `mariadb-dump` ou `mysqldump` e não exigir permissão global `PROCESS` do MySQL 8 (CI pendente). Não considerar v1.0.0 lançada até completar P0.
- [x] Procedimento de diagnóstico criado em `docs/SONI_DEPLOY_UBUNTU_DIGITALOCEAN.md`.

## Marco oficial — MVP v0.9.0 congelado (2026-10-15)
- [x] Responsável aprovou explicitamente o congelamento da v0.9.0 como produto mínimo viável.
- [x] Responsável informou "Os testes passaram" após a implementação da biblioteca de vídeos; foi uma confirmação funcional geral, sem relatório caso a caso.
- [x] Escopo oficial: site institucional, administração, testes com devolutivas, bibliotecas de documentos/vídeos e manutenção básica.
- [x] Registrada a decisão em `docs/SONI_MVP_ESCOPO.md`.
- [x] Criado o checklist de lançamento com bloqueadores P0 em `docs/SONI_CHECKLIST_LANCAMENTO.md`.
- [ ] Validar ambiente de produção, HTTPS, limites de abuso, proteção de uploads, política de privacidade, backups externos, restauração isolada, deploy, monitoramento, conteúdo e testes ponta a ponta.
- [ ] Realizar aceite GO/NO-GO para a v1.0.0; publicação e disponibilidade pública **não confirmadas**.

**Política:** fase de estabilização. Somente correções, segurança, backup, conteúdo essencial, implantação e testes. Funcionalidades novas ficam após a v1.0.0.

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
- [x] Backup e auditoria conservadora entregues em v0.6.0. Ajustes SEO/acessibilidade continuam pendentes para revisão de lançamento.

## Manutenção e backup v0.6.0
- [x] Implementado `php bin/backup.php`: exportação SQL MariaDB com senha fora dos argumentos, cópia das mídias e manifesto SHA-256, armazenamento fora do webroot.
- [x] Implementada verificação de integridade do snapshot; cópias incompletas não são promovidas a backup concluído.
- [x] Implementado `php bin/media-audit.php`: análise sem modificação por padrão; exclui da lista mídias usadas em blocos publicados, ocultos, rascunhos e logotipo.
- [x] Implementada quarentena opcional (movimentação recuperável, não remoção permanente), restrita a mídias sem vínculo mais antigas que 30 dias, dependente de backup íntegro criado nas últimas 12 horas.
- [x] GitHub Actions 38799724661 aprovou exportação SQL, integridade SHA-256, identificação de órfãos, preservação de mídia em uso e quarentena no MariaDB descartável.
- [x] Backup e auditoria segura executados no Windows pelo responsável; cópia off-site e restauração isolada seguem pendentes.
- [ ] Testar restauração completa em banco separado, testar backup externo/off-site e definir retenção/quota.


## Confirmação local da v0.6.0 — 2026-10-15
- [x] O responsável executou `php bin/backup.php`: "Backup concluído e verificado", snapshot `20261015-201352-540b7e04` (diretório privado, não versionado).
- [x] O responsável executou `php bin/media-audit.php`: 0 mídias sem referência com mais de 30 dias; simulação sem alterações.
- [ ] Confirmar cópia do backup para outro disco/destino seguro e ensaio de restauração isolada; a verificação SHA-256 não é restauração.

## Primeira versão dos questionários — v0.7.0
- [x] Código da migração `002_quizzes.sql`: categorias, testes, questões, alternativas, tentativas e respostas, com mídias opcionais de imagem e áudio.
- [x] API administrativa JWT para criar/editar categorias e testes e gerenciar questões e alternativas; publicação sujeita à validação de todas as questões.
- [x] API pública de catálogo e aplicação, sem revelar gabarito/explicação antes de completar a tentativa; correção e persistência do resultado no servidor.
- [x] Formulário do aluno com seleção de uma alternativa por questão e explicações após envio; painel do administrador com cadastro de mídia via biblioteca existente.
- [x] Mídias associadas aos testes publicados podem ser reproduzidas via rota pública; auditoria de mídias protege vínculos em questões e alternativas, inclusive rascunhos.
- [x] Teste HTTP integrado ao CI para rascunhos, publicação, alternativas, gabarito e persistência de tentativas.
- [x] CI da versão de quizzes executado e migração 002 aplicada no Windows; validação e devolutivas confirmadas na v0.7.1.
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

## Biblioteca de Documentos — v0.8.0
- [x] Migração 004_documents.sql: categoria e cadastro de documentos associados à mídia, além do tipo DOCUMENT para PDF.
- [x] Upload PDF até 20 MiB com verificação de extensão, MIME real e cabeçalho; PNG até 6 MiB mantém as validações existentes.
- [x] API administrativa JWT: listar/criar/editar/apagar cadastros, criar e editar categorias; exclusão preserva arquivos físicos.
- [x] API pública: catálogo filtrado por pesquisa/categoria e consulta individual, com acesso somente a documentos publicados e visíveis.
- [x] Endpoint de mídias permite acesso público a PDFs/PNGs apenas quando associados a cadastro publicado/visível.
- [x] Frontend /admin/documents: upload, categorias, edição e prévia privada, status/visibilidade.
- [x] Frontend /documentos e /documentos/{id}: catálogo com pesquisa e leitor de PDF ou PNG.
- [x] Auditoria e quarentena de mídias respeitam vínculos a documentos, inclusive rascunhos e ocultos; backup contempla arquivos PDF.
- [x] Teste HTTP no CI inclui JWT, privacidade, PDF/PNG, publicação/ocultação, pesquisa, exclusão de metadados sem perda física.
- [x] CI 38807367006 aprovado: build React, PHP, migrações 001–004, envio/consulta PDF/PNG, rascunho, pesquisa, JWT, ocultação, exclusão de cadastro preservando arquivo e rejeição de HTML renomeado para PDF.
- [x] Migração 004_documents.sql concluída no Windows, segundo resultado enviado pelo responsável: 001–003 já aplicadas, 004 aplicada, «Migrações concluídas.»
- [ ] Testar upload de PDF/PNG, prévia privada, publicação e pesquisa no navegador.
- [ ] Definir paginação avançada, segurança de PDFs para produção, antivírus/CDN, categorias reordenáveis e teste de restauração isolada.

## Biblioteca de Vídeos — v0.9.0
- [x] Banco 005_videos.sql: categorias e registros YouTube ou upload MP4/WebM, rascunho/publicado, visível/oculto.
- [x] API JWT para categorias e CRUD de vídeos, validação estrita da URL HTTPS YouTube e vínculo a mídia tipo VIDEO.
- [x] API pública /api/videos e /api/videos/{id}, pesquisa por título, descrição e categoria.
- [x] Upload MP4/WebM reutiliza a biblioteca de mídia existente (32 MiB) e prévia autenticada para arquivos em rascunho.
- [x] Leitor público YouTube no youtube-nocookie.com ou player HTML5 para vídeo enviado.
- [x] Frontend /admin/videos e /videos, /videos/{id}, integrado com site institucional e menu administrativo.
- [x] Auditoria de mídias protege vídeos associados, mesmo em rascunho; backup já cobre MP4/WebM.
- [x] Teste HTTP integrado ao CI verifica autorização JWT, fontes YouTube falsas, publicação, busca, ocultação, MP4 privado e publicado.
- [x] CI 38817558203 passou em React, PHP e MariaDB 11.4: migração 005, JWT, link YouTube, vídeo MP4 privado/publicado, pesquisa, ocultação e exclusão segura.
- [x] Correção da inconsistência `composer.lock` x `composer.json` após sincronização local/remota.
- [x] Responsável confirmou migração 005_videos.sql aplicada no Windows: 001–004 já aplicadas; «Migrações concluídas.»
- [x] Responsável informou que os testes funcionais da biblioteca de vídeos passaram no Windows. Não foi discriminado individualmente cada teste de MP4/WebM, busca, ocultação e logotipo.
- [ ] Registrar, na homologação, evidência por cenário em HTTPS real.
- [ ] Limites de upload PHP para 32 MiB, vídeos maiores via armazenamento externo, proteção de abuso e rate limiting antes da produção.

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

## Pendências reais para produção
- [ ] Rate limiting administrativo e nos quizzes públicos; revisar segurança, privacidade e dependências.
- [ ] Backup criptografado fora do servidor e restauração completa validada em ambiente isolado.
- [ ] HTTPS, configurações de produção, ambiente, domínio e monitoramento.
- [ ] Verificação visual documentada de PDF/PNG e fluxos de erro; testes de ponta a ponta em dispositivos reais.
- [ ] Preparação de conteúdos mínimos e ensaio com primeiros usuários.

**Novos módulos e melhorias opcionais:** adiados para pós-lançamento, conforme escopo congelado.

## Próxima ação
Domínios e caminhos definidos. Executar inspeção somente leitura dos diretórios de destino, módulos Apache, serviço PHP-FPM e banco, e RAM; não sobrescrever os sites antigos. Validar CI do ajuste de URLs cross-domain, depois backups, VirtualHosts isolados, TLS, segurança e homologação. **Não criar funcionalidades novas nesta fase.**

**Limite da verificação:** CI consegue compilar/validar o código, mas não substitui a validação visual/end-to-end no ambiente do responsável.
