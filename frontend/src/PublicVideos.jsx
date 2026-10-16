import { useEffect, useState } from 'react';
import { apiRequest, apiMediaUrl } from './lib/api.js';
import soniLogo from './assets/soni-logo.png';
import './videos.css';

function Header() {
  return <header className="videoHeader"><div className="layout videoHeaderInner">
    <a href="/" className="videoBrand"><img src={soniLogo} alt="" width={45} height={45} style={{ objectFit: 'contain' }}/><strong>SONI</strong><small>SONORIZAÇÃO DE IGREJAS</small></a>
    <nav aria-label="Navegação"><a href="/">Início</a><a href="/testes">Testes</a><a href="/documentos">Documentos</a></nav>
  </div></header>;
}

function VideoPlayer({ video }) {
  if (video.source_type === 'YOUTUBE') {
    return <iframe
      className="videoPlayer"
      title={'Vídeo: ' + video.title}
      src={'https://www.youtube-nocookie.com/embed/' + video.youtube_id}
      loading="lazy" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
      referrerPolicy="strict-origin-when-cross-origin" allowFullScreen/>;
  }
  return <video className="videoPlayer" controls preload="metadata" playsInline
    src={apiMediaUrl('/api/site/media/' + video.media_id)}>Seu navegador não suporta reprodução de vídeo.</video>;
}

function Catalog() {
  const [data, setData] = useState(null);
  const [input, setInput] = useState('');
  const [query, setQuery] = useState('');
  const [category, setCategory] = useState('');
  const [error, setError] = useState('');
  useEffect(() => {
    const controller = new AbortController();
    const params = new URLSearchParams();
    if (query) params.set('q', query);
    if (category) params.set('category', category);
    setError('');
    apiRequest('/videos' + (params.size ? '?' + params.toString() : ''), { signal: controller.signal })
      .then(setData).catch((err) => { if (err.name !== 'AbortError') setError(err.message); });
    return () => controller.abort();
  }, [query, category]);
  return <div className="videoPublic">
    <Header/>
    <main className="layout videoCatalog">
      <div className="videoPublicIntro"><span>AULAS · DEMONSTRAÇÕES · APRENDIZAGEM</span>
        <h1>Biblioteca de Vídeos</h1>
        <p>Assista a conteúdos sobre microfones, mixagem, acústica e operação de som para igrejas.</p>
      </div>
      <form className="videoSearch" onSubmit={(event) => { event.preventDefault(); setQuery(input.trim()); }}>
        <label><span>Pesquisar vídeos</span><input type="search" placeholder="Buscar título ou assunto" maxLength={120}
          value={input} onChange={(event) => setInput(event.target.value)}/></label>
        <label><span>Categoria</span><select value={category} onChange={(event) => setCategory(event.target.value)}>
          <option value="">Todas as categorias</option>
          {(data?.categories || []).map((cat) => <option key={cat.id} value={cat.id}>{cat.title}</option>)}
        </select></label>
        <button type="submit">Pesquisar</button>
      </form>
      {error && <p className="videoAlert videoError" role="alert">{error}</p>}
      {!data && !error && <p role="status">Carregando vídeos...</p>}
      {data && <p className="videoResultCount">{data.videos.length} {data.videos.length === 1 ? 'vídeo disponível' : 'vídeos disponíveis'} (até 100 por pesquisa)</p>}
      {data && data.videos.length === 0 && <div className="videoEmpty"><h2>Nenhum vídeo encontrado</h2><p>Os materiais desta categoria podem estar sendo preparados.</p></div>}
      <div className="videoCardGrid">
        {(data?.videos || []).map((video) => <article key={video.id} className="videoCard">
          {video.source_type === 'YOUTUBE' && video.youtube_id
            ? <div className="videoThumb" style={{ backgroundImage: 'url(https://i.ytimg.com/vi/' + video.youtube_id + '/hqdefault.jpg)' }}><span aria-hidden="true">▶</span></div>
            : <div className="videoThumb videoLocalThumb"><span aria-hidden="true">▶</span></div>}
          <div className="videoCardContents">
            <span>{video.category_title} · {video.source_type === 'YOUTUBE' ? 'YouTube' : 'SONI'}</span>
            <h2>{video.title}</h2>
            <p>{video.description || 'Conteúdo técnico disponível para assistir.'}</p>
            <a href={'/videos/' + video.id}>Assistir vídeo →</a>
          </div>
        </article>)}
      </div>
    </main>
  </div>;
}

function Detail({ id }) {
  const [video, setVideo] = useState(null);
  const [error, setError] = useState('');
  useEffect(() => {
    const controller = new AbortController();
    apiRequest('/videos/' + id, { signal: controller.signal }).then(setVideo).catch((err) => {
      if (err.name !== 'AbortError') setError('Vídeo não encontrado ou não publicado.');
    });
    return () => controller.abort();
  }, [id]);
  return <div className="videoPublic">
    <Header/>
    <main className="layout videoDetail">
      <a href="/videos" className="videoBack">← Voltar à biblioteca</a>
      {error && <p className="videoAlert videoError" role="alert">{error}</p>}
      {!video && !error && <p role="status">Carregando vídeo...</p>}
      {video && <>
        <div className="videoDetailHeading"><span>{video.category_title} · {video.source_type === 'YOUTUBE' ? 'YOUTUBE' : 'VÍDEO DA SONI'}</span>
          <h1>{video.title}</h1><p>{video.description}</p></div>
        <VideoPlayer video={video}/>
        <p className="videoHelp">Clique em reproduzir para assistir. A disponibilidade dos vídeos do YouTube também depende das permissões definidas pelo autor no YouTube.</p>
      </>}
    </main>
  </div>;
}

export default function PublicVideos() {
  const id = window.location.pathname.match(/^\/videos\/([1-9][0-9]*)\/?$/)?.[1];
  return id ? <Detail id={id}/> : <Catalog/>;
}
