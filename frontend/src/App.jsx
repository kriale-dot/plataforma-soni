import { useEffect, useMemo, useState } from 'react';
import { apiRequest, apiMediaUrl } from './lib/api.js';
import soniLogo from './assets/soni-logo.png';

const symbols = { TESTES: '◉', DOCUMENTOS: '▤', VIDEOS: '▶' };
const resourceTypes = new Set(['TESTES', 'DOCUMENTOS', 'VIDEOS']);
const sectionId = { TESTES: 'testes', DOCUMENTOS: 'documentos', VIDEOS: 'videos' };

function internalLink(url) {
  if (!url) return null;
  return url;
}

function MixerPreview() {
  const levels = [26, 47, 34, 77, 58, 91, 43, 65, 83, 55, 96, 38, 74, 51, 69, 34, 59, 41];
  return (
    <div className="mixer" role="img" aria-label="Representação gráfica de uma mesa de som digital">
      <div className="mixerTop"><span><span className="liveDot"/> SISTEMA DE SOM · SONI</span><span>SIGNAL ACTIVE</span></div>
      <div className="eq" aria-hidden="true">{levels.map((height, index) => <span key={index} style={{ height: height + '%' }}/>)}</div>
      <div className="channels" aria-hidden="true">
        {['VOCAL', 'MÚSICA', 'MASTER'].map((name, index) => (
          <div className="channel" key={name}>
            <span>{name}</span>
            <div className="channelSlider"><div className="sliderKnob" style={{ top: (19 + index * 22) + '%' }}/></div>
            <small>CH {index + 1}</small>
          </div>
        ))}
      </div>
      <div className="mixerFooter"><span>CLAREZA EM CADA NOTA</span><span>48 kHz · 24 bit</span></div>
    </div>
  );
}

function YoutubeFrame({ url, title }) {
  try {
    const parsed = new URL(url);
    let id = null;
    const host = parsed.hostname.toLowerCase();
    if (host === 'youtu.be' || host === 'www.youtu.be') {
      id = parsed.pathname.slice(1);
    } else if (['youtube.com', 'www.youtube.com', 'm.youtube.com'].includes(host)) {
      id = parsed.pathname === '/watch' ? parsed.searchParams.get('v') : parsed.pathname.match(/^\/(?:embed|shorts)\/([A-Za-z0-9_-]{11})$/)?.[1];
    }
    if (id && /^[A-Za-z0-9_-]{11}$/.test(id)) {
      return <iframe className="siteVideo" title={title || 'Vídeo da SONI'} src={'https://www.youtube-nocookie.com/embed/' + id} loading="lazy" referrerPolicy="strict-origin-when-cross-origin" allowFullScreen />;
    }
  } catch {
    // Usar player local ou retornar sem player.
  }
  return <video className="siteVideo" controls preload="metadata" src={url}>Seu navegador não suporta vídeo HTML5.</video>;
}

function ResourceCard({ block }) {
  const destination = internalLink(block.button_url);
  return (
    <article className="resourceCard" id={sectionId[block.type]}>
      <div className="cardTop"><span>SONI · {block.type}</span><span className="cardIcon" aria-hidden="true">{symbols[block.type]}</span></div>
      <h3>{block.title}</h3><p>{block.body_text}</p>
      <div className="cardBottom">
        <span>{block.type === 'TESTES' ? 'Avaliações interativas disponíveis' : block.type === 'DOCUMENTOS' ? 'Materiais educativos disponíveis' : block.type === 'VIDEOS' ? 'Aulas e demonstrações disponíveis' : 'Conteúdo em desenvolvimento'}</span>
        {destination && <a href={destination}>{block.button_label || 'Saiba mais'} ↗</a>}
      </div>
    </article>
  );
}

