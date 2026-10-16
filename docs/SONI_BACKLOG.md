# SONI — Etapas de desenvolvimento

**Marco posterior (2026-10-16):** deploy operacional do MVP 0.9.0 encerrado a pedido do responsável, com site e API disponíveis em HTTPS. F7 (segurança/qualidade) conserva pendências; F8 (deploy técnico) está operacionalmente encerrada. O projeto aguarda reporte futuro de problemas; release formal 1.0.0 não homologada. Ver [registro de encerramento](SONI_DEPLOY_ENCERRAMENTO_2026-10-16.md).

**Decisão em 2026-10-15:** MVP v0.9.0 **APROVADO E CONGELADO**. Próxima meta: versão 1.0.0 em produção. Priorizar qualidade, segurança, restauração de backup e lançamento; novas funcionalidades fora do escopo ficam no pós-MVP. Referências: `SONI_MVP_ESCOPO.md` e `SONI_CHECKLIST_LANCAMENTO.md`.



1. **F0 — Fundação [código concluído]:** GitHub, paleta oficial, documentação, base React/Slim e schema preliminar.
2. **F1 — Integração [código entregue / testes pendentes]:** comunicação React/Slim, login admin .env, tela e proxy, validação de funcionamento local.
3. **F2 — CMS institucional editável [obrigatório, planejado]:** referência no editor por blocos da plataforma Tânia; tabelas `site_settings` e `site_blocks`, API pública e CRUD JWT, React dinâmico, editor de criar/editar/excluir/reordenar/ocultar/publicar já implementados. Uploads autenticados de imagem/áudio/vídeo e prévia do bloco implementados na v0.5.0. Uploads de imagem, áudio e vídeo validados localmente pelo responsável em 2026-10-15. Backup e auditoria conservadora v0.6.0 aprovados no CI e validados no Windows (sem órfãos). Pendentes: restauração isolada, backup externo, quotas, refinamento SEO e acessibilidade. Não deixar site final estático.
4. **F3 — Admin e MariaDB [primeira versão entregue na v0.7]:** migration 002, categorias e gerenciamento básico de questionários pelo painel. CI 38802753975 aprovado; migração 002 aplicada no Windows; permanecem reforços de segurança.
5. **F4 — Testes interativos [devolutivas implementadas no código v0.7.1]:** questões e alternativas com texto/imagem/áudio, publicação, aplicação, tentativas, resultados e mensagens por intervalo de acertos editáveis por teste. CI MariaDB/React/PHP aprovado na execução 38804274629; migração 003 aplicada e validação geral do editor confirmada no Windows («Tudo ok»); seguem rate limit e funcionalidades avançadas.
6. **F5 — Documentos [primeira versão implementada v0.8.0]:** upload autenticado de PDF/PNG, categorias, busca, visualização, publicação e rascunhos; CI 38807367006 aprovado, migração 004 concluída no Windows; pendentes validação visual e funcional da biblioteca. Pendentes paginação, revisão de segurança e recursos avançados.
7. **F6 — Vídeos [MVP validado pelo responsável v0.9.0]:** YouTube (links HTTPS validados), MP4/WebM (até 32 MiB), categorias, pesquisa, publicação, visualização e painel admin. CI 38817558203 aprovado; migração 005 confirmada no Windows e testes funcionais gerais confirmados; evidências detalhadas serão reunidas em homologação. Pendentes paginação, arquivos maiores e recursos avançados.
8. **F7 — Qualidade [em preparação / P0]:** rate limiting, testes E2E, segurança de uploads e login, acessibilidade essencial, backups externos, restauração isolada e monitoramento. Conferir checklist.
9. **F8 — Deploy [infraestrutura implantada; encerrado operacionalmente em 2026-10-16]:** hospedagem, HTTPS, CORS e rotas públicos funcionando; jobs/monitoramento e homologação formal ainda pendentes.

Atualizar status e histórico a cada etapa; não marcar funcionalidades como testadas sem execução verificada.

## Regra do pós-MVP
Funcionalidades e otimizações não bloqueadoras (novos perfis, certificados, gamificação, relatórios avançados, paginação avançada e storage para vídeos maiores) não entram na v1.0.0, salvo decisão explícita após os testes do piloto. Qualquer correção aceita no MVP deve ser verificada pelo CI e registrada no histórico.
