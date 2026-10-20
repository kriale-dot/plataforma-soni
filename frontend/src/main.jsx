import React, { useEffect } from 'react';
import { createRoot } from 'react-dom/client';
import App from './App.jsx';
import AdminPage from './AdminPage.jsx';
import PublicQuizzes from './PublicQuizzes.jsx';
import PublicDocuments from './PublicDocuments.jsx';
import PublicVideos from './PublicVideos.jsx';
import './styles.css';
import { apiRequest } from './lib/api.js';

const currentPath = window.location.pathname.replace(/\/+$/, '') || '/';

let visitSent = false;
function TrackVisits() {
  useEffect(() => {
    if (visitSent || navigator.doNotTrack === '1') return;
    visitSent = true;
    let session;
    try {
      session = JSON.parse(sessionStorage.getItem('soni_visit_session') || 'null');
    } catch { session = null; }
    const now = Date.now();
    if (!session || typeof session.id !== 'string' || !Number.isFinite(session.time) || now - session.time > 30 * 60 * 1000 || now < session.time) {
      session = { id: crypto.randomUUID(), time: now };
    } else {
      session.time = now;
    }
    try { sessionStorage.setItem('soni_visit_session', JSON.stringify(session)); } catch { /* Navegação sem armazenamento disponível. */ }
    apiRequest('/analytics/visit', { method: 'POST', body: { session_id: session.id, page: currentPath } })
      .then((result) => {
        if (result.visit_count != null) window.dispatchEvent(new CustomEvent('soni:visit-count', { detail: result }));
      })
      .catch(() => { /* Métrica opcional: a navegação não pode ser bloqueada. */ });
  }, []);
  return null;
}

function Root() {
  if (currentPath === '/admin' || currentPath === '/admin/site' || currentPath === '/admin/quizzes' || currentPath === '/admin/documents' || currentPath === '/admin/videos' || currentPath === '/admin/media' || currentPath === '/admin/analytics') return <AdminPage />;

  if (currentPath === '/testes' || /^\/testes\/[1-9][0-9]*$/.test(currentPath)) {
    return <><TrackVisits /><PublicQuizzes /></>;
  }

  if (currentPath === '/documentos' || /^\/documentos\/[1-9][0-9]*$/.test(currentPath)) {
    return <><TrackVisits /><PublicDocuments /></>;
  }

  if (currentPath === '/videos' || /^\/videos\/[1-9][0-9]*$/.test(currentPath)) {
    return <><TrackVisits /><PublicVideos /></>;
  }

  return (
    <>
      <TrackVisits />
      <App />
      <div className="adminSiteLink">
        <div className="layout">
          <a href="/admin">Área administrativa ↗</a>
        </div>
      </div>
    </>
  );
}

createRoot(document.getElementById('root')).render(
  <React.StrictMode>
    <Root />
  </React.StrictMode>
);
