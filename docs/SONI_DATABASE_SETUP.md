# Banco SONI — MariaDB 11.4 (Windows)

**Estado:** scripts da v0.3.0 publicados no GitHub; instalação local ainda não verificada.
**Importante:** o banco é dedicado ao projeto SONI. NÃO use o banco de outros sistemas.

## 1. Verificar PHP PDO MySQL
No PowerShell:
~~~powershell
php -m | findstr /I pdo_mysql
~~~
Deverá aparecer pdo_mysql. Caso não apareça, habilite extension=pdo_mysql no php.ini da versão do PHP em uso e reinicie o terminal.

## 2. Abrir o console MariaDB (Windows)
~~~powershell
& "C:\Program Files\MariaDB 11.4\bin\mariadb.exe" -u root -p
~~~
Digite sua senha quando solicitada. Não copie nem compartilhe a senha.

## 3. Criar apenas o banco SONI
No prompt MariaDB (não no PowerShell):
~~~sql
CREATE DATABASE IF NOT EXISTS plataforma_soni
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
~~~

## 4. Criar usuário local dedicado
Escolha uma senha **nova e forte**, exclusiva para a plataforma, e substitua SENHA_NOVA_FORTE nas duas instruções abaixo antes de executá-las:

~~~sql
CREATE USER IF NOT EXISTS 'soni_app'@'localhost'
  IDENTIFIED BY 'SENHA_NOVA_FORTE';
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX, REFERENCES
  ON plataforma_soni.* TO 'soni_app'@'localhost';
~~~

Se o usuário já existir, o comando IF NOT EXISTS não altera sua senha; ajuste-a deliberadamente com ALTER USER se necessário. O usuário tem privilégios de migração **somente no banco plataforma_soni**. Em produção, separar conta de migração de conta de aplicação e remover privilégios DDL da conta de aplicação.

É recomendável evitar armazenar senhas em histórico de comandos e sessões compartilhadas. As instruções SQL interativas podem ser registradas pelo histórico do cliente; use senha de desenvolvimento exclusiva, não a senha root, não a mesma senha do administrador SONI.

No MariaDB, confirme o nome do banco (sem revelar dados privados):
~~~sql
SHOW DATABASES LIKE 'plataforma_soni';
EXIT;
~~~

## 5. Adicionar variáveis no api/.env sem sobrescrever as existentes
Manter ADMIN_EMAIL, ADMIN_PASSWORD_HASH e JWT_SECRET do login que já funciona. Acrescentar somente:
~~~dotenv
DB_HOST=localhost
DB_PORT=3306
DB_NAME=plataforma_soni
DB_USER=soni_app
DB_PASSWORD=SENHA_NOVA_FORTE
~~~
Não copie o arquivo api/.env.example sobre api/.env, pois apagaria suas configurações locais.

## 6. Aplicar migrações
Abra terminal **na pasta api** do projeto (já atualizado via git pull):
~~~powershell
php bin/migrate.php
php bin/db-check.php
~~~
Resultado esperado:
~~~text
Aplicada: 001_site_institucional.sql
Migrações concluídas.
Conexão PDO: OK
Banco: plataforma_soni
Site institucional: SONI | Sonorização de Igrejas
Blocos publicados: 5
~~~

A repetição de php bin/migrate.php deverá retornar "Já aplicada" sem duplicar os registros. As migrações são rastreadas na tabela schema_migrations com hash SHA-256. Como operações DDL fazem commits implícitos em MariaDB, erros intermediários podem deixar tabelas criadas parcialmente — não modifique migrações já aplicadas.

## 7. Verificar API pública
Reinicie o servidor PHP se alterou variáveis no api/.env. Acesse:
- http://localhost:8282/api/site

A resposta deverá conter "settings" e cinco blocos "blocks". Esta é apenas a primeira rota pública da API: o site React ainda não utiliza esses registros, e o painel CMS editável ainda não está construído.

## Erros comuns
- Could not find driver: instalar/habilitar pdo_mysql no PHP em uso.
- Access denied: conferir DB_USER, DB_PASSWORD e host. Não enviar credenciais ao chat.
- Unknown database: criar plataforma_soni antes de migrar.
- Erro de permissão: conferir GRANT do usuário local.
- Conexão recusada: conferir serviço MariaDB, DB_HOST e DB_PORT.
