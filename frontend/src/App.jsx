const resources = [
  {
    id: 'testes',
    number: '01',
    symbol: '◉',
    title: 'Teste seus conhecimentos',
    description: 'Desafios sobre microfones, mesas de som, equalização, acústica e técnicas de sonorização.',
  },
  {
    id: 'documentos',
    number: '02',
    symbol: '▤',
    title: 'Biblioteca de documentos',
    description: 'Guias, materiais de apoio e conteúdos técnicos em PDF e PNG para estudar e compartilhar.',
  },
  {
    id: 'videos',
    number: '03',
    symbol: '▶',
    title: 'Biblioteca de vídeos',
    description: 'Vídeos educativos do YouTube e produções hospedadas na plataforma para revisar conceitos.',
  },
];

function MixerPreview() {
  const levels = [26, 47, 34, 77, 58, 91, 43, 65, 83, 55, 96, 38, 74, 51, 69, 34, 59, 41];

  return (
    <div className="mixer" role="img" aria-label="Representação gráfica de uma mesa de som digital">
      <div className="mixerTop"><span><span className="liveDot"/> SISTEMA DE SOM · SONI</span><span>SIGNAL ACTIVE</span></div>
      <div className="eq" aria-hidden="true">
        {levels.map((height, index) => <span key={index} style={{ height: height + '%' }}/>)}
      </div>
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

export default function App() {
  return (
    <div className="site">
      <header className="header">
        <div className="layout headerInner">
          <a className="brand" href="#inicio" aria-label="SONI, voltar ao início">
            <span className="brandIcon" aria-hidden="true">≋</span>
            <span>SONI<small>SONORIZAÇÃO DE IGREJAS</small></span>
          </a>
          <nav aria-label="Navegação principal">
            <a href="#sobre">Sobre</a>
            <a href="#testes">Testes</a>
            <a href="#documentos">Documentos</a>
            <a href="#videos">Vídeos</a>
          </nav>
          <a className="navCta" href="#recursos">Explorar plataforma <span aria-hidden="true">↗</span></a>
        </div>
      </header>

      <main id="inicio">
        <section className="hero" id="sobre">
          <div className="layout heroGrid">
            <div className="heroText">
              <span className="overline"><i/> CONHECIMENTO QUE TRANSFORMA O SOM</span>
              <h1>Técnica para servir.<br/><em>Som para conectar.</em></h1>
              <p>Aprenda sonorização de igrejas com orientações acessíveis, recursos para estudo e avaliações interativas para você e sua equipe.</p>
              <div className="heroButtons">
                <a className="buttonAmber" href="#testes">Conheça os testes <span aria-hidden="true">→</span></a>
                <a className="buttonOutline" href="#recursos">Explorar recursos</a>
              </div>
              <p className="notice">Plataforma em desenvolvimento · Prévia institucional v0.1.0</p>
            </div>
            <MixerPreview/>
          </div>
        </section>

        <section className="layout resources" id="recursos">
          <div className="sectionHeading">
            <div><span className="overline teal">APRENDA NO SEU RITMO</span><h2>Conhecimento que faz<br/>a diferença no culto.</h2></div>
            <p>Uma plataforma para voluntários, operadores e líderes que desejam desenvolver técnicas e compartilhar o aprendizado com suas equipes.</p>
          </div>
          <div className="resourceGrid">
            {resources.map((item) => (
              <article className="resourceCard" key={item.id} id={item.id}>
                <div className="cardTop"><span>{item.number} / SONI</span><span className="cardIcon" aria-hidden="true">{item.symbol}</span></div>
                <h3>{item.title}</h3><p>{item.description}</p>
                <div className="cardBottom"><span>Em desenvolvimento</span><span aria-hidden="true">↗</span></div>
              </article>
            ))}
          </div>
        </section>

        <section className="mission">
          <div className="layout missionGrid">
            <div><span className="overline teal">NOSSO PROPÓSITO</span><h2>Mais do que operar equipamentos.</h2></div>
            <div><p>Queremos facilitar o aprendizado técnico e ajudar equipes a construir uma sonorização mais clara, organizada e confiável, a serviço da mensagem e da comunidade.</p><span className="missionBadge">Técnica · Didática · Serviço</span></div>
          </div>
        </section>
      </main>

      <footer className="footer">
        <div className="layout footerInner"><div><strong>SONI</strong><small>PLATAFORMA DE SONORIZAÇÃO DE IGREJAS</small></div><span>© SONI · Projeto em desenvolvimento · v0.1.0</span></div>
      </footer>
    </div>
  );
}
