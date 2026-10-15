# SONI — Requisitos funcionais (versão inicial)

## Atores
**Visitante:** lê site, estuda documentos/vídeos e responde testes publicados sem precisar de acesso administrativo.
**Administrador:** login protegido; gerencia conteúdos, provas, mídias e configurações.

## Site institucional editável (requisito confirmado)
A página institucional deve ser **montada e editada no painel administrativo**, seguindo a experiência da plataforma Tânia (`mapa-percepcao-relacional`), sem necessidade de alterações no código para mudar conteúdo ou ordem de apresentação.

**Editor por blocos:** incluir, editar, salvar, excluir, reordenar (subir/descer) e alternar visibilidade/publicação de seções. Configurar título do site, textos, subtítulos, descrições, imagens, textos alternativos, áudio, vídeo, botões, URLs e chamadas para ação.

**Tipos de blocos na SONI:** HERO (capa), TITULO, TEXTO, IMAGEM, TEXTO_IMAGEM, VIDEO, AUDIO, CTA, LINK, TESTES, DOCUMENTOS e VIDEOS. Blocos de TESTES/DOCUMENTOS/VIDEOS apontam para os catálogos da plataforma, evitando duplicar informações.

**Dinâmica:** a página pública React busca as configurações e a sequência de blocos publicados na API Slim; somente blocos visíveis/publicados aparecem aos visitantes. Alterações são persistidas no MySQL e passam a aparecer sem alterações de código/deploy, após publicação.

**Identidade visual:** editor não modifica a paleta Harmonia Técnica aprovada; edição de conteúdo independente do layout e da autenticação. Upload de mídias passa por validação de formato/tamanho, armazenamento seguro e controle de acesso.

**Experiência administrativa:** item `Site institucional` no painel, pré-visualização, estados de rascunho/publicado e mensagens claras de sucesso/erro.

Requisitos de segurança e arquitetura detalhados em `docs/SONI_CMS_INSTITUCIONAL.md`.

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
