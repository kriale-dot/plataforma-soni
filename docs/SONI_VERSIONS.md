# SONI — Histórico de versões

## v0.2.0 — 2026-10-15 — Fluxo inicial de login administrativo
- Criada página React /admin com formulário, estado da API, mensagens de erro e logout.
- Criado cliente HTTP frontend para conexão com Slim.
- Integração com rotas /api/health, /api/admin/login e /api/admin/me.
- Proxy de desenvolvimento de /api para 127.0.0.1:8282 no Vite.
- Implementada retenção do token somente em memória (sem localStorage).
- Reforçados cabeçalhos de cache e tipo de conteúdo para rotas administrativas na API.
- Adicionado guia de instalação local do Windows, documentação e workflow de CI.
- Mantida identidade oficial Harmonia Técnica (Paleta 1).

**Testes:** publicação do código, pendentes instalação e integração executadas na máquina do responsável. Não confundir tela pós-login com CRUD funcional.

**Próxima etapa:** validação local e primeiro banco MySQL/migrations.

## v0.1.0 — 2026-10-15 — Fundação
- Repositório kriale-dot/plataforma-soni autorizado e inicializado.
- React/Vite, Slim 4, schema SQL de referência e documentação de desenvolvimento.
- Definida a paleta oficial Harmonia Técnica (Paleta 1).

**Testes:** arquivos confirmados no GitHub, não executados.

## Regra de continuidade
Documentar versão, data, arquivos, testes realizados, pendências e próximo passo após cada mudança.
