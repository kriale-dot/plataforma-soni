# SONI — Entrega de resultados por e-mail

A funcionalidade está na PR #4. Antes de publicar:

1. Conferir os três jobs da pipeline GitHub Actions (frontend, api, database).
2. Fazer deploy do código somente após aprovação e merge; nunca executar os testes HTTP destrutivos no banco de produção.
3. No Brevo, autenticar o domínio e cadastrar o remetente. A SONI utiliza HTTPS e a API transacional do Brevo; não utiliza sendmail.
4. Configurar **somente no servidor** BREVO_API_KEY (chave API v3, não SMTP) e SONI_MAIL_FROM em api/.env. Nunca publicar as credenciais no Git.
5. Executar `cd /var/www/somdaigreja.com.br/api && php bin/migrate.php` após backup e revisão da migração 006.
6. Validar com um teste real, usando endereço de destino autorizado, e verificar o PDF anexado, caracteres acentuados, pontuação e devolutiva.
7. Conferir o log transacional no Brevo; HTTP 201 indica aceite, não entrega final na caixa postal.

## Comportamento
- Uma solicitação por tentativa, com token assinado de duas horas.
- O PDF inclui classificação e devolutiva congelada no fechamento da tentativa.
- A solicitação não altera a nota ou as respostas.
- O PDF é gerado em memória; não fica disponível em URL pública.
- Não há envio automático: o avaliado precisa fornecer seu e-mail e clicar no botão.
- Em caso de falha local no `mail()`, é permitida uma nova solicitação.

## Pendências antes de produção
- Teste de entrega real pela API do Brevo.
- Revisão de taxa de envios, reputação e proteção contra abuso; a limitação de uma solicitação por tentativa não impede múltiplas tentativas.
- Conferir apresentação gráfica do relatório em diferentes visualizadores PDF.
