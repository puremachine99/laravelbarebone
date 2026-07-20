// Dipakai bareng x-mask:dynamic="$money(...)" di input nominal Rupiah — Alpine mask cuma ngatur tampilan
// (menyisipkan pemisah ribuan), nilai aslinya (buat dikirim ke server) tetap perlu di-strip dulu dari
// pemisahnya. Global biar bisa dipakai di komponen Alpine manapun tanpa nulis ulang.
window.normalizeCurrency = function (value) {
    if (value === null || value === undefined || value === '') {
        return null;
    }

    const digits = String(value).replace(/[^0-9]/g, '');

    return digits === '' ? null : Number(digits);
};
