# SONI — Contexto permanente

**Atualizado:** 2026-10-15 · **Repositório:** https://github.com/kriale-dot/plataforma-soni · **Paleta escolhida:** Paleta 1 — Harmonia Técnica.

## Definições confirmadas
- Projeto: Plataforma SONI, dedicada à sonorização de igrejas.
- Site institucional público **totalmente editável pelo administrador via editor de blocos**, tomando como referência o site institucional de `kriale-dot/mapa-percepcao-relacional` (plataforma Tânia).
- O CMS institucional é requisito obrigatório, não funcionalidade opcional: adicionar, alterar, ocultar/mostrar, excluir e reordenar blocos; editar configurações do site; gerenciar imagens, vídeos, áudio, links e CTAs; visualização pública dinâmica.
- Na SONI haverá **um site institucional global** e **uma conta de administrador configurada no `api/.env`** (sem vínculo a perfil profissional da Tânia).
- Área pública "Teste seus Conhecimentos".
- Enunciados e opções podem combinar **texto, imagem e áudio**.
- Biblioteca pública de documentos em **PDF ou PNG**.
- Biblioteca de vídeos por **YouTube ou upload**.
- **Frontend:** React + Vite. **Backend/API:** PHP 8.2+ com Slim 4. **Banco local:** MariaDB 11.4, compatível com o SQL atual.
- Administrador com credenciais armazenadas no **api/.env**, não em banco de dados; senha somente como hash.
- O contexto e andamento são registrados neste repositório.
- Consultar outros chats apenas se forem relevantes ao desenvolvimento.

## Identidade visual OFICIAL — Harmonia Técnica
| Papel | Cor | Código |
| --- | --- | --- |
| Primária | Azul petróleo | #153B4A |
| Secundária | Turquesa | #2FA89C |
| Base suave | Areia | #F3EBDD |
| Destaque | Âmbar | #D8A24A |
| Texto | Grafite | #27333A |

A seleção é definitiva até nova solicitação explícita. Remover o seletor experimental de paletas das páginas finais.

## Diretrizes de implementação
- React/Vite e Slim 4, proposta de banco MySQL 8.
- Token JWT com validade, hash da senha e segredo gerenciado fora do repositório.
- Arquivos de upload com inspeção MIME e armazenamento seguro fora do webroot.
- Backup de MariaDB e pasta api/storage/site_media juntos, sem publicar backups; verificação SHA-256. Exclusão de órfãos somente após backup recente e usando quarentena.
- Não revelar respostas corretas ao visitante antes da conclusão da tentativa.
- Testes devem ser interativos; não são PDFs preenchíveis.
- Na v0.7, todas as questões são de única escolha (2 a 6 alternativas, uma correta); não revelar o gabarito antes do POST de finalização da tentativa; nunca usar este endpoint de respostas para relatório administrativo sem autorização.
- Design responsivo, acessível por teclado, textos alternativos e controles de reprodução de áudio.

## Planejamento técnico do editor institucional
- Replicar o **conceito**, e não copiar cegamente os componentes/dados da Tânia: adaptar blocos `TESTES`, `DOCUMENTOS`, `VIDEOS` e `HERO` à SONI.
- Conteúdo do site editável e persistido no MySQL; credenciais do administrador permanecem exclusivamente no `api/.env`.
- Layout, estilos e paleta oficial continuam controlados pelo React/CSS, não pelo editor de conteúdo.
- Ler `docs/SONI_CMS_INSTITUCIONAL.md` para arquitetura, contratos de API e critérios de aceite.

## Pendências funcionais
- CMS textual v0.4 implementado e CI aprovado: login, painel, conteúdo do site editável e persistente em MariaDB. Editor v0.4 validado localmente. Upload de imagem/áudio/vídeo da v0.5 validado localmente pelo responsável; implementação da v0.6 com backup e auditoria de mídias implementados, aguardando validação Windows; aplicação de questionários ainda pendente.
- Definir política de tentativas, pontuação, relatórios e publicação.
- Definir limites/licenciamento de documentos e vídeos e configuração de produção.

## Regra obrigatória de continuidade
1. Ler este arquivo, SONI_STATUS.md, SONI_VERSIONS.md e SONI_BACKLOG.md antes de programar.
2. Não confundir código testado em CI com aceitação final no Windows: o CMS textual/dinâmico foi implementado na v0.4.0, e a v0.5.0 adicionou upload e prévia do bloco (imagem/áudio/vídeo confirmados pelo responsável no Windows).
3. Ao concluir qualquer alteração, atualizar status e histórico de versões e informar próxima etapa.
4. Registrar decisões novas neste documento; nunca versionar api/.env, tokens ou senhas.

## Devolutivas educacionais — decisão 2026-10-15
- Devolutivas diferentes conforme percentual, editáveis individualmente em cada teste, com classificação, mensagem e recomendação opcional.
- Faixas precisam cobrir 0,00–100,00% sem lacunas/sobreposições; resposta exibida só após finalizar; snapshot de texto é preservado em quiz_attempt_feedback.
- Implementação no código v0.7.1; pendente validação local.

## Documentos — v0.8.0 (2026-10-15)
- Biblioteca PDF/PNG com categorias e busca, publicação/rascunho, frontend /admin/documents e /documentos.
- Mídias na mesma pasta privada, com novo enum DOCUMENT para PDF e tipo IMAGE para PNG, associadas ao cadastro em documents.
- Alterações mantêm CMS e quizzes funcionais; no Windows a migração 004 é necessária e ainda não confirmada.

## Vídeos — v0.9.0 (2026-10-15)
- Biblioteca /videos e /admin/videos, categorias, busca, origem YouTube por ID verificado ou uploads MP4/WebM pela biblioteca de mídia existente.
- Banco migration 005; armazenagem segura e acesso público somente quando publicado/visível; auditoria protege vídeos em rascunho.
- Repositório estava sincronizado no commit 0841ebe antes da v0.9, preservando novo logo em frontend/src/assets/soni-logo.png e App.jsx.

## Marco de produto — congelamento do MVP (2026-10-15)
- O responsável informou que os testes passaram e aprovou considerar a SONI 0.9.0 como MVP funcional oficial.
- A etapa seguinte é preparar v1.0.0 para produção, com escopo de funcionalidades congelado.
- Documentos de referência: docs/SONI_MVP_ESCOPO.md e docs/SONI_CHECKLIST_LANCAMENTO.md. Não iniciar novas funcionalidades opcionais até homologação da v1.0.0.
- Aprovação funcional local não substitui HTTPS, security hardening, rate limiting, backup off-site, restauração e monitoramento.

## Infraestrutura de produção escolhida (2026-10-16)
- Usuário confirmou que a SONI será implantada no Droplet já existente da DigitalOcean com Ubuntu 24.04 LTS.
- O servidor hospeda outros serviços: isolar SONI por VirtualHost e banco, sem modificar outras aplicações; nenhum deploy foi executado ainda.
- Domínio, Apache, PHP-FPM, banco, DNS, TLS, espaço e atualizações precisam ser inspecionados; primeiro passo é diagnóstico somente leitura.
- Guia: docs/SONI_DEPLOY_UBUNTU_DIGITALOCEAN.md.
