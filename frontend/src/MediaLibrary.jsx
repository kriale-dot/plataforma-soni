import { useCallback, useEffect, useState } from 'react';
import { apiRequest } from './lib/api.js';
import './media-library.css';

const labels = {
  IMAGE: 'Imagem', AUDIO: 'Áudio', VIDEO: 'Vídeo', DOCUMENT: 'Documento',
};
const descriptions = {
  blocos: 'blocos do site', logotipo: 'logotipo', questoes: 'questões',
  alternativas: 'alternativas', documentos: 'documentos', videos: 'vídeos',
};
const formatBytes = (bytes) => {
  const value = Number(bytes) || 0;
  return value >= 1048576 ? (value / 1048576).toFixed(2) + ' MB' : (value / 1024).toFixed(1) + ' KB';
};

export default function MediaLibrary({ token, onUnauthorized }) {
  const [data, setData] = useState(null);
  const [page, setPage] = useState(0);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');
  const [target, setTarget] = useState(null);
  const [confirmation, setConfirmation] = useState('');
  const limit = 50;

  const refresh = useCallback(async (offset) => {
    try {
      const response = await apiRequest('/admin/media?limit=' + limit + '&offset=' + offset, { token });
      setData(response);
      setPage(offset);
      setError('');
    } catch (err) {
      setError(err.message || 'Não foi possível consultar a biblioteca.');
      if (err.status === 401) onUnauthorized();
    }
  }, [token, onUnauthorized]);

  useEffect(() => { refresh(0); }, [refresh]);

  function openDelete(item) {
    if (busy) return;
    setTarget(item);
    setConfirmation('');
    setNotice('');
    setError('');
  }

  async function remove() {
    if (!target || confirmation !== 'EXCLUIR' || busy) return;
    setBusy(true);
    setError('');
    setNotice('');
    try {
      const result = await apiRequest('/admin/media/' + target.id, {
        method: 'DELETE', token, body: { confirmation: 'EXCLUIR' },
      });
      setTarget(null);
      setConfirmation('');
      const outcome = [
        result.blocks_hidden + ' bloco(s) ocultado(s)',
        result.quizzes_unpublished + ' teste(s) retirado(s) da publicação',
        result.documents_removed + ' documento(s) removido(s)',
        result.videos_removed + ' vídeo(s) removido(s)',
      ].join(' · ');
      setNotice(result.physical_deleted
        ? 'Arquivo apagado do servidor (' + formatBytes(result.bytes) + '). ' + outcome + '.'
        : 'Cadastro excluído, mas o arquivo não pôde ser apagado do disco; verifique a limpeza pendente com suporte. ' + outcome + '.');
      await refresh(page);
    } catch (err) {
      setError(err.message || 'Não foi possível excluir a mídia.');
      if (err.status === 401) onUnauthorized();
    } finally {
      setBusy(false);
    }
  }

  const items = data?.items ?? [];
  return <section className="soniMediaLibrary" aria-label="Biblioteca de mídias">
    <div className="soniMediaIntro">
      <h2>Arquivos de mídia</h2>
      <p>Gerencie os arquivos realmente armazenados no servidor, inclusive os utilizados em testes, documentos, vídeos e blocos do site.</p>
      <p className="soniMediaWarning">Excluir um arquivo em uso retira conteúdos dependentes da publicação. A exclusão é definitiva e não pode ser desfeita. Os resultados anteriores dos testes são mantidos.</p>
    </div>
    {error && <p role="alert" className="soniMediaError">{error}</p>}
    {notice && <p role="status" className="soniMediaNotice">{notice}</p>}
    {!data && !error && <p>Carregando arquivos...</p>}
    {data && <>
      <p className="soniMediaCount">{data.total} arquivo(s) no cadastro · {formatBytes(data.bytes_total)} armazenados · exibindo {items.length} nesta página</p>
      <div className="soniMediaList">
        {items.map((item) => <article className="soniMediaItem" key={item.id}>
          <div className="soniMediaInfo">
            <strong>{item.original_name}</strong>
            <small>#{item.id} · {labels[item.kind] || item.kind} · {formatBytes(item.size_bytes)}</small>
            <small>{Number(item.used_count) === 0 ? 'Sem vínculos (não utilizado)' :
              'Em uso: ' + Object.entries(item.usage).filter(([, n]) => Number(n) > 0)
                .map(([name, n]) => n + ' ' + (descriptions[name] || name)).join(', ')}</small>
          </div>
          <button type="button" disabled={busy} className="soniMediaDelete" onClick={() => openDelete(item)}>Excluir arquivo</button>
        </article>)}
        {items.length === 0 && <p>Nenhum arquivo cadastrado nesta página.</p>}
      </div>
      <div className="soniMediaPagination">
        <button type="button" disabled={busy || page === 0} onClick={() => refresh(Math.max(0, page - limit))}>← Anterior</button>
        <span>{data.total ? Math.floor(page / limit) + 1 : 0} / {Math.max(1, Math.ceil(data.total / limit))}</span>
        <button type="button" disabled={busy || page + limit >= data.total} onClick={() => refresh(page + limit)}>Próxima →</button>
      </div>
    </>}

    {target && <div className="soniMediaOverlay" role="presentation">
      <section role="dialog" aria-modal="true" aria-labelledby="soniMediaDeleteTitle" className="soniMediaDialog">
        <h3 id="soniMediaDeleteTitle">Excluir arquivo definitivamente?</h3>
        <p><strong>{target.original_name}</strong> · {formatBytes(target.size_bytes)}</p>
        <p>{target.used_count ? 'Este arquivo está em uso. A exclusão afetará os conteúdos abaixo:' : 'Este arquivo não possui vínculos registrados.'}</p>
        {target.used_count > 0 && <ul>{Object.entries(target.usage).filter(([, n]) => Number(n) > 0).map(([key, n]) =>
          <li key={key}>{n} {descriptions[key] || key}</li>)}</ul>}
        <p className="soniMediaWarning">Documentos e vídeos associados serão removidos do catálogo; blocos do site serão ocultados; testes associados voltarão a rascunho. As notas anteriores permanecem, mas testes já respondidos não podem ter questões alteradas sem nova versão.</p>
        <label className="soniMediaConfirm">Digite <strong>EXCLUIR</strong> para confirmar:
          <input autoFocus value={confirmation} onChange={(event) => setConfirmation(event.target.value)} autoComplete="off" />
        </label>
        <div className="soniMediaDialogActions">
          <button type="button" disabled={busy} onClick={() => setTarget(null)}>Cancelar</button>
          <button type="button" className="soniMediaDelete" disabled={busy || confirmation !== 'EXCLUIR'} onClick={remove}>{busy ? 'Excluindo...' : 'Excluir definitivamente'}</button>
        </div>
      </section>
    </div>}
  </section>;
}
