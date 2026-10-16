# SONI — Checklist de lançamento v1.0.0

**Referência:** MVP v0.9.0 congelado em `SONI_MVP_ESCOPO.md`.
**Meta:** tornar a primeira versão utilizável com segurança, backup restaurável e monitoramento em um domínio HTTPS.
**Situação atual (2026-10-16):** frontend e API acessíveis via HTTPS, conforme testes do responsável, mas P0 de segurança, armazenamento, backup, homologação e monitoração não concluídos; v1.0.0 ainda não autorizada.

> **Regra de avanço:** marcar uma caixa apenas depois de executar a verificação no ambiente-alvo e guardar evidência (data, comando sem segredos, resultado, URL de CI ou anotação de teste). Não guardar senhas, tokens, dumps, arquivos de usuários ou `.env` no repositório.

## Decisão de infraestrutura — confirmada em 2026-10-16

- [x] Servidor selecionado: Droplet DigitalOcean já existente, com Ubuntu 24.04 LTS, compartilhado com outras aplicações do responsável.
- [x] Diagnóstico concluído: Apache 2.4.58/mod_php, PHP 8.3.6 (pdo_mysql/fileinfo), MySQL 8.0.46 ativo, RAM 7,8 GiB e 4,2 GiB disponíveis.
- [x] Endereços do deploy escolhidos: `https://somdaigreja.com.br` e `https://www.somdaigreja.com.br` (frontend), `https://api.somdaigreja.com.br` (API). DNS e TLS existentes precisam ser conferidos.
- [x] Caminhos implantados: frontend em `frontend/dist`, API em `api/public`, VirtualHosts dedicados com SSL existente e MySQL `plataforma_soni` usuário `soni_app` restrito ao banco.
- [x] Inspeção confirmou diretório base existente e vazio, sem `frontend` nem `api`, em 2026-10-16; não será necessário substituir arquivos anteriores nesta pasta.
- [x] Git clonado, dependências PHP/Node instaladas, build React realizado; migrações 001–005 relatadas como corretas pelo responsável.
- [x] VirtualHosts HTTPS ativados e checagens de frontend/API aprovadas, sem concluir homologação funcional e segurança.
- [x] Permissões de mídia validadas (`www-data` escreve exclusivamente em `api/storage/site_media`); `.env` legível por `www-data`, modo `640`.
- [x] CORS corrigido e comunicação de frontend com API funcionando após correção do `.env`.
- [x] Configuração do VirtualHost da API com limite de upload de 40 MB e POST de 48 MB; teste de imagem acima de 2 MB confirmado.
- [x] Biblioteca de documentos PDF e PNG: upload, publicação, visualização e ocultação validados pelo responsável.
- [x] Biblioteca de vídeos YouTube e MP4/WebM, player público, rascunhos e ocultação testados com sucesso pelo responsável.
- [ ] Verificar backups externos e plano de recuperação do Droplet antes da implantação.

- [x] Partição raiz verificada: 96 GB total, 29 GB utilizados, 68 GB livres (31%).
- [x] Apache já utiliza múltiplos VirtualHosts TLS nos demais sites; preservar suas configurações.

**Procedimento somente leitura:** [Guia de diagnóstico Ubuntu / DigitalOcean](SONI_DEPLOY_UBUNTU_DIGITALOCEAN.md).

## Ordem de execução

### Fase A — Levantamento e preparação de ambiente (P0)
- [ ] Definir servidor de produção, sistema operacional, domínio/subdomínio, DNS, responsável por operação e disponibilidade.
- [ ] Definir versões suportadas do PHP (8.2+), MariaDB/MySQL, Composer, Node para build e servidor HTTP; validar extensões `pdo_mysql` e `fileinfo`.
- [ ] Identificar limites de infraestrutura: volume dos arquivos, uploads PHP, espaço livre, memória, TLS, timeouts e tamanho máximo das requisições.
- [ ] Criar usuário de banco exclusivo para SONI com privilégios mínimos; não reutilizar bancos/credenciais de outras plataformas.
- [ ] Separar configuração `production` da local; não publicar `.env`, `api/storage`, backups, logs privados, nem pastas `vendor` com rotas públicas indevidas.
- [ ] Registrar URL pública da aplicação e API, comportamento de proxy/rewrites e procedimento de deploy/rollback.

**Evidência exigida:** ambiente e domínio definidos, checagem dos requisitos e inventário de configurações sem valores secretos.

### Fase B — Segurança e privacidade (P0)
- [ ] Habilitar HTTPS com certificado válido, redirecionamento de HTTP para HTTPS e cabeçalhos seguros adequados.
- [ ] Configurar `APP_ENV=production`, tratamento de erros sem stack trace público, `FRONTEND_URL`/CORS estrito e rotação de credenciais/segredo JWT.
- [ ] Implantar limite de tentativas no login administrativo e na submissão pública de quizzes; definir resposta `429`, janela, IP/proxy confiável e auditoria mínima.
- [ ] Revisar upload/download: MIME, assinatura de arquivos, extensão, tamanho no PHP/reverse proxy, locais graváveis, `nosniff`, permissões e autorização de rascunhos/arquivos ocultos.
- [ ] Determinar política para arquivos PDF e vídeo de terceiros: origem confiável, varredura antivírus antes de publicar, retenção e remoção de conteúdo perigoso.
- [ ] Validar senhas, permissões de diretórios, proteção de endpoints administrativos, inexistência de gabaritos antes da correção e bloqueio de dados privados.
- [ ] Definir política de privacidade/termos de uso conforme informações efetivamente coletadas; validar logs para não gravar credenciais, JWT ou dados pessoais desnecessários.
- [ ] Fazer revisão básica de dependências e vulnerabilidades; resolver achados bloqueadores.

