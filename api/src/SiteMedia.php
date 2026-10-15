<?php

declare(strict_types=1);

namespace Soni;

use InvalidArgumentException;
use LengthException;
use PDO;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;
use RuntimeException;
use Slim\Psr7\Stream;

final class SiteMedia
{
    private const ALLOWED = [
        'jpg' => ['IMAGE', 'image/jpeg', 6 * 1024 * 1024],
        'jpeg' => ['IMAGE', 'image/jpeg', 6 * 1024 * 1024],
        'png' => ['IMAGE', 'image/png', 6 * 1024 * 1024],
        'webp' => ['IMAGE', 'image/webp', 6 * 1024 * 1024],
        'gif' => ['IMAGE', 'image/gif', 6 * 1024 * 1024],
        'mp3' => ['AUDIO', 'audio/mpeg', 15 * 1024 * 1024],
        'wav' => ['AUDIO', 'audio/x-wav', 15 * 1024 * 1024],
        'ogg' => ['AUDIO', 'audio/ogg', 15 * 1024 * 1024],
        'mp4' => ['VIDEO', 'video/mp4', 32 * 1024 * 1024],
        'webm' => ['VIDEO', 'video/webm', 32 * 1024 * 1024],
    ];

    private static function directory(): string
    {
        // Fora de api/public; nunca expor via servidor de arquivos estáticos.
        return dirname(__DIR__) . '/storage/site_media';
    }

    public static function upload(PDO $db, ?UploadedFileInterface $file): array
    {
        if ($file === null) {
            throw new InvalidArgumentException('Selecione um arquivo para enviar.');
        }
        if ($file->getError() === UPLOAD_ERR_INI_SIZE || $file->getError() === UPLOAD_ERR_FORM_SIZE) {
            throw new LengthException('O arquivo excede o limite de upload do servidor PHP.');
        }
        if ($file->getError() !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException('Não foi possível receber o arquivo.');
        }

        $name = $file->getClientFilename() ?? '';
        $name = str_replace('\\', '/', $name);
        $name = basename($name);
        if ($name === '' || strlen($name) > 200 || preg_match('/[\x00-\x1F\x7F]/', $name)) {
            throw new InvalidArgumentException('Nome de arquivo inválido.');
        }

        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (!isset(self::ALLOWED[$extension])) {
            throw new InvalidArgumentException('Extensão de arquivo não permitida.');
        }
        [$kind, $expectedMime, $limit] = self::ALLOWED[$extension];

        $size = $file->getSize();
        if ($size === null || $size < 1) {
            throw new InvalidArgumentException('Arquivo vazio ou tamanho desconhecido.');
        }
        if ($size > $limit) {
            throw new LengthException('Arquivo excede o limite permitido para este tipo de mídia.');
        }

        $temp = $file->getStream()->getMetadata('uri');
        if (!is_string($temp) || !is_file($temp)) {
            throw new InvalidArgumentException('Arquivo temporário inválido.');
        }

        $actualMime = (new \finfo(FILEINFO_MIME_TYPE))->file($temp);
        $mimeAliases = [
            'audio/x-wav' => ['audio/wav', 'audio/x-wav', 'audio/wave', 'audio/vnd.wave'],
            'audio/mpeg' => ['audio/mpeg', 'audio/mp3'],
            'video/mp4' => ['video/mp4', 'application/mp4'],
            'video/webm' => ['video/webm', 'audio/webm'],
            'audio/ogg' => ['audio/ogg', 'application/ogg'],
        ];
        if (!in_array($actualMime, $mimeAliases[$expectedMime] ?? [$expectedMime], true)) {
            throw new InvalidArgumentException('O conteúdo do arquivo não corresponde ao formato indicado.');
        }

        if ($kind === 'IMAGE') {
            $info = @getimagesize($temp);
            if (!$info || $info[0] < 1 || $info[1] < 1 ||
                $info[0] > 10000 || $info[1] > 10000 ||
                $info[0] * $info[1] > 40000000) {
                throw new InvalidArgumentException('Imagem inválida ou com dimensões excessivas.');
            }
        }

        $root = self::directory();
        $random = bin2hex(random_bytes(16));
        $subdir = substr($random, 0, 2);
        $dir = $root . '/' . $subdir;
        if (!is_dir($dir) && !@mkdir($dir, 0770, true) && !is_dir($dir)) {
            throw new RuntimeException('Diretório de mídia não pode ser criado.');
        }
        if (!is_writable($dir)) {
            throw new RuntimeException('Diretório de mídia não permite gravação.');
        }
        $relative = $subdir . '/' . $random . '.' . $extension;
        $destination = $root . '/' . $relative;

        $file->moveTo($destination);
        @chmod($destination, 0640);
        try {
            $stmt = $db->prepare(
                'INSERT INTO site_media (kind, original_name, storage_path, mime_type, size_bytes)
                 VALUES (?, ?, ?, ?, ?)'
            );
            $stmt->execute([$kind, $name, $relative, $expectedMime, $size]);
            return [
                'id' => (int) $db->lastInsertId(),
                'kind' => $kind,
                'original_name' => $name,
                'mime_type' => $expectedMime,
                'size_bytes' => $size,
            ];
        } catch (\Throwable $error) {
            @unlink($destination);
            throw $error;
        }
    }

