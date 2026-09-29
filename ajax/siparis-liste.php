<?php
declare(strict_types=1);

require __DIR__ . '/../inc/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonCevap(['hata' => 'Yalnız POST desteklenir.'], 405);
}

/**
 * Filtre panelinden gelen değerlerden WHERE koşulunu üretir.
 * Tüm koşullar yalnız ana tablo (s = Siparisler) üzerinden çalışır;
 * müşteri adı araması JOIN yerine IN (alt sorgu) ile yapılır.
 * Böylece sayımlar JOIN'siz kalır.
 */
function filtreKur(array $girdi): array
{
    $kosul = ['s.Durum = 1'];
    $param = [];

    $arama = trim((string)($girdi['arama'] ?? ''));
    if ($arama !== '') {
        $kosul[] = '(s.Siparisler_No LIKE ? OR s.Siparisler_Musteriler_id IN (
                        SELECT Musteriler_id FROM dbo.Musteriler WHERE Musteriler_Ad LIKE ?))';
        $param[] = likeKacir($arama);
        $param[] = likeKacir($arama);
    }

    if (!empty($girdi['durum_id'])) {
        $kosul[] = 's.Siparisler_SiparisDurumlari_id = ?';
        $param[] = (int)$girdi['durum_id'];
    }

    if (!empty($girdi['musteri_id'])) {
        $kosul[] = 's.Siparisler_Musteriler_id = ?';
        $param[] = (int)$girdi['musteri_id'];
    }

    $tarihBas = DateTime::createFromFormat('!Y-m-d', (string)($girdi['tarih_bas'] ?? ''));
    if ($tarihBas) {
        $kosul[] = 's.Siparisler_Tarih >= ?';
        $param[] = $tarihBas->format('Y-m-d H:i:s');
    }

    $tarihBit = DateTime::createFromFormat('!Y-m-d', (string)($girdi['tarih_bit'] ?? ''));
    if ($tarihBit) {
        $kosul[] = 's.Siparisler_Tarih < ?';
        $param[] = $tarihBit->modify('+1 day')->format('Y-m-d H:i:s');
    }

    return [implode(' AND ', $kosul), $param];
}

