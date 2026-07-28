function confirmCatalogReset(formId, confirmInputId) {
    if (typeof Swal === 'undefined') {
        if (window.confirm('Tüm ürün ve varyantlar silinecek. Emin misiniz?')) {
            document.getElementById(confirmInputId).value = 'SIFIRLA';
            document.getElementById(formId).submit();
        }
        return false;
    }

    Swal.fire({
        title: 'Katalog sıfırlansın mı?',
        html: 'Tüm <strong>ürünler</strong>, <strong>görseller</strong> ve <strong>varyantlar</strong> kalıcı olarak silinir.<br><span class="text-danger">Bu işlem geri alınamaz.</span>',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Evet, sıfırla',
        cancelButtonText: 'İptal',
        confirmButtonColor: '#dc3545',
        input: 'text',
        inputPlaceholder: 'Onaylamak için SIFIRLA yazın',
        inputAttributes: { autocapitalize: 'characters', autocorrect: 'off' },
        preConfirm: function (value) {
            if ((value || '').trim().toUpperCase() !== 'SIFIRLA') {
                Swal.showValidationMessage('Devam etmek için SIFIRLA yazmalısınız');
                return false;
            }
            return value;
        }
    }).then(function (result) {
        if (result.isConfirmed) {
            document.getElementById(confirmInputId).value = 'SIFIRLA';
            document.getElementById(formId).submit();
        }
    });

    return false;
}
