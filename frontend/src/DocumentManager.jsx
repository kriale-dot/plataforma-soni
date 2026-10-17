import { useCallback, useEffect, useState } from 'react';
import { apiRequest, apiUploadFile, apiMediaBlob } from './lib/api.js';
import { UPLOAD_LIMIT_MIB, maxUploadBytes } from './lib/uploadLimits.js';
import './documents.css';

const blank = () => ({
  title: '', description: '', category_id: '', media_id: '',
  publication_status: 'DRAFT', is_visible: 1,
});

function PrivatePreview({ mediaId, token, mime }) {
  const [url, setUrl] = useState('');
  const [error, setError] = useState('');
  useEffect(() => {
    if (!mediaId) { setUrl(''); return; }
    const controller = new AbortController();
    let objectUrl = '';
    apiMediaBlob(mediaId, token, controller.signal)
      .then((blob) => {
        if (controller.signal.aborted) return;
        objectUrl = URL.createObjectURL(blob);
        setUrl(objectUrl);
        setError('');
      })
      .catch((err) => { if (err.name !== 'AbortError') setError(err.message || 'Não foi possível abrir a prévia.'); });
    return () => {
      controller.abort();
      if (objectUrl) URL.revokeObjectURL(objectUrl);
    };
  }, [mediaId, token]);
  if (!mediaId) return null;
  return <div className="docPrivatePreview">
    <strong>Pré-visualização privada do arquivo</strong>
    <p>Esta prévia exige autenticação; o arquivo ainda não é público se o documento for um rascunho.</p>
    {error && <p role="alert">{error}</p>}
    {!url && !error && <p>Carregando prévia...</p>}
    {url && (mime === 'application/pdf'
      ? <iframe title="Prévia privada do PDF" src={url} />
      : <img src={url} alt="Prévia privada do documento PNG"/>)}
    {url && <a href={url} target="_blank" rel="noopener noreferrer">Abrir prévia em nova guia ↗</a>}
  </div>;
}

