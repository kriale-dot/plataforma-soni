import { useCallback, useEffect, useState } from 'react';
import { apiRequest, apiUploadFile, apiMediaBlob } from './lib/api.js';
import './videos.css';

const fresh = () => ({
  title: '', description: '', category_id: '', source_type: 'YOUTUBE',
  youtube_url: '', youtube_id: '', media_id: '',
  publication_status: 'DRAFT', is_visible: 1,
});

function VideoPreview({ form, token }) {
  const [preview, setPreview] = useState('');
  const [error, setError] = useState('');
  useEffect(() => {
    if (form.source_type !== 'UPLOAD' || !form.media_id) { setPreview(''); return; }
    const controller = new AbortController();
    let objectUrl = '';
    apiMediaBlob(form.media_id, token, controller.signal).then((blob) => {
      if (controller.signal.aborted) return;
      objectUrl = URL.createObjectURL(blob);
      setPreview(objectUrl);
    }).catch((err) => { if (err.name !== 'AbortError') setError(err.message); });
    return () => { controller.abort(); if (objectUrl) URL.revokeObjectURL(objectUrl); };
  }, [form.media_id, form.source_type, token]);
  const id = form.source_type === 'YOUTUBE'
    ? (form.youtube_url?.match(/(?:v=|youtu\.be\/|\/embed\/|\/shorts\/|\/live\/)([A-Za-z0-9_-]{11})/)?.[1] || form.youtube_id)
    : '';
  if (form.source_type === 'YOUTUBE' && /^[A-Za-z0-9_-]{11}$/.test(id || '')) {
    return <div className="videoPreview"><h4>Prévia do YouTube</h4><iframe title="Prévia de vídeo do YouTube" src={'https://www.youtube-nocookie.com/embed/' + id} allowFullScreen loading="lazy"/></div>;
  }
  if (form.source_type === 'UPLOAD' && form.media_id) return <div className="videoPreview">
    <h4>Prévia privada do arquivo</h4>
    {preview ? <video src={preview} controls preload="metadata"/> : <p>{error || 'Carregando...'}</p>}
  </div>;
  return <div className="videoPreview"><p>Adicione um link do YouTube ou um arquivo MP4/WebM para ver a prévia.</p></div>;
}

