# SONI — Requisitos funcionais (versão inicial)

## Atores
**Visitante:** lê site, estuda documentos/vídeos e responde testes publicados sem precisar de acesso administrativo.
**Administrador:** login protegido; gerencia conteúdos, provas, mídias e configurações.

## Site institucional
Página inicial, apresentação do projeto, navegação para Testes, Documentos e Vídeos. Responsivo e acessível. Textos podem tornar-se editáveis pelo painel.

## Teste seus Conhecimentos
- Testes compostos por perguntas ordenadas.
- Enunciados com texto, imagem e/ou áudio; alternativas com texto, imagem e/ou áudio.
- Seleção única como tipo inicial; múltipla seleção prevista para validação.
- Pontuação, tentativas e feedback definidos posteriormente.
- Gabarito guardado no backend; nunca exposto no payload público antes da conclusão.
- Áudio com controles acessíveis e conteúdo textual alternativo quando cabível.

## Biblioteca de documentos
PDF/PNG; título, descrição, categoria, publicação e opção de download/visualização. Upload somente para administrador, com validação estrita de conteúdo.

## Biblioteca de vídeos
Link de YouTube validado ou arquivo de vídeo carregado pelo administrador. Player responsivo, título, descrição e publicação. Armazenamento e limites configuráveis.

## Segurança
Credenciais administrativas no api/.env: ADMIN_EMAIL e ADMIN_PASSWORD_HASH; JWT_SECRET separado. Senhas reais nunca versionadas. HTTPS, CORS, limitação de login, limpeza/validação de upload e backups necessários antes de produção.

## Tecnologias
React + Vite; PHP 8.2+ + Slim Framework 4; MySQL 8 inicialmente planejado.

## Questões a definir
Avaliação anônima? Tentativas múltiplas? Histórico? Cronômetro? Percentual mínimo de aprovação? Feedback imediato ou final? Padrões de áudio e vídeo? Termos/licenças?
