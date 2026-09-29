# PHP + MSSQL Server-side DataTables

Framework'süz **PHP 8.3** ve **MSSQL** ile, yüz binlerce satırlık hareket tablolarında hızlı açılan server-side DataTables listesi örneği.

> A framework-free PHP 8.3 + SQL Server example of a fast server-side DataTables list using a two-stage (CTE paging + OUTER APPLY) query.

![PHP](https://img.shields.io/badge/PHP_8.3-777BB4?logo=php&logoColor=white)
![MSSQL](https://img.shields.io/badge/SQL_Server-CC2927?logo=microsoftsqlserver&logoColor=white)
![DataTables](https://img.shields.io/badge/DataTables_2-0769AD)
![Bootstrap](https://img.shields.io/badge/Bootstrap_5-7952B3?logo=bootstrap&logoColor=white)

## Neden?

Liste sayfaları genelde şöyle yavaşlar: her satır için ödeme toplamı, son işlem tarihi gibi ağır alt sorgular **tüm tabloya** uygulanır, sonra ilk 25 satır alınır. 200.000 sipariş varsa 200.000 kez hesaplanır.

Bu örnekte ağır hesaplama yalnız **ekranda görünen 25 satıra** yapılır.

## İki aşamalı sorgu

```sql
-- 1. aşama: ucuz CTE — yalnız filtre + sıralama + sayfalama, sadece ID'ler
WITH Sayfa AS (
    SELECT s.Siparisler_id,
           ROW_NUMBER() OVER (ORDER BY s.Siparisler_Tarih DESC, s.Siparisler_id DESC) AS Sira
    FROM dbo.Siparisler s
    WHERE s.Durum = 1 /* + filtreler */
    ORDER BY s.Siparisler_Tarih DESC, s.Siparisler_id DESC
    OFFSET 0 ROWS FETCH NEXT 25 ROWS ONLY
)
-- 2. aşama: ağır OUTER APPLY yalnız bu 25 satıra
SELECT s.*, m.Musteriler_Ad, o.Odenen, o.SonOdeme
FROM Sayfa p
INNER JOIN dbo.Siparisler s ON s.Siparisler_id = p.Siparisler_id
INNER JOIN dbo.Musteriler m ON m.Musteriler_id = s.Siparisler_Musteriler_id
OUTER APPLY (
    SELECT SUM(Odemeler_Tutar) AS Odenen, MAX(Odemeler_Tarih) AS SonOdeme
    FROM dbo.Odemeler WHERE Odemeler_Siparisler_id = s.Siparisler_id
) o
ORDER BY p.Sira;
```

## Uygulanan kurallar

| Kural | Nerede |
|---|---|
| Ağır alt sorgular CTE'de değil, dış SELECT'te | [ajax/siparis-liste.php](ajax/siparis-liste.php) → `liste()` |
| `recordsTotal` / `recordsFiltered` JOIN'siz, tek tablodan | Müşteri adı araması JOIN yerine `IN (alt sorgu)` |
| Sıralama kolonu istemciden doğrudan alınmaz | Kolon index → kolon adı whitelist, yön `ASC`/`DESC` normalize |
| Bir kolona göre sıralanırken gereken JOIN yalnız o durumda CTE'ye eklenir | `$siralamaHaritasi` |
| Özet kutuları (InfoBox) tablo sorgusundan ayrı | `islem=istatistik`, tablo çizildikten sonra `loadStats()` |
| DataTables global arama kapalı, filtre paneli kullanılır | `layout: { topEnd: null }` |
| Büyük seçim listeleri Select2 ajax ile aranır | Müşteri filtresi |
| Filtre/sıralama kolonlarına indeks | [config/proje.sql](config/proje.sql) |
| LIKE joker karakterleri kaçırılır, tüm değerler parametreli | `likeKacir()` |

## Kurulum

Gereksinimler: PHP 8.3 + `pdo_sqlsrv` eklentisi, SQL Server 2016+.

1. [config/proje.sql](config/proje.sql) dosyasını SQL Server'da çalıştır. `DataTablesOrnek` veritabanını oluşturur ve 200.000 örnek sipariş ekler.
2. Bağlantı ayarını oluştur:
   ```bash
   cp config/ayar.ornek.php config/ayar.php
   ```
   `config/ayar.php` içine sunucu, kullanıcı ve şifreyi yaz (bu dosya `.gitignore` içindedir).
3. Çalıştır:
   ```bash
   php -S localhost:8000
   ```
   Tarayıcıda `http://localhost:8000` adresini aç.

## Dosya yapısı

```
├── siparis-liste.php        # Sayfa: InfoBox + filtre paneli + tablo
├── ajax/siparis-liste.php   # liste / istatistik / durumlar / musteri_ara
├── assets/siparis-liste.js  # DataTables, Select2, bildirimler
├── inc/db.php               # PDO bağlantısı ve yardımcılar
└── config/
    ├── proje.sql            # Şema, indeksler, örnek veri
    └── ayar.ornek.php       # Bağlantı ayarı şablonu
```

## Lisans

MIT
