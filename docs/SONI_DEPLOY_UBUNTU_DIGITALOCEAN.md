# SONI — Preparação de deploy no Ubuntu 24.04 / DigitalOcean

**Data de decisão:** 2026-10-16.
**Status:** diagnóstico inicial recebido e favorável; PHP-FPM/módulos, serviço de banco, RAM, domínio, DNS e implantação ainda pendentes. Este guia NÃO é uma confirmação de que a aplicação esteja no ar.

## Infraestrutura confirmada

- Usaremos o Droplet Ubuntu 24.04 LTS **já existente** da DigitalOcean.
- O Droplet é compartilhado com outras aplicações. Não sobrescrever arquivos, VirtualHosts, certificados, bancos nem pastas existentes.
- Domínios e diretórios planejados: `somdaigreja.com.br`/`www.somdaigreja.com.br` (frontend) e `api.somdaigreja.com.br` (API); conforme topologia definida abaixo.
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

**Decisão posterior:** o responsável definiu `somdaigreja.com.br` e `www.somdaigreja.com.br` para o frontend e `api.somdaigreja.com.br` para o backend.

**Consultas adicionais seguras, quando solicitado:**

```bash
sudo apache2ctl -M | grep -E 'rewrite_module|ssl_module|proxy_fcgi_module|headers_module|php_module'
free -h
systemctl is-active mysql
systemctl is-active mariadb
systemctl list-units 'php*-fpm.service' --type=service --all --no-pager
```

Esses comandos apenas leem informações. O estado `inactive` ou `unknown` não autoriza instalar nem reiniciar serviços sem verificar a configuração.


## Topologia definida pelo responsável (2026-10-16)

**Aprovada para planejamento; ainda não implantada.** Utilizar o mesmo Droplet Ubuntu existente e domínios já controlados:

| Função | Endereço | Diretório no Ubuntu | DocumentRoot efetivo |
| --- | --- | --- | --- |
| React (site e administração) | `https://somdaigreja.com.br` | `/var/www/somdaigreja.com.br/frontend` | `/var/www/somdaigreja.com.br/frontend/dist` |
| React (alias) | `https://www.somdaigreja.com.br` | mesma pasta do frontend | mesmo `dist` |
| PHP/Slim (API) | `https://api.somdaigreja.com.br` | `/var/www/somdaigreja.com.br/api` | `/var/www/somdaigreja.com.br/api/public` |

**Importante:** usar barras normais `/` nos caminhos Linux. Não publicar arquivos-fonte React, `.env`, `vendor`, `storage` ou backups no DocumentRoot.

As versões antigas de `somdaigreja.com.br` e `api.somdaigreja.com.br` já tiveram VirtualHosts HTTP/HTTPS desativados pelo responsável, mas arquivos, certificados e DNS continuam preservados. `admin.somdaigreja.com.br` permanecerá desativado; o painel novo ficará em `https://somdaigreja.com.br/admin`. O diretório antigo `/var/www/atendente-ia` **não deve ser removido**, pois outro VirtualHost pode compartilhá-lo.

### Ajuste do frontend para API em outro domínio

O build de produção deverá receber a variável:

```dotenv
VITE_API_BASE_URL=https://api.somdaigreja.com.br/api
```

Aplicar antes de `npm run build` (variáveis Vite são incorporadas no artefato estático). O código da SONI agora transforma referências `/api/site/media/...` retornadas pelo CMS e usadas em quizzes, vídeos e documentos em URLs da API de produção; em desenvolvimento com Vite mantém caminhos relativos.

Configuração privada da API (não versionar `.env`):

```dotenv
APP_ENV=production
FRONTEND_URL=https://somdaigreja.com.br
FRONTEND_ALT_URL=https://www.somdaigreja.com.br
```

A API autoriza CORS apenas para a origem `Origin` que corresponda exatamente a um desses valores (ou ao frontend local em desenvolvimento). Requer configuração segura de ADMIN_EMAIL, ADMIN_PASSWORD_HASH, JWT_SECRET e DB_*; nunca copiar as credenciais da instalação local por mensagens ou GitHub.

**Roteamento:** o frontend precisa entregar `index.html` para `/admin`, `/testes`, `/documentos`, `/videos` e subrotas, mas não capturar endpoints da API. No subdomínio API o Apache deve encaminhar `/api/*` ao Slim `public/index.php` por configuração de rewrite/fallback testada, com PHP-FPM/mod_php conforme modo real constatado. As configurações de Apache/SSL serão preparadas depois de conhecer os módulos e as pastas atuais. Não sobrescrever nem reabilitar cegamente os antigos VirtualHosts.

### Etapa imediatamente seguinte: verificar as pastas sem alterar dados

```bash
for p in /var/www/somdaigreja.com.br /var/www/somdaigreja.com.br/frontend /var/www/somdaigreja.com.br/api; do
  if [ -e "$p" ]; then
    sudo ls -ld "$p"
    sudo find "$p" -mindepth 1 -maxdepth 1 -printf '  %f\n' | head -35
  else
    echo "Ainda não existe: $p"
  fi
done

sudo apache2ctl -M | grep -E 'rewrite_module|dir_module|ssl_module|headers_module|proxy_fcgi_module|php_module'
systemctl list-units 'php*-fpm.service' --type=service --all --no-pager
sudo systemctl is-active mysql
free -h
```

Esses comandos são **somente leitura**. Não executar `rm`, `mv`, `git clone`, `certbot`, `a2ensite`, `chown -R` ou migrations no Droplet até identificar os arquivos antigos e preparar um backup.


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

## Diagnóstico adicional confirmado em 2026-10-16

- Caminho `/var/www/somdaigreja.com.br` existe e está **vazio** (`root:root`). Pastas `frontend` e `api` ainda não existem.
- Apache com módulos `dir`, `headers`, `php`, `rewrite` e `ssl`. `proxy_fcgi` não apareceu; nenhum serviço PHP-FPM foi listado. Planejar API com Apache/mod_php, sem instalar FPM nesta etapa.
- MySQL `active` (versão efetiva do servidor ainda a consultar).
- RAM 7,8 GiB total e 4,2 GiB disponíveis; sem swap. Disco 68 GB livres segundo inspeção anterior.
- Repositório `kriale-dot/plataforma-soni` é público; permite `git clone` HTTPS sem token. Como o diretório base está vazio, o clone diretamente nele produzirá as pastas `frontend` e `api` exigidas, sem sobrescrever dados existentes.

### Próxima ação: clonar somente o código

```bash
cd /var/www/somdaigreja.com.br
sudo git clone --branch main --single-branch https://github.com/kriale-dot/plataforma-soni.git .
git status --short --branch
ls -ld frontend api
```

Ainda não executar `composer install`, `npm install`, migração, `a2ensite`, reiniciar Apache, configurar DNS ou alterar permissões até verificar o clone e as versões de ferramentas. Repositório versionado nunca deve conter `api/.env` nem `api/storage` de produção.
