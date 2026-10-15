# Plataforma SONI

SONI — Sonorização de Igrejas. Plataforma educacional com site institucional, testes multimídia (texto, imagem e áudio), biblioteca PDF/PNG e vídeos (YouTube ou upload).

## Tecnologias e cores
- Frontend: React 19 e Vite 6.
- API: PHP 8.2+ e Slim 4.
- Banco planejado: MySQL 8 (modelo preliminar em database/schema.sql).
- Paleta oficial: **Harmonia Técnica**, com azul petróleo #153B4A, turquesa #2FA89C, areia #F3EBDD, âmbar #D8A24A e grafite #27333A.

## Requisito definitivo — site institucional editável
O site público da SONI **não será estático**: o administrador poderá montar e atualizar as seções via editor visual por blocos, no modelo da plataforma Tânia (`kriale-dot/mapa-percepcao-relacional`). Poderá editar títulos, textos, imagens, áudios, vídeos, links e chamadas para ação; incluir, ocultar, excluir e reordenar blocos. Haverá integração com as áreas de testes, documentos e vídeos.

**Status:** na v0.3.0 existe o modelo MariaDB e um endpoint público de leitura; editor e renderização dinâmica no React ainda não estão implementados. Arquitetura e critérios de aceite em [docs/SONI_CMS_INSTITUCIONAL.md](docs/SONI_CMS_INSTITUCIONAL.md).

## Situação — v0.3.0
Implementado no código: site institucional demonstrativo, tela /admin, login da API, logout em memória, proxy Vite /api, conexão MariaDB PDO, migration do CMS e GET /api/site.

**React/Slim e login já validados no Windows; migrações MariaDB ainda pendentes na máquina do usuário.** O painel depois de entrar mostra módulos ainda não implementados; MySQL, CRUD, testes interativos e bibliotecas não estão funcionais.

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

Endereços: http://localhost:5173 e http://localhost:5173/admin. A API responde em http://localhost:8282/api/health.

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