function SiteBlock({ block }) {
  const url = internalLink(block.button_url);
  const image = block.media_url && (block.type === 'IMAGEM' || block.type === 'TEXTO_IMAGEM');
  if (block.type === 'HERO') {
    return (
      <section className="hero" id="sobre">
        <div className="layout heroGrid">
          <div className="heroText">
            <span className="overline"><i/> {block.subtitle || 'SONORIZAÇÃO DE IGREJAS'}</span>
            <h1>{block.title || 'Bem-vindo à SONI'}</h1>
            <p>{block.body_text}</p>
            {url && <div className="heroButtons"><a className="buttonAmber" href={url}>{block.button_label || 'Saiba mais'} <span aria-hidden="true">→</span></a></div>}
          </div>
          {block.media_url ? <img className="siteHeroImage" src={apiMediaUrl(block.media_url)} alt={block.alt_text || ''}/> : <MixerPreview/>}
        </div>
      </section>
    );
  }
  if (block.type === 'TITULO') {
    return <section className="layout siteContentBlock"><span className="overline teal">{block.subtitle}</span><h2>{block.title}</h2>{block.body_text && <p>{block.body_text}</p>}</section>;
  }
  if (block.type === 'TEXTO') {
    return <section className="mission"><div className="layout missionGrid"><div><span className="overline teal">{block.subtitle || 'NOSSO PROPÓSITO'}</span><h2>{block.title}</h2></div><div><p>{block.body_text}</p></div></div></section>;
  }
  if (block.type === 'IMAGEM' || block.type === 'TEXTO_IMAGEM') {
    return <section className="layout siteContentBlock siteImageText">
      <div>
        <span className="overline teal">{block.subtitle}</span>
        <h2>{block.title}</h2>
        <p>{block.body_text}</p>
        {block.type === 'TEXTO_IMAGEM' && url && (
          <div className="heroButtons">
            <a className="buttonAmber" href={url}>{block.button_label || 'Saiba mais'} <span aria-hidden="true">→</span></a>
          </div>
        )}
      </div>
      {image && <img src={apiMediaUrl(block.media_url)} alt={block.alt_text || ''} loading="lazy"/>}
    </section>;
  }
  if (block.type === 'VIDEO' || block.type === 'AUDIO') {
    return <section className="layout siteContentBlock"><span className="overline teal">{block.subtitle}</span><h2>{block.title}</h2><p>{block.body_text}</p>
      {block.media_url && (block.type === 'VIDEO' ? <YoutubeFrame url={apiMediaUrl(block.media_url)} title={block.title}/> : <audio controls src={apiMediaUrl(block.media_url)} preload="none">Seu navegador não suporta áudio HTML5.</audio>)}
    </section>;
  }
  if (block.type === 'CTA' || block.type === 'LINK') {
    return <section className="siteCta"><div className="layout"><span className="overline">{block.subtitle}</span><h2>{block.title}</h2><p>{block.body_text}</p>
      {url && <a className="buttonAmber" href={url}>{block.button_label || 'Saiba mais'} →</a>}
    </div></section>;
  }
  return null;
}

function groupBlocks(blocks) {
  const result = [];
  for (const block of blocks) {
    const last = result[result.length - 1];
    if (resourceTypes.has(block.type)) {
      if (last?.kind === 'resources') last.blocks.push(block);
      else result.push({ kind: 'resources', blocks: [block] });
    } else {
      result.push({ kind: 'block', block });
    }
  }
  return result;
}

export default function App() {
  const [site, setSite] = useState(null);
  const [error, setError] = useState('');

  useEffect(() => {
    const controller = new AbortController();
    apiRequest('/site', { signal: controller.signal })
      .then(setSite)
      .catch((err) => { if (err.name !== 'AbortError') setError('Não foi possível carregar o site. Verifique se a API SONI está funcionando.'); });
    return () => controller.abort();
  }, []);

  useEffect(() => {
    if (!site) return;
    document.title = site.settings?.meta_title || site.settings?.site_title || 'SONI';
    let meta = document.querySelector('meta[name="description"]');
    if (!meta) {
      meta = document.createElement('meta');
      meta.name = 'description';
      document.head.appendChild(meta);
    }
    meta.content = site.settings?.meta_description || site.settings?.site_description || '';
  }, [site]);

  const groups = useMemo(() => groupBlocks(site?.blocks ?? []), [site]);
  return (
    <div className="site">
      <header className="header">
        <div className="layout headerInner">
          <a className="brand" href="/" aria-label="SONI, voltar ao início">
            <img
              src={soniLogo}
              alt=""
              aria-hidden="true"
              width={48}
              height={44}
              style={{ objectFit: 'contain', flexShrink: 0 }}
            />
            <span>SONI<small>SONORIZAÇÃO DE IGREJAS</small></span>
          </a>
          <nav aria-label="Navegação principal">
            <a href="#sobre">Sobre</a><a href="/testes">Testes</a>
            <a href="/documentos">Documentos</a><a href="/videos">Vídeos</a>
          </nav>
          <a className="navCta" href="#recursos">Explorar plataforma <span aria-hidden="true">↗</span></a>
        </div>
      </header>

      <main id="inicio">
        {!site && !error && <div className="layout siteLoadMessage" role="status">Carregando conteúdo SONI...</div>}
        {error && <div className="layout siteLoadMessage" role="alert">{error}</div>}
        {groups.map((group, index) => group.kind === 'resources'
          ? <section key={'resources-' + index} className="layout resources" id="recursos">
              <div className="sectionHeading"><div><span className="overline teal">APRENDA NO SEU RITMO</span><h2>Conhecimento para servir melhor.</h2></div><p>Testes, documentos e vídeos para desenvolver as habilidades da equipe.</p></div>
              <div className="resourceGrid">{group.blocks.map((block) => <ResourceCard key={block.id} block={block}/>)}</div>
            </section>
          : <SiteBlock key={group.block.id} block={group.block}/>
        )}
        {site && groups.length === 0 && <div className="layout siteLoadMessage">Nenhuma seção publicada no momento.</div>}
      </main>

      <footer className="footer">
        <div className="layout footerInner"><div><strong>SONI</strong><small>PLATAFORMA DE SONORIZAÇÃO DE IGREJAS</small></div><span>© SONI · Aprendizagem e sonorização de igrejas</span></div>
      </footer>
    </div>
  );
}
