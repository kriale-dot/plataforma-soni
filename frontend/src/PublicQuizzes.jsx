import { useEffect, useState } from 'react';
import { apiRequest, apiMediaUrl } from './lib/api.js';
import './quiz.css';

function QuizMedia({ imageId, audioId }) {
  return <div className="quizMedia">
    {Number(imageId) > 0 && <img loading="lazy" src={apiMediaUrl('/api/site/media/' + Number(imageId))} alt="Imagem integrante da questão ou alternativa"/>}
    {Number(audioId) > 0 && <audio controls preload="none" src={apiMediaUrl('/api/site/media/' + Number(audioId))}>Seu navegador não suporta áudio.</audio>}
  </div>;
}

function QuizHeader() {
  return <header className="quizPublicHeader">
    <div className="layout quizPublicHeaderInner">
      <a href="/" className="quizPublicBrand"><span>≋</span><strong>SONI</strong><small>SONORIZAÇÃO DE IGREJAS</small></a>
      <nav><a href="/">Início</a><a href="/testes">Todos os testes</a></nav>
    </div>
  </header>;
}

function Catalog({ onOpen }) {
  const [tests, setTests] = useState(null);
  const [error, setError] = useState('');
  useEffect(() => {
    const controller = new AbortController();
    apiRequest('/quizzes', { signal: controller.signal }).then(setTests)
      .catch((err) => { if (err.name !== 'AbortError') setError(err.message); });
    return () => controller.abort();
  }, []);
  const categories = [...new Set((tests || []).map((item) => item.category_title))];
  return <div className="quizPublic">
    <QuizHeader/>
    <main className="layout quizCatalog">
      <div className="quizCatalogHeading">
        <span>APRENDA · PRATIQUE · EVOLUA</span>
        <h1>Teste seus Conhecimentos</h1>
        <p>Avalie seus conhecimentos de sonorização de igrejas com questões práticas. Você verá sua pontuação e as explicações após finalizar cada teste.</p>
      </div>
      {!tests && !error && <p role="status">Carregando testes disponíveis...</p>}
      {error && <p className="quizAlert quizError" role="alert">{error}</p>}
      {tests && tests.length === 0 && <div className="quizBlank">
        <h2>Os primeiros testes estão sendo preparados.</h2>
        <p>Volte em breve para conferir as novas atividades.</p>
        <a href="/">← Voltar ao início</a>
      </div>}
      {categories.map((cat) => <section className="quizCategoryGroup" key={cat}>
        <h2>{cat}</h2>
        <div className="quizCatalogGrid">
          {tests.filter((item) => item.category_title === cat).map((quiz) => <article className="quizCatalogCard" key={quiz.id}>
            <span className="quizCardEyebrow">AVALIAÇÃO INTERATIVA</span>
            <h3>{quiz.title}</h3>
            <p>{quiz.description || 'Responda às questões e confira seus resultados.'}</p>
            <div className="quizCardBottom"><small>{quiz.question_count} {Number(quiz.question_count) === 1 ? 'questão' : 'questões'}</small>
              <a href={'/testes/' + quiz.id} onClick={(event) => { if (!event.ctrlKey && !event.metaKey && event.button === 0) { event.preventDefault(); onOpen(quiz.id); } }}>Iniciar teste →</a>
            </div>
          </article>)}
        </div>
      </section>)}
    </main>
  </div>;
}

