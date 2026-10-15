const configuredBase = import.meta.env.VITE_API_BASE_URL || '/api';
const apiBase = configuredBase.replace(/\/+$/, '');

export class ApiError extends Error {
  constructor(message, status = 0) {
    super(message);
    this.name = 'ApiError';
    this.status = status;
  }
}

export async function apiRequest(path, { method = 'GET', body, token, signal } = {}) {
  const endpoint = path.startsWith('/') ? path : '/' + path;
  const headers = { Accept: 'application/json' };
  if (body !== undefined) headers['Content-Type'] = 'application/json';
  if (token) headers.Authorization = 'Bearer ' + token;

  let response;
  try {
    response = await fetch(apiBase + endpoint, {
      method,
      headers,
      body: body === undefined ? undefined : JSON.stringify(body),
      credentials: 'omit',
      cache: 'no-store',
      signal,
    });
  } catch (error) {
    if (error.name === 'AbortError') throw error;
    throw new ApiError('Não foi possível conectar à API. Verifique se o servidor Slim está funcionando.');
  }

  const data = await response.json().catch(() => ({}));
  if (!response.ok) {
    throw new ApiError(data.error || 'A solicitação não pôde ser concluída.', response.status);
  }
  return data;
}


export async function apiUploadFile(path, file, token) {
  const form = new FormData();
  form.append('file', file);
  let response;
  try {
    response = await fetch(apiBase + path, {
      method: 'POST',
      headers: { Accept: 'application/json', Authorization: 'Bearer ' + token },
      body: form,
      credentials: 'omit',
      cache: 'no-store',
    });
  } catch {
    throw new ApiError('Falha na conexão durante o envio.');
  }
  const data = await response.json().catch(() => ({}));
  if (!response.ok) throw new ApiError(data.error || 'Falha no upload.', response.status);
  return data;
}

export async function apiMediaBlob(id, token, signal) {
  let response;
  try {
    response = await fetch(apiBase + '/admin/site/media/' + Number(id), {
      headers: { Authorization: 'Bearer ' + token },
      credentials: 'omit',
      cache: 'no-store',
      signal,
    });
  } catch (error) {
    if (error.name === 'AbortError') throw error;
    throw new ApiError('Não foi possível carregar a mídia.');
  }
  if (!response.ok) {
    const data = await response.json().catch(() => ({}));
    throw new ApiError(data.error || 'Mídia indisponível.', response.status);
  }
  return response.blob();
}