function liste(PDO $db, array $girdi): array
{
    [$where, $param] = filtreKur($girdi);

    // Sıralama: istemciden gelen kolon index'i whitelist ile kolon adına çevrilir.
    // Müşteri / durum adına göre sıralamada gereken JOIN yalnız o durumda CTE'ye eklenir.
    $siralamaHaritasi = [
        0 => ['s.Siparisler_No', ''],
        1 => ['s.Siparisler_Tarih', ''],
        2 => ['m.Musteriler_Ad', 'INNER JOIN dbo.Musteriler m ON m.Musteriler_id = s.Siparisler_Musteriler_id'],
        3 => ['d.SiparisDurumlari_Sira', 'INNER JOIN dbo.SiparisDurumlari d ON d.SiparisDurumlari_id = s.Siparisler_SiparisDurumlari_id'],
        4 => ['s.Siparisler_Tutar', ''],
    ];
    $kolonIndex = (int)($girdi['order'][0]['column'] ?? 1);
    [$siralaKolon, $siralaJoin] = $siralamaHaritasi[$kolonIndex] ?? $siralamaHaritasi[1];
    $yon = strtolower((string)($girdi['order'][0]['dir'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';
    $sirala = "{$siralaKolon} {$yon}, s.Siparisler_id DESC";

    $baslangic = max(0, (int)($girdi['start'] ?? 0));
    $uzunluk   = (int)($girdi['length'] ?? 25);
    $uzunluk   = ($uzunluk < 1 || $uzunluk > 100) ? 25 : $uzunluk;

    // Sayımlar: tek ana tablo, JOIN yok.
    $toplam = (int)$db->query('SELECT COUNT(*) FROM dbo.Siparisler s WHERE s.Durum = 1')->fetchColumn();

    $st = $db->prepare("SELECT COUNT(*) FROM dbo.Siparisler s WHERE {$where}");
    $st->execute($param);
    $filtreli = (int)$st->fetchColumn();

    // 1. aşama (CTE): yalnız filtre + sıralama + sayfalama -> o sayfanın ID'leri.
    // 2. aşama: ağır OUTER APPLY (ödeme toplamı) yalnız bu N satıra uygulanır.
    $sql = "
        WITH Sayfa AS (
            SELECT s.Siparisler_id,
                   ROW_NUMBER() OVER (ORDER BY {$sirala}) AS Sira
            FROM dbo.Siparisler s
            {$siralaJoin}
            WHERE {$where}
            ORDER BY {$sirala}
            OFFSET {$baslangic} ROWS FETCH NEXT {$uzunluk} ROWS ONLY
        )
        SELECT s.Siparisler_id,
               s.Siparisler_No,
               CONVERT(VARCHAR(16), s.Siparisler_Tarih, 120) AS Siparisler_Tarih,
               s.Siparisler_Tutar,
               m.Musteriler_Ad,
               d.SiparisDurumlari_Ad,
               d.SiparisDurumlari_Renk,
               ISNULL(o.Odenen, 0)                    AS Odenen,
               o.OdemeSayisi,
               CONVERT(VARCHAR(10), o.SonOdeme, 120)  AS SonOdeme
        FROM Sayfa p
        INNER JOIN dbo.Siparisler s       ON s.Siparisler_id = p.Siparisler_id
        INNER JOIN dbo.Musteriler m       ON m.Musteriler_id = s.Siparisler_Musteriler_id
        INNER JOIN dbo.SiparisDurumlari d ON d.SiparisDurumlari_id = s.Siparisler_SiparisDurumlari_id
        OUTER APPLY (
            SELECT SUM(od.Odemeler_Tutar) AS Odenen,
                   COUNT(*)               AS OdemeSayisi,
                   MAX(od.Odemeler_Tarih) AS SonOdeme
            FROM dbo.Odemeler od
            WHERE od.Odemeler_Siparisler_id = s.Siparisler_id AND od.Durum = 1
        ) o
        ORDER BY p.Sira";

    $st = $db->prepare($sql);
    $st->execute($param);

    return [
        'draw'            => (int)($girdi['draw'] ?? 0),
        'recordsTotal'    => $toplam,
        'recordsFiltered' => $filtreli,
        'data'            => $st->fetchAll(),
    ];
}

/** InfoBox özetleri: tablo yüklendikten sonra ayrı istekle çekilir, aynı filtreyi kullanır. */
function istatistik(PDO $db, array $girdi): array
{
    [$where, $param] = filtreKur($girdi);

    $st = $db->prepare("SELECT COUNT(*) AS Adet, ISNULL(SUM(s.Siparisler_Tutar), 0) AS Ciro
                        FROM dbo.Siparisler s WHERE {$where}");
    $st->execute($param);
    $ozet = $st->fetch();

    $st = $db->prepare("SELECT ISNULL(SUM(o.Odemeler_Tutar), 0)
                        FROM dbo.Odemeler o
                        INNER JOIN dbo.Siparisler s ON s.Siparisler_id = o.Odemeler_Siparisler_id
                        WHERE o.Durum = 1 AND {$where}");
    $st->execute($param);
    $tahsilat = (float)$st->fetchColumn();

    return [
        'adet'     => (int)$ozet['Adet'],
        'ciro'     => (float)$ozet['Ciro'],
        'tahsilat' => $tahsilat,
        'bakiye'   => (float)$ozet['Ciro'] - $tahsilat,
    ];
}

function durumlar(PDO $db): array
{
    return $db->query('SELECT SiparisDurumlari_id AS id, SiparisDurumlari_Ad AS text
                       FROM dbo.SiparisDurumlari WHERE Durum = 1
                       ORDER BY SiparisDurumlari_Sira')->fetchAll();
}

/** Select2 ajax araması: müşteri listesi büyük olduğu için tamamı yüklenmez. */
function musteriAra(PDO $db, array $girdi): array
{
    $st = $db->prepare('SELECT TOP (20) Musteriler_id AS id, Musteriler_Ad AS text
                        FROM dbo.Musteriler
                        WHERE Durum = 1 AND Musteriler_Ad LIKE ?
                        ORDER BY Musteriler_Ad');
    $st->execute([likeKacir(trim((string)($girdi['q'] ?? '')))]);
    return ['results' => $st->fetchAll()];
}

try {
    $db = db();
    jsonCevap(match ($_POST['islem'] ?? '') {
        'liste'       => liste($db, $_POST),
        'istatistik'  => istatistik($db, $_POST),
        'durumlar'    => durumlar($db),
        'musteri_ara' => musteriAra($db, $_POST),
        default       => throw new InvalidArgumentException('Geçersiz işlem.'),
    });
} catch (InvalidArgumentException $e) {
    jsonCevap(['hata' => $e->getMessage()], 400);
} catch (Throwable $e) {
    error_log($e->getMessage());
    jsonCevap(['hata' => 'Sunucu hatası.'], 500);
}
