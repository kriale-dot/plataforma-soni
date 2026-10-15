# SONI — Status de desenvolvimento

**Data:** 2026-10-15  
**Versão:** 0.2.0  
**Etapa:** 1 — Integração React/Slim e login administrativo  
**Estado:** implementação publicada no GitHub; validação ponta a ponta com dependências instaladas pendente.

## Entregue no código
- [x] Repositório oficial e Paleta 1 Harmonia Técnica.
- [x] Página institucional React/Vite demonstrativa.
- [x] Tela React /admin e link no rodapé do site.
- [x] Cliente HTTP com tratamento de erro e sinalização da saúde da API.
- [x] Login e consulta de autorização à API Slim (/api/admin/login e /api/admin/me).
- [x] Token mantido somente na memória React; saída/logout.
- [x] Proxy Vite /api na porta 8282.
- [x] Configuração do administrador pelo arquivo api/.env, não versionado.
- [x] Headers adicionais anti-cache e nosniff para a API.
- [x] Instruções detalhadas de ambiente local e workflow de CI.

## Validação pendente
- [ ] composer install, npm install e execução simultânea no Windows do responsável.
- [ ] Teste manual de login com email/senha reais e token validado.
- [ ] Confirmar npm run build e execução do workflow GitHub Actions.
- [ ] Confirmar regras de servidor, CORS e rate limiting para produção.

## Ainda não desenvolvido
- [ ] Interface real de gestão de conteúdo e CRUD.
- [ ] Migrations e conexão MySQL.
- [ ] Motor de provas, cadastro e aplicação com texto/imagem/áudio.
- [ ] Biblioteca de PDF/PNG com upload seguro.
- [ ] Biblioteca de vídeos via YouTube ou upload.
- [ ] Testes completos, backup e deploy.

## Próxima ação
Seguir docs/SONI_LOCAL_SETUP.md, validar os dois servidores localmente e testar login. Em seguida implementar banco e primeiros CRUDs.

Observação: verificação de publicação no GitHub não equivale a teste integrado no computador do usuário.
