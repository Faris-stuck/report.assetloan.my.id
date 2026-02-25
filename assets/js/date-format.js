/**
 * Global Date Format Helper
 * Standarisasi format tanggal ke dd/mm/yyyy di seluruh sistem
 */

/**
 * Format tanggal menjadi dd/mm/yyyy
 * @param {string|Date|null} tanggal - tanggal dalam format apapun
 * @returns {string} - tanggal dalam format dd/mm/yyyy, atau '-' jika tidak valid
 */
function formatTanggal(tanggal) {
    if (!tanggal || tanggal === '-' || tanggal === null || tanggal === undefined) return '-';
    // Already formatted dd/mm/yyyy
    if (/^\d{2}\/\d{2}\/\d{4}/.test(String(tanggal))) return String(tanggal).substring(0, 10);
    const d = new Date(tanggal);
    if (isNaN(d.getTime())) return tanggal;
    const day = String(d.getDate()).padStart(2, '0');
    const month = String(d.getMonth() + 1).padStart(2, '0');
    const year = d.getFullYear();
    return `${day}/${month}/${year}`;
}

/**
 * Format tanggal + waktu menjadi dd/mm/yyyy HH:MM
 * @param {string|Date|null} tanggal - datetime dalam format apapun
 * @returns {string} - datetime dalam format dd/mm/yyyy HH:MM, atau '-' jika tidak valid
 */
function formatTanggalWaktu(tanggal) {
    if (!tanggal || tanggal === '-' || tanggal === null || tanggal === undefined) return '-';
    // Already formatted dd/mm/yyyy HH:MM
    if (/^\d{2}\/\d{2}\/\d{4} \d{2}:\d{2}/.test(String(tanggal))) return String(tanggal).substring(0, 16);
    const d = new Date(tanggal);
    if (isNaN(d.getTime())) return tanggal;
    const day = String(d.getDate()).padStart(2, '0');
    const month = String(d.getMonth() + 1).padStart(2, '0');
    const year = d.getFullYear();
    const hour = String(d.getHours()).padStart(2, '0');
    const min = String(d.getMinutes()).padStart(2, '0');
    return `${day}/${month}/${year} ${hour}:${min}`;
}

/**
 * Parse tanggal dd/mm/yyyy ke format yyyy-mm-dd (untuk input hidden / API request)
 * @param {string} tanggal - tanggal dalam format dd/mm/yyyy
 * @returns {string} - tanggal dalam format yyyy-mm-dd
 */
function tanggalToISO(tanggal) {
    if (!tanggal || tanggal === '-') return '';
    if (/^\d{4}-\d{2}-\d{2}/.test(tanggal)) return tanggal.substring(0, 10);
    const parts = tanggal.split('/');
    if (parts.length === 3) return `${parts[2]}-${parts[1]}-${parts[0]}`;
    return tanggal;
}
