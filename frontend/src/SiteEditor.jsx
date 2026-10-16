import { useEffect, useState } from 'react';
import { apiRequest, apiUploadFile, apiMediaBlob, apiMediaUrl } from './lib/api.js';
import './site-editor.css';

const blockTypes = [
  ['HERO', 'Destaque principal'], ['TITULO', 'Título de seção'], ['TEXTO', 'Texto'],
  ['IMAGEM', 'Imagem'], ['TEXTO_IMAGEM', 'Texto e imagem'], ['VIDEO', 'Vídeo'],
  ['AUDIO', 'Áudio'], ['CTA', 'Chamada para ação'], ['LINK', 'Link'],
  ['TESTES', 'Testes'], ['DOCUMENTOS', 'Documentos'], ['VIDEOS', 'Biblioteca de vídeos'],
];
const defaultBlock = {
  type: 'TEXTO', title: '', subtitle: '', body_text: '', media_url: '', media_id: null,
  alt_text: '', button_label: '', button_url: '', is_visible: 1, publication_status: 'DRAFT',
};
const messageFor = (err) => err?.status === 401
  ? 'Sua sessão expirou. Faça login novamente.'
  : err?.message || 'Não foi possível salvar a alteração.';


const mediaKindForBlock = (type) => {
  if (['HERO', 'IMAGEM', 'TEXTO_IMAGEM'].includes(type)) return 'IMAGE';
  if (type === 'AUDIO') return 'AUDIO';
  if (type === 'VIDEO') return 'VIDEO';
  return null;
};

const acceptForKind = (kind) => {
  if (kind === 'IMAGE') return '.jpg,.jpeg,.png,.webp,.gif';
  if (kind === 'AUDIO') return '.mp3,.wav,.ogg';
  return '.mp4,.webm';
};
const maxForKind = (kind) => kind === 'IMAGE' ? 6 * 1024 * 1024 : kind === 'AUDIO' ? 15 * 1024 * 1024 : 32 * 1024 * 1024;

