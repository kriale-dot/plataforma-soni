import React from 'react';
import { createRoot } from 'react-dom/client';
import App from './App.jsx';
import AdminPage from './AdminPage.jsx';
import PublicQuizzes from './PublicQuizzes.jsx';
import './styles.css';

const currentPath = window.location.pathname.replace(/\/+$/, '') || '/';

function Root() {
  if (currentPath === '/admin' || currentPath === '/admin/site' || currentPath === '/admin/quizzes') return <AdminPage />;

  if (currentPath === '/testes' || /^\/testes\/[1-9][0-9]*$/.test(currentPath)) {
    return <PublicQuizzes />;
  }

  return (
    <>
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
