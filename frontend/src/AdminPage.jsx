import { useCallback, useEffect, useState } from 'react';
import SiteEditor from './SiteEditor.jsx';
import QuizManager from './QuizManager.jsx';
import DocumentManager from './DocumentManager.jsx';
import VideoManager from './VideoManager.jsx';
import MediaLibrary from './MediaLibrary.jsx';
import { apiRequest } from './lib/api.js';
import './admin.css';

export default function AdminPage() {
  const [health, setHealth] = useState('verificando');
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [token, setToken] = useState(null);
  const [busy, setBusy] = useState(false);
  const [view, setView] = useState('dashboard');
  const [error, setError] = useState('');

  useEffect(() => {
    const controller = new AbortController();
    apiRequest('/health', { signal: controller.signal })
      .then(() => setHealth('online'))
      .catch((err) => {
        if (err.name !== 'AbortError') setHealth('offline');
      });
    return () => controller.abort();
  }, []);

  async function handleLogin(event) {
    event.preventDefault();
    if (busy) return;
    setError('');
    setBusy(true);

    try {
      const login = await apiRequest('/admin/login', {
        method: 'POST',
        body: { email: email.trim(), password },
      });

      if (!login.token) throw new Error('Token de autenticação não recebido.');
      await apiRequest('/admin/me', { token: login.token });
      // Em memória apenas; recarregar a página encerra a sessão do frontend.
      setToken(login.token);
      setPassword('');
      if (window.location.pathname === '/admin/site') setView('site');
      if (window.location.pathname === '/admin/quizzes') setView('quizzes');
      if (window.location.pathname === '/admin/documents') setView('documents');
      if (window.location.pathname === '/admin/videos') setView('videos');
      if (window.location.pathname === '/admin/media') setView('media');
    } catch (err) {
      if (err.status === 503) setError('O administrador ainda não foi configurado no arquivo api/.env.');
      else if (err.status === 401) setError('E-mail ou senha inválidos.');
      else setError(err.message || 'Ocorreu um erro ao autenticar.');
    } finally {
      setBusy(false);
    }
  }

  const logout = useCallback(() => {
    setToken(null);
    setPassword('');
    setError('');
    setView('dashboard');
    if (window.location.pathname === '/admin/site' || window.location.pathname === '/admin/quizzes' || window.location.pathname === '/admin/documents' || window.location.pathname === '/admin/videos' || window.location.pathname === '/admin/media') window.history.replaceState(null, '', '/admin');
  }, []);

  function openEditor() {
    setView('site');
    window.history.pushState(null, '', '/admin/site');
  }

  function openQuizzes() {
    setView('quizzes');
    window.history.pushState(null, '', '/admin/quizzes');
  }

  function openDocuments() {
    setView('documents');
    window.history.pushState(null, '', '/admin/documents');
  }

  function openVideos() {
    setView('videos');
    window.history.pushState(null, '', '/admin/videos');
  }

  function openMedia() {
    setView('media');
    window.history.pushState(null, '', '/admin/media');
  }

  function backToDashboard() {
    setView('dashboard');
    window.history.pushState(null, '', '/admin');
  }

  return (
    <div className="adminPage">
      <header className="adminHeader">
        <div className="adminContainer adminHeaderInner">
          <a className="adminBrand" href="/" aria-label="Voltar à página inicial da SONI">
            <span className="adminBrandMark" aria-hidden="true">≋</span>
            <span>SONI <small>ADMINISTRAÇÃO</small></span>
          </a>
          <a href="/" className="adminBack">← Voltar ao site</a>
        </div>
      </header>

      <main className="adminMain">
        <div className="adminContainer">
          <div className="adminIntro">
            <span className="adminEyebrow">PAINEL DE GESTÃO</span>
            <h1>{token ? (view === 'site' ? 'Conteúdo do site' : view === 'quizzes' ? 'Gerenciar testes' : view === 'documents' ? 'Biblioteca de Documentos' : view === 'videos' ? 'Biblioteca de Vídeos' : view === 'media' ? 'Arquivos de mídia' : 'Bem-vindo ao painel.') : 'Acesso administrativo'}</h1>
            <p>{token ? 'O administrador está autenticado na API SONI.' : 'Entre com as credenciais configuradas no servidor. Nenhuma senha é armazenada no navegador.'}</p>
          </div>

          {token && view !== 'dashboard' && (
            <div className="adminEditorNavigation">
              <button type="button" onClick={backToDashboard}>← Painel administrativo</button>
              <button type="button" onClick={logout}>Sair da administração</button>
            </div>
          )}
          {token && view === 'site' ? <SiteEditor token={token} onUnauthorized={logout} /> : token && view === 'quizzes' ? <QuizManager token={token} onUnauthorized={logout} /> : token && view === 'documents' ? <DocumentManager token={token} onUnauthorized={logout} /> : token && view === 'videos' ? <VideoManager token={token} onUnauthorized={logout} /> : token && view === 'media' ? <MediaLibrary token={token} onUnauthorized={logout} /> : (
          <section className="adminPanel" aria-label="Autenticação do administrador">
            <div className="adminPanelHead">
              <strong>{token ? 'Sessão autenticada' : 'Entrar na plataforma'}</strong>
              <span className={'apiStatus ' + (health === 'online' ? 'online' : health === 'offline' ? 'offline' : '')}>
                <i aria-hidden="true" />
                API: {health === 'verificando' ? 'verificando...' : health === 'online' ? 'conectada' : 'indisponível'}
              </span>
            </div>

            {token ? (
              <div className="adminWelcome">
                <div className="adminSuccessIcon" aria-hidden="true">✓</div>
                <h2>Acesso autorizado</h2>
                <p>Seu acesso está autorizado. Você já pode gerenciar o conteúdo institucional por blocos.</p>
                <button type="button" className="adminEditorStart" onClick={openEditor}>Editar site institucional →</button>
                <div className="adminModuleList">
                  <button type="button" onClick={openQuizzes}>Testes <small>Gerenciar categorias e questões →</small></button>
                  <button type="button" onClick={openDocuments}>Documentos <small>Gerenciar biblioteca PDF e PNG →</small></button>
                  <button type="button" onClick={openVideos}>Vídeos <small>Gerenciar vídeos do YouTube e arquivos MP4/WebM →</small></button>
                  <button type="button" onClick={openMedia}>Arquivos de mídia <small>Ver espaço ocupado e excluir arquivos, mesmo em uso →</small></button>
                </div>
                <button type="button" className="adminSecondaryButton" onClick={logout}>Sair da administração</button>
              </div>
            ) : (
              <form className="adminForm" onSubmit={handleLogin}>
                <label htmlFor="adminEmail">E-mail do administrador</label>
                <input id="adminEmail" name="username" type="email" autoComplete="username" required
                  value={email} onChange={(event) => setEmail(event.target.value)}
                  placeholder="seuemail@dominio.com" disabled={busy} />
                <label htmlFor="adminPassword">Senha</label>
                <input id="adminPassword" name="password" type="password" autoComplete="current-password"
                  required value={password} onChange={(event) => setPassword(event.target.value)}
                  placeholder="Digite sua senha" disabled={busy} />
                {error && <p className="adminError" role="alert">{error}</p>}
                <button type="submit" disabled={busy}>{busy ? 'Verificando...' : 'Entrar'}</button>
                <p className="adminHelp">O acesso só será liberado depois que o arquivo <code>api/.env</code> estiver configurado.</p>
              </form>
            )}
          </section>
          )}
          <p className="adminFootnote">Plataforma SONI · v0.9.0 · Identidade Harmonia Técnica</p>
        </div>
      </main>
    </div>
  );
}
