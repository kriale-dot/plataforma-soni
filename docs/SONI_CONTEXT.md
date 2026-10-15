# SONI — Contexto permanente

**Atualizado:** 2026-10-15 · **Repositório:** https://github.com/kriale-dot/plataforma-soni · **Paleta escolhida:** Paleta 1 — Harmonia Técnica.

## Definições confirmadas
- Projeto: Plataforma SONI, dedicada à sonorização de igrejas.
- Site institucional público.
- Área pública "Teste seus Conhecimentos".
- Enunciados e opções podem combinar **texto, imagem e áudio**.
- Biblioteca pública de documentos em **PDF ou PNG**.
- Biblioteca de vídeos por **YouTube ou upload**.
- **Frontend:** React + Vite. **Backend/API:** PHP 8.2+ com Slim 4.
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
- Não revelar respostas corretas ao visitante antes da conclusão da tentativa.
- Testes devem ser interativos; não são PDFs preenchíveis.
- Design responsivo, acessível por teclado, textos alternativos e controles de reprodução de áudio.

## Pendências funcionais
- Login web do administrador, painel administrativo, CRUD de conteúdo, uploads, persistência e aplicação de questionários.
- Definir política de tentativas, pontuação, relatórios e publicação.
- Definir limites/licenciamento de documentos e vídeos e configuração de produção.

## Regra obrigatória de continuidade
1. Ler este arquivo, SONI_STATUS.md, SONI_VERSIONS.md e SONI_BACKLOG.md antes de programar.
2. Não confundir propostas/documentação com recursos funcionais já entregues.
3. Ao concluir qualquer alteração, atualizar status e histórico de versões e informar próxima etapa.
4. Registrar decisões novas neste documento; nunca versionar api/.env, tokens ou senhas.
