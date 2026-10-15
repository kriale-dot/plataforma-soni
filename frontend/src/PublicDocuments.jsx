import { useEffect, useState } from 'react';
import { apiRequest } from './lib/api.js';
import './documents.css';

function DocHeader() {
  return <header className="docPublicHeader">
    <div className="layout docHeaderInner">
      <a href="/" className="docBrand"><span aria-hidden="true">≋</span><strong>SONI</strong><small>SONORIZAÇÃO DE IGREJAS</small></a>
      <nav aria-label="Navegação de documentos"><a href="/">Início</a><a href="/documentos">Documentos</a><a href="/testes">Testes</a></nav>
    </div>
  </header>;
}

function Catalog() {
  const [catalog, setCatalog] = useState(null);
  const [queryInput, setQueryInput] = useState('');
  const [query, setQuery] = useState('');
  const [category, setCategory] = useState('');
  const [error, setError] = useState('');

  useEffect(() => {
    const abort = new AbortController();
    const params = new URLSearchParams();
    if (query) params.set('q', query);
    if (category) params.set('category', category);
    const suffix = params.size ? '?' + params.toString() : '';
    setError('');
    apiRequest('/documents' + suffix, { signal: abort.signal })
      .then(setCatalog).catch((err) => {
        if (err.name !== 'AbortError') setError(err.message || 'Não foi possível carregar os documentos.');
      });
    return () => abort.abort();
  }, [query, category]);

  const entries = catalog?.documents ?? [];
  return <div className="docPublic">
    <DocHeader/>
    <main className="layout docCatalog">
      <div className="docPublicIntro">
        <span>BIBLIOTECA SONI</span>
        <h1>Documentos para aprender e consultar.</h1>
        <p>Guias, diagramas e materiais de apoio sobre sonorização de igrejas, organizados para consulta no seu ritmo.</p>
      </div>
      <form className="docSearch" onSubmit={(event) => { event.preventDefault(); setQuery(queryInput.trim()); }}>
        <label><span>Pesquisar documentos</span>
          <input maxLength={120} type="search" placeholder="Buscar por título, assunto ou descrição"
            value={queryInput} onChange={(event) => setQueryInput(event.target.value)}/>
        </label>
        <label><span>Categoria</span>
          <select value={category} onChange={(event) => setCategory(event.target.value)}>
            <option value="">Todas as categorias</option>
            {(catalog?.categories ?? []).map((item) => <option value={item.id} key={item.id}>{item.title}</option>)}
          </select>
        </label>
        <button type="submit">Pesquisar</button>
      </form>
      {error && <p role="alert" className="docNotice docError">{error}</p>}
      {!catalog && !error && <p role="status">Carregando documentos...</p>}
      {catalog && <p className="docResultCount">{entries.length} {entries.length === 1 ? 'documento encontrado' : 'documentos encontrados'} (até 100 por consulta)</p>}
      {catalog && entries.length === 0 && <div className="docEmpty"><h2>Nenhum documento encontrado.</h2><p>Tente outro termo ou categoria. Os primeiros materiais podem estar em preparação.</p></div>}
      <div className="docCardGrid">
        {entries.map((item) => <article className="docCard" key={item.id}>
          <div className="docIcon" aria-hidden="true">{item.mime_type === 'application/pdf' ? 'PDF' : 'PNG'}</div>
          <span>{item.category_title}</span>
          <h2>{item.title}</h2>
          <p>{item.description || 'Material educativo disponível para consulta.'}</p>
          <div className="docCardBottom">
            <small>{(Number(item.size_bytes) / 1048576).toFixed(1)} MB</small>
            <a href={'/documentos/' + item.id}>Visualizar material →</a>
          </div>
        </article>)}
      </div>
    </main>
  </div>;
}

function Detail({ id }) {
  const [document, setDocument] = useState(null);
  const [error, setError] = useState('');
  useEffect(() => {
    const abort = new AbortController();
    apiRequest('/documents/' + id, { signal: abort.signal })
      .then(setDocument).catch((err) => {
        if (err.name !== 'AbortError') setError('Documento indisponível ou não publicado.');
      });
    return () => abort.abort();
  }, [id]);
  const mediaUrl = document ? '/api/site/media/' + Number(document.media_id) : '';
  return <div className="docPublic">
    <DocHeader/>
    <main className="layout docDetail">
      <a href="/documentos" className="docBack">← Voltar à biblioteca</a>
      {error && <p className="docNotice docError" role="alert">{error}</p>}
      {!document && !error && <p role="status">Carregando documento...</p>}
      {document && <>
        <div className="docDetailTitle">
          <span>{document.category_title} · {document.mime_type === 'application/pdf' ? 'DOCUMENTO PDF' : 'IMAGEM PNG'}</span>
          <h1>{document.title}</h1>
          {document.description && <p>{document.description}</p>}
          <a href={mediaUrl} target="_blank" rel="noopener noreferrer" className="docOpenFile">Abrir arquivo em nova guia ↗</a>
        </div>
        <div className="docViewer">
          {document.mime_type === 'application/pdf'
            ? <iframe src={mediaUrl + '#toolbar=1'} title={'Leitor de PDF: ' + document.title}/>
            : <img loading="lazy" src={mediaUrl} alt={document.title}/>}
        </div>
        <p className="docViewerHint">A visualização de PDFs depende do leitor integrado do navegador. Se não aparecer, abra o arquivo em nova guia.</p>
      </>}
    </main>
  </div>;
}

export default function PublicDocuments() {
  const id = window.location.pathname.match(/^\/documentos\/([1-9][0-9]*)\/?$/)?.[1];
  return id ? <Detail id={id}/> : <Catalog/>;
}