    public static function serve(
        PDO $db,
        int $id,
        ResponseInterface $response,
        ServerRequestInterface $request,
        bool $admin
    ): ResponseInterface {
        $sql = 'SELECT m.kind, m.storage_path, m.mime_type, m.size_bytes
                  FROM site_media m WHERE m.id = ?';
        if (!$admin) {
            $sql .= " AND EXISTS (
                SELECT 1 FROM site_blocks b WHERE b.media_id = m.id
                AND b.is_visible = 1 AND b.publication_status = 'PUBLISHED'
            )";
        }
        $stmt = $db->prepare($sql);
        $stmt->execute([$id]);
        $record = $stmt->fetch();
        if (!$record) {
            throw new InvalidArgumentException('Mídia não encontrada ou não publicada.');
        }

        $relative = $record['storage_path'];
        if (!is_string($relative) ||
            !preg_match('~^[a-f0-9]{2}/[a-f0-9]{32}\.(jpg|jpeg|png|webp|gif|mp3|wav|ogg|mp4|webm)$~D', $relative)) {
            throw new RuntimeException('Caminho de mídia inválido.');
        }
        $path = self::directory() . '/' . $relative;
        if (!is_file($path) || !is_readable($path)) {
            throw new RuntimeException('Arquivo de mídia indisponível.');
        }
        $size = filesize($path);
        if (!is_int($size) || $size < 1) {
            throw new RuntimeException('Tamanho de mídia inválido.');
        }

        $mime = (string) $record['mime_type'];
        $start = 0;
        $end = $size - 1;
        $status = 200;
        $range = $request->getHeaderLine('Range');
        if ($range !== '') {
            if (!preg_match('/^bytes=(\d*)-(\d*)$/D', $range, $matches)) {
                return $response->withStatus(416)->withHeader('Content-Range', 'bytes */' . $size);
            }
            $first = $matches[1];
            $last = $matches[2];
            if ($first === '' && $last === '') {
                return $response->withStatus(416)->withHeader('Content-Range', 'bytes */' . $size);
            }
            if ($first === '') {
                $suffix = (int) $last;
                if ($suffix < 1) {
                    return $response->withStatus(416)->withHeader('Content-Range', 'bytes */' . $size);
                }
                $start = max(0, $size - $suffix);
            } else {
                $start = (int) $first;
                $end = $last === '' ? $size - 1 : min($size - 1, (int) $last);
            }
            if ($start > $end || $start >= $size) {
                return $response->withStatus(416)->withHeader('Content-Range', 'bytes */' . $size);
            }
            $status = 206;
        }

        $handle = fopen($path, 'rb');
        if ($handle === false || fseek($handle, $start) !== 0) {
            throw new RuntimeException('Não foi possível abrir a mídia.');
        }

        // Respeitar o fim do intervalo solicitado, sem carregar arquivos grandes na memória.
        $body = new SiteRangeStream($handle, $end + 1);
        $result = $response->withBody($body)->withStatus($status)
            ->withHeader('Content-Type', $mime)
            ->withHeader('Content-Disposition', 'inline; filename="soni-media.' . pathinfo($relative, PATHINFO_EXTENSION) . '"')
            ->withHeader('Accept-Ranges', 'bytes')
            ->withHeader('Content-Length', (string) ($end - $start + 1))
            ->withHeader('X-Content-Type-Options', 'nosniff')
            ->withHeader('Cache-Control', $admin ? 'private, no-store' : 'public, max-age=300');
        if ($status === 206) {
            $result = $result->withHeader('Content-Range', "bytes {$start}-{$end}/{$size}");
        }
        return $result;
    }
}

final class SiteRangeStream extends Stream
{
    public function __construct($resource, private readonly int $stop)
    {
        parent::__construct($resource);
    }

    public function eof(): bool
    {
        return $this->tell() >= $this->stop || parent::eof();
    }

    public function read(int $length): string
    {
        if ($this->eof()) {
            return '';
        }
        return parent::read(min($length, $this->stop - $this->tell()));
    }

    public function getContents(): string
    {
        $result = '';
        while (!$this->eof()) {
            $result .= $this->read(8192);
        }
        return $result;
    }
}
