import { useCallback, useEffect, useState } from 'react';
import { apiRequest, apiUploadFile } from './lib/api.js';
import './quiz.css';

const blankOption = () => ({ option_text: '', image_media_id: null, audio_media_id: null, is_correct: false });
const newQuestion = () => ({
  prompt_text: '', image_media_id: null, audio_media_id: null, explanation: '',
  options: [
    { ...blankOption(), is_correct: true },
    blankOption(),
    blankOption(),
    blankOption(),
  ],
});

function MediaSelect({ label, type, value, items, onChange }) {
  return <label className="quizField">
    <span>{label}</span>
    <select value={value || ''} onChange={(event) => onChange(event.target.value ? Number(event.target.value) : null)}>
      <option value="">Nenhuma mídia</option>
      {items.filter((item) => item.kind === type).map((item) =>
        <option key={item.id} value={item.id}>{item.original_name} (#{item.id})</option>
      )}
    </select>
  </label>;
}

export default function QuizManager({ token, onUnauthorized }) {
  const [catalog, setCatalog] = useState(null);
  const [quiz, setQuiz] = useState(null);
  const [feedbackRanges, setFeedbackRanges] = useState([]);
  const [meta, setMeta] = useState(null);
  const [question, setQuestion] = useState(null);
  const [questionId, setQuestionId] = useState(null);
  const [categoryTitle, setCategoryTitle] = useState('');
  const [categoryDescription, setCategoryDescription] = useState('');
  const [newTitle, setNewTitle] = useState('');
  const [newDescription, setNewDescription] = useState('');
  const [newCategory, setNewCategory] = useState('');
  const [busy, setBusy] = useState(false);
  const [message, setMessage] = useState('');
  const [error, setError] = useState('');
  const [showCreate, setShowCreate] = useState(false);

  const handleError = useCallback((err) => {
    setError(err?.message || 'Não foi possível completar esta ação.');
    if (err?.status === 401) onUnauthorized();
  }, [onUnauthorized]);

  const loadCatalog = useCallback(async () => {
    const data = await apiRequest('/admin/quizzes', { token });
    setCatalog(data);
    setNewCategory((current) => current || String(data.categories[0]?.id || ''));
  }, [token]);

  useEffect(() => {
    let active = true;
    apiRequest('/admin/quizzes', { token }).then((data) => {
      if (!active) return;
      setCatalog(data);
      setNewCategory(String(data.categories[0]?.id || ''));
    }).catch((err) => {
      if (active) handleError(err);
    });
    return () => { active = false; };
  }, [token, handleError]);

  async function openQuiz(id) {
    if (busy) return;
    setError('');
    setMessage('');
    setQuestion(null);
    setQuestionId(null);
    try {
      const data = await apiRequest('/admin/quizzes/' + id, { token });
      setQuiz(data);
      setFeedbackRanges((data.feedback_ranges ?? []).map((range) => ({ ...range })));
      setMeta({
        title: data.title, description: data.description ?? '',
        category_id: Number(data.category_id),
        publication_status: data.publication_status,
      });
    } catch (err) {
      handleError(err);
    }
  }

  async function execute(action, success, refresh = true) {
    setBusy(true);
    setError('');
    setMessage('');
    try {
      const result = await action();
      if (refresh) await loadCatalog();
      setMessage(success);
      return result;
    } catch (err) {
      handleError(err);
      return null;
    } finally {
      setBusy(false);
    }
  }

  async function createCategory(event) {
    event.preventDefault();
    const result = await execute(
      () => apiRequest('/admin/quiz-categories', {
        method: 'POST', token,
        body: { title: categoryTitle, description: categoryDescription },
      }),
      'Categoria criada.'
    );
    if (result) {
      setCategoryTitle('');
      setCategoryDescription('');
      setNewCategory(String(result.id));
    }
  }

  async function createQuiz(event) {
    event.preventDefault();
    const result = await execute(
      () => apiRequest('/admin/quizzes', {
        method: 'POST', token,
        body: { title: newTitle, description: newDescription, category_id: Number(newCategory) },
      }),
      'Teste criado como rascunho.'
    );
    if (result) {
      setNewTitle('');
      setNewDescription('');
      setShowCreate(false);
      await openQuiz(result.id);
    }
  }

  async function deleteQuiz() {
    if (!quiz || busy) return;
    const attempts = Number((catalog?.quizzes || []).find((item) => Number(item.id) === Number(quiz.id))?.attempt_count || 0);
    const warning = 'Excluir definitivamente o teste "' + quiz.title + '"?\n\n'
      + 'Serão excluídas as questões, alternativas, faixas de devolutiva e ' + attempts
      + ' tentativa(s), incluindo respostas, notas e devolutivas.\n'
      + 'Os arquivos enviados à biblioteca de mídias serão preservados.\n\n'
      + 'Essa operação é IRREVERSÍVEL.';
    if (!window.confirm(warning)) return;
    if (window.prompt('Para confirmar a exclusão, digite EXCLUIR:') !== 'EXCLUIR') return;

    const result = await execute(
      () => apiRequest('/admin/quizzes/' + quiz.id, { method: 'DELETE', token }),
      'Teste excluído definitivamente. As mídias foram preservadas.'
    );
    if (result?.deleted) {
      setQuiz(null);
      setMeta(null);
      setFeedbackRanges([]);
      setQuestion(null);
      setQuestionId(null);
    }
  }

  async function saveMeta(event) {
    event.preventDefault();
    const result = await execute(
      () => apiRequest('/admin/quizzes/' + quiz.id, { method: 'PUT', token, body: meta }),
      'Configurações do teste salvas.'
    );
    if (result) await openQuiz(quiz.id);
  }

  async function publish(nextStatus) {
    const result = await execute(
      () => apiRequest('/admin/quizzes/' + quiz.id, {
        method: 'PUT', token, body: { ...meta, publication_status: nextStatus },
      }),
      nextStatus === 'PUBLISHED' ? 'Teste publicado.' : 'Teste retirado da publicação.'
    );
    if (result) await openQuiz(quiz.id);
  }


  async function saveFeedbackRanges(event) {
    event.preventDefault();
    const saved = await execute(
      () => apiRequest('/admin/quizzes/' + quiz.id + '/feedback-ranges', {
        method: 'PUT', token, body: { ranges: feedbackRanges },
      }),
      'Faixas de devolutiva salvas. A classificação será aplicada às próximas tentativas.'
    );
    if (saved) {
      setFeedbackRanges(saved);
      setQuiz((current) => ({ ...current, feedback_ranges: saved }));
    }
  }

  function updateRange(index, field, value) {
    setFeedbackRanges((current) =>
      current.map((range, i) => i === index ? { ...range, [field]: value } : range)
    );
  }

  function addFeedbackRange() {
    setFeedbackRanges((current) => {
      if (current.length >= 20) return current;
      if (current.length === 0) return [{
        min_percent: '0.00', max_percent: '100.00', label: '', feedback_text: '', study_recommendation: '',
      }];
      const rows = current.map((range) => ({ ...range }));
      const last = rows[rows.length - 1];
      const min = Math.round(Number(last.min_percent) * 100);
      const max = Math.round(Number(last.max_percent) * 100);
      if (!Number.isFinite(min) || !Number.isFinite(max) || max <= min) return rows;
      const mid = Math.floor((min + max) / 2);
      last.max_percent = (mid / 100).toFixed(2);
      rows.push({
        min_percent: ((mid + 1) / 100).toFixed(2),
        max_percent: (max / 100).toFixed(2),
        label: '', feedback_text: '', study_recommendation: '',
      });
      return rows;
    });
  }

  function removeFeedbackRange(index) {
    setFeedbackRanges((current) => {
      if (current.length <= 1) return current;
      const rows = current.map((range) => ({ ...range }));
      if (index === 0) rows[1].min_percent = rows[0].min_percent;
      else rows[index - 1].max_percent = rows[index].max_percent;
      return rows.filter((_, i) => i !== index);
    });
  }

  async function saveQuestion(event) {
    event.preventDefault();
    const method = questionId === null ? 'POST' : 'PUT';
    const path = '/admin/quizzes/' + quiz.id + '/questions' + (questionId === null ? '' : '/' + questionId);
    const result = await execute(
      () => apiRequest(path, { method, body: question, token }),
      questionId === null ? 'Questão adicionada.' : 'Questão atualizada.'
    );
    if (result) {
      await openQuiz(quiz.id);
    }
  }

  async function deleteQuestion(id) {
    if (!window.confirm('Excluir permanentemente esta questão e suas alternativas?')) return;
    const result = await execute(
      () => apiRequest('/admin/quizzes/' + quiz.id + '/questions/' + id, {
        method: 'DELETE', token,
      }),
      'Questão excluída.'
    );
    if (result) await openQuiz(quiz.id);
  }

  async function upload(event) {
    const file = event.target.files?.[0];
    event.target.value = '';
    if (!file || !quiz || busy) return;
    setBusy(true);
    setError('');
    setMessage('');
    try {
      const item = await apiUploadFile('/admin/site/media', file, token);
      setQuiz((current) => ({
        ...current,
        media: [item, ...(current?.media || [])],
      }));
      setMessage('Mídia enviada. Selecione-a no campo da questão ou alternativa.');
    } catch (err) {
      handleError(err);
    } finally {
      setBusy(false);
    }
  }

  function editQuestion(row) {
    setQuestionId(Number(row.id));
    setQuestion({
      prompt_text: row.prompt_text || '',
      image_media_id: row.image_media_id,
      audio_media_id: row.audio_media_id,
      explanation: row.explanation || '',
      options: row.options.map((option) => ({
        option_text: option.option_text || '',
        image_media_id: option.image_media_id,
        audio_media_id: option.audio_media_id,
        is_correct: Number(option.is_correct) === 1,
      })),
    });
  }

  function updateOption(index, changes) {
    setQuestion((current) => ({
      ...current,
      options: current.options.map((option, i) => i === index ? { ...option, ...changes } : option),
    }));
  }

  const media = quiz?.media ?? [];
  const draft = quiz?.publication_status === 'DRAFT';
  return <section className="quizAdmin">
    <div className="quizManagerIntro">
      <h2>Teste seus Conhecimentos</h2>
      <p>Crie categorias e provas de múltipla escolha com textos, imagens e áudios. Somente avaliações publicadas ficam disponíveis ao público.</p>
    </div>
    {error && <p className="quizAlert quizError" role="alert">{error}</p>}
    {message && <p className="quizAlert quizOk" role="status">{message}</p>}
    <div className="quizManagerLayout">
      <aside className="quizSidebar">
        <h3>Categorias</h3>
        <form onSubmit={createCategory} className="quizSmallForm">
          <input required maxLength={120} placeholder="Nova categoria" value={categoryTitle} onChange={(event) => setCategoryTitle(event.target.value)}/>
          <textarea rows={2} maxLength={500} placeholder="Descrição (opcional)" value={categoryDescription} onChange={(event) => setCategoryDescription(event.target.value)}/>
          <button type="submit" disabled={busy}>Criar categoria</button>
        </form>
        <h3>Testes cadastrados</h3>
        {!catalog && <p>Carregando testes...</p>}
        {(catalog?.quizzes || []).map((item) =>
          <button key={item.id} type="button" className={'quizSelect ' + (Number(quiz?.id) === Number(item.id) ? 'selected' : '')} onClick={() => openQuiz(item.id)} disabled={busy}>
            <b>{item.title}</b>
            <small>{item.publication_status === 'PUBLISHED' ? 'Publicado' : 'Rascunho'} · {item.question_count} questões · {item.attempt_count} tentativas</small>
          </button>
        )}
        <button type="button" className="quizNewButton" onClick={() => setShowCreate((old) => !old)}>+ Criar teste</button>
        {showCreate && <form onSubmit={createQuiz} className="quizSmallForm">
          <input required maxLength={180} placeholder="Título do novo teste" value={newTitle} onChange={(event) => setNewTitle(event.target.value)}/>
          <select required value={newCategory} onChange={(event) => setNewCategory(event.target.value)}>
            {(catalog?.categories || []).map((cat) => <option value={cat.id} key={cat.id}>{cat.title}</option>)}
          </select>
          <textarea rows={2} maxLength={1000} placeholder="Descrição (opcional)" value={newDescription} onChange={(event) => setNewDescription(event.target.value)}/>
          <button type="submit" disabled={busy || !newCategory}>Salvar novo teste</button>
        </form>}
      </aside>

      <div className="quizWorkspace">
        {!quiz && <div className="quizBlank"><h3>Selecione ou crie um teste.</h3><p>Depois, cadastre as questões e marque a alternativa correta de cada uma.</p></div>}
        {quiz && meta && <>
          <form className="quizMeta" onSubmit={saveMeta}>
            <div className="quizMetaHeading"><h3>{quiz.title}</h3><span>{quiz.publication_status === 'PUBLISHED' ? 'Publicado' : 'Rascunho'}</span></div>
            <label className="quizField"><span>Título</span><input required value={meta.title} maxLength={180} onChange={(event) => setMeta({ ...meta, title: event.target.value })}/></label>
            <label className="quizField"><span>Descrição</span><textarea value={meta.description} maxLength={1000} rows={2} onChange={(event) => setMeta({ ...meta, description: event.target.value })}/></label>
            <label className="quizField"><span>Categoria</span><select value={meta.category_id} onChange={(event) => setMeta({ ...meta, category_id: Number(event.target.value) })}>
              {(catalog?.categories || []).map((cat) => <option value={cat.id} key={cat.id}>{cat.title}</option>)}
            </select></label>
            <div className="quizActions">
              <button type="submit" disabled={busy}>Salvar dados</button>
              <button type="button" disabled={busy} onClick={() => publish(draft ? 'PUBLISHED' : 'DRAFT')}>
                {draft ? 'Publicar teste' : 'Retirar da publicação'}
              </button>
              {quiz.publication_status === 'PUBLISHED' &&
                <a href={'/testes/' + quiz.id} target="_blank" rel="noopener noreferrer">Abrir teste público ↗</a>}
              <button type="button" className="quizDeleteButton" disabled={busy} onClick={deleteQuiz}>Excluir teste</button>
            </div>
          </form>


          <form className="quizFeedbackForm" onSubmit={saveFeedbackRanges}>
            <div className="quizAreaTitle">
              <h3>Devolutiva por percentual de acertos</h3>
              <button type="button" disabled={busy || feedbackRanges.length >= 20} onClick={addFeedbackRange}>+ Adicionar faixa</button>
            </div>
            <p className="quizNotice">Defina uma classificação, uma mensagem e uma recomendação para cada faixa. Os intervalos devem cobrir de 0,00% a 100,00%, sem lacunas nem sobreposições. Cada teste possui suas próprias devolutivas.</p>
            {feedbackRanges.map((range, index) => (
              <div className="quizFeedbackRange" key={range.id || 'nova-' + index}>
                <div className="quizFeedbackRangeHead">
                  <strong>Faixa {index + 1}</strong>
                  <button type="button" disabled={busy || feedbackRanges.length <= 1}
                    onClick={() => removeFeedbackRange(index)}>Remover faixa</button>
                </div>
                <div className="quizFeedbackPercent">
                  <label className="quizField">
                    <span>De (%)</span>
                    <input type="number" min="0" max="100" step="0.01" required
                      value={range.min_percent ?? ''} onChange={(event) => updateRange(index, 'min_percent', event.target.value)}/>
                  </label>
                  <label className="quizField">
                    <span>Até (%)</span>
                    <input type="number" min="0" max="100" step="0.01" required
                      value={range.max_percent ?? ''} onChange={(event) => updateRange(index, 'max_percent', event.target.value)}/>
                  </label>
                </div>
                <label className="quizField">
                  <span>Classificação</span>
                  <input type="text" required maxLength={120} placeholder="Ex.: Bom desempenho"
                    value={range.label ?? ''} onChange={(event) => updateRange(index, 'label', event.target.value)}/>
                </label>
                <label className="quizField">
                  <span>Texto da devolutiva</span>
                  <textarea rows={3} required maxLength={3000}
                    placeholder="Mensagem exibida ao participante após a correção"
                    value={range.feedback_text ?? ''} onChange={(event) => updateRange(index, 'feedback_text', event.target.value)}/>
                </label>
                <label className="quizField">
                  <span>Recomendação de estudo (opcional)</span>
                  <textarea rows={2} maxLength={2000}
                    placeholder="Ex.: Revise os conceitos e pratique com a equipe"
                    value={range.study_recommendation ?? ''} onChange={(event) => updateRange(index, 'study_recommendation', event.target.value)}/>
                </label>
              </div>
            ))}
            <div className="quizActions">
              <button type="submit" disabled={busy || !feedbackRanges.length}>
                {busy ? 'Salvando...' : 'Salvar devolutivas'}
              </button>
            </div>
            <p className="quizNotice">As alterações valem para as próximas tentativas. As devolutivas já recebidas ficam registradas como histórico.</p>
          </form>

          <div className="quizQuestionArea">
            <div className="quizAreaTitle">
              <h3>Questões ({quiz.questions.length})</h3>
              {draft && <button type="button" onClick={() => { setQuestionId(null); setQuestion(newQuestion()); }}>+ Adicionar questão</button>}
            </div>
            {quiz.questions.map((entry, i) => <div className="quizQuestionRow" key={entry.id}>
              <div><strong>{i + 1}. {entry.prompt_text || '(Questão por imagem/áudio)'}</strong><small>{entry.options.length} alternativas</small></div>
              {draft && <div>
                <button type="button" disabled={busy} onClick={() => editQuestion(entry)}>Editar</button>
                <button type="button" disabled={busy} onClick={() => deleteQuestion(entry.id)}>Excluir</button>
              </div>}
            </div>)}
            {!draft && <p className="quizNotice">Para alterar questões, volte o teste para rascunho. Testes com tentativas registradas mantêm seu conteúdo, para preservar a validade dos resultados; nesse caso, crie outro teste.</p>}

            {draft && <div className="quizMediaUpload">
              <h4>Biblioteca de mídias das questões</h4>
              <p>Você pode usar imagens e áudios já enviados ou adicionar novos arquivos aqui.</p>
              <input type="file" accept=".jpg,.jpeg,.png,.webp,.gif,.mp3,.wav,.ogg" aria-label="Enviar imagem ou áudio para questão" disabled={busy} onChange={upload}/>
            </div>}
            {draft && question && <form className="quizQuestionEditor" onSubmit={saveQuestion}>
              <h4>{questionId === null ? 'Nova questão' : 'Editar questão'}</h4>
              <label className="quizField"><span>Enunciado (pode combinar texto, imagem e áudio)</span>
                <textarea rows={3} maxLength={5000} value={question.prompt_text} onChange={(event) => setQuestion({ ...question, prompt_text: event.target.value })}/>
              </label>
              <div className="quizMediaPair">
                <MediaSelect label="Imagem do enunciado" type="IMAGE" items={media} value={question.image_media_id} onChange={(id) => setQuestion({ ...question, image_media_id: id })}/>
                <MediaSelect label="Áudio do enunciado" type="AUDIO" items={media} value={question.audio_media_id} onChange={(id) => setQuestion({ ...question, audio_media_id: id })}/>
              </div>
              <label className="quizField"><span>Explicação (aparece somente após a finalização)</span>
                <textarea rows={2} maxLength={2500} value={question.explanation} onChange={(event) => setQuestion({ ...question, explanation: event.target.value })}/>
              </label>
              <h4>Alternativas</h4>
              <p className="quizNotice">Marque exatamente uma resposta correta. Cada alternativa pode conter texto, imagem e/ou áudio.</p>
              {question.options.map((option, i) => <div className="quizOptionEdit" key={i}>
                <div className="quizOptionTop">
                  <label><input type="radio" name="correct-answer" checked={Boolean(option.is_correct)}
                    onChange={() => setQuestion((old) => ({ ...old, options: old.options.map((o, j) => ({ ...o, is_correct: i === j })) }))}/>
                    Correta</label>
                  <strong>Alternativa {String.fromCharCode(65 + i)}</strong>
                  {question.options.length > 2 &&
                    <button type="button" onClick={() => setQuestion((old) => {
                      const options = old.options.filter((_, j) => j !== i);
                      if (!options.some((o) => o.is_correct)) options[0] = { ...options[0], is_correct: true };
                      return { ...old, options };
                    })}>Remover</button>}
                </div>
                <textarea value={option.option_text} rows={2} maxLength={1200}
                  placeholder="Texto da alternativa (opcional quando houver imagem/áudio)"
                  onChange={(event) => updateOption(i, { option_text: event.target.value })}/>
                <div className="quizMediaPair">
                  <MediaSelect label="Imagem" type="IMAGE" items={media} value={option.image_media_id} onChange={(id) => updateOption(i, { image_media_id: id })}/>
                  <MediaSelect label="Áudio" type="AUDIO" items={media} value={option.audio_media_id} onChange={(id) => updateOption(i, { audio_media_id: id })}/>
                </div>
              </div>)}
              {question.options.length < 6 && <button type="button" className="quizSecondary" onClick={() => setQuestion((old) => ({ ...old, options: [...old.options, blankOption()] }))}>+ Alternativa</button>}
              <div className="quizActions">
                <button type="submit" disabled={busy}>{busy ? 'Salvando...' : 'Salvar questão'}</button>
                <button type="button" className="quizSecondary" onClick={() => setQuestion(null)} disabled={busy}>Cancelar</button>
              </div>
            </form>}
          </div>
        </>}
      </div>
    </div>
  </section>;
}
