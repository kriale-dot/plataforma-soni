# SONI — Checklist de lançamento v1.0.0

**Referência:** MVP v0.9.0 congelado em `SONI_MVP_ESCOPO.md`.
**Meta:** tornar a primeira versão utilizável com segurança, backup restaurável e monitoramento em um domínio HTTPS.
**Situação inicial:** P0 não comprovados no servidor de produção. Nenhuma implantação pública foi confirmada.

> **Regra de avanço:** marcar uma caixa apenas depois de executar a verificação no ambiente-alvo e guardar evidência (data, comando sem segredos, resultado, URL de CI ou anotação de teste). Não guardar senhas, tokens, dumps, arquivos de usuários ou `.env` no repositório.

## Decisão de infraestrutura — confirmada em 2026-10-16

- [x] Servidor selecionado: Droplet DigitalOcean já existente, com Ubuntu 24.04 LTS, compartilhado com outras aplicações do responsável.
- [ ] Verificar versões e configuração real do Apache, PHP/PHP-FPM, MariaDB/MySQL, módulos habilitados e recursos livres, antes de instalar qualquer pacote.
- [ ] Definir o domínio/subdomínio da SONI; não assumir endereço nem registrar DNS ainda.
- [ ] Projetar isolamento da SONI por VirtualHost, diretório e banco exclusivos, sem alterar sites e bancos já ativos.
- [ ] Verificar backups externos e plano de recuperação do Droplet antes da implantação.

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
- [ ] Executar `php bin/backup.php` e validar o manifesto para a base real e todas as mídias.
- [ ] Manter cópia criptografada em outro dispositivo/local seguro, com retenção e periodicidade definidas; testar acesso à cópia.
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

**Próxima ação prática recomendada:** escolher o ambiente/servidor onde a SONI será implantada e preparar o inventário de configuração, sem colocar dados reais em ambiente não validado.