function BlockPreview({ block, media, token }) {
  const [source, setSource] = useState('');
  const [error, setError] = useState('');
  const kind = mediaKindForBlock(block.type);
  const chosen = media.find((entry) => Number(entry.id) === Number(block.media_id)) ?? null;

  useEffect(() => {
    if (!chosen) {
      setSource('');
      setError('');
      return;
    }
    const controller = new AbortController();
    let objectUrl = null;
    apiMediaBlob(chosen.id, token, controller.signal)
      .then((blob) => {
        if (controller.signal.aborted) return;
        objectUrl = URL.createObjectURL(blob);
        setSource(objectUrl);
        setError('');
      })
      .catch((err) => { if (err.name !== 'AbortError') setError('A prévia da mídia não pôde ser carregada.'); });
    return () => {
      controller.abort();
      if (objectUrl) URL.revokeObjectURL(objectUrl);
    };
  }, [chosen?.id, token]);

  const mediaUrl = chosen ? source : block.media_url;
  return (
    <div className="siteBlockPreview" aria-label="Pré-visualização privada do bloco">
      <div className="sitePreviewLabel">PRÉVIA PARTICULAR · alterações ainda não publicadas</div>
      <small>{block.subtitle}</small>
      <h3>{block.title || 'Título do bloco'}</h3>
      <p>{block.body_text || 'O texto aparecerá aqui.'}</p>
      {kind === 'IMAGE' && mediaUrl && <img src={apiMediaUrl(mediaUrl)} alt={block.alt_text || ''} />}
      {kind === 'AUDIO' && mediaUrl && <audio controls preload="none" src={apiMediaUrl(mediaUrl)}/>}
      {kind === 'VIDEO' && mediaUrl && (mediaUrl.startsWith('blob:') || mediaUrl.startsWith('/api/') || /\.(mp4|webm)(\?|#|$)/i.test(mediaUrl)
        ? <video controls preload="metadata" src={apiMediaUrl(mediaUrl)}/>
        : <p>Vídeo externo: <a href={apiMediaUrl(mediaUrl)} target="_blank" rel="noopener noreferrer">Abrir vídeo ↗</a></p>)}
      {error && <p role="alert">{error}</p>}
      {block.button_label && <span className="sitePreviewButton">{block.button_label}</span>}
    </div>
  );
}

export default function SiteEditor({ token, onUnauthorized }) {
  const [data, setData] = useState(null);
  const [settings, setSettings] = useState(null);
  const [selected, setSelected] = useState(null);
  const [editing, setEditing] = useState(null);
  const [tab, setTab] = useState('blocks');
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');
  const [previewOpen, setPreviewOpen] = useState(false);

  useEffect(() => {
    let active = true;
    apiRequest('/admin/site', { token })
      .then((content) => {
        if (!active) return;
        setData(content);
        setSettings(content.settings);
        setSelected(content.blocks[0]?.id ?? null);
        setEditing(content.blocks[0] ?? null);
      })
      .catch((err) => {
        if (!active) return;
        setError(messageFor(err));
        if (err.status === 401) onUnauthorized();
      })
      .finally(() => { if (active) setLoading(false); });
    return () => { active = false; };
  }, [token, onUnauthorized]);

  async function refresh(selectedId = selected) {
    const updated = await apiRequest('/admin/site', { token });
    setData(updated);
    setSettings(updated.settings);
    const block = updated.blocks.find((item) => String(item.id) === String(selectedId));
    setSelected(block?.id ?? updated.blocks[0]?.id ?? null);
    setEditing(block ?? updated.blocks[0] ?? null);
  }

  function showBlock(block) {
    if (busy) return;
    setSelected(block.id);
    setEditing({ ...block });
    setPreviewOpen(false);
    setError('');
    setSuccess('');
  }

  function addBlock() {
    if (busy) return;
    setTab('blocks');
    setSelected('new');
    setEditing({ ...defaultBlock });
    setPreviewOpen(false);
    setError('');
    setSuccess('');
  }

  async function run(action, message, selectedId) {
    setBusy(true);
    setError('');
    setSuccess('');
    try {
      const response = await action();
      await refresh(typeof selectedId === 'function' ? selectedId(response) : selectedId);
      setSuccess(message);
    } catch (err) {
      setError(messageFor(err));
      if (err.status === 401) onUnauthorized();
    } finally {
      setBusy(false);
    }
  }


  async function uploadMedia(event) {
    const file = event.target.files?.[0];
    event.target.value = '';
    if (!file || !editing || busy) return;
    const kind = mediaKindForBlock(editing.type);
    if (!kind) return;
    if (file.size > maxForKind(kind)) {
      setError('Arquivo muito grande para este tipo de mídia.');
      return;
    }
    setBusy(true);
    setError('');
    setSuccess('');
    try {
      const uploaded = await apiUploadFile('/admin/site/media', file, token);
      setData((current) => ({ ...current, media: [uploaded, ...(current?.media ?? [])] }));
      setEditing((current) => ({ ...current, media_id: uploaded.id, media_url: '' }));
      setPreviewOpen(true);
      setSuccess('Arquivo enviado com sucesso. Clique em Salvar bloco para vincular a mídia.');
    } catch (err) {
      setError(messageFor(err));
      if (err.status === 401) onUnauthorized();
    } finally {
      setBusy(false);
    }
  }

  function saveBlock(event) {
    event.preventDefault();
    const isNew = selected === 'new';
    run(
      () => apiRequest(isNew ? '/admin/site/blocks' : '/admin/site/blocks/' + selected, {
        method: isNew ? 'POST' : 'PUT', body: editing, token,
      }),
      isNew ? 'Bloco criado. Publique-o para aparecer no site.' : 'Bloco salvo com sucesso.',
      (saved) => saved.id
    );
  }

  function saveSettings(event) {
    event.preventDefault();
    run(
      () => apiRequest('/admin/site/settings', { method: 'PUT', body: settings, token }),
      'Configurações do site salvas.',
      selected
    );
  }

  function changeVisibility(block) {
    run(
      () => apiRequest('/admin/site/blocks/' + block.id + '/publication', {
        method: 'PATCH', token, body: { is_visible: !Boolean(Number(block.is_visible)) },
      }),
      'Visibilidade atualizada.', block.id
    );
  }

  function changePublication(block) {
    const publication_status = block.publication_status === 'PUBLISHED' ? 'DRAFT' : 'PUBLISHED';
    run(
      () => apiRequest('/admin/site/blocks/' + block.id + '/publication', {
        method: 'PATCH', token, body: { publication_status },
      }),
      publication_status === 'PUBLISHED' ? 'Bloco publicado.' : 'Bloco movido para rascunho.',
      block.id
    );
  }

  function move(block, direction) {
    run(
      () => apiRequest('/admin/site/blocks/' + block.id + '/order', {
        method: 'PATCH', token, body: { direction },
      }),
      'Ordem dos blocos atualizada.', block.id
    );
  }

  function remove() {
    if (selected === 'new' || !editing?.id) return;
    if (!window.confirm('Excluir este bloco permanentemente?')) return;
    run(
      () => apiRequest('/admin/site/blocks/' + editing.id, { method: 'DELETE', token }),
      'Bloco excluído.', null
    );
  }

  const blocks = data?.blocks ?? [];
  const media = data?.media ?? [];
  const published = blocks.filter((block) => block.publication_status === 'PUBLISHED' && Number(block.is_visible) === 1);

  return (
    <section className="siteEditor" aria-label="Editor do site institucional">
      <div className="siteEditorTop">
        <div>
          <span className="adminEyebrow">CONTEÚDO INSTITUCIONAL</span>
          <h2>Editar site SONI</h2>
          <p>Organize o conteúdo sem alterar o código. Somente blocos publicados e visíveis aparecem para visitantes.</p>
        </div>
        <a className="siteEditorPreview" href="/" target="_blank" rel="noopener noreferrer">Ver site público ↗</a>
      </div>

      <div className="siteEditorTabs" role="group" aria-label="Seções do editor">
        <button className={tab === 'blocks' ? 'active' : ''} type="button" onClick={() => setTab('blocks')}>Blocos ({blocks.length})</button>
        <button className={tab === 'settings' ? 'active' : ''} type="button" onClick={() => setTab('settings')}>Configurações</button>
        <span>{published.length} publicados e visíveis</span>
      </div>
      {error && <p className="adminError" role="alert">{error}</p>}
      {success && <p className="siteEditorSuccess" role="status">{success}</p>}
      {loading && <p>Carregando conteúdo institucional...</p>}

      {!loading && data && tab === 'settings' && settings && (
        <form className="siteEditorSettings" onSubmit={saveSettings}>
          {[
            ['site_title', 'Título do site'], ['site_description', 'Descrição institucional'],
            ['meta_title', 'Título SEO'], ['meta_description', 'Descrição SEO'],
            ['contact_email', 'E-mail de contato'], ['contact_phone', 'Telefone'],
          ].map(([field, label]) => (
            <label key={field}>
              <span>{label}</span>
              {field.endsWith('description')
                ? <textarea value={settings[field] ?? ''} onChange={(event) => setSettings({ ...settings, [field]: event.target.value })} rows={3} />
                : <input type={field === 'contact_email' ? 'email' : 'text'} required={field === 'site_title'}
                    value={settings[field] ?? ''} onChange={(event) => setSettings({ ...settings, [field]: event.target.value })} />}
            </label>
          ))}
          <button type="submit" disabled={busy}>{busy ? 'Salvando...' : 'Salvar configurações'}</button>
        </form>
      )}

      {!loading && data && tab === 'blocks' && (
        <div className="siteEditorColumns">
          <aside className="siteEditorList" aria-label="Blocos na ordem de exibição">
            <button type="button" className="siteEditorAdd" disabled={busy} onClick={addBlock}>+ Adicionar bloco</button>
            {blocks.map((block, index) => (
              <div className={'siteEditorListItem ' + (String(selected) === String(block.id) ? 'chosen' : '')} key={block.id}>
                <button type="button" className="siteEditorSelect" disabled={busy} onClick={() => showBlock(block)}>
                  <span>{index + 1}. {block.title || blockTypes.find((item) => item[0] === block.type)?.[1]}</span>
                  <small>{block.type} · {block.publication_status === 'PUBLISHED' ? 'Publicado' : 'Rascunho'} · {Number(block.is_visible) ? 'Visível' : 'Oculto'}</small>
                </button>
                <div className="siteEditorListActions">
                  <button type="button" title="Subir bloco" aria-label={'Subir ' + (block.title || block.type)} disabled={busy || index === 0} onClick={() => move(block, 'up')}>↑</button>
                  <button type="button" title="Descer bloco" aria-label={'Descer ' + (block.title || block.type)} disabled={busy || index === blocks.length - 1} onClick={() => move(block, 'down')}>↓</button>
                </div>
              </div>
            ))}
            {blocks.length === 0 && <p>Nenhum bloco. Adicione o primeiro.</p>}
          </aside>

          <div className="siteEditorEdit">
            {!editing && <p>Selecione um bloco para editar ou crie um novo.</p>}
            {editing && (
              <form onSubmit={saveBlock} key={selected}>
                <h3>{selected === 'new' ? 'Novo bloco' : 'Editar bloco'}</h3>
                <div className="siteEditorFields">
                  <label>
                    <span>Tipo de bloco</span>
                    <select value={editing.type} onChange={(event) => setEditing({ ...editing, type: event.target.value, media_id: null })}>
                      {blockTypes.map(([type, label]) => <option key={type} value={type}>{label}</option>)}
                    </select>
                  </label>
                  <label>
                    <span>Título</span>
                    <input value={editing.title ?? ''} maxLength={255} onChange={(event) => setEditing({ ...editing, title: event.target.value })} />
                  </label>
                  <label>
                    <span>Subtítulo</span>
                    <input value={editing.subtitle ?? ''} maxLength={300} onChange={(event) => setEditing({ ...editing, subtitle: event.target.value })} />
                  </label>
                  <label className="wide">
                    <span>Texto principal</span>
                    <textarea rows={5} maxLength={8000} value={editing.body_text ?? ''} onChange={(event) => setEditing({ ...editing, body_text: event.target.value })} />
                  </label>
                  <label className="wide">
                    <span>URL da mídia (HTTPS ou caminho interno)</span>
                    <input value={editing.media_url ?? ''} maxLength={1000} placeholder="https://... (alternativa ao upload)" onChange={(event) => setEditing({ ...editing, media_url: event.target.value })} />
                  </label>

                  {mediaKindForBlock(editing.type) && (
                    <div className="siteMediaPicker wide">
                      <label>
                        <span>Mídia enviada ao servidor</span>
                        <select value={editing.media_id ?? ''} onChange={(event) => setEditing({ ...editing, media_id: event.target.value ? Number(event.target.value) : null })}>
                          <option value="">Nenhuma — usar URL externa se informada</option>
                          {media.filter((item) => item.kind === mediaKindForBlock(editing.type)).map((item) => (
                            <option value={item.id} key={item.id}>{item.original_name} ({(Number(item.size_bytes) / 1048576).toFixed(1)} MB)</option>
                          ))}
                        </select>
                      </label>
                      <label>
                        <span>Enviar novo arquivo ({mediaKindForBlock(editing.type) === 'IMAGE' ? 'até 6 MB' : mediaKindForBlock(editing.type) === 'AUDIO' ? 'até 15 MB' : 'até 32 MB'})</span>
                        <input aria-label="Selecionar arquivo de mídia" type="file" disabled={busy} accept={acceptForKind(mediaKindForBlock(editing.type))} onChange={uploadMedia}/>
                      </label>
                      <small>Arquivos ficam fora da pasta pública. Faça upload, verifique a prévia e salve o bloco.</small>
                    </div>
                  )}
                  <label className="wide">
                    <span>Descrição da imagem (acessibilidade)</span>
                    <input value={editing.alt_text ?? ''} maxLength={350} onChange={(event) => setEditing({ ...editing, alt_text: event.target.value })} />
                  </label>
                  <label>
                    <span>Texto do botão</span>
                    <input value={editing.button_label ?? ''} maxLength={120} onChange={(event) => setEditing({ ...editing, button_label: event.target.value })} />
                  </label>
                  <label>
                    <span>Link do botão</span>
                    <input value={editing.button_url ?? ''} maxLength={1000} placeholder="/testes ou https://..." onChange={(event) => setEditing({ ...editing, button_url: event.target.value })} />
                  </label>
                  <label className="siteEditorCheck">
                    <input type="checkbox" checked={Boolean(Number(editing.is_visible))} onChange={(event) => setEditing({ ...editing, is_visible: event.target.checked })} />
                    <span>Bloco visível</span>
                  </label>
                  <label>
                    <span>Publicação</span>
                    <select value={editing.publication_status} onChange={(event) => setEditing({ ...editing, publication_status: event.target.value })}>
                      <option value="DRAFT">Rascunho</option>
                      <option value="PUBLISHED">Publicado</option>
                    </select>
                  </label>
                </div>
                <div className="siteEditorFormActions">
                  <button type="submit" className="siteEditorSave" disabled={busy}>{busy ? 'Salvando...' : 'Salvar bloco'}</button>
                  <button type="button" disabled={busy} onClick={() => setPreviewOpen((value) => !value)}>{previewOpen ? 'Ocultar prévia' : 'Pré-visualizar'}</button>
                  {selected !== 'new' && (
                    <>
                      <button type="button" disabled={busy} onClick={() => changeVisibility(editing)}>{Number(editing.is_visible) ? 'Ocultar' : 'Mostrar'}</button>
                      <button type="button" disabled={busy} onClick={() => changePublication(editing)}>
                        {editing.publication_status === 'PUBLISHED' ? 'Voltar a rascunho' : 'Publicar'}
                      </button>
                      <button type="button" className="siteEditorDanger" disabled={busy} onClick={remove}>Excluir</button>
                    </>
                  )}
                </div>
              </form>
            )}
            {editing && previewOpen && <BlockPreview block={editing} media={media} token={token}/>}
          </div>
        </div>
      )}
      <p className="siteEditorHint">Prévia é particular e não publica alterações. Para atualizar o site, salve e publique o bloco. Formatos: JPG, PNG, WebP, GIF; MP3, WAV, OGG; MP4 e WebM.</p>
    </section>
  );
}
