# SONI — Termo de congelamento do MVP v0.9.0

**Decisão:** APROVADO como MVP funcional em 2026-10-15, pelo responsável pelo projeto.
**Referência técnica do código aprovado no CI:** commit `4c3964ee319f77f366349c5238d005c334d5b414`, execução `38817558203` (Frontend React, API PHP e MariaDB 11.4).
**Ambiente:** desenvolvimento local em Windows. **Não significa que o sistema esteja implantado ou liberado para usuários externos.**

## 1. Objetivo e público

A SONI reúne materiais e avaliação de conhecimentos de sonorização de igrejas para operadores de áudio, voluntários, líderes e equipes. O MVP deve permitir que um administrador publique conteúdo e que um visitante encontre, consulte e responda às avaliações, sem exigir novos módulos.

## 2. Escopo funcional congelado

| Área | Funcionalidade disponível no MVP |
| --- | --- |
| Site institucional | Editor administrativo de configurações e blocos, publicação e visibilidade, mídias e navegação |
| Administração | Autenticação administrativa JWT, controle de acesso às rotas de edição |
| Testes | Categorias, questões com texto/imagem/áudio, alternativas, publicação, tentativas e correção no servidor |
| Devolutivas | Faixas de percentual, classificação, mensagens e recomendações, sem alterar resultado histórico |
| Documentos | Categorias, upload de PDF/PNG, pesquisa, leitura, rascunho, publicação e ocultação |
| Vídeos | Categorias, YouTube por URL HTTPS, upload MP4/WebM, busca, player, rascunho, publicação e ocultação |
| Manutenção | Migrações SQL, backup SQL+mídias com manifesto SHA-256, auditoria conservadora |

Migrações locais `001`–`005` aplicadas; o responsável confirmou genericamente que os testes da biblioteca de vídeos passaram. Isso não certifica individualmente todos os cenários de navegação ou segurança.

## 3. Política de congelamento

A partir desta decisão, **não serão incluídos módulos ou recursos opcionais** até a avaliação de lançamento da v1.0.0.

São admitidos na linha do MVP:
- correções de defeitos com reprodução documentada;
- correções de segurança e privacidade;
- ajustes de configuração, deploy, backups, restauração, monitoramento e acessibilidade indispensável;
- ajustes de conteúdo, documentação e experiência necessários aos usuários-piloto;
- testes automatizados de regressão, sem modificar o comportamento esperado.

Toda mudança deve registrar motivo, risco, testes e impacto nas migrações. Evitar alterações destrutivas ou exclusão de dados. Novas funcionalidades e melhorias não essenciais vão ao backlog pós-MVP.

## 4. O que fica fora do MVP

Sem compromisso de desenvolvimento até após a versão 1.0.0: aplicativo mobile nativo; pagamentos/assinaturas; perfis de alunos e trilhas com progresso autenticado; certificados; gamificação; chat/comunidade; streaming profissional e transcodificação; uploads de vídeo grandes/segmentados; paginação avançada; dashboards analíticos extensos.

Algumas melhorias podem ser reavaliadas se uma falha impedir o uso seguro em produção. Isso não significa implementar todo o backlog antes do lançamento.

## 5. Situação da qualidade

- **Concluído:** execução automática de PHP, React, migrações e testes HTTP no MariaDB; migração 005 aplicada localmente; testes funcionais locais da biblioteca de vídeos informados pelo responsável.
- **A validar com evidência:** documentos PDF/PNG no navegador, fluxos de erro e proteção em produção, ensaio de restauração isolada, cópia off-site, HTTPS, bloqueio de abuso, dispositivos móveis/acessibilidade, disponibilidade e primeiros usuários.
- **Status oficial:** `MVP_FUNCIONAL_CONGELADO`, **não** `PRONTO_PARA_PRODUCAO`.

## 6. Critério para a v1.0.0

A versão 1.0.0 somente será anunciada/publicada após todos os itens **P0 (bloqueadores)** de `SONI_CHECKLIST_LANCAMENTO.md` terem evidência verificável e após um teste de ponta a ponta no endereço público com usuários de teste, incluindo recuperação a partir de backup.

A confirmação de testes automatizados ou da migração local, isoladamente, não libera produção.

## 7. Identificação da decisão

Data: 2026-10-15. Aceite explícito do responsável após proposta de congelar a v0.9.0 e preparar a implantação sem novos módulos.

**Próxima fase:** Preparação operacional e segurança para a SONI v1.0.0.
