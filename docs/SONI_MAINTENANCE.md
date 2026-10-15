# SONI — Backup e auditoria de mídias (v0.6.0)

**IMPORTANTE:** as ferramentas abaixo trabalham com o MariaDB e arquivos privados da SONI.
Elas foram pensadas para preservar o conteúdo institucional já publicado.
**Não execute uma importação SQL diretamente no banco de trabalho.**

## 1. Pré-requisitos
- Estar na pasta \`api\`, com \`composer install\` executado e \`api/.env\` já configurado.
- Ter instalado o utilitário MariaDB Dump. No Windows MariaDB 11.4 ele costuma estar em:
  \`C:\Program Files\MariaDB 11.4\bin\mariadb-dump.exe\`.
- A conta do banco usada em \`DB_USER\` precisa conseguir ler as tabelas SONI (SELECT).
- Faça preferencialmente com o painel administrativo sem usuários editando conteúdo.

Se o dump não for encontrado automaticamente, acrescente **somente** ao seu \`api/.env\`:
~~~dotenv
SONI_DUMP_BIN="C:\Program Files\MariaDB 11.4\bin\mariadb-dump.exe"
~~~
Se usar um diretório diferente, informe o caminho real do seu executável.

## 2. Criar um backup local completo
Execute no PowerShell, dentro de \`api\`:
~~~powershell
php bin/backup.php
~~~
Esse comando:
1. usa um arquivo temporário de credenciais (sem senha na linha de comando), apagado ao final;
2. faz dump SQL do banco informado em DB_NAME;
3. copia os arquivos de \`api/storage/site_media\` para um snapshot privado;
4. escreve \`manifest.json\` com hashes SHA-256;
5. verifica os arquivos e somente depois conclui o diretório.

O resultado é uma pasta datada em \`api/storage/backups\`, contendo:
- \`database.sql\`
- \`manifest.json\`
- \`media/...\` com as mídias originais.

**Atenção:** o backup local sozinho não protege contra falha ou perda do disco. Copie a pasta completa, preferencialmente com criptografia, para outro dispositivo ou destino seguro, sem colocá-la no GitHub, em um site público ou em e-mails.

A verificação por hashes prova integridade dos arquivos copiados, **não** prova que o SQL consegue ser restaurado. A restauração deverá ser ensaiada em **banco de teste separado** antes de considerar o backup pronto para uso em produção.

## 3. Auditoria de mídias: modo seguro
~~~powershell
php bin/media-audit.php
~~~
Por padrão, não altera nenhum arquivo ou registro. Exibe **até 25** mídias enviadas há mais de 30 dias, sem vínculo a qualquer bloco (inclusive rascunhos/ocultos) nem ao logotipo.

Pode aumentar a janela ou quantidade com:
~~~powershell
php bin/media-audit.php --days=60 --limit=50
~~~
Nenhum arquivo é apagado pela auditoria simples.

## 4. Quarentena opcional (somente após revisar os candidatos)
Esta operação **altera o banco e movimenta arquivos**. Não a execute se ainda não revisou os resultados ou não possui backup recente validado.

Depois de um backup criado há menos de 12 horas, a operação poderia ser:
~~~powershell
php bin/media-audit.php --quarantine --backup="C:\CAMINHO\SONI\api\storage\backups\PASTA_DO_BACKUP"
~~~
A ferramenta confere os hashes do backup, checa novamente vínculos de cada mídia dentro de uma transação e move apenas candidatas antigas para \`api/storage/site_media_quarantine\`. Os arquivos **não são apagados definitivamente**. Um registro do movimento permanece em \`quarantine-log.jsonl\`.

Para restaurar de uma quarentena, use a pasta de backup correspondente e os registros da quarentena; um comando automatizado de restauração ainda não foi implementado. Portanto, **não execute \`--quarantine\` antes de uma revisão acompanhada**.

## 5. Segurança e limitações
- O diretório de backup está fora de \`api/public\` e ignorado pelo Git.
- O arquivo de credenciais temporário recebe permissão restrita quando suportado pelo sistema operacional. Em Windows, proteja a pasta TEMP e evite usuários não confiáveis na mesma sessão.
- Não compartilhe \`api/.env\`, dump SQL nem backups em conversas ou repositórios.
- Arquivos são copiados enquanto o SQL é exportado; mudanças simultâneas podem gerar diferenças. Faça cópias em período sem edições.
- Não há agendamento automático, cópia remota, rotatividade, restauração automatizada nem proteção contra perda do dispositivo ainda.
- **Próximo passo:** realizar backup real com verificação local e teste de restauração isolada.

## Backups da Biblioteca de Documentos (v0.8.0)
- O script `bin/backup.php` inclui os arquivos PDF em `api/storage/site_media` e o SQL das tabelas de documentos.
- A auditoria não trata como órfão nenhum arquivo vinculado a documento, inclusive rascunho ou oculto.
- Refaça o backup após importar documentos; o snapshot anterior não contém os novos materiais.

## Biblioteca de Vídeos (v0.9.0)
- O backup do MariaDB e `site_media` inclui vídeos MP4/WebM vinculados, como outros arquivos. Copiar o backup para outro dispositivo.
- Auditoria/quarentena não toca mídias vinculadas a registros `videos`, mesmo rascunhos/ocultos.
- Links de vídeos do YouTube são dados no banco, não arquivos locais.
