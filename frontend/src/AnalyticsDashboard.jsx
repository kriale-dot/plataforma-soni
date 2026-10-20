import { useEffect, useState } from 'react';
import { apiRequest } from './lib/api.js';
import './analytics.css';

const format = (value) => Number(value || 0).toLocaleString('pt-BR');
const percent = (value) => value == null ? '—' : Number(value).toLocaleString('pt-BR', { maximumFractionDigits: 1 }) + '%';

const displayPage = (path) => {
  if (path === '/') return 'Página inicial';
  if (path === '/testes') return 'Lista de testes';
  if (path === '/documentos') return 'Biblioteca de documentos';
  if (path === '/videos') return 'Biblioteca de vídeos';
  return path;
};

export default function AnalyticsDashboard({ token, onUnauthorized }) {
  const [days, setDays] = useState(30);
  const [report, setReport] = useState(null);
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(true);
  const [revision, setRevision] = useState(0);

  useEffect(() => {
    const controller = new AbortController();
    setLoading(true);
    setError('');
    apiRequest('/admin/analytics?days=' + days, { token, signal: controller.signal })
      .then(setReport)
      .catch((err) => {
        if (err.name === 'AbortError') return;
        if (err.status === 401) onUnauthorized();
        else setError(err.message || 'Não foi possível carregar os relatórios.');
      })
      .finally(() => { if (!controller.signal.aborted) setLoading(false); });
    return () => controller.abort();
  }, [days, token, onUnauthorized, revision]);

  const total = report?.totals;
  const maxVisits = Math.max(1, ...(report?.series ?? []).map((day) => Number(day.visits)));
  return (
    <section className="analytics" aria-label="Relatórios de utilização da SONI">
      <div className="analyticsToolbar">
        <div><span className="adminEyebrow">INTELIGÊNCIA DE GESTÃO</span>
          <h2>Visitas e desempenho da SONI</h2>
          <p>Indicadores agregados, sem identificação pessoal de visitantes.</p></div>
        <div className="analyticsControls">
          <label>Período
            <select value={days} onChange={(event) => setDays(Number(event.target.value))}>
              <option value={7}>7 dias</option><option value={30}>30 dias</option><option value={90}>90 dias</option>
            </select>
          </label>
          <button type="button" onClick={() => setRevision((value) => value + 1)} disabled={loading}>Atualizar</button>
        </div>
      </div>
      {error && <p className="adminError" role="alert">{error}</p>}
      {loading && <p role="status">Consultando indicadores...</p>}
      {!loading && report && <>
        <div className="analyticsCards">
          {[
            ['Visitas totais', total.visits_all],
            ['Visitas hoje', total.visits_today],
            ['Visitas nos últimos 7 dias', total.visits_7d],
            ['Visitas nos últimos 30 dias', total.visits_30d],
            ['Visualizações totais', total.pageviews_all],
            ['Visualizações em 30 dias', total.pageviews_30d],
            ['Testes concluídos (total)', total.attempts_all],
            ['Testes concluídos (30 dias)', total.attempts_30d],
          ].map(([label, value]) => (
            <article className="analyticsCard" key={label}><span>{label}</span><strong>{format(value)}</strong></article>
          ))}
        </div>

        <div className="analyticsPanel">
          <h3>Visitas diárias — últimos {days} dias</h3>
          <div className="analyticsBars" role="img" aria-label={'Visitas diárias durante ' + days + ' dias'}>
            {report.series.map((item) => (
              <div key={item.date} className="analyticsBarCol" title={item.date + ': ' + format(item.visits) + ' visitas, ' + format(item.pageviews) + ' visualizações'}>
                <span className="analyticsBar" style={{ height: Math.max(2, Number(item.visits) / maxVisits * 100) + '%' }} />
              </div>
            ))}
          </div>
          <div className="analyticsAxis"><span>{report.series[0]?.date?.split('-').reverse().join('/')}</span><span>{report.series.at(-1)?.date?.split('-').reverse().join('/')}</span></div>
          <p className="analyticsHint">Passe o cursor sobre as barras para ver os números de cada dia.</p>
        </div>

        <div className="analyticsColumns">
          <section className="analyticsPanel">
            <h3>Páginas mais acessadas · {days} dias</h3>
            <div className="analyticsTableScroll">
              <table><thead><tr><th>Página</th><th>Exibições</th><th>Sessões</th></tr></thead>
                <tbody>{report.pages.map((page) => <tr key={page.path}><td>{displayPage(page.path)}</td><td>{format(page.views)}</td><td>{format(page.sessions)}</td></tr>)}
                  {report.pages.length === 0 && <tr><td colSpan={3}>Ainda não há visitas no período.</td></tr>}</tbody>
              </table>
            </div>
          </section>
          <section className="analyticsPanel">
            <h3>Testes mais realizados · {days} dias</h3>
            <div className="analyticsTableScroll">
              <table><thead><tr><th>Teste</th><th>Tentativas</th><th>Média</th></tr></thead>
                <tbody>{report.quizzes.map((item) => <tr key={item.id}><td>{item.title}</td><td>{format(item.attempts)}</td><td>{percent(item.average_score)}</td></tr>)}
                  {report.quizzes.length === 0 && <tr><td colSpan={3}>Nenhum teste cadastrado.</td></tr>}</tbody>
              </table>
            </div>
          </section>
        </div>
        <section className="analyticsPanel">
          <h3>Inventário de conteúdo</h3>
          <div className="analyticsInventory">
            <span>Testes publicados <strong>{format(report.catalog.quizzes_published)}</strong></span>
            <span>Documentos publicados <strong>{format(report.catalog.documents_published)}</strong></span>
            <span>Vídeos publicados <strong>{format(report.catalog.videos_published)}</strong></span>
            <span>Armazenamento de mídia <strong>{(Number(report.catalog.media_bytes) / 1048576).toLocaleString('pt-BR', { maximumFractionDigits: 1 })} MiB</strong></span>
          </div>
        </section>
        <p className="analyticsHint">{report.notes} Horários de agrupamento: São Paulo (UTC−3). A coleta começa após a implantação deste módulo.</p>
      </>}
    </section>
  );
}