**Evidência exigida:** resultados de testes de acesso negado, segurança de uploads, proteção de endpoints, HTTPS e rate limiting em produção.

### Fase C — Backup, restauração e atualização (P0)
- [x] `php bin/backup.php` executado em produção: backup local verificado em 2026-10-16; SQL e mídias no diretório `20261016-110803-d28cea70`.
- [ ] **Não priorizado por decisão do responsável em 2026-10-16:** não configurar novos backups externos por ora, porque os arquivos-fonte já estão no GitHub/Google Drive. Isso não equivale a backup do banco MySQL em produção nem das mídias recebidas no Droplet. Documentar aceitação do risco antes do GO/NO-GO.
- [ ] Testar restauração **em banco e armazenamento isolados**, conferindo tabelas, arquivos, permissões e funcionamento de uma amostra de conteúdos. Nunca restaurar sobre a base de trabalho para fazer um ensaio.
- [ ] Definir rotina de backup recorrente e alerta em caso de falha; conferir espaço livre.
- [ ] Documentar implantação e retorno à versão anterior, incluindo **compatibilidade do banco**; não desfazer migrations ou apagar dados por tentativa.
- [ ] Fazer migrações primeiro em ambiente de homologação/copiar dados fictícios; registrar resultado e plano de rollback.

**Evidência exigida:** relatório de restauração isolada bem-sucedida e localização segura da cópia externa (sem divulgar o caminho sensível).

### Fase D — Testes de ponta a ponta (P0)
- [ ] Confirmar pipeline GitHub Actions do commit candidato em verde.
- [ ] Verificar login, edição/publicação/ocultação do CMS e exibição pública.
- [ ] Criar e completar teste com resultados diferentes, conferindo pontuação/devolutiva e privacidade do gabarito.
- [ ] Testar PDF e PNG: upload, prévia privada, pesquisa, publicação, visualização, ocultação e rejeição de arquivo inválido.
- [ ] Testar vídeo YouTube e MP4/WebM: criação, prévia, publicação, player, busca, ocultação e retorno `404` para mídia não publicada.
- [ ] Verificar API e frontend por HTTPS no domínio real, inclusive celular, diferentes navegadores, mensagens de erro e formulários acessíveis.
- [ ] Testar perda e recuperação controlada da conexão do banco, limites de upload e interrupções do servidor.
- [ ] Confirmar integridade de páginas/menus e do logotipo SONI em site e módulos.

**Evidência exigida:** checklist de smoke tests preenchido com data/ambiente, sem dados privados.

### Fase E — Operação mínima e lançamento (P0)
- [ ] Configurar logs privados com rotação, métricas simples (HTTP 5xx, disco, banco, jobs/backup), health-check e alertas básicos.
- [ ] Confirmar política de atualizações de segurança, responsável pelo suporte e procedimento para incidentes/retirada temporária do site.
- [ ] Inserir conteúdo inicial revisado, de autoria ou com licença adequada: páginas essenciais, ao menos um quiz, um documento e um vídeo publicados e testados.
- [ ] Definir quem pode editar conteúdo e quem poderá relatar erros; preparar instruções curtas para administrador.
- [ ] Fazer homologação final e registrar decisão **GO / NO-GO**, data, responsável, commit implantado e procedimento de rollback.

**Evidência exigida:** URL pública validada, monitoramento ativo, conteúdo pronto e aceite de lançamento.

## Pilotagem e melhorias após P0 (P1)
- [ ] Convidar um grupo pequeno de operadores e líderes de sonorização de igrejas para experimentar conteúdos e testes.
- [ ] Registrar três informações simples: conseguem iniciar sem ajuda? conseguem encontrar material? avaliação e devolutiva fazem sentido?
- [ ] Corrigir falhas que bloqueiem tarefas essenciais; registrar pedidos de novas funções separadamente.
- [ ] Revisar acessibilidade, SEO, performance e versão mobile com base nos resultados reais.

## Itens adiados (P2)
Paginação avançada, contas para alunos, certificados, relatórios sofisticados, upload de vídeos grandes, armazenamento externo de streaming, gamificação, comunidade e monetização integrada.

## Marco de acompanhamento

| Marco | Estado inicial | Para avançar |
| --- | --- | --- |
| MVP 0.9.0 | **Aprovado** | Escopo congelado e CI aprovado |
| Preparação 1.0.0 | **Em andamento** | Confirmar e executar P0 |
| Homologação pública | **Não iniciada** | Segurança, backups e deploy de teste aprovados |
| Lançamento 1.0.0 | **Não autorizado** | Evidência de todos os P0 e decisão GO |
| Piloto | **Não iniciado** | Primeiros usuários e formulário de retorno |

**Próxima ação prática recomendada:** verificar permissões do `.env` e `api/storage` para `www-data`, testar login e publicação/ocultação de mídias, corrigir rate limiting e segurança, implementar backup fora do Droplet com restauração isolada e monitorar antes do aceite v1.0.0.
