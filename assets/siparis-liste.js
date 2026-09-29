$(function () {
    const AJAX = 'ajax/siparis-liste.php';
    const $form = $('#filtreForm');
    const para = new Intl.NumberFormat('tr-TR', { style: 'currency', currency: 'TRY' });
    const sayi = new Intl.NumberFormat('tr-TR');

    function bildirim(tur, mesaj) {
        const $t = $(`
            <div class="toast align-items-center text-bg-${tur} border-0" role="alert">
                <div class="d-flex">
                    <div class="toast-body"></div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            </div>`);
        $t.find('.toast-body').text(mesaj);
        $('#bildirimAlani').append($t);
        new bootstrap.Toast($t[0], { delay: 4000 }).show();
        $t.on('hidden.bs.toast', () => $t.remove());
    }

    function filtreler() {
        return Object.fromEntries(new FormData($form[0]));
    }

    function hataGoster(xhr) {
        bildirim('danger', xhr.responseJSON?.hata ?? 'İstek başarısız.');
    }

    // Durum listesi DB'den gelir
    $.post(AJAX, { islem: 'durumlar' }, function (durumlar) {
        $('#fDurum').select2({
            theme: 'bootstrap-5',
            data: durumlar,
            placeholder: 'Tümü',
            allowClear: true,
        }).val(null).trigger('change');
    }).fail(hataGoster);

    // Müşteri listesi büyük: Select2 ajax araması
    $('#fMusteri').select2({
        theme: 'bootstrap-5',
        placeholder: 'Tümü',
        allowClear: true,
        minimumInputLength: 2,
        ajax: {
            url: AJAX,
            type: 'POST',
            delay: 250,
            data: p => ({ islem: 'musteri_ara', q: p.term }),
        },
    });

    // Özet sayımlar tablo sorgusundan ayrı çekilir
    function loadStats() {
        $.post(AJAX, { islem: 'istatistik', ...filtreler() }, function (o) {
            $('[data-ozet="adet"]').text(sayi.format(o.adet));
            $('[data-ozet="ciro"]').text(para.format(o.ciro));
            $('[data-ozet="tahsilat"]').text(para.format(o.tahsilat));
            $('[data-ozet="bakiye"]').text(para.format(o.bakiye));
        }).fail(hataGoster);
    }

    const tablo = $('#siparisTablosu').DataTable({
        serverSide: true,
        processing: true,
        scrollX: true,
        layout: { topEnd: null },             // global arama kapalı (v1: dom 'lrtip'), filtre paneli kullanılır
        order: [[1, 'desc']],
        pageLength: 25,
        lengthMenu: [10, 25, 50, 100],
        language: { url: 'https://cdn.datatables.net/plug-ins/2.1.8/i18n/tr.json' },
        ajax: {
            url: AJAX,
            type: 'POST',
            data: d => Object.assign(d, { islem: 'liste' }, filtreler()),
            error: hataGoster,
        },
        columns: [
            { data: 'Siparisler_No', className: 'fw-semibold', render: DataTable.render.text() },
            { data: 'Siparisler_Tarih' },
            { data: 'Musteriler_Ad', render: DataTable.render.text() },
            {
                data: 'SiparisDurumlari_Ad',
                render: (v, t, r) => `<span class="badge text-bg-${r.SiparisDurumlari_Renk}">${$('<i>').text(v).html()}</span>`,
            },
            { data: 'Siparisler_Tutar', className: 'text-end', render: v => para.format(v) },
            { data: 'Odenen', className: 'text-end', orderable: false, render: v => para.format(v) },
            {
                data: null, className: 'text-end', orderable: false,
                render: r => {
                    const kalan = r.Siparisler_Tutar - r.Odenen;
                    return `<span class="${kalan > 0 ? 'text-danger' : 'text-success'}">${para.format(kalan)}</span>`;
                },
            },
            { data: 'SonOdeme', orderable: false, render: (v, t, r) => v ? `${v} <small class="text-muted">(${r.OdemeSayisi})</small>` : '–' },
        ],
    });

    tablo.one('draw', loadStats);

    $form.on('submit', function (e) {
        e.preventDefault();
        tablo.ajax.reload();
        loadStats();
    });

    $('#filtreTemizle').on('click', function () {
        setTimeout(() => {
            $('#fDurum, #fMusteri').val(null).trigger('change');
            $form.trigger('submit');
        });
    });
});
