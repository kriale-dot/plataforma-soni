# Plataforma SONI — Encerramento operacional do deploy

**Data da decisão:** 16/10/2026  
**Responsável:** proprietário do projeto, conforme solicitação nesta conversa  
**Versão do produto:** MVP 0.9.0 (escopo congelado)  
**Situação:** **implantação operacional encerrada por solicitação do responsável; plataforma em funcionamento por HTTPS**. Release formal 1.0.0 não homologada.

## Ambiente publicado
| Componente | URL | Servidor / diretório |
| --- | --- | --- |
| Site institucional e páginas públicas | https://somdaigreja.com.br / https://www.somdaigreja.com.br | Apache, `/var/www/somdaigreja.com.br/frontend/dist` |
| Painel administrativo | https://somdaigreja.com.br/admin | Mesma aplicação React |
| API PHP Slim 4 | https://api.somdaigreja.com.br | Apache/mod_php, `/var/www/somdaigreja.com.br/api/public` |
| Banco | MySQL 8.0.46, banco `plataforma_soni`, usuário dedicado `soni_app` | Droplet DigitalOcean existente; outros sistemas devem ser preservados |
| Mídias privadas | Diretório interno | `/var/www/somdaigreja.com.br/api/storage/site_media` |

**URLs:** `/testes`, `/documentos`, `/videos` e `/admin` no frontend; API base `/api` no subdomínio `api.somdaigreja.com.br`.

**Configuração:** `api/.env` fora do DocumentRoot, verificado como `640 root:www-data`; CORS restrito a raiz/www; PHP do VirtualHost HTTPS da API com upload de 40 MB e POST de 48 MB. Certificados existentes reutilizados; as datas informadas durante o deploy foram 27/12/2026 (site/www) e 08/12/2026 (API), sujeitos à renovação do Certbot. Não publicar conteúdo privado de `.env`, certificados ou backups no repositório.

## Verificações efetivamente confirmadas pelo responsável
1. Migrações 001–005, conectividade ao MySQL, Apache e HTTPS: aprovadas na instalação.
2. Comunicação frontend/API após correção das URLs no `.env`: aprovada.
3. Upload e prévia de imagem acima de 2 MB: aprovados; pasta de mídias gravável por `www-data`, sem liberar escrita sobre `api/storage` inteiro.
4. PDF e PNG: upload, publicação, visualização e ocultação: aprovados.
5. YouTube e MP4/WebM: pré-visualização, upload para arquivo, publicação, reprodução e ocultação: aprovados.
6. `GET /api/admin/site`, `GET /api/admin/videos`, `GET /api/admin/documents`: HTTP 401 sem login.
7. Frontend `/`, `/testes`, `/documentos`, `/videos`, `/admin`: HTTP 200. API `/api/health`, `/api/site`, `/api/quizzes`, `/api/documents`, `/api/videos`: HTTP 200.
8. Backup local SQL + mídia criado e verificado: `api/storage/backups/20261016-110803-d28cea70`. O diretório não representa backup externo.
9. CI React, PHP e MariaDB do ajuste de compatibilidade para dump MySQL 8 aprovado: GitHub Actions `38891500536`.

## Não testado ou expressamente adiado
- **Sem confirmação de cópia externa de dados em produção:** arquivos no GitHub/Google Drive não garantem a restauração do MySQL e de mídias criadas por upload. Ensaio de restauração isolada também não foi feito.
- **Rate limiting** de login administrativo e tentativas públicas: adiado por decisão do responsável; risco residual de força bruta/abuso.
- **Testes E2E dos quizzes** em produção: página e API retornaram 200, mas uma tentativa completa com respostas diferentes, pontuação e devolutiva não foi executada/confirmada nesta fase.
- Monitoramento/alertas e demais revisões de segurança, privacidade, respostas 404 para arquivos não publicados e outros endpoints não homologados integralmente.
- `/.env` via GET retornou HTTP 405: conteúdo não foi retornado nesse teste; motivo do status não foi investigado em profundidade. O arquivo deve permanecer fora do DocumentRoot.
- Commit SHA exato atualmente instalado no Droplet **não foi informado**. A documentação do GitHub pode avançar além do checkout no servidor.

## Decisão de encerramento
O responsável declarou em 16/10/2026: «Vamos finalizar aqui mesmo o deploy e atualizar o github. Se futuramente surgir algum problema eu te aviso.»

A conclusão acima significa **fim das etapas operacionais de implantação solicitadas**; não significa aprovação técnica das pendências P0, nem atribuição da tag v1.0.0. A plataforma permanece acessível por HTTPS, com riscos documentados no [checklist da release](SONI_CHECKLIST_LANCAMENTO.md). Não executar alterações no Droplet em decorrência deste documento.

## Como retomar diante de um problema
Informar: URL afetada, data/hora aproximada, o que estava tentando fazer, mensagem de erro visível e passos mínimos para reproduzir. Ao compartilhar saídas de console/log, remover senhas, JWTs, `.env` e dados pessoais. Consultar [status](SONI_STATUS.md), [contexto](SONI_CONTEXT.md) e este documento antes de agir. Preservar os demais sites hospedados no Droplet.
