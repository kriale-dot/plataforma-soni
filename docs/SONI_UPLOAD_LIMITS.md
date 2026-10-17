# SONI — Limites de upload (17/10/2026)

## Regras da aplicação

Os máximos por arquivo são **20 MiB para imagens** (JPG/JPEG, PNG, WebP, GIF), **50 MiB para áudios** (MP3, WAV, OGG), **50 MiB para PDFs** e **200 MiB para vídeos** (MP4/WebM). Um MiB equivale a 1.048.576 bytes. Os limites são aplicados pela API em `api/src/SiteMedia.php` e pelo frontend em `frontend/src/lib/uploadLimits.js`. O backend continua verificando extensão, MIME, assinatura de PDF, dimensões das imagens e armazenamento fora de `public`. Os limites das imagens continuam incluindo **até 10.000 px por eixo e 40 megapixels**.

## Servidor de produção (Apache com mod_php no VirtualHost HTTPS da API)

**Antes de atualizar:** criar backup íntegro de MySQL e `api/storage/site_media`, confirmar cópia externa e verificar espaço disponível com `df -h`. Não executar testes destrutivos no servidor. O mesmo Droplet hospeda outros sistemas; não mudar configurações globais do PHP/Apache se for possível limitar ao VirtualHost da API.

Na configuração do **VirtualHost HTTPS de `api.somdaigreja.com.br`**, com `mod_php`, ajustar especificamente:

```apache
php_admin_value upload_max_filesize 220M
php_admin_value post_max_size 240M
php_admin_value max_input_time 600
php_admin_value max_execution_time 600
```

O teto do PHP precisa ser maior que 200 MiB e o teto de POST maior que o teto de upload, com margem para multipart. Estas diretivas exigem mod_php; em PHP-FPM a configuração é diferente. Antes de salvar, conferir como os valores estão definidos no VirtualHost atual e preservar os demais ajustes (inclusive limites existentes de outras diretivas). Validar com `sudo apache2ctl configtest` e só depois aplicar com `sudo systemctl reload apache2`. Para verificar valores **efetivos via HTTP**, usar procedimento administrativo privado e temporário no VirtualHost da API, remover imediatamente o script de diagnóstico e nunca exibir `phpinfo()` publicamente. `php -i` mede apenas o PHP CLI, não necessariamente o PHP usado pelo Apache.

## Deploy e validação

1. Fazer merge após aprovação do GitHub Actions, conferir checkout limpo e versão esperada no servidor.
2. Executar `git pull --ff-only origin main` na raiz da SONI.
3. Executar `VITE_API_BASE_URL=https://api.somdaigreja.com.br/api npm run build` na pasta `frontend`.
4. Atualizar o VirtualHost exclusivo da API com os valores acima, sem modificar outros projetos.
5. Verificar `/api/health`, site, `/admin`, autenticação e um envio de arquivo **descartável**, com tamanho superior ao limite antigo mas inferior ao novo, confirmando prévia e remoção segura.
6. Verificar a resposta a arquivo acima do novo limite: o frontend deve barrar e o backend permanece responsável pela rejeição. Não usar dados reais para testes.

**Observações:** uploads HTTP são enviados integralmente (sem retomada automática); conexões lentas podem atingir timeout. O upload de vídeos de 200 MiB requer espaço temporário suficiente no PHP e no diretório de mídia, além de folga para backups. Backup local não é cópia externa nem teste de restauração.
