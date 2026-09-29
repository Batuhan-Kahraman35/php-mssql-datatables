<?php
declare(strict_types=1);

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $ayarDosyasi = __DIR__ . '/../config/ayar.php';
    if (!is_file($ayarDosyasi)) {
        throw new RuntimeException('config/ayar.php bulunamadı. config/ayar.ornek.php dosyasını kopyalayın.');
    }
    $ayar = (require $ayarDosyasi)['db'];

    $dsn = sprintf(
        'sqlsrv:Server=%s;Database=%s;TrustServerCertificate=1',
        $ayar['sunucu'],
        $ayar['veritabani']
    );

    $pdo = new PDO($dsn, $ayar['kullanici'], $ayar['sifre'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::SQLSRV_ATTR_FETCHES_NUMERIC_TYPE => true,
    ]);

    return $pdo;
}

/** LIKE içinde kullanılacak değerdeki joker karakterleri kaçırır. */
function likeKacir(string $deger): string
{
    return '%' . strtr($deger, ['[' => '[[]', '%' => '[%]', '_' => '[_]']) . '%';
}

function jsonCevap(array $veri, int $kod = 200): never
{
    http_response_code($kod);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($veri, JSON_UNESCAPED_UNICODE);
    exit;
}
