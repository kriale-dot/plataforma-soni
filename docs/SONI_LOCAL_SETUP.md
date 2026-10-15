# Instalação local — Plataforma SONI

Ambiente alvo: Windows 11, PowerShell e VS Code.

## Pré-requisitos
- Git
- Node.js 20+ e npm
- PHP 8.2+ com extensões exigidas pelas dependências
- Composer 2
- Portas locais 5173 e 8282 disponíveis

O MySQL não é necessário para verificar o login inicial e será configurado depois.

## 1. Baixar ou atualizar
Na primeira vez:

~~~powershell
git clone https://github.com/kriale-dot/plataforma-soni.git
cd plataforma-soni
~~~

Se a pasta já estiver clonada:

~~~powershell
cd plataforma-soni
git pull origin main
~~~

## 2. API Slim e credenciais administrativas
No PowerShell na raiz do projeto:

~~~powershell
cd api
composer install
Copy-Item .env.example .env
~~~

Abra api/.env e preencha:
- APP_ENV=development
- FRONTEND_URL=http://localhost:5173
- ADMIN_EMAIL=seu e-mail de administrador
- ADMIN_PASSWORD_HASH=hash de senha
- JWT_SECRET=chave aleatória única com mais de 32 caracteres

Para gerar o hash sem colocar a senha diretamente no histórico de comandos do PowerShell:

~~~powershell
php -a
~~~

Na sessão interativa do PHP:

~~~php
echo password_hash(readline('Senha: '), PASSWORD_DEFAULT), PHP_EOL;
~~~

Copie para ADMIN_PASSWORD_HASH somente a hash gerada, geralmente iniciada em $2y$. Saia do modo interativo com exit.

**A senha poderá aparecer enquanto for digitada.** Use terminal privado sem compartilhar sua tela.

Para gerar o JWT_SECRET seguro:

~~~powershell
php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
~~~

Copie o valor aleatório para api/.env. Não envie o segredo ao GitHub nem à conversa.

Inicie a API:

~~~powershell
composer start
~~~

Em outro terminal execute:

~~~powershell
Invoke-RestMethod http://localhost:8282/api/health
~~~

Resposta esperada: status ok, versão 0.2.0.

## 3. Frontend React
Em outro PowerShell, partindo da raiz:

~~~powershell
cd frontend
npm install
npm run dev
~~~

Abra http://localhost:5173/admin. O proxy de desenvolvimento encaminha /api para a API Slim na porta 8282.

Verificações manuais:
1. O indicador da API deve mostrar "conectada".
2. Com email/senha válidos configurados no .env, entrar deve mostrar "Acesso autorizado".
3. Senha incorreta deve retornar erro, sem entrar.
4. Clicar em "Sair" encerra a sessão. Recarregar a página também encerra a sessão do frontend.
5. Desligar a API deve mostrar conexão indisponível ao recarregar a página.

## 4. Validação estática
Frontend:

~~~powershell
npm run build
~~~

API, na respectiva pasta:

~~~powershell
composer validate
php -l public/index.php
php -l src/AdminAuth.php
php -l src/Http.php
~~~

## 5. Conexão `localhost` x `127.0.0.1` no Windows
Se a tela administrativa mostrar `API: indisponível` e o navegador relatar erro 500 em `/api/health`, compare os testes:

~~~powershell
curl.exe -i http://localhost:8282/api/health
curl.exe -i http://127.0.0.1:8282/api/health
~~~

No computador do responsável (15/10/2026), o primeiro retornou HTTP 200 e o segundo falhou. O proxy Vite foi atualizado para `http://localhost:8282` para acompanhar o endereço que atende a API. Após atualizar com `git pull origin main`, **pare e reinicie** o processo `npm run dev` e recarregue `/admin`. Mantenha o servidor PHP aberto. Se não resolver, inspecione a saída do terminal Vite e o erro de rede, sem enviar credenciais.

## 6. Problemas conhecidos
- API indisponível: verificar se o terminal com composer start está aberto.
- HTTP 503 no login: configurar corretamente todas as variáveis em api/.env.
- HTTP 401: credenciais não conferem.
- Em produção, o Vite e o servidor embutido de PHP devem ser substituídos por hospedagem/servidor HTTPS.

**Após testar localmente**, atualizar docs/SONI_STATUS.md e registrar resultados, sem compartilhar valores secretos.

