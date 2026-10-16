# SONI — Preparação de deploy no Ubuntu 24.04 / DigitalOcean

**Data de decisão:** 2026-10-16.
**Status:** diagnóstico inicial recebido e favorável; PHP-FPM/módulos, serviço de banco, RAM, domínio, DNS e implantação ainda pendentes. Este guia NÃO é uma confirmação de que a aplicação esteja no ar.

## Infraestrutura confirmada

- Usaremos o Droplet Ubuntu 24.04 LTS **já existente** da DigitalOcean.
- O Droplet é compartilhado com outras aplicações. Não sobrescrever arquivos, VirtualHosts, certificados, bancos nem pastas existentes.
- Nome do domínio/subdomínio SONI e topologia final: **a definir depois do diagnóstico**.
- O MVP v0.9.0 está congelado; são permitidas apenas alterações necessárias à produção (segurança, deploy, correções, operações e testes).

## Etapa 1 — Diagnóstico seguro (somente leitura)

Conectar via SSH/MobaXterm ao Droplet já utilizado. Executar **um por vez** os comandos abaixo:

```bash
lsb_release -ds
apache2 -v
php -v
php -m | grep -Ei '^(pdo_mysql|fileinfo)$'
mariadb --version || mysql --version
sudo apache2ctl -S
sudo apache2ctl -M | grep -E 'rewrite_module|ssl_module|proxy_fcgi_module|headers_module'
df -h /
free -h
```

Os comandos coletam informação do sistema e **não devem alterar** pacotes, configurações ou banco. A ausência de algum executável não exige instalá-lo imediatamente; registrar a ausência e prosseguir com segurança.

**Privacidade:** não colar conteúdo de `.env`, certificados, senhas, tokens, dumps, IP público, chaves privadas ou URLs administrativas secretas. `apache2ctl -S` pode mostrar nomes de sites: omitir os que desejar antes de compartilhar.

## Resultado parcial do diagnóstico (informado pelo responsável em 2026-10-16)

- Ubuntu 24.04.4 LTS, Apache 2.4.58, PHP CLI 8.3.6; módulos PHP `fileinfo` e `pdo_mysql` presentes.
- Cliente `mysql` 8.0.46 está instalado. `mariadb` ausente **não é erro e não exige instalação**. O resultado `mysql --version` identifica apenas o cliente; tipo/versão e estado do serviço de banco ainda não foram verificados.
- O Apache apresenta VirtualHosts HTTPS ativos de diversos domínios existentes em `*:443`, e HTTP em `*:80`. **Nenhum VirtualHost SONI foi criado**.
- Partição raiz: 96 GB de capacidade, 29 GB usados, 68 GB livres (31% ocupação).
- Não foram informadas saídas de `apache2ctl -M`, `free -h` e checagem do serviço banco/PHP-FPM.
- Não alterar os VirtualHosts/certificados existentes, não executar `apt install` nesta fase e não disponibilizar dados sensíveis do servidor.

**Próxima decisão:** escolher um subdomínio exclusivo para SONI. Sugestão de organização, sem configurar ainda: `soni.somdaigreja.com.br`. A escolha deve ser confirmada pelo responsável.

**Consultas adicionais seguras, quando solicitado:**

```bash
sudo apache2ctl -M | grep -E 'rewrite_module|ssl_module|proxy_fcgi_module|headers_module|php_module'
free -h
systemctl is-active mysql
systemctl is-active mariadb
systemctl list-units 'php*-fpm.service' --type=service --all --no-pager
```

Esses comandos apenas leem informações. O estado `inactive` ou `unknown` não autoriza instalar nem reiniciar serviços sem verificar a configuração.

## Etapa 2 — Planejamento (após inspecionar diagnóstico)

1. Escolher um nome DNS de propriedade/controlado pelo responsável, ainda sem fazer alterações.
2. Registrar arquitetura proposta para o mesmo domínio: `/` serve o build estático de `frontend/dist`; `/api` deve encaminhar requisições ao Slim, com PHP-FPM/Apache e roteamento adequado a todas as rotas `/api/...`.
3. Manter arquivos de mídia e backups fora do DocumentRoot; nunca servir `api/.env` nem `api/storage` diretamente.
4. Criar banco e usuário próprios da SONI, separados de qualquer banco existente, com permissões mínimas.
5. Garantir que o roteamento SPA preserve `/admin`, `/testes`, `/documentos` e `/videos` sem capturar `/api`.
6. Planejar HTTPS/Certbot sem reconfigurar VirtualHosts de outros sites. O frontend deve usar mesmo domínio e base `/api`; `FRONTEND_URL` e CORS precisam refletir a URL real.
7. Verificar se o PHP possui `pdo_mysql` e `fileinfo`; PHP >= 8.2 e Composer. Node/Vite podem compilar localmente/CI, não precisam rodar servidor Vite em produção.
8. Criar backup externo e ensaio de restauração isolada antes de importar dados reais; registrar rollback de deploy sem reverter migrações destrutivamente.

**Atenção:** não usar `php -S` nem `npm run dev` como servidores públicos de produção. Este projeto ainda não possui VirtualHost de produção validado e o roteamento HTTP precisa ser testado no ambiente-alvo.

## Etapa 3 — Bloqueadores obrigatórios do lançamento

Consultar [SONI_CHECKLIST_LANCAMENTO.md](SONI_CHECKLIST_LANCAMENTO.md). Os pontos principais são: segurança dos endpoints, rate limiting de login e quizzes, proteção de upload, HTTPS, backup off-site, restauração isolada, homologação de frontend/API, monitoramento e operação.

## Próxima interação

Receber saída dos comandos somente leitura, confirmar disponibilidade/versões e projetar um VirtualHost exclusivo para SONI. **Não instalar nem modificar o Droplet nesta etapa.**
