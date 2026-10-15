# SONI — Etapas de desenvolvimento

1. **F0 — Fundação [código concluído]:** GitHub, paleta oficial, documentação, base React/Slim e schema preliminar.
2. **F1 — Integração [código entregue / testes pendentes]:** comunicação React/Slim, login admin .env, tela e proxy, validação de funcionamento local.
3. **F2 — CMS institucional editável [obrigatório, planejado]:** referência no editor por blocos da plataforma Tânia; tabelas `site_settings` e `site_blocks`, API pública e CRUD JWT, React dinâmico, editor de criar/editar/excluir/reordenar/ocultar/publicar já implementados. Uploads autenticados de imagem/áudio/vídeo e prévia do bloco implementados na v0.5.0. Uploads de imagem, áudio e vídeo validados localmente pelo responsável em 2026-10-15. Backup e auditoria conservadora v0.6.0 aprovados no CI e validados no Windows (sem órfãos). Pendentes: restauração isolada, backup externo, quotas, refinamento SEO e acessibilidade. Não deixar site final estático.
4. **F3 — Admin e MariaDB [primeira versão entregue na v0.7]:** migration 002, categorias e gerenciamento básico de questionários pelo painel. CI 38802753975 aprovado; migração 002 aplicada no Windows; permanecem reforços de segurança.
5. **F4 — Testes interativos [devolutivas implementadas no código v0.7.1]:** questões e alternativas com texto/imagem/áudio, publicação, aplicação, tentativas, resultados e mensagens por intervalo de acertos editáveis por teste. CI MariaDB/React/PHP aprovado na execução 38804274629; migração 003 aplicada e validação geral do editor confirmada no Windows («Tudo ok»); seguem rate limit e funcionalidades avançadas.
6. **F5 — Documentos [planejado]:** upload seguro PDF/PNG, catálogo e visualizador.
7. **F6 — Vídeos [planejado]:** YouTube e uploads, player e gestão.
8. **F7 — Qualidade [planejado]:** testes E2E, segurança de uploads e login, acessibilidade e backups.
9. **F8 — Deploy [planejado]:** hospedagem, HTTPS, CORS, jobs e monitoramento.

Atualizar status e histórico a cada etapa; não marcar funcionalidades como testadas sem execução verificada.
