# SONI — Biblioteca de Documentos (v0.8.0)

## Funcionalidades
- Admin autenticado: categorias, título, descrição, upload PDF/PNG, prévia privada, rascunho, publicação, visibilidade e exclusão segura de cadastro.
- Biblioteca pública em \`/documentos\`, filtros por título, descrição e categoria; visualização em \`/documentos/{id}\`.
- API: \`GET /api/documents?q=termo&category=id\`, \`GET /api/documents/{id}\`.
- API JWT: \`GET /api/admin/documents\`, \`POST /api/admin/document-categories\`,
  \`PUT /api/admin/document-categories/{id}\`, \`POST /api/admin/documents\`,
  \`PUT /api/admin/documents/{id}\`, \`DELETE /api/admin/documents/{id}\`.
- Mídia: envio ao endpoint JWT de mídia já existente (\`POST /api/admin/site/media\`).
  PDF utiliza tipo \`DOCUMENT\`, limite 20 MiB, MIME \`application/pdf\`, verificação de cabeçalho.
  PNG utiliza tipo \`IMAGE\`, limite 6 MiB e verificação de dimensões.
- Arquivos ficam fora do webroot e a URL pública é retornada por PHP somente se algum documento associado estiver publicado e visível.
- A exclusão apaga apenas o registro do catálogo. O arquivo original é mantido para recuperação ou futura auditoria.

## Uso local
1. Atualize o Git e confirme backup em dispositivo seguro.
2. Na pasta \`api\`, aplique \`php bin/migrate.php\`, que cria a migração 004 e mantém o conteúdo anterior.
3. Reinicie PHP e Vite.
4. Abra \`http://localhost:5173/admin\`, clique em **Documentos**.
5. Cadastre uma categoria se desejar; envie um PDF ou PNG; preencha título, categoria e descrição.
6. Clique em **Pré-visualizar** (somente admin); salve como rascunho e depois altere o estado para publicado e visível.
7. Acesse \`http://localhost:5173/documentos\`, pesquise e abra o documento.
8. Volte ao admin, oculte o documento e confira que a rota pública não entrega seu arquivo.

## Limitações
- O catálogo apresenta no máximo 100 registros por consulta; paginação futura.
- PDF usa leitor integrado ao navegador; alguns navegadores exigem abrir em nova guia.
- Não há antivírus nem inspeção profunda dos conteúdos internos do PDF; antes de publicar na internet estabeleça fonte confiável, antivírus e segurança de upload.
- Não foi adicionado agendamento de backup externo ou restauração automatizada.
- Não execute testes CI no banco local. O teste de integração escreve dados descartáveis.