export default function VideoManager({ token, onUnauthorized }) {
  const [catalog, setCatalog] = useState(null);
  const [selected, setSelected] = useState(null);
  const [form, setForm] = useState(fresh);
  const [categoryTitle, setCategoryTitle] = useState('');
  const [categoryDescription, setCategoryDescription] = useState('');
  const [error, setError] = useState('');
  const [message, setMessage] = useState('');
  const [busy, setBusy] = useState(false);
  const [preview, setPreview] = useState(false);

  const handleError = useCallback((err) => {
    setError(err?.message || 'Não foi possível concluir esta operação.');
    if (err?.status === 401) onUnauthorized();
  }, [onUnauthorized]);

  const reload = useCallback(async () => {
    const data = await apiRequest('/admin/videos', { token });
    setCatalog(data);
    return data;
  }, [token]);

  useEffect(() => {
    let mounted = true;
    reload().then((data) => {
      if (mounted) setForm((current) => ({ ...current, category_id: current.category_id || data.categories[0]?.id || '' }));
    }).catch((err) => { if (mounted) handleError(err); });
    return () => { mounted = false; };
  }, [reload, handleError]);

  async function act(fn, success, callback) {
    setBusy(true); setError(''); setMessage('');
    try {
      const value = await fn();
      const data = await reload();
      callback?.(value, data);
      setMessage(success);
    } catch (err) {
      handleError(err);
    } finally {
      setBusy(false);
    }
  }

  function open(item) {
    if (busy) return;
    setSelected(item.id);
    setForm({ ...item, youtube_url: item.youtube_id ? 'https://www.youtube.com/watch?v=' + item.youtube_id : '' });
    setPreview(false); setError(''); setMessage('');
  }

  function startNew() {
    if (busy) return;
    setSelected(null);
    setForm({ ...fresh(), category_id: catalog?.categories[0]?.id || '' });
    setPreview(false); setError(''); setMessage('');
  }

  async function addCategory(event) {
    event.preventDefault();
    await act(() => apiRequest('/admin/video-categories', {
      method: 'POST', token, body: { title: categoryTitle, description: categoryDescription },
    }), 'Categoria cadastrada.', (created) => {
      setCategoryTitle(''); setCategoryDescription('');
      setForm((current) => ({ ...current, category_id: created.id }));
    });
  }

  async function upload(event) {
    const file = event.target.files?.[0];
    event.target.value = '';
    if (!file) return;
    if (!/\.(mp4|webm)$/i.test(file.name)) { setError('Escolha um arquivo MP4 ou WebM.'); return; }
    if (file.size > 32 * 1024 * 1024) { setError('O limite de upload é 32 MiB.'); return; }
    await act(() => apiUploadFile('/admin/site/media', file, token),
      'Vídeo enviado! Confira a prévia e salve o cadastro para vinculá-lo.', (uploaded) => {
        setForm((current) => ({ ...current, media_id: uploaded.id, source_type: 'UPLOAD' }));
        setPreview(true);
      });
  }

  async function save(event) {
    event.preventDefault();
    const newVideo = selected === null;
    await act(() => apiRequest(newVideo ? '/admin/videos' : '/admin/videos/' + selected, {
      method: newVideo ? 'POST' : 'PUT', token,
      body: {
        title: form.title, description: form.description, category_id: Number(form.category_id),
        source_type: form.source_type, youtube_url: form.source_type === 'YOUTUBE' ? form.youtube_url : undefined,
        media_id: form.source_type === 'UPLOAD' ? Number(form.media_id) : null,
        publication_status: form.publication_status, is_visible: form.is_visible,
      },
    }), newVideo ? 'Vídeo cadastrado.' : 'Vídeo atualizado.', (saved) => {
      setSelected(saved.id);
      setForm({ ...saved, youtube_url: saved.youtube_id ? 'https://www.youtube.com/watch?v=' + saved.youtube_id : '' });
    });
  }

  async function remove() {
    if (selected === null || !window.confirm('Excluir o cadastro deste vídeo? Arquivos enviados serão preservados para recuperação.')) return;
    await act(() => apiRequest('/admin/videos/' + selected, { method: 'DELETE', token }),
      'Cadastro excluído. Arquivo físico preservado.', (_, data) => {
        setSelected(null); setPreview(false);
        setForm({ ...fresh(), category_id: data.categories[0]?.id || '' });
      });
  }

  return <section className="videoAdmin">
    <div className="videoAdminTitle">
      <h2>Biblioteca de Vídeos</h2>
      <p>Publique aulas e demonstrações por link do YouTube ou por upload MP4/WebM. Os vídeos enviados só ficam públicos após a publicação.</p>
    </div>
    {error && <p className="videoAlert videoError" role="alert">{error}</p>}
    {message && <p className="videoAlert videoSuccess" role="status">{message}</p>}
    <div className="videoAdminGrid">
      <aside className="videoSidebar">
        <h3>Categorias</h3>
        <form onSubmit={addCategory} className="videoSmallForm">
          <input required maxLength={120} placeholder="Título da categoria" value={categoryTitle} onChange={(event) => setCategoryTitle(event.target.value)}/>
          <textarea rows={2} maxLength={500} placeholder="Descrição opcional" value={categoryDescription} onChange={(event) => setCategoryDescription(event.target.value)}/>
          <button disabled={busy}>Adicionar categoria</button>
        </form>
        <h3>Vídeos cadastrados</h3>
        <button type="button" className="videoNew" onClick={startNew} disabled={busy}>+ Novo vídeo</button>
        {!catalog && <p>Carregando vídeos...</p>}
        {catalog && catalog.videos.length === 0 && <p>Nenhum vídeo cadastrado.</p>}
        {(catalog?.videos || []).map((item) => <button key={item.id} type="button"
          className={'videoSelect ' + (Number(selected) === Number(item.id) ? 'selected' : '')}
          onClick={() => open(item)} disabled={busy}>
          <strong>{item.title}</strong>
          <small>{item.source_type === 'YOUTUBE' ? 'YouTube' : 'MP4/WebM'} · {item.publication_status === 'PUBLISHED' ? 'Publicado' : 'Rascunho'} · {Number(item.is_visible) ? 'Visível' : 'Oculto'}</small>
        </button>)}
      </aside>
      <div className="videoWorkspace">
        <form onSubmit={save}>
          <h3>{selected === null ? 'Cadastrar novo vídeo' : 'Editar vídeo'}</h3>
          <div className="videoFields">
            <label><span>Título</span><input required maxLength={180} value={form.title || ''} onChange={(event) => setForm({ ...form, title: event.target.value })}/></label>
            <label><span>Categoria</span><select required value={form.category_id || ''} onChange={(event) => setForm({ ...form, category_id: Number(event.target.value) })}>
              <option value="">Selecione</option>
              {(catalog?.categories || []).map((item) => <option key={item.id} value={item.id}>{item.title}</option>)}
            </select></label>
            <label className="wide"><span>Descrição</span><textarea maxLength={4000} rows={4} value={form.description || ''} onChange={(event) => setForm({ ...form, description: event.target.value })}/></label>
            <label><span>Origem do vídeo</span><select value={form.source_type} onChange={(event) => { setForm({ ...form, source_type: event.target.value, youtube_url: '', media_id: '' }); setPreview(false); }}>
              <option value="YOUTUBE">Link do YouTube</option>
              <option value="UPLOAD">Arquivo MP4/WebM</option>
            </select></label>
            {form.source_type === 'YOUTUBE' ? <label className="wide"><span>URL HTTPS do YouTube</span>
              <input type="url" required maxLength={350} placeholder="https://www.youtube.com/watch?v=..." value={form.youtube_url || ''} onChange={(event) => { setForm({ ...form, youtube_url: event.target.value }); setPreview(false); }}/>
            </label> : <>
              <label className="wide"><span>Enviar vídeo MP4 ou WebM (até 32 MiB)</span><input type="file" accept=".mp4,.webm,video/mp4,video/webm" disabled={busy} onChange={upload}/></label>
              <label className="wide"><span>Arquivo enviado</span><select required value={form.media_id || ''} onChange={(event) => { setForm({ ...form, media_id: event.target.value }); setPreview(false); }}>
                <option value="">Selecione o arquivo</option>
                {(catalog?.media || []).map((item) => <option value={item.id} key={item.id}>{item.original_name} (#{item.id})</option>)}
              </select></label>
            </>}
            <label><span>Publicação</span><select value={form.publication_status} onChange={(event) => setForm({ ...form, publication_status: event.target.value })}>
              <option value="DRAFT">Rascunho</option>
              <option value="PUBLISHED">Publicado</option>
            </select></label>
            <label className="videoCheckbox"><input type="checkbox" checked={Boolean(Number(form.is_visible))} onChange={(event) => setForm({ ...form, is_visible: event.target.checked })}/> <span>Visível no site</span></label>
          </div>
          <div className="videoActions">
            <button type="submit" disabled={busy || !form.category_id || (form.source_type === 'UPLOAD' && !form.media_id)}>Salvar vídeo</button>
            <button type="button" className="secondary" disabled={busy} onClick={() => setPreview((v) => !v)}>{preview ? 'Ocultar prévia' : 'Pré-visualizar'}</button>
            {selected !== null && <button type="button" className="danger" onClick={remove} disabled={busy}>Excluir cadastro</button>}
          </div>
        </form>
        {preview && <VideoPreview form={form} token={token}/>}
        {selected !== null && form.publication_status === 'PUBLISHED' && Number(form.is_visible) === 1 &&
          <p className="videoPublishedLink"><a href={'/videos/' + selected} target="_blank" rel="noopener noreferrer">Abrir vídeo publicado ↗</a></p>}
        <p className="videoHelp">Links do YouTube são incorporados pelo domínio de privacidade aprimorada do YouTube. Para os vídeos enviados, a prévia usa autenticação; publicar libera a reprodução pública.</p>
      </div>
    </div>
  </section>;
}
