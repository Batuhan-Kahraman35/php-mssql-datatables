<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sipariş Listesi · Server-side DataTables</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/2.1.8/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css">
    <link rel="stylesheet" href="assets/siparis-liste.css">
</head>
<body class="bg-body-tertiary">
<div class="container-fluid py-4">

    <h1 class="h4 mb-4"><i class="bi bi-receipt"></i> Sipariş Listesi</h1>

    <!-- InfoBox: tablo yüklendikten sonra loadStats() ile dolar -->
    <div class="row g-3 mb-4" id="infoBoxlar">
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm info-box">
                <div class="card-body d-flex align-items-center gap-3">
                    <span class="info-ikon bg-primary"><i class="bi bi-bag"></i></span>
                    <div><div class="text-muted small">Sipariş</div><div class="fs-5 fw-semibold" data-ozet="adet">–</div></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm info-box">
                <div class="card-body d-flex align-items-center gap-3">
                    <span class="info-ikon bg-info"><i class="bi bi-graph-up"></i></span>
                    <div><div class="text-muted small">Ciro</div><div class="fs-5 fw-semibold" data-ozet="ciro">–</div></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm info-box">
                <div class="card-body d-flex align-items-center gap-3">
                    <span class="info-ikon bg-success"><i class="bi bi-cash-coin"></i></span>
                    <div><div class="text-muted small">Tahsilat</div><div class="fs-5 fw-semibold" data-ozet="tahsilat">–</div></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm info-box">
                <div class="card-body d-flex align-items-center gap-3">
                    <span class="info-ikon bg-danger"><i class="bi bi-hourglass-split"></i></span>
                    <div><div class="text-muted small">Açık Bakiye</div><div class="fs-5 fw-semibold" data-ozet="bakiye">–</div></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filtre paneli (açılır/kapanır) -->
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-body d-flex justify-content-between align-items-center"
             role="button" data-bs-toggle="collapse" data-bs-target="#filtrePanel">
            <span><i class="bi bi-funnel"></i> Filtre</span>
            <i class="bi bi-chevron-down"></i>
        </div>
        <div class="collapse show" id="filtrePanel">
            <form class="card-body row g-3" id="filtreForm">
                <div class="col-md-3">
                    <label class="form-label" for="fArama">Ara</label>
                    <input type="search" class="form-control" id="fArama" name="arama" placeholder="Sipariş no veya müşteri">
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="fMusteri">Müşteri</label>
                    <select class="form-select" id="fMusteri" name="musteri_id"></select>
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="fDurum">Durum</label>
                    <select class="form-select" id="fDurum" name="durum_id"></select>
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="fTarihBas">Başlangıç</label>
                    <input type="date" class="form-control" id="fTarihBas" name="tarih_bas">
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="fTarihBit">Bitiş</label>
                    <input type="date" class="form-control" id="fTarihBit" name="tarih_bit">
                </div>
                <div class="col-12 d-flex gap-2 justify-content-end">
                    <button type="reset" class="btn btn-outline-secondary" id="filtreTemizle"><i class="bi bi-x-lg"></i> Temizle</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-search"></i> Filtrele</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <table class="table table-hover align-middle w-100" id="siparisTablosu">
                <thead>
                <tr>
                    <th>Sipariş No</th>
                    <th>Tarih</th>
                    <th>Müşteri</th>
                    <th>Durum</th>
                    <th class="text-end">Tutar</th>
                    <th class="text-end">Ödenen</th>
                    <th class="text-end">Kalan</th>
                    <th>Son Ödeme</th>
                </tr>
                </thead>
            </table>
        </div>
    </div>
</div>

<!-- Bildirim (toast) alanı -->
<div class="toast-container position-fixed bottom-0 end-0 p-3" id="bildirimAlani"></div>

<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/2.1.8/js/dataTables.min.js"></script>
<script src="https://cdn.datatables.net/2.1.8/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="assets/siparis-liste.js"></script>
</body>
</html>
