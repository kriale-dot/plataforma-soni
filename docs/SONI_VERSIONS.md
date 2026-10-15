# SONI — Histórico de versões

## v0.5.0 — 2026-10-15 — Upload de mídias e prévia institucional
- Upload autenticado com verificação conjunta da extensão e MIME real, tamanho e dimensões de imagem.
- Tipos: JPG/JPEG/PNG/WebP/GIF até 6 MiB; MP3/WAV/OGG até 15 MiB; MP4/WebM até 32 MiB.
- Arquivos em api/storage/site_media fora do webroot com nomes aleatórios; acesso público apenas para blocos visíveis e publicados.
- Prévia de mídias não publicadas exige token JWT; leitura de arquivos implementa HTTP Range para áudio/vídeo.
- Editor associa upload ao media_id e mostra prévia particular antes de salvar/publicar.
- A tabela site_media e site_blocks.media_id já existiam na migração 001; nenhuma nova migração necessária.
- GitHub Actions possui teste integrado de upload, validação, publicação e ocultação. **Aprovado no commit a9ce7fe, execução 38797251656**, após correção de compatibilidade Slim PSR-7 no stream e substituição do PNG de teste.
- Teste local no Windows ainda pendente.
- Ainda pendentes: remoção/limpeza de arquivos órfãos, cotas, thumbnails, backup de mídia e segurança de produção.



## Validação local — 2026-10-15 — Editor institucional v0.4.0 aprovado
- Após baixar as alterações, o responsável confirmou que o editor institucional funcionou no Windows (resposta: "tudo certo").
- Isso encerra a pendência de validação básica do editor e da visualização de alterações no site.
- Não foi enviada comprovação individual de cada ação de ordem/visibilidade/exclusão; essas ações tiveram testes automatizados aprovados no GitHub.
- Próxima fase: upload seguro de imagens, áudio e vídeo e pré-visualização de rascunhos, sem alterar identidade visual ou descartar conteúdos.



## v0.4.0 — 2026-10-15 — Primeiro editor institucional funcional
- O responsável confirmou funcionamento de `GET /api/site` no MariaDB local (não informou saída detalhada dos scripts).
- `api/src/SiteCms.php`: CRUD das configurações e dos blocos, validação de campos/links, ordenação transacional, rascunhos e visibilidade.
- Rotas JWT: `GET /api/admin/site`, `PUT /api/admin/site/settings`, `POST /api/admin/site/blocks`, `PUT /api/admin/site/blocks/{id}`, `PATCH /api/admin/site/blocks/{id}/publication`, `PATCH /api/admin/site/blocks/{id}/order`, `DELETE /api/admin/site/blocks/{id}`.
- `frontend/src/SiteEditor.jsx`: interface para título, textos, URL de mídia, links e gestão de blocos.
- `frontend/src/AdminPage.jsx`: acesso ao editor a partir do painel autenticado; `/admin/site` exige login (JWT mantido apenas em memória).
- `frontend/src/App.jsx`: renderização da página pública a partir do banco pela rota `GET /api/site`; preserva identidade Harmonia Técnica.
- **GitHub Actions aprovado** em execução `38794375321`: PHP, build React, MariaDB e ciclo CRUD real com JWT via HTTP.
- **Falta validar no Windows do responsável:** edição no painel e visualização no site.
- **Falta implementar:** upload de mídia, visualização de rascunhos, gerenciamento de ativos, recursos de testes/documentos/vídeos e recursos de produção.



## Correção v0.3.0 — 2026-10-15 — Rota pública HTTP e favicon
- Após captura de tela do responsável com HTTP 405, auditado o arquivo `api/public/index.php` publicado: a rota `GET /api/site` estava ausente, embora o repositório SiteRepository, a migration e a documentação já estivessem presentes.
- Corrigido registro da rota `GET /api/site` com leitura PDO dos blocos publicados e tratamento de indisponibilidade do banco.
- Adicionada resposta 204 para `GET /favicon.ico`, eliminando erro 405 secundário no DevTools.
- GitHub Actions passou a testar a rota `GET /api/site` via servidor HTTP PHP com MariaDB 11.4, impedindo regressões de registro de rota. Teste aprovado em 2026-10-15 (commit 748c2c6, execução 38793328967).
- A migração local ainda depende de confirmação do responsável; não alterar nem apagar dados existentes.



## v0.3.0 — 2026-10-15 — Fundação MariaDB e CMS institucional
- Responsável confirmou instalação/execução de MariaDB 11.4 e acesso ao cliente local.
- api/src/Database.php: conexão PDO MySQL/MariaDB via DB_* do api/.env.
- api/migrations/001_site_institucional.sql: tabelas site_media, site_settings e site_blocks, com 5 blocos iniciais de SONI.
- api/bin/migrate.php: histórico/checksum para migrations idempotentes.
- api/bin/db-check.php: verificação de conexão e contagem dos blocos públicos.
- api/src/SiteRepository.php e GET /api/site: leitura pública de configurações e blocos publicados/visíveis.
- api/.env.example e composer.json atualizados; documentação de setup em docs/SONI_DATABASE_SETUP.md.
- **GitHub Actions aprovado no commit ca9eab8:** migração, segunda execução idempotente, consulta PDO e testes de visibilidade/publicação dos cinco blocos. A correção em api/src/Database.php permite ler variáveis DB_* do ambiente do CI com getenv().
- **Não executado contra o banco local do usuário**; banco e usuário precisam ser criados e migrações executadas localmente.
- **Pendente:** editar conteúdo via painel e carregar dados dinamicamente no frontend React.



