# SONI — Entrega de resultados por e-mail

A funcionalidade está na PR #4. Antes de publicar:

1. Conferir os três jobs da pipeline GitHub Actions (frontend, api, database).
2. Fazer deploy do código somente após aprovação e merge; nunca executar os testes HTTP destrutivos no banco de produção.
3. No servidor, verificar PHP `mail()` e a rota do MTA local/relay autorizado. Configurar autenticação SPF/DKIM/DMARC para o domínio remetente.
4. Configurar **somente no servidor** `SONI_MAIL_FROM` no arquivo `api/.env`, com endereço de domínio configurado para envio. Não publicar credenciais no Git.
5. Executar `cd /var/www/somdaigreja.com.br/api && php bin/migrate.php` após backup e revisão da migração 006.
6. Validar com um teste real, usando endereço de destino autorizado, e verificar o PDF anexado, caracteres acentuados, pontuação e devolutiva.
7. Conferir logs do MTA para confirmar entrega ou rejeição. `mail()` retornar true indica aceite local, **não** entrega final na caixa postal.

## Comportamento
- Uma solicitação por tentativa, com token assinado de duas horas.
- O PDF inclui classificação e devolutiva congelada no fechamento da tentativa.
- A solicitação não altera a nota ou as respostas.
- O PDF é gerado em memória; não fica disponível em URL pública.
- Não há envio automático: o avaliado precisa fornecer seu e-mail e clicar no botão.
- Em caso de falha local no `mail()`, é permitida uma nova solicitação.

## Pendências antes de produção
- Teste de entrega real pelo MTA em uso.
- Revisão de taxa de envios, reputação e proteção contra abuso; a limitação de uma solicitação por tentativa não impede múltiplas tentativas.
- Conferir apresentação gráfica do relatório em diferentes visualizadores PDF.
