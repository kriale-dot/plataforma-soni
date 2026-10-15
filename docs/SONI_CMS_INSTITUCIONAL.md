# SONI — CMS do site institucional editável

**Decisão confirmada em 2026-10-15. Status: esquema MariaDB, migration e endpoint público criados na v0.3.0; editor e renderização dinâmica ainda pendentes.**
**Referência analisada:** [mapa-percepcao-relacional](https://github.com/kriale-dot/mapa-percepcao-relacional), incluindo `ProfessionalSiteEditor.jsx`, `PublicInstitutionalSite.jsx`, `SiteController.php` e migration `009_site_institucional.sql`.

## Objetivo

O administrador deverá criar e alterar o conteúdo do site institucional da Plataforma SONI sem editar arquivos React, CSS ou PHP. Usar **editor por blocos**, inspirado na plataforma Tânia, adaptado à SONI e mantendo a paleta oficial Harmonia Técnica.

A página React ainda exibe conteúdo demonstrativo **estático**; a v0.3.0 já contém a fundação SQL e a rota pública `GET /api/site`, mas o editor CMS ainda será implementado.

## Funcionalidades do editor

1. Listar todas as seções na ordem exibida no site público.
2. Adicionar, editar, duplicar (opcional), excluir e reordenar blocos (botões subir/descer; drag-and-drop opcional).
3. Marcar bloco como visível/oculto e gerenciar rascunho/publicação. Apenas publicados e visíveis aparecem no site público.
4. Editar título da aba, descrição geral, identificação/branding, contatos e links.
5. Editar por bloco: título, subtítulo, corpo, texto alternativo, mídias, descrição, link/legenda do botão, ordem e visibilidade.
6. Upload de imagem; áudio/vídeo por upload autorizado ou URL validada (YouTube para vídeos). Evitar links não confiáveis e execução de HTML arbitrário.
7. Pré-visualizar alterações e navegar para o site publicado.
8. Mensagens de sucesso, erros de validação e confirmação antes de excluir.
9. Criar automaticamente conjunto inicial de seções baseado na página demonstrativa atual, para não iniciar com site em branco.

## Tipos de bloco propostos (SONI)

| Tipo | Uso |
| --- | --- |
| HERO | Área principal: chamada, descrição, imagem, botões |
| TITULO | Divisão de conteúdo/títulos de seção |
| TEXTO | Parágrafos e apresentação institucional |
| IMAGEM | Logomarca, fotos de equipamentos e ilustrações |
| TEXTO_IMAGEM | Seção com texto e imagem lado a lado |
| VIDEO | Reprodução de YouTube ou vídeo enviado |
| AUDIO | Reprodução de áudio educativo |
| CTA | Chamada para curso, testes, material ou contato |
| LINK | Botão/link configurável |
| TESTES | Lista ou destaque de testes publicados |
| DOCUMENTOS | Destaques/atalho para biblioteca PDF/PNG |
| VIDEOS | Destaques/atalho para biblioteca de vídeos |

Não replicar tipos `PERFIL`, `APRESENTACAO` ou `AVALIACAO` ligados a dados de profissionais da plataforma Tânia: a SONI terá um único site institucional global com blocos próprios.

## Persistência MySQL proposta

`site_settings` (registro global)
- id, title, description, logo_media_id (opcional), contact_email, contact_phone, updated_at.
- SEO: meta_title e meta_description (campos opcionais); não permitir strings de script/HTML arbitrário.

`site_blocks`
- id, type (whitelist de tipos permitidos), title, subtitle, body_text, media_id (opcional), media_url (opcional), alt_text, button_label, button_url, sort_order, is_visible, publication_status (DRAFT/PUBLISHED), created_at, updated_at.
- Índice: publication_status + is_visible + sort_order + id.
- Blocos específicos podem ter configurações JSON de apresentação **validadas por schema**; não usar JSON para identidade de URL não segura ou código executável.

`site_media`
- id, type (IMAGE/AUDIO/VIDEO), storage_path, mime_type, size_bytes, original_name (metadados), created_at.
- Conteúdo enviado sob armazenamento dedicado, com caminhos relativos controlados e nomes gerados pelo servidor. Configurar exposição de recursos públicos autorizados, sem servir diretamente arquivos fora do webroot.

Migração inicial formalizada em `api/migrations/001_site_institucional.sql`, com seed de 5 blocos. Executar somente após criar o banco isolado `plataforma_soni`, conforme `docs/SONI_DATABASE_SETUP.md`. A criação local ainda precisa ser validada.

## Rotas da API previstas

Públicas:
- `GET /api/site` — retorna configurações e somente blocos publicados e visíveis, ordenados.
- `GET /api/site/media/:id` — obter mídia autorizada (se necessária; estratégia de CDN pode substituir).

Privadas — exigem token válido do administrador:
- `GET /api/admin/site/settings` — configurações do site.
- `PUT /api/admin/site/settings` — atualizar título, metadados e demais opções permitidas.
- `GET /api/admin/site/blocks` — todos os blocos, inclusive ocultos/rascunhos.
- `POST /api/admin/site/blocks` — criar.
- `PUT /api/admin/site/blocks/:id` — salvar edição.
- `DELETE /api/admin/site/blocks/:id` — excluir (confirmação no frontend).
- `PATCH /api/admin/site/blocks/:id/order` — subir/descer ou definir ordem, com transação.
- `PATCH /api/admin/site/blocks/:id/publication` — publicar/retirar/ocultar.
- `POST /api/admin/site/media` — upload autenticado, com limites e inspeção MIME real.

Rotas são propostas; contratos exatos, resposta e padronização serão finalizados na implementação. Nunca expor edição pública.

## Frontend React previsto

- `/`: página institucional dinâmica, renderizando blocos recebidos em `GET /api/site`.
- `/admin`: painel administrativo inicial já existente em v0.2.0.
- `/admin/site`: editor institucional, acessível somente a administradores autenticados.
- Componentes separados por tipo de bloco, com texto alternativo para imagens, controles de áudio/vídeo e layout responsivo.
- A paleta Harmonia Técnica fica fixa no design system e não é editada pelo CMS.
- A navegação para Testes, Documentos e Vídeos deve continuar acessível mesmo que blocos editoriais sejam ocultados.

## Segurança e consistência
- Conta do administrador: `api/.env` com e-mail e hash da senha; nunca persistir segredo em banco ou frontend.
- Os conteúdos do site, configurações e mídias **sim** ficam persistidos no MySQL/armazenamento de mídia.
- Rotas mutáveis exigem autorização do JWT; validar payload, tamanho dos campos, formatos e links.
- Não renderizar HTML fornecido pelo administrador via `dangerouslySetInnerHTML` sem sanitização rigorosa; preferir texto estruturado.
- Evitar XSS em links, javascript:, data: e iframes livres. Player do YouTube via identificador ou URL validada.
- Limitar uploads por MIME real, extensão permitida, tamanho, arquivos poliglotas e caminho; armazenar sem permissões de execução.
- Resposta pública não deve expor rascunhos nem dados administrativos.
- Documentar backup de banco e mídia, e auditoria simples de publicação.

## Critérios de aceite

- Administrador faz login, acessa `/admin/site`, altera título/texto e salva.
- Cria bloco de texto e imagem, ordena blocos e vê ordem refletida na página publicada.
- Oculta bloco; visitante não o vê, administrador continua conseguindo editá-lo.
- Publica alteração e confere site público sem mudar código nem executar novo deploy.
- Upload inválido é rejeitado; imagem aceita aparece na prévia e na página.
- Visitante não consegue acessar rotas de escrita.
- Carregar o site após reiniciar API conserva os conteúdos, pois estão no MySQL.

## Implementação em fases

1. Estrutura MySQL, migração e seed do site demonstrativo atual.
2. Endpoint público de leitura e componentes React dinâmicos (sem quebrar a homepage).
3. CRUD privado na API com controle de publicação e reordenação.
4. Editor React em `/admin/site`.
5. Upload/validação de mídia e reprodução; preview e testes E2E.
6. Documentar operações de publicação, rollback e recuperação.

**Não marcar CMS concluído antes dos critérios de aceite terem sido verificados.**
