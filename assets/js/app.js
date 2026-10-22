document.addEventListener("DOMContentLoaded", function () {
    updateClock();
    setInterval(updateClock, 1000);
    getJadwalSholat();
});

// 1. Jam Digital & Hijriah
function updateClock() {
    const now = new Date();
    const jam = String(now.getHours()).padStart(2, '0');
    const menit = String(now.getMinutes()).padStart(2, '0');
    const detik = String(now.getSeconds()).padStart(2, '0');
    
    document.getElementById('jam').innerText = `${jam}:${menit}:${detik}`;

    const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
    document.getElementById('tanggal-masehi').innerText = now.toLocaleDateString('id-ID', options);
}

// 2. Fetch API Jadwal Sholat & Hijriah (Jakarta Timur)
async function getJadwalSholat() {
    try {
        const response = await fetch('https://api.aladhan.com/v1/timingsByCity?city=Jakarta%20Timur&country=Indonesia&method=11');
        const data = await response.json();
        const timings = data.data.timings;
        const hijri = data.data.date.hijri;

        document.getElementById('subuh').innerText = timings.Fajr;
        document.getElementById('syuruq').innerText = timings.Sunrise;
        document.getElementById('dzuhur').innerText = timings.Dhuhr;
        document.getElementById('ashar').innerText = timings.Asr;
        document.getElementById('maghrib').innerText = timings.Maghrib;
        document.getElementById('isya').innerText = timings.Isha;

        // Tampilkan Kalender Hijriah
        document.getElementById('tanggal-hijriah').innerText = `${hijri.day} ${hijri.month.ar} / ${hijri.month.en} ${hijri.year} H`;
    } catch (error) {
        console.error("Gagal mengambil jadwal sholat:", error);
    }
}