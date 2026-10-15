import { useEffect, useState } from 'react';
import { apiRequest } from './lib/api.js';
import './admin.css';

export default function AdminPage() {
  const [health, setHealth] = useState('verificando');
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [token, setToken] = useState(null);
  const [busy, setBusy] = useState(false);
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
    } catch (err) {
      if (err.status === 503) setError('O administrador ainda não foi configurado no arquivo api/.env.');
      else if (err.status === 401) setError('E-mail ou senha inválidos.');
      else setError(err.message || 'Ocorreu um erro ao autenticar.');
    } finally {
      setBusy(false);
    }
  }

  function logout() {
    setToken(null);
    setPassword('');
    setError('');
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
            <h1>{token ? 'Bem-vindo ao painel.' : 'Acesso administrativo'}</h1>
            <p>{token ? 'O administrador está autenticado na API SONI.' : 'Entre com as credenciais configuradas no servidor. Nenhuma senha é armazenada no navegador.'}</p>
          </div>

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
                <p>O login administrativo está funcionando. Os módulos de gerenciamento de testes, documentos e vídeos serão implementados nas próximas etapas.</p>
                <div className="adminModuleList">
                  <span>Testes <small>Em desenvolvimento</small></span>
                  <span>Documentos <small>Em desenvolvimento</small></span>
                  <span>Vídeos <small>Em desenvolvimento</small></span>
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
          <p className="adminFootnote">Plataforma SONI · v0.2.0 · Identidade Harmonia Técnica</p>
        </div>
      </main>
    </div>
  );
}
