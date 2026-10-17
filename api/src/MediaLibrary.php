<?php

declare(strict_types=1);

namespace Soni;

use InvalidArgumentException;
use PDO;
use RuntimeException;
use Throwable;

/** Biblioteca única de arquivos armazenados. Somente o administrador pode utilizar as rotas. */
final class MediaLibrary
{
    private const SAFE_PATH = '~^[a-f0-9]{2}/[a-f0-9]{32}\.(jpg|jpeg|png|webp|gif|mp3|wav|ogg|mp4|webm|pdf)$~D';

    private static function storage(): string
    {
        return dirname(__DIR__) . '/storage/site_media';
    }

    /** Contagens de referências reais, inclusive as não publicadas. */
    private static function usage(PDO $db, int $id): array
    {
        $sql = [
            'blocos' => 'SELECT COUNT(*) FROM site_blocks WHERE media_id = ?',
            'logotipo' => 'SELECT COUNT(*) FROM site_settings WHERE logo_media_id = ?',
            'questoes' => 'SELECT COUNT(*) FROM quiz_questions WHERE image_media_id = ? OR audio_media_id = ?',
            'alternativas' => 'SELECT COUNT(*) FROM quiz_options WHERE image_media_id = ? OR audio_media_id = ?',
            'documentos' => 'SELECT COUNT(*) FROM documents WHERE media_id = ?',
            'videos' => 'SELECT COUNT(*) FROM videos WHERE media_id = ?',
        ];
        $result = [];
        foreach ($sql as $name => $query) {
            $stmt = $db->prepare($query);
            $stmt->execute(in_array($name, ['questoes', 'alternativas'], true) ? [$id, $id] : [$id]);
            $result[$name] = (int) $stmt->fetchColumn();
        }
        return $result;
    }

    public static function list(PDO $db, int $offset = 0, int $limit = 50): array
    {
        if ($offset < 0 || $offset > 1000000 || $limit < 1 || $limit > 100) {
            throw new InvalidArgumentException('Paginação de mídias inválida.');
        }
        $totals = $db->query('SELECT COUNT(*) AS total, COALESCE(SUM(size_bytes), 0) AS bytes_total FROM site_media')->fetch();
        $total = (int) $totals['total'];
        $stmt = $db->prepare(
            'SELECT id, kind, original_name, mime_type, size_bytes, created_at
             FROM site_media ORDER BY id DESC LIMIT ? OFFSET ?'
        );
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->bindValue(2, $offset, PDO::PARAM_INT);
        $stmt->execute();
        $items = $stmt->fetchAll();
        foreach ($items as &$item) {
            $item['usage'] = self::usage($db, (int) $item['id']);
            $item['used_count'] = array_sum($item['usage']);
        }
        unset($item);
        return ['items' => $items, 'total' => $total, 'bytes_total' => (int) $totals['bytes_total'], 'offset' => $offset, 'limit' => $limit];
    }

