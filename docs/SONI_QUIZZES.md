# SONI — Módulo Teste seus Conhecimentos (v0.7.0)

## Objetivo
Provas digitais interativas dentro da plataforma, sem PDFs preenchidos. Aluno visitante acessa avaliações publicadas, responde e vê pontuação + explicação **apenas depois de finalizar**.

## Recursos incluídos
- Categorias e testes criados pelo administrador autenticado.
- Questões de escolha única, entre 2 e 6 alternativas, exatamente uma correta.
- Enunciado e alternativas podem conter texto, imagem, áudio ou uma combinação.
- Biblioteca de mídia reaproveita imagens e áudios enviados pelo CMS.
- Estados Rascunho / Publicado. Sem perguntas, opções inválidas ou sem gabarito correto, o teste não pode ser publicado.
- Listagem e aplicação pública, correção no PHP e armazenamento da tentativa no MariaDB.
- Antes da conclusão, endpoints públicos não retornam \`is_correct\` nem \`explanation\`.
- Mídias em testes publicados recebem permissão pública; rascunhos não.
- Questões publicadas precisam ser retiradas de publicação para editar; questões de testes com tentativas registradas ficam imutáveis para preservar resultados.

## Rotas
Públicas:
- \`GET /api/quizzes\` — catálogo somente dos publicados.
- \`GET /api/quizzes/{id}\` — conteúdo sem respostas corretas ou explicações.
- \`POST /api/quizzes/{id}/attempts\` — envia \`{"answers":[{"question_id":1,"option_id":2}]}\`, requer todas as questões; grava resultado e retorna gabarito/explicações.

Privadas com JWT:
- \`GET /api/admin/quizzes\` — categorias e testes.
- \`POST /api/admin/quiz-categories\`, \`PUT /api/admin/quiz-categories/{id}\`.
- \`POST /api/admin/quizzes\`, \`GET /api/admin/quizzes/{id}\`, \`PUT /api/admin/quizzes/{id}\`.
- \`POST /api/admin/quizzes/{id}/questions\`, \`PUT /api/admin/quizzes/{id}/questions/{qid}\`, \`DELETE /api/admin/quizzes/{id}/questions/{qid}\`.

## Implantação local (Windows)
1. Copie o backup v0.6.0 para destino seguro; não compartilhe dumps ou arquivo .env.
2. Na raiz do repositório: \`git pull origin main\`.
3. Na pasta \`api\`: \`php bin/migrate.php\`. A migração 001 já aplicada não será repetida; a 002 será aplicada uma vez.
4. Reinicie os servidores PHP e Vite.
5. Abra \`http://localhost:5173/admin\`, faça login e clique em **Gerenciar testes**.
6. Cadastre um teste na categoria inicial e duas questões, com opções e explicações. Deixe como rascunho durante a edição.
7. Clique em **Publicar teste**; abra \`http://localhost:5173/testes\`.
8. Responda. O gabarito e a explicação devem aparecer apenas após finalizar.
9. Para imagem/áudio, envie arquivos na área de mídia do editor de questões e selecione os IDs na pergunta ou alternativa.

## Limitações e próximos passos
- Sem cadastro de aluno ou perfil de resultados; tentativas são anônimas.
- Ainda não existe rate limit de requisições, prevenção avançada de abuso, relatório gerencial, importador, banco de questões, randomização ou temporização.
- O conteúdo é alterável só enquanto não houver tentativas registradas; depois deve ser criado outro teste para uma nova versão.
- Vídeos continuam gerenciados no site institucional, não nas alternativas.
- Testar a v0.7.0 no Windows e revisar segurança/limites antes de produção.
