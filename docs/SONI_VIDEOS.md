# SONI — Biblioteca de Vídeos v0.9.0

## Recursos
- YouTube: informar uma URL HTTPS dos domínios oficiais (youtube.com ou youtu.be); o backend armazena ID validado e o player usa youtube-nocookie.com.
- Upload: escolher MP4/WebM com até 32 MiB; a rota de upload existente verifica MIME real, extensão, tamanho e armazena o arquivo fora do webroot.
- Painel protegido por JWT: cadastrar/editar categorias, criar, editar, excluir vídeos, prévia privada e status rascunho/publicado + visibilidade.
- Biblioteca pública em \`/videos\` com pesquisa por título/descrição e filtro por categoria. Player em \`/videos/{id}\`.
- A rota de mídia só permite acesso anônimo a arquivos vinculados a vídeos publicados/visíveis (ou outros usos publicados permitidos).

## API
Públicas:
- \`GET /api/videos?q=...&category=...\`
- \`GET /api/videos/{id}\`

Admin JWT:
- \`GET /api/admin/videos\`
- \`POST /api/admin/video-categories\`
- \`PUT /api/admin/video-categories/{id}\`
- \`POST /api/admin/videos\`
- \`PUT /api/admin/videos/{id}\`
- \`DELETE /api/admin/videos/{id}\`
- \`POST /api/admin/site/media\` — upload multipart com campo \`file\`

## Instalação no Windows
1. Faça um backup (\`php bin/backup.php\`) e guarde cópia externa.
2. \`git pull origin main\`, na pasta \`api\` execute \`php bin/migrate.php\` e reinicie PHP/Vite.
3. Faça login em \`http://localhost:5173/admin\`, clique em Vídeos e cadastre um vídeo YouTube em rascunho.
4. Confira prévia, publique e verifique \`http://localhost:5173/videos\`.
5. Repita com MP4/WebM pequeno; altere visibilidade para oculto e confirme que a mídia não abre publicamente.
6. Para 32 MiB, o \`php.ini\` deverá aceitar POST superior a 32 MiB (\`upload_max_filesize=36M\` e \`post_max_size=40M\`). Consulte \`php --ini\`.

## Limitações e cuidados
- O limite 32 MiB não serve a aulas longas. Para aulas maiores, publicar no YouTube ou futuramente integrar storage de vídeo com upload segmentado.
- O YouTube pode proibir incorporação em certos vídeos; isso depende da configuração do autor.
- Sem busca paginada (máximo 100 itens por consulta), sem armazenamento externo, sem transcodificação e sem rate limiting.
- O upload exige validação MIME e extensão, mas não é garantia de ausência de malware; antes da produção acrescentar antivírus, controle de cotas e segurança adicional.
- Excluir um cadastro não remove o arquivo físico; a auditoria só avalia arquivos órfãos antigos após backup recente.
- Nunca executar scripts de CI no banco da instalação real.

## Teste automatizado
- A v0.9.0 passou GitHub Actions 38817558203 com PHP, React e MariaDB 11.4.
- Não foi executada migração nem teste visual no computador do usuário nesta etapa.
