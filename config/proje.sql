-- Veritabanı: DataTablesOrnek (MSSQL 2016+)
-- Şema + indeksler + örnek veri (200.000 sipariş)

IF DB_ID(N'DataTablesOrnek') IS NULL
    CREATE DATABASE [DataTablesOrnek];
GO

USE [DataTablesOrnek];
GO

/* ---------- Tanım tablosu ---------- */
CREATE TABLE dbo.SiparisDurumlari (
    SiparisDurumlari_id    INT IDENTITY(1,1) PRIMARY KEY,
    SiparisDurumlari_Ad    NVARCHAR(50)  NOT NULL,
    SiparisDurumlari_Renk  NVARCHAR(20)  NOT NULL,   -- Bootstrap renk sınıfı
    SiparisDurumlari_Sira  INT           NOT NULL DEFAULT 0,
    OlusturanKullanici     INT           NULL,
    OlusturmaTarihi        DATETIME      NOT NULL DEFAULT GETDATE(),
    GuncelleyenKullanici   INT           NULL,
    GuncellemeTarihi       DATETIME      NULL,
    Durum                  BIT           NOT NULL DEFAULT 1
);

CREATE TABLE dbo.Musteriler (
    Musteriler_id          INT IDENTITY(1,1) PRIMARY KEY,
    Musteriler_Ad          NVARCHAR(150) NOT NULL,
    Musteriler_Sehir       NVARCHAR(50)  NULL,
    OlusturanKullanici     INT           NULL,
    OlusturmaTarihi        DATETIME      NOT NULL DEFAULT GETDATE(),
    GuncelleyenKullanici   INT           NULL,
    GuncellemeTarihi       DATETIME      NULL,
    Durum                  BIT           NOT NULL DEFAULT 1
);

/* ---------- Hareket tabloları ---------- */
CREATE TABLE dbo.Siparisler (
    Siparisler_id                   INT IDENTITY(1,1) PRIMARY KEY,
    Siparisler_No                   NVARCHAR(20)  NOT NULL,
    Siparisler_Musteriler_id        INT           NOT NULL REFERENCES dbo.Musteriler(Musteriler_id),
    Siparisler_SiparisDurumlari_id  INT           NOT NULL REFERENCES dbo.SiparisDurumlari(SiparisDurumlari_id),
    Siparisler_Tarih                DATETIME      NOT NULL,
    Siparisler_Tutar                DECIMAL(18,2) NOT NULL,
    OlusturanKullanici              INT           NULL,
    OlusturmaTarihi                 DATETIME      NOT NULL DEFAULT GETDATE(),
    GuncelleyenKullanici            INT           NULL,
    GuncellemeTarihi                DATETIME      NULL,
    Durum                           BIT           NOT NULL DEFAULT 1
);

CREATE TABLE dbo.Odemeler (
    Odemeler_id               INT IDENTITY(1,1) PRIMARY KEY,
    Odemeler_Siparisler_id    INT           NOT NULL REFERENCES dbo.Siparisler(Siparisler_id),
    Odemeler_Tutar            DECIMAL(18,2) NOT NULL,
    Odemeler_Tarih            DATETIME      NOT NULL,
    OlusturanKullanici        INT           NULL,
    OlusturmaTarihi           DATETIME      NOT NULL DEFAULT GETDATE(),
    GuncelleyenKullanici      INT           NULL,
    GuncellemeTarihi          DATETIME      NULL,
    Durum                     BIT           NOT NULL DEFAULT 1
);
GO

/* ---------- İndeksler: filtre ve sıralama kolonları ---------- */
CREATE INDEX IX_Siparisler_Tarih      ON dbo.Siparisler (Siparisler_Tarih DESC) INCLUDE (Siparisler_id);
CREATE INDEX IX_Siparisler_Musteri    ON dbo.Siparisler (Siparisler_Musteriler_id);
CREATE INDEX IX_Siparisler_Durum      ON dbo.Siparisler (Siparisler_SiparisDurumlari_id);
CREATE INDEX IX_Siparisler_No         ON dbo.Siparisler (Siparisler_No);
CREATE INDEX IX_Musteriler_Ad         ON dbo.Musteriler (Musteriler_Ad);
CREATE INDEX IX_Odemeler_Siparis      ON dbo.Odemeler (Odemeler_Siparisler_id) INCLUDE (Odemeler_Tutar, Odemeler_Tarih, Durum);
GO

/* ---------- Örnek veri ---------- */
INSERT INTO dbo.SiparisDurumlari (SiparisDurumlari_Ad, SiparisDurumlari_Renk, SiparisDurumlari_Sira) VALUES
(N'Yeni', N'primary', 1),
(N'Hazırlanıyor', N'warning', 2),
(N'Kargoda', N'info', 3),
(N'Teslim Edildi', N'success', 4),
(N'İptal', N'danger', 5);

;WITH Sayilar AS (
    SELECT TOP (5000) ROW_NUMBER() OVER (ORDER BY (SELECT NULL)) AS n
    FROM sys.all_objects a CROSS JOIN sys.all_objects b
)
INSERT INTO dbo.Musteriler (Musteriler_Ad, Musteriler_Sehir)
SELECT CONCAT(N'Müşteri ', RIGHT(CONCAT('0000', n), 5)),
       CHOOSE(n % 6 + 1, N'İzmir', N'İstanbul', N'Ankara', N'Bursa', N'Antalya', N'Manisa')
FROM Sayilar;

;WITH Sayilar AS (
    SELECT TOP (200000) ROW_NUMBER() OVER (ORDER BY (SELECT NULL)) AS n
    FROM sys.all_objects a CROSS JOIN sys.all_objects b
)
INSERT INTO dbo.Siparisler (Siparisler_No, Siparisler_Musteriler_id, Siparisler_SiparisDurumlari_id, Siparisler_Tarih, Siparisler_Tutar)
SELECT CONCAT('SP', RIGHT(CONCAT('000000', n), 7)),
       ABS(CHECKSUM(NEWID())) % 5000 + 1,
       ABS(CHECKSUM(NEWID())) % 5 + 1,
       DATEADD(MINUTE, -(ABS(CHECKSUM(NEWID())) % 1051200), GETDATE()),   -- son 2 yıl
       CAST(ABS(CHECKSUM(NEWID())) % 2500000 / 100.0 + 50 AS DECIMAL(18,2))
FROM Sayilar;

-- Her siparişe 0-3 arası ödeme
INSERT INTO dbo.Odemeler (Odemeler_Siparisler_id, Odemeler_Tutar, Odemeler_Tarih)
SELECT s.Siparisler_id,
       CAST(s.Siparisler_Tutar / 3 AS DECIMAL(18,2)),
       DATEADD(DAY, t.n, s.Siparisler_Tarih)
FROM dbo.Siparisler s
CROSS APPLY (SELECT TOP (s.Siparisler_id % 4) v.n FROM (VALUES (1),(2),(3)) v(n) ORDER BY v.n) t;
GO
