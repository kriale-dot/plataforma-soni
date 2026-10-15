# Plataforma SONI

SONI — Sonorização de Igrejas. Plataforma educacional com site institucional, testes multimídia (texto, imagem e áudio), biblioteca PDF/PNG e vídeos (YouTube ou upload).

## Tecnologias e cores
- Frontend: React 19 e Vite 6.
- API: PHP 8.2+ e Slim 4.
- Banco planejado: MySQL 8 (modelo preliminar em database/schema.sql).
- Paleta oficial: **Harmonia Técnica**, com azul petróleo #153B4A, turquesa #2FA89C, areia #F3EBDD, âmbar #D8A24A e grafite #27333A.

## Requisito definitivo — site institucional editável
O site público da SONI **não será estático**: o administrador poderá montar e atualizar as seções via editor visual por blocos, no modelo da plataforma Tânia (`kriale-dot/mapa-percepcao-relacional`). Poderá editar títulos, textos, imagens, áudios, vídeos, links e chamadas para ação; incluir, ocultar, excluir e reordenar blocos. Haverá integração com as áreas de testes, documentos e vídeos.

**Status:** v0.5.0 adiciona upload de imagens/áudio/vídeo com prévia particular do bloco. CMS textual da v0.4.0 e site dinâmico permanecem; validação v0.5 no Windows pendente. Arquitetura e critérios de aceite em [docs/SONI_CMS_INSTITUCIONAL.md](docs/SONI_CMS_INSTITUCIONAL.md).

## Situação — v0.7.0
Implementado no código: site institucional dinâmico por blocos, tela /admin, editor /admin/site após login, rotas privadas JWT para CRUD e ordem, proxy Vite /api, conexão MariaDB PDO, upload de mídia autenticado e migration do CMS.

**React/Slim, login e API pública MariaDB validados no Windows. Editor v0.4.0 aprovado no CI e validado no Windows; novo upload v0.5.0 precisa de verificação local.** O painel depois de entrar mostra módulos ainda não implementados; MySQL, CRUD, testes interativos e bibliotecas não estão funcionais.

## MariaDB / CMS institucional

O usuário confirmou MariaDB 11.4 localmente. Siga [docs/SONI_DATABASE_SETUP.md](docs/SONI_DATABASE_SETUP.md) para criar `plataforma_soni`, usuário próprio, DB_* no .env e aplicar migração inicial.

## Executar no Windows
Consulte o guia detalhado: [Instalação e testes locais](docs/SONI_LOCAL_SETUP.md).

Terminal 1, na raiz do repositório:

~~~powershell
cd api
composer install
Copy-Item .env.example .env
# Edite o arquivo .env local com ADMIN_EMAIL, ADMIN_PASSWORD_HASH e JWT_SECRET
composer start
~~~

Terminal 2, na raiz do repositório:

~~~powershell
cd frontend
npm install
npm run dev
~~~

Endereços: http://localhost:5173, http://localhost:5173/admin e http://localhost:5173/admin/site (login necessário). A API responde em http://localhost:8282/api/health.

## Regras de segurança
- Não compartilhar nem versionar api/.env. Arquivo de exemplo api/.env.example não contém segredos.
- O administrador usa hash da senha, não senha em texto puro.
- JWT não é salvo em localStorage; atualizar a página encerra a sessão visual.
- Antes da produção, implementar limitação de tentativas de login, configurações HTTPS/CORS, políticas de upload e monitoramento.
- Nunca revelar o gabarito dos testes antes da conclusão da tentativa.

## Documentação viva
No início e final de cada etapa, consultar e atualizar os documentos:
- docs/SONI_CONTEXT.md — decisões confirmadas;
- docs/SONI_STATUS.md — situação atual;
- docs/SONI_VERSIONS.md — histórico;
- docs/SONI_BACKLOG.md — etapas seguintes.

Workflow CI disponível em .github/workflows/ci.yml para compilar o frontend e validar arquivos PHP.

## Manutenção e segurança dos arquivos

A v0.6.0 inclui backup verificável do MariaDB + mídias e auditoria conservadora de arquivos não utilizados. Veja [docs/SONI_MAINTENANCE.md](docs/SONI_MAINTENANCE.md). **Não execute quarentena sem verificar e copiar o backup.**

## Teste seus Conhecimentos — v0.7.0

A v0.7.0 inclui catálogo e aplicação em `/testes`, editor em `/admin/quizzes`, criação de categorias, testes e questões com texto, imagens e áudios. **É necessário executar `php bin/migrate.php` dentro de `api` após atualizar o código**, pois a migração `002_quizzes.sql` cria novas tabelas sem alterar as antigas. O resultado é corrigido somente após a finalização do teste. Veja `docs/SONI_QUIZZES.md`.

### Devolutivas personalizadas — v0.7.1

Após atualizar o projeto, execute `php bin/migrate.php` na pasta `api` para aplicar a migração `003_quiz_feedback.sql`. No editor de cada teste, configure **Devolutiva por percentual de acertos**; a mensagem só aparecerá após finalizar a tentativa. Consulte `docs/SONI_QUIZZES.md`.

### Biblioteca de Documentos — v0.8.0

PDFs e imagens PNG com categorias, pesquisa e visualização pública em `/documentos`; cadastro e publicação por JWT em `/admin/documents`. A migração `004_documents.sql` é necessária: execute `php bin/migrate.php` na pasta `api` após atualizar o repositório. Limites internos: 20 MiB para PDF e 6 MiB para PNG. A publicação controla o acesso público à mídia; o upload sozinho não publica o arquivo.

### Biblioteca de Vídeos — v0.9.0

Área pública em `/videos`, painel JWT em `/admin/videos`. Vídeos via link HTTPS YouTube ou upload MP4/WebM até 32 MiB, com categorias, busca, rascunho/publicação e player. Execute `php bin/migrate.php` na pasta `api` após atualizar o repositório para aplicar `005_videos.sql`. Consulte `docs/SONI_VIDEOS.md`.