export default function DocumentManager({ token, onUnauthorized }) {
  const [data, setData] = useState(null);
  const [selected, setSelected] = useState('new');
  const [form, setForm] = useState(blank());
  const [categoryTitle, setCategoryTitle] = useState('');
  const [categoryDescription, setCategoryDescription] = useState('');
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');
  const [message, setMessage] = useState('');
  const [showPreview, setShowPreview] = useState(false);

  const fail = useCallback((err) => {
    setError(err?.message || 'Não foi possível concluir a operação.');
    if (err?.status === 401) onUnauthorized();
  }, [onUnauthorized]);

  const load = useCallback(async () => {
    const result = await apiRequest('/admin/documents', { token });
    setData(result);
    return result;
  }, [token]);

  useEffect(() => {
    let live = true;
    load().then((result) => {
      if (!live) return;
      setForm((current) => ({ ...current, category_id: current.category_id || result.categories[0]?.id || '' }));
    }).catch((err) => { if (live) fail(err); });
    return () => { live = false; };
  }, [load, fail]);

  async function execute(action, success, onResult) {
    setError('');
    setMessage('');
    setBusy(true);
    try {
      const result = await action();
      const refreshed = await load();
      onResult?.(result, refreshed);
      setMessage(success);
    } catch (err) {
      fail(err);
    } finally {
      setBusy(false);
    }
  }

  function choose(entry) {
    if (busy) return;
    setSelected(entry.id);
    setForm({ ...entry });
    setShowPreview(false);
    setError('');
    setMessage('');
  }

  function add() {
    if (busy) return;
    setSelected('new');
    setForm({ ...blank(), category_id: data?.categories[0]?.id || '' });
    setShowPreview(false);
    setError('');
    setMessage('');
  }

  async function createCategory(event) {
    event.preventDefault();
    await execute(
      () => apiRequest('/admin/document-categories', {
        method: 'POST', token, body: { title: categoryTitle, description: categoryDescription },
      }),
      'Categoria cadastrada.',
      (created) => {
        setCategoryTitle('');
        setCategoryDescription('');
        setForm((current) => ({ ...current, category_id: created.id }));
      }
    );
  }

  async function sendFile(event) {
    const file = event.target.files?.[0];
    event.target.value = '';
    if (!file || busy) return;
    if (!/\.pdf$|\.png$/i.test(file.name)) {
      setError('Selecione um PDF ou PNG.');
      return;
    }
    const kind = file.name.toLowerCase().endsWith('.pdf') ? 'DOCUMENT' : 'IMAGE';
    if (file.size > maxUploadBytes(kind)) {
      setError('O arquivo excede o limite de ' + UPLOAD_LIMIT_MIB[kind] + ' MiB.');
      return;
    }
    await execute(
      () => apiUploadFile('/admin/site/media', file, token),
      'Arquivo enviado. Confira a prévia e clique em Salvar documento para vinculá-lo.',
      (uploaded) => {
        setForm((current) => ({ ...current, media_id: uploaded.id }));
        setShowPreview(true);
      }
    );
  }

  async function saveDocument(event) {
    event.preventDefault();
    const fresh = selected === 'new';
    await execute(
      () => apiRequest(fresh ? '/admin/documents' : '/admin/documents/' + selected, {
        method: fresh ? 'POST' : 'PUT', token,
        body: {
          title: form.title, description: form.description, category_id: form.category_id,
          media_id: form.media_id, publication_status: form.publication_status,
          is_visible: form.is_visible,
        },
      }),
      fresh ? 'Documento cadastrado.' : 'Documento atualizado.',
      (saved) => {
        setSelected(saved.id);
        setForm({ ...saved });
      }
    );
  }

  async function removeDocument() {
    if (selected === 'new' || !window.confirm('Excluir o cadastro do documento? O arquivo original ficará guardado na biblioteca de mídia para recuperação.')) return;
    await execute(
      () => apiRequest('/admin/documents/' + selected, { method: 'DELETE', token }),
      'Documento removido do catálogo. O arquivo original foi preservado.',
      (_, refreshed) => {
        setSelected('new');
        setForm({ ...blank(), category_id: refreshed.categories[0]?.id || '' });
        setShowPreview(false);
      }
    );
  }

  const pdfOrPng = (data?.media || []).filter((item) =>
    (item.kind === 'DOCUMENT' && item.mime_type === 'application/pdf') ||
    (item.kind === 'IMAGE' && item.mime_type === 'image/png')
  );
  const currentMedia = pdfOrPng.find((item) => Number(item.id) === Number(form.media_id));
  return <section className="docAdmin">
    <div className="docAdminIntro">
      <h2>Biblioteca de Documentos</h2>
      <p>Organize guias e materiais de sonorização em PDF e PNG. Os rascunhos e documentos ocultos não ficam acessíveis ao público.</p>
    </div>
    {error && <p role="alert" className="docNotice docError">{error}</p>}
    {message && <p role="status" className="docNotice docSuccess">{message}</p>}
    <div className="docAdminGrid">
      <aside className="docSide">
        <h3>Categorias</h3>
        <form className="docSmallForm" onSubmit={createCategory}>
          <input required maxLength={120} placeholder="Nome da nova categoria" value={categoryTitle}
            onChange={(e) => setCategoryTitle(e.target.value)}/>
          <textarea rows={2} maxLength={500} placeholder="Descrição (opcional)" value={categoryDescription}
            onChange={(e) => setCategoryDescription(e.target.value)}/>
          <button type="submit" disabled={busy}>Adicionar categoria</button>
        </form>
        <h3>Documentos cadastrados</h3>
        <button className="docAdd" type="button" onClick={add} disabled={busy}>+ Novo documento</button>
        {!data && <p>Carregando catálogo...</p>}
        {data && data.documents.length === 0 && <p>Nenhum documento cadastrado.</p>}
        {(data?.documents || []).map((item) => <button type="button" key={item.id}
          className={'docSelect ' + (Number(selected) === Number(item.id) ? 'selected' : '')}
          disabled={busy} onClick={() => choose(item)}>
          <strong>{item.title}</strong>
          <small>{item.category_title} · {item.publication_status === 'PUBLISHED' ? 'Publicado' : 'Rascunho'} · {Number(item.is_visible) ? 'Visível' : 'Oculto'}</small>
        </button>)}
      </aside>
      <div className="docWorkspace">
        <form onSubmit={saveDocument}>
          <h3>{selected === 'new' ? 'Cadastrar documento' : 'Editar documento'}</h3>
          <div className="docFields">
            <label><span>Título</span>
              <input required maxLength={180} value={form.title || ''} onChange={(e) => setForm({ ...form, title: e.target.value })}/>
            </label>
            <label><span>Categoria</span>
              <select required value={form.category_id || ''} onChange={(e) => setForm({ ...form, category_id: Number(e.target.value) })}>
                <option value="">Selecione</option>
                {(data?.categories || []).map((item) => <option key={item.id} value={item.id}>{item.title}</option>)}
              </select>
            </label>
            <label className="wide"><span>Descrição</span>
              <textarea rows={4} maxLength={4000} value={form.description || ''} onChange={(e) => setForm({ ...form, description: e.target.value })}/>
            </label>
            <label className="wide"><span>Enviar arquivo PDF (até {UPLOAD_LIMIT_MIB.DOCUMENT} MiB) ou PNG (até {UPLOAD_LIMIT_MIB.IMAGE} MiB)</span>
              <input type="file" accept=".pdf,.png,application/pdf,image/png" disabled={busy}
                onChange={sendFile} aria-label="Enviar documento PDF ou PNG"/>
            </label>
            <label className="wide"><span>Arquivo vinculado</span>
              <select required value={form.media_id || ''} onChange={(e) => { setForm({ ...form, media_id: Number(e.target.value) || '' }); setShowPreview(false); }}>
                <option value="">Selecione o arquivo enviado</option>
                {pdfOrPng.map((item) => <option key={item.id} value={item.id}>{item.original_name} — {item.kind === 'DOCUMENT' ? 'PDF' : 'PNG'} (#{item.id})</option>)}
              </select>
            </label>
            <label><span>Publicação</span>
              <select value={form.publication_status} onChange={(e) => setForm({ ...form, publication_status: e.target.value })}>
                <option value="DRAFT">Rascunho</option>
                <option value="PUBLISHED">Publicado</option>
              </select>
            </label>
            <label className="docCheck">
              <input type="checkbox" checked={Boolean(Number(form.is_visible))}
                onChange={(e) => setForm({ ...form, is_visible: e.target.checked })}/>
              <span>Visível no site</span>
            </label>
          </div>
          <div className="docActions">
            <button type="submit" disabled={busy || !form.media_id || !form.category_id}>Salvar documento</button>
            <button type="button" className="docSecondary" disabled={busy || !form.media_id}
              onClick={() => setShowPreview((v) => !v)}>{showPreview ? 'Ocultar prévia' : 'Pré-visualizar'}</button>
            {selected !== 'new' && <button type="button" className="docDanger" disabled={busy} onClick={removeDocument}>Excluir cadastro</button>}
          </div>
        </form>
        {showPreview && <PrivatePreview token={token} mediaId={form.media_id} mime={currentMedia?.mime_type}/>}
        {selected !== 'new' && form.publication_status === 'PUBLISHED' && Number(form.is_visible) === 1 &&
          <p className="docAdminPublicLink"><a href={'/documentos/' + selected} target="_blank" rel="noopener noreferrer">Ver documento publicado ↗</a></p>}
        <p className="docHelp">O upload não publica o documento automaticamente. Primeiro envie, confira a prévia, salve o cadastro e defina a publicação.</p>
      </div>
    </div>
  </section>;
}
