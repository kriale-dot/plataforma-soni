// Limites em MiB, espelhados no backend (api/src/SiteMedia.php).
// O servidor PHP também deve aceitar requisições maiores que o maior arquivo.
export const UPLOAD_LIMIT_MIB = Object.freeze({
  IMAGE: 20,
  AUDIO: 50,
  VIDEO: 200,
  DOCUMENT: 50,
});

export const maxUploadBytes = (kind) => UPLOAD_LIMIT_MIB[kind] * 1024 * 1024;
