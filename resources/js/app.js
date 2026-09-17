// Dipakai bareng x-mask:dynamic="$money(...)" di input nominal Rupiah — Alpine mask cuma ngatur tampilan
// (menyisipkan pemisah ribuan), nilai aslinya (buat dikirim ke server) tetap perlu di-strip dulu dari
// pemisahnya. Global biar bisa dipakai di komponen Alpine manapun tanpa nulis ulang.
window.normalizeCurrency = function (value) {
    if (value === null || value === undefined || value === '') {
        return null;
    }

    // "." is the mask's thousands separator, "," is the decimal separator. Dropping
    // everything from the first "," before stripping keeps a stray ",00" from getting
    // concatenated into the integer (e.g. "500.000.000,00" turning into 50000000000).
    const withoutThousands = String(value).replace(/\./g, '');
    const integerPart = withoutThousands.split(',')[0];
    const digits = integerPart.replace(/[^0-9]/g, '');

    return digits === '' ? null : Number(digits);
};

/**
 * Echo exposes an expressive API for subscribing to channels and listening
 * for events that are broadcast by Laravel. Echo and event broadcasting
 * allow your team to quickly build robust real-time web applications.
 */

import './echo';
