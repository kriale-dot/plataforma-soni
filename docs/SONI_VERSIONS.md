# SONI — Histórico de versões

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
