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
- Não revelar respostas corretas ao visitante antes da conclusão da tentativa.
- Testes devem ser interativos; não são PDFs preenchíveis.
- Design responsivo, acessível por teclado, textos alternativos e controles de reprodução de áudio.

## Planejamento técnico do editor institucional
- Replicar o **conceito**, e não copiar cegamente os componentes/dados da Tânia: adaptar blocos `TESTES`, `DOCUMENTOS`, `VIDEOS` e `HERO` à SONI.
- Conteúdo do site editável e persistido no MySQL; credenciais do administrador permanecem exclusivamente no `api/.env`.
- Layout, estilos e paleta oficial continuam controlados pelo React/CSS, não pelo editor de conteúdo.
- Ler `docs/SONI_CMS_INSTITUCIONAL.md` para arquitetura, contratos de API e critérios de aceite.

## Pendências funcionais
- CMS textual v0.4 implementado e CI aprovado: login, painel, conteúdo do site editável e persistente em MariaDB. Editor v0.4 validado localmente. Upload e prévia de blocos implementados na v0.5 (pendente validação local), aplicação de questionários ainda pendente.
- Definir política de tentativas, pontuação, relatórios e publicação.
- Definir limites/licenciamento de documentos e vídeos e configuração de produção.

## Regra obrigatória de continuidade
1. Ler este arquivo, SONI_STATUS.md, SONI_VERSIONS.md e SONI_BACKLOG.md antes de programar.
2. Não confundir código testado em CI com aceitação final no Windows: o CMS textual/dinâmico foi implementado na v0.4.0, e a v0.5.0 adicionou upload e prévia do bloco (pendente validação local).
3. Ao concluir qualquer alteração, atualizar status e histórico de versões e informar próxima etapa.
4. Registrar decisões novas neste documento; nunca versionar api/.env, tokens ou senhas.
