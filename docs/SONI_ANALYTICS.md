# SONI — Contador de visitas e relatório de gestão

## Comportamento

- O registro inicia somente depois da migração `007_analytics.sql` e da publicação do frontend. Não há importação retroativa de acessos antigos.
- Os visitantes recebem um identificador aleatório v4 em `sessionStorage` (somente no navegador) por no máximo 30 minutos sem atividade. A API armazena exclusivamente o hash SHA-256 do identificador, a data UTC e o caminho público visitado. Não armazena IP, email, user-agent, cookie, query string nem referrer.
- Visita significa uma nova sessão, não uma pessoa única. Visualização significa abertura de página. A mesma página enviada repetidamente em 5 segundos conta uma vez.
- Não rastreia rotas administrativas, solicitações com DNT:1 nem user agents conhecidos de robôs.
- A coleta independe de exibir o contador. O total no rodapé do site só aparece após marcar **Mostrar o total de visitas** em Administração → Conteúdo do site → Configurações e salvar. A opção vem desativada.
- O módulo **Relatórios gerenciais** em `/admin/analytics` exige JWT, e fornece totais, séries diárias, páginas populares, testes mais concluídos, nota média e inventário de conteúdo. A visualização fica restrita ao administrador.
- Relatórios usam período móvel em dias (7, 30, 90) e agrupamento diário de Brasília UTC−3 (São Paulo). Dados de tentativas excluídos junto com seus testes deixam de aparecer nos indicadores; não são um histórico contábil imutável.
- Por não identificar pessoas, números não representam visitantes únicos reais. Bloqueadores, navegação privada, redes instáveis e bots podem afetar a medição.

## API

- `POST /api/analytics/visit`: `{"session_id":"UUID-v4","page":"/testes"}`. Responde `tracked` e `visit_count` (nulo quando oculto).
- `GET /api/admin/analytics?days=30`: JWT exigido. Valores permitidos: 7, 30 ou 90.
- `GET /api/site`: configurações incluem `show_visit_counter`; inclui `visit_count` apenas quando habilitado.
- `PUT /api/admin/site/settings`: recebe `show_visit_counter` 0/1 além dos campos existentes. Clientes antigos podem omitir a opção sem redefini-la.

## Deploy seguro (após merge na branch main)

1. Antes de alterar o sistema, criar e conferir o backup já previsto na rotina SONI.
2. No servidor, em `/var/www/somdaigreja.com.br`, conferir `git status -sb` e executar `git pull --ff-only origin main`. Não sobrescrever alterações locais.
3. Executar `cd api && php bin/migrate.php` **uma vez** e conferir mensagem `Aplicada: 007_analytics.sql`. Não editar migrações antigas.
4. Executar `cd ../frontend && npm ci && npm run build`, preservando a configuração de `VITE_API_BASE_URL` já usada em produção.
5. Verificar `https://somdaigreja.com.br`, `/admin/analytics` após login, mostrar/ocultar contador no editor, e navegação em `/testes`.
6. Não executar `api/tests/analytics-http-smoke.php` em produção: é teste reservado ao banco efêmero do CI.

As tabelas de analytics são novas e não alteram dados existentes de quizzes, documentos, vídeos ou CMS. Não apagar tabelas para reverter o frontend.