    /**
     * Remove o arquivo físico e TODAS as suas referências com proteção de FK.
     * Documentos/vídeos dependentes são excluídos. Blocos afetados são ocultados,
     * e testes afetados voltam a rascunho antes de perder a mídia. Resultados de
     * tentativas anteriores são preservados, pois só a mídia está sendo removida.
     *
     * Arquivo é movido para um diretório não-servível enquanto a transação está
     * aberta. Se o banco falhar, o arquivo volta. Após commit, é desvinculado.
     */
    public static function delete(PDO $db, int $id, string $confirmation): array
    {
        if ($id < 1 || $confirmation !== 'EXCLUIR') {
            throw new InvalidArgumentException('Confirme a exclusão definitiva digitando EXCLUIR.');
        }
        $db->beginTransaction();
        $source = '';
        $staged = '';
        $moved = false;
        try {
            $stmt = $db->prepare('SELECT id, original_name, storage_path, size_bytes FROM site_media WHERE id = ? FOR UPDATE');
            $stmt->execute([$id]);
            $media = $stmt->fetch();
            if (!$media) {
                throw new InvalidArgumentException('Arquivo de mídia não encontrado.');
            }
            $relative = (string) $media['storage_path'];
            if (!preg_match(self::SAFE_PATH, $relative)) {
                throw new RuntimeException('Caminho de mídia inválido. Exclusão interrompida.');
            }
            $source = self::storage() . '/' . $relative;
            $rootReal = realpath(self::storage());
            $fileReal = realpath($source);
            if (!is_file($source) || is_link($source) || !is_readable($source) ||
                $rootReal === false || $fileReal === false ||
                !str_starts_with($fileReal, $rootReal . DIRECTORY_SEPARATOR)) {
                throw new RuntimeException('Arquivo ausente ou inseguro. Exclusão interrompida.');
            }
            $pending = self::storage() . '/.pending_delete';
            if (!is_dir($pending) && !@mkdir($pending, 0770, true) && !is_dir($pending)) {
                throw new RuntimeException('Não foi possível preparar a remoção do arquivo.');
            }
            $pendingReal = realpath($pending);
            if (is_link($pending) || $pendingReal === false ||
                !str_starts_with($pendingReal, $rootReal . DIRECTORY_SEPARATOR)) {
                throw new RuntimeException('Diretório temporário de remoção inseguro.');
            }
            $staged = $pending . '/' . $id . '-' . bin2hex(random_bytes(12)) . '.' . pathinfo($relative, PATHINFO_EXTENSION);

            $usage = self::usage($db, $id);
            $stmt = $db->prepare(
                'SELECT DISTINCT quiz_id FROM quiz_questions WHERE image_media_id = ? OR audio_media_id = ?
                 UNION
                 SELECT DISTINCT qq.quiz_id FROM quiz_options qo
                 JOIN quiz_questions qq ON qq.id = qo.question_id
                 WHERE qo.image_media_id = ? OR qo.audio_media_id = ?'
            );
            $stmt->execute([$id, $id, $id, $id]);
            $quizIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
            $quizzesUnpublished = 0;
            if ($quizIds !== []) {
                $placeholders = implode(',', array_fill(0, count($quizIds), '?'));
                $stmt = $db->prepare("UPDATE quizzes SET publication_status = 'DRAFT' WHERE id IN ({$placeholders}) AND publication_status = 'PUBLISHED'");
                $stmt->execute($quizIds);
                $quizzesUnpublished = $stmt->rowCount();
            }

            // Remoção explícita das referências com FK RESTRICT.
            $stmt = $db->prepare('DELETE FROM documents WHERE media_id = ?');
            $stmt->execute([$id]);
            $documentsRemoved = $stmt->rowCount();
            $stmt = $db->prepare('DELETE FROM videos WHERE media_id = ?');
            $stmt->execute([$id]);
            $videosRemoved = $stmt->rowCount();

            // Registros opcionais não ficam apontando para arquivo inexistente.
            $stmt = $db->prepare("UPDATE site_blocks SET media_id = NULL, is_visible = 0 WHERE media_id = ?");
            $stmt->execute([$id]);
            $blocksHidden = $stmt->rowCount();
            $stmt = $db->prepare('UPDATE site_settings SET logo_media_id = NULL WHERE logo_media_id = ?');
            $stmt->execute([$id]);
            $stmt = $db->prepare('UPDATE quiz_questions SET image_media_id = NULL WHERE image_media_id = ?');
            $stmt->execute([$id]);
            $stmt = $db->prepare('UPDATE quiz_questions SET audio_media_id = NULL WHERE audio_media_id = ?');
            $stmt->execute([$id]);
            $stmt = $db->prepare('UPDATE quiz_options SET image_media_id = NULL WHERE image_media_id = ?');
            $stmt->execute([$id]);
            $stmt = $db->prepare('UPDATE quiz_options SET audio_media_id = NULL WHERE audio_media_id = ?');
            $stmt->execute([$id]);

            if (!rename($source, $staged)) {
                throw new RuntimeException('O arquivo não pôde ser movido. Exclusão cancelada.');
            }
            $moved = true;
            $stmt = $db->prepare('DELETE FROM site_media WHERE id = ?');
            $stmt->execute([$id]);
            if ($stmt->rowCount() !== 1) {
                throw new RuntimeException('Falha ao atualizar o cadastro da mídia.');
            }
            $db->commit();
        } catch (Throwable $error) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            if ($moved && is_file($staged)) {
                if (!@rename($staged, $source)) {
                    throw new RuntimeException('Falha grave ao restaurar arquivo após erro de banco; solicite suporte.', 0, $error);
                }
            }
            throw $error;
        }

        // A partir daqui os vínculos estão definitivamente removidos do banco.
        $physicalDeleted = is_file($staged) && @unlink($staged);
        return [
            'deleted' => true,
            'id' => $id,
            'name' => (string) $media['original_name'],
            'bytes' => (int) $media['size_bytes'],
            'physical_deleted' => $physicalDeleted,
            'cleanup_required' => !$physicalDeleted,
            'usage' => $usage,
            'blocks_hidden' => $blocksHidden,
            'quizzes_unpublished' => $quizzesUnpublished,
            'quiz_ids_affected' => $quizIds,
            'documents_removed' => $documentsRemoved,
            'videos_removed' => $videosRemoved,
        ];
    }
}
