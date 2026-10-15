# SONI — Status de desenvolvimento

**Data:** 2026-10-15  
**Versão:** 0.2.0  
**Etapa:** 1 — Integração React/Slim e login administrativo  
**Estado:** integração e login com credenciais locais validados pelo responsável no Windows; testes negativos/logout e CMS pendentes.

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
- [ ] Implementar CMS na API e MySQL, frontend público dinâmico e editor de blocos em `/admin/site`.
- [ ] Validar fluxo admin → salvar/publicar → página pública refletindo mudanças.
- [ ] Editor não vai alterar a paleta oficial, apenas conteúdo configurável.

## Ainda não desenvolvido
- [ ] Interface real de gestão de conteúdo e CRUD.
- [ ] Migrations e conexão MySQL.
- [ ] Motor de provas, cadastro e aplicação com texto/imagem/áudio.
- [ ] Biblioteca PDF/PNG com upload seguro.
- [ ] Biblioteca de vídeos por YouTube e upload.
- [ ] Testes completos, backup e deploy.

## Próxima ação
Verificar disponibilidade local do MySQL (`mysql --version` e conexão de teste) sem compartilhar senhas. Em seguida iniciar infraestrutura MySQL + migrations do CMS institucional editável (modelo Tânia adaptado). Teste com credenciais erradas e logout no login permanece pendente.

**Limite da verificação:** CI consegue compilar/validar o código, mas não substitui a validação visual/end-to-end no ambiente do responsável.
