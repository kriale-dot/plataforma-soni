# SONI — Biblioteca de mídias e exclusão definitiva

## Funcionalidade

A área autenticada **Administração → Arquivos de mídia** (`/admin/media`) lista os arquivos presentes no banco `site_media`, seu tamanho e quantos vínculos existem nos módulos do site. Mostra 50 por página, com navegação. Usa a paleta Harmonia Técnica.

O administrador pode excluir qualquer mídia, **inclusive já usada**, após digitar `EXCLUIR` na caixa de confirmação. A operação é definitiva e remove o arquivo físico do Droplet, liberando espaço quando a remoção física é bem-sucedida.

## O que acontece com os conteúdos ligados ao arquivo

| Local de uso | Consequência |
| --- | --- |
| Blocos do site | O bloco continua no editor, mas fica oculto e sem vínculo de mídia. |
| Logotipo no banco | A referência ao arquivo é retirada. O logotipo estático empacotado no frontend não é afetado. |
| Testes, questões e alternativas | O arquivo é desvinculado; **todos os testes afetados são retirados da publicação**. As respostas e notas anteriores são preservadas. |
| Documentos PDF/PNG | O cadastro do documento que depende do arquivo é excluído da biblioteca. |
| Vídeos MP4/WebM | O cadastro do vídeo que depende do arquivo é excluído da biblioteca. Vídeos do YouTube sem upload não são afetados. |

**Atenção:** se um teste já foi respondido, a regra existente da SONI impede editar as questões do teste. Assim, apagar uma mídia ligada a ele pode exigir criar outro teste se for necessário alterar questões para republicá-lo. O painel avisa antes de confirmar.

## Como a API se protege

- `GET /api/admin/media?limit=50&offset=0` — lista a biblioteca e as referências (JWT obrigatório).
- `DELETE /api/admin/media/{id}` — corpo `{"confirmation":"EXCLUIR"}` (JWT obrigatório).
- A API consulta referências do banco; desvincula ou remove cadastros dependentes em **transação MySQL**, e não modifica outros bancos ou diretórios externos à SONI.
- Somente arquivos com caminhos gerados pelo sistema (`xx/hash.ext`) podem ser excluídos; links simbólicos e caminhos suspeitos são recusados.
- Antes de confirmar o banco, o arquivo físico é movido para a área interna `.pending_delete`; em caso de falha da transação, o arquivo é restaurado.
- Após o commit, o arquivo temporário é apagado. Se a exclusão física falhar, a resposta informa `cleanup_required=true`, e será necessária inspeção manual do diretório `api/storage/site_media/.pending_delete`. Não limpar esse diretório em massa sem identificar arquivos que realmente ficaram pendentes.
- A exclusão é irreversível pelo painel. O sistema não executa remoção em massa nem exclusão automática.

## Implantação e validação

1. No Windows, aplicar `soni-gerenciar-midias.patch` no commit-base indicado no pacote.
2. Conferir `git diff --check`, `php -l api/src/MediaLibrary.php`, `php -l api/public/index.php`, `php -l api/tests/media-delete-http-smoke.php` e `npm run build` em `frontend`.
3. Publicar no GitHub e exigir sucesso das verificações React, PHP e MariaDB no GitHub Actions. O teste HTTP `api/tests/media-delete-http-smoke.php` **cria e destrói dados** e só pode rodar no banco descartável do CI.
4. No Droplet, confirmar Git limpo, `git pull --ff-only`, conferir sintaxe PHP e reconstruir o frontend com `VITE_API_BASE_URL=https://api.somdaigreja.com.br/api npm run build`.
5. Abrir `/admin/media`, visualizar referências e experimentar inicialmente com **arquivo criado exclusivamente para teste**, sem vínculos importantes. Não apagar arquivos reais por tentativa.

Não há novas migrações de banco. O patch não faz alterações no Droplet nem remove arquivos reais automaticamente.