## Validação local — 2026-10-15 — Login administrativo aprovado
- O responsável confirmou que o login no painel `/admin` passou a funcionar após configurar as credenciais no `api/.env`.
- Isso valida o fluxo positivo React ↔ Slim com as credenciais locais; não implica que a proteção de produção, rate limiting, testes negativos ou logout estejam concluídos.
- Credenciais e segredos **não foram enviados ao repositório**.
- Próxima atividade: verificar conexão local com MySQL e implementar o CMS do site institucional editável; versão do código permanece **v0.2.0**.



## Verificação local — 2026-10-15 — Proxy da API corrigido
- Após baixar as alterações e reiniciar Vite, o responsável confirmou que a comunicação React ↔ Slim passou a funcionar, com a API conectada em `/admin`.
- `curl.exe -i http://localhost:8282/api/health` havia retornado HTTP 200; acesso via IPv4 explícito falhava nessa instalação do Windows.
- Instalação de dependências Composer/npm também foi informada como bem-sucedida pelo responsável.
- **Login administrativo com credenciais locais ainda não foi verificado.** Próxima ação: configurar `api/.env` sem versionar segredos.
- Versão funcional mantida em v0.2.0.


## Ajuste de ambiente — 2026-10-15 — Proxy Vite no Windows (v0.2.0)
- Diagnóstico: API Slim confirmou HTTP 200 em `http://localhost:8282/api/health`, mas apresentou erro de conexão via `http://127.0.0.1:8282/api/health`.
- Alterado `frontend/vite.config.js` para fazer proxy do `/api` para `http://localhost:8282` em vez do IPv4 explícito. Evita desencontro com a interface usada pelo PHP no computador do responsável.
- Atualizadas instruções locais e status. **A correção ainda precisa de reteste no navegador após reiniciar o Vite.**
- Mantido o número v0.2.0: correção de configuração, sem nova funcionalidade.



## Adendo de escopo — 2026-10-15 — CMS do site institucional (sem alteração da versão executável)
- Requisito confirmado: site institucional da SONI **editável pelo administrador**, com editor de blocos semelhante ao de `mapa-percepcao-relacional` (plataforma Tânia).
- Revisada implementação de referência: edição de blocos, título do site, mídia, visibilidade e reordenação.
- Especificadas adaptações SONI: HERO, TESTES, DOCUMENTOS, VIDEOS, textos, imagens, áudio, vídeo e CTAs.
- Adicionado `docs/SONI_CMS_INSTITUCIONAL.md` e atualizados CONTEXT, REQUIREMENTS, BACKLOG e STATUS.
- **Nenhum editor CMS foi implementado neste adendo**; versão funcional permanece v0.2.0.
- **Próxima entrega planejada:** modelo MySQL e API de conteúdo dinâmico, seguidos de editor React.


## v0.2.0 — 2026-10-15 — Integração e login administrativo
- Página React /admin com formulário de login, estado da API, tratamento de erros e logout.
- Cliente HTTP para comunicação com as rotas Slim /api/health, /api/admin/login e /api/admin/me.
- Proxy Vite /api em 127.0.0.1:8282.
- Token de sessão apenas na memória do frontend.
- Cabeçalhos de no-store e nosniff em rotas administrativas.
- Guia de instalação no Windows em docs/SONI_LOCAL_SETUP.md.
- Workflow GitHub Actions com build React, instalação de Composer, lint PHP e teste inicial de autenticação com dados fictícios.
- Atualização de segurança de firebase/php-jwt de ^6.10 para ^7.2.1: a série 6 era bloqueada pelo Composer por vulnerabilidades conhecidas.
- No commit 8612fb1, GitHub Actions confirmou êxito do build React, Composer e PHP lint. No commit 27ad41d, GitHub Actions confirmou também a aprovação de todos os testes iniciais de autenticação e do build React.

**Limitações:** integração end-to-end no Windows, administração de conteúdos e banco ainda não validados/implementados. O painel após entrar é demonstrativo.

**Próxima etapa:** validar acesso local e começar MySQL + CRUD administrativo.

## v0.1.0 — 2026-10-15 — Fundação
- Repositório kriale-dot/plataforma-soni inicializado.
- React/Vite, Slim 4, SQL preliminar e documentação.
- Paleta oficial Harmonia Técnica (Paleta 1).

## Regra de continuidade
Registrar data, versão, alterações, verificações, problemas e próxima etapa; nunca versionar segredos.