## 7. Testar o editor do site (v0.4.0)
1. Atualize na raiz do repositório: `git pull origin main`.
2. Reinicie o servidor PHP (`composer start` na pasta api) e o Vite (`npm run dev` na pasta frontend).
3. Abra http://localhost:5173/admin e faça login. Clique em **Editar site institucional**.
4. Selecione um bloco de texto, altere seu título, clique em **Salvar bloco**.
5. Abra http://localhost:5173 em outra aba e atualize: a mudança deve aparecer.
6. Teste botões de **Ocultar/Mostrar**, **Publicar/Voltar a rascunho**, **Subir/Descer**, **Adicionar bloco** e **Configurações**.
7. O editor fica em `/admin/site`, mas o JWT é mantido na memória: recarregar a página exigirá novo login.

Limitações conhecidas da v0.4.0: os arquivos de mídia ainda não possuem upload; é possível referenciar URLs HTTPS válidas. Preview de rascunhos não implementado. Não digite scripts HTML ou URLs `javascript:` (a API rejeita links inseguros).

## 8. Upload de mídias do site (v0.5.0)
- Atualize com git pull origin main na pasta do projeto. Reinicie API e Vite. Não sobrescreva api/.env.
- Confira extensões habilitadas com: php -m | findstr /I "fileinfo pdo_mysql"
- Confira php.ini usando php --ini. Para vídeos de até 32 MiB, no php.ini do PHP CLI e somente no desenvolvimento, ajuste:
~~~ini
file_uploads=On
upload_max_filesize=36M
post_max_size=40M
~~~
- Reinicie composer start para aplicar os limites. A API ainda restringe imagens a 6 MiB, áudios a 15 MiB e vídeos a 32 MiB.
- Abra http://localhost:5173/admin, faça login, entre em Editar site institucional e selecione um bloco HERO/IMAGEM/TEXTO_IMAGEM (imagem), AUDIO (áudio) ou VIDEO (vídeo).
- Em Enviar novo arquivo, selecione a mídia. Aguarde o retorno de sucesso, clique em Pré-visualizar (se necessário), depois clique em Salvar bloco.
- Para disponibilizar no site público, marque o bloco como Publicado e Visível e salve. Faça refresh da homepage.
- Mídias de rascunhos não ficam disponíveis no endereço público; a prévia do editor exige token JWT.
- Pasta de mídia: api/storage/site_media fora de api/public. Garanta permissões e inclua em backups junto do MariaDB.
- Não há migração nova: site_media e site_blocks.media_id já estão na migração 001.
- Vídeos longos: prefira YouTube/HTTPS por enquanto; uploads fragmentados e CDN serão avaliados futuramente.

## 9. Backup e auditoria sem excluir nada (v0.6.0)

Veja docs/SONI_MAINTENANCE.md. Na pasta api, execute `php bin/backup.php` para criar uma cópia de dados e arquivos, e `php bin/media-audit.php` para simular uma análise de mídias antigas sem vínculo. Não execute `--quarantine` na primeira validação. Depois copie seu backup para outro dispositivo.

## 10. Testes interativos — v0.7.0

Após `git pull origin main`, execute **dentro de `api`**: `php bin/migrate.php`. Deve retornar `Aplicada: 002_quizzes.sql`, mantendo a migration 001 como já aplicada. Reinicie PHP e Vite. Entre em http://localhost:5173/admin e clique em **Gerenciar testes**. Crie um teste em Fundamentos de Sonorização, adicione questões com 2 a 6 alternativas, marque uma correta e clique em Publicar teste. Aplique em http://localhost:5173/testes e confira a correção final. Nenhuma pergunta fictícia é publicada automaticamente.

## 11. Devolutivas conforme porcentagem (v0.7.1)

Depois de atualizar o repositório, execute `php bin/migrate.php` na pasta `api` (aplicará 002 e/ou 003 conforme seu histórico). Reinicie PHP e React. Em http://localhost:5173/admin, entre em Testes, selecione um teste e revise **Devolutiva por percentual de acertos**. Customize classificação e mensagens, clique **Salvar devolutivas**. Em /testes, conclua tentativas com desempenhos diferentes: somente após finalizar aparecerão classificação, texto e recomendação correspondentes. Não execute testes automatizados de CI no banco real.
