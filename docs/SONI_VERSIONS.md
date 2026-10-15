# SONI — Histórico de versões

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