function Play({ id }) {
  const [quiz, setQuiz] = useState(null);
  const [answers, setAnswers] = useState({});
  const [result, setResult] = useState(null);
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);

  useEffect(() => {
    const controller = new AbortController();
    setQuiz(null);
    setError('');
    setAnswers({});
    setResult(null);
    apiRequest('/quizzes/' + id, { signal: controller.signal })
      .then(setQuiz)
      .catch((err) => { if (err.name !== 'AbortError') setError(err.message); });
    return () => controller.abort();
  }, [id]);

  const questions = quiz?.questions ?? [];
  const allAnswered = questions.length > 0 && questions.every((question) => answers[question.id] != null);
  async function finish(event) {
    event.preventDefault();
    if (!allAnswered || busy || result) return;
    setBusy(true);
    setError('');
    try {
      const data = await apiRequest('/quizzes/' + id + '/attempts', {
        method: 'POST',
        body: { answers: questions.map((question) => ({
          question_id: Number(question.id), option_id: Number(answers[question.id]),
        })) },
      });
      setResult(data);
      window.scrollTo({ top: 0, behavior: 'smooth' });
    } catch (err) {
      setError(err.message || 'Não foi possível enviar as respostas.');
    } finally {
      setBusy(false);
    }
  }
  const byQuestion = Object.fromEntries((result?.feedback ?? []).map((entry) => [entry.question_id, entry]));

  return <div className="quizPublic">
    <QuizHeader/>
    <main className="layout quizPlay">
      <a className="quizBackLink" href="/testes">← Todos os testes</a>
      {!quiz && !error && <p role="status">Carregando a avaliação...</p>}
      {error && <p className="quizAlert quizError" role="alert">{error}</p>}
      {quiz && <>
        <section className="quizPlayIntro">
          <span>SONI · TESTE INTERATIVO</span>
          <h1>{quiz.title}</h1>
          <p>{quiz.description}</p>
          <strong>{questions.length} {questions.length === 1 ? 'questão' : 'questões'} · Uma resposta por questão</strong>
        </section>
        {result && <section className="quizResult" role="status">
          <span>AVALIAÇÃO CONCLUÍDA</span>
          <h2>{result.score_percent}% de acertos</h2>
          <p>Você acertou {result.correct} de {result.total} questões.</p>
          {result.performance_feedback && <div className="quizPerformanceFeedback">
            <span>SEU DESEMPENHO</span>
            <h3>{result.performance_feedback.label}</h3>
            <p>{result.performance_feedback.feedback_text}</p>
            {result.performance_feedback.study_recommendation && <>
              <strong>Recomendação para continuar aprendendo</strong>
              <p>{result.performance_feedback.study_recommendation}</p>
            </>}
          </div>}
          <p>Confira abaixo o gabarito e as explicações de cada questão.</p>
          <a href="/testes">Escolher outro teste →</a>
        </section>}
        <form onSubmit={finish}>
          {questions.map((question, index) => {
            const feedback = byQuestion[question.id];
            return <fieldset className="quizPlayQuestion" key={question.id}>
              <legend><span>QUESTÃO {index + 1} DE {questions.length}</span>{question.prompt_text}</legend>
              <QuizMedia imageId={question.image_media_id} audioId={question.audio_media_id}/>
              <div className="quizPlayOptions">
                {question.options.map((option, i) => {
                  const selected = Number(answers[question.id]) === Number(option.id);
                  const correct = feedback && Number(feedback.correct_option_id) === Number(option.id);
                  return <label key={option.id} className={'quizPlayChoice ' + (selected ? 'checked ' : '') + (result ? (correct ? 'answerRight' : selected ? 'answerWrong' : '') : '')}>
                    <input type="radio" disabled={!!result || busy} name={'quiz-question-' + question.id}
                      checked={selected} onChange={() => setAnswers((current) => ({ ...current, [question.id]: Number(option.id) }))}/>
                    <span className="quizChoiceLetter">{String.fromCharCode(65 + i)}</span>
                    <span className="quizChoiceContents">
                      {option.option_text && <span>{option.option_text}</span>}
                      <QuizMedia imageId={option.image_media_id} audioId={option.audio_media_id}/>
                      {result && correct && <small>Resposta correta</small>}
                      {result && selected && !correct && <small>Sua resposta</small>}
                    </span>
                  </label>;
                })}
              </div>
              {feedback && <div className="quizExplanation">
                <strong>{feedback.correct ? 'Você acertou!' : 'Você não acertou esta questão.'}</strong>
                {feedback.explanation && <p>{feedback.explanation}</p>}
              </div>}
            </fieldset>;
          })}
          {!result && <div className="quizSubmitBox">
            <p>{Object.keys(answers).length} de {questions.length} questões respondidas</p>
            <button type="submit" disabled={!allAnswered || busy}>{busy ? 'Corrigindo...' : 'Finalizar e ver resultado'}</button>
            <small>O gabarito será mostrado somente depois de finalizar.</small>
          </div>}
        </form>
      </>}
    </main>
  </div>;
}

export default function PublicQuizzes() {
  const initial = window.location.pathname.match(/^\/testes\/([1-9][0-9]*)\/?$/)?.[1] || null;
  const [id, setId] = useState(initial);
  function open(next) {
    window.history.pushState(null, '', '/testes/' + next);
    setId(String(next));
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }
  useEffect(() => {
    const listener = () => setId(window.location.pathname.match(/^\/testes\/([1-9][0-9]*)\/?$/)?.[1] || null);
    window.addEventListener('popstate', listener);
    return () => window.removeEventListener('popstate', listener);
  }, []);
  return id ? <Play id={id}/> : <Catalog onOpen={open}/>;
}
