# Kalkulator Kenaikan Gaji 2026

Aplikasi berbasis web ini dirancang untuk menyimulasikan persentase dan nominal kenaikan gaji karyawan berdasarkan beberapa variabel yang telah ditentukan (seperti inflasi, market adjustment, faktor performa, dan level/golongan). Selain rincian kenaikan gaji pokok, aplikasi ini juga memperhitungkan *Commuting Allowance* terbaru serta *Tunjangan Jabatan* untuk memproyeksikan total Take Home Pay (THP).

## ✨ Fitur Utama

- **Kalkulasi Dinamis**: Menghitung kenaikan gaji pokok berdasarkan Level/Golongan (CL1.1 hingga CL2), Faktor GPMS (Performance), dan Adjustment.
- **Rincian Tunjangan**: Menghitung secara otomatis *Commuting Allowance* baru dan *Tunjangan Jabatan* sesuai level atau jabatan (contoh: Group Leader, Part Leader, dll).
- **Hasil Terperinci**: Menampilkan perbandingan dan rincian persentase kenaikan (Inflasi, Adjustment, GPMS), total kenaikan, gaji pokok baru, dan perhitungan gap total Take Home Pay (THP).
- **Admin History Panel**: Berisi riwayat seluruh perhitungan kenaikan gaji. Dilengkapi dengan pengamanan password sederhana serta pencatatan otomatis nama PC/Hostname (melalui IP Address pengguna) beserta waktu perhitungannya.
- **Modern & Interaktif UI**: Antarmuka bergaya material/Google yang bersih. Menggunakan Tailwind CSS untuk styling responsif secara instan, dan *Canvas Confetti* untuk animasi seru saat hasil dihitung.

## 🛠 Teknologi yang Digunakan

Aplikasi ini menggunakan pola *Single-File Application* yang mudah dipasang.
- **Backend**: Native PHP
- **Database**: SQLite (via PDO) -> File database terbuat secara otomatis (`history.sqlite`)
- **Frontend / UI**: HTML5, Vanilla JavaScript, Tailwind CSS (via CDN)
- **Animasi Tambahan**: Canvas Confetti

## 🚀 Instalasi & Cara Penggunaan

1. **Persyaratan Sistem**: Pastikan server lokal Anda telah terinstall **PHP** (direkomendasikan versi 7.4 ke atas) dan memiliki ekstensi **PDO SQLite** yang aktif.
2. **Setup File**: Pindahkan atau clone repository ini ke dalam direktori server lokal Anda (misal: `htdocs` untuk XAMPP, `www` untuk Laragon, `/var/www/html` untuk distro Linux).
3. **Konfigurasi Database (Otomatis)**: Anda tidak perlu melakukan setup SQL. Pada saat aplikasi *browser* diakses pertama kali, script akan otomatis membuat file `history.sqlite` dan menyiapkan skema tabel berserta perubahannya (migrations).
4. **Jalankan Aplikasi via Terminal (Alternatif)**:
   Jika Anda tidak menggunakan web server khusus, Anda juga bisa menjalankannya lewat *built-in server* bawaan PHP:
   ```bash
   php -S localhost:8000
   ```
   Lalu buka web browser dan kunjungi `http://localhost:8000/`.

## 🔒 Akses Riwayat (Admin History)

1. Pada halaman utama kalkulator, klik **Tombol Ikon Roda Gigi / Setting** di sudut kanan bawah.
2. Masukkan password default: **`admin123`**, lalu tekan Enter / Masuk.
3. Anda akan dapat melihat riwayat lengkap seluruh perhitungan gaji beserta gap kenaikan, rincian lama/baru, dan data *Nama PC* pengguna yang melakukan kalkulasi.

## 📝 Catatan Hak Akses Database (Linux / macOS)

Pastikan *web server user* (seperti `www-data` atau `nginx`) memiliki akses *write* (tulis) pada direktori / folder aplikasi, agar PHP dapat meng-create database `history.sqlite` dan melakukan proses insert dengan lancar tanpa error `readonly database`.

---
*Dibuat menggunakan antarmuka modern yang ditujukan untuk internal tim dan kemudahan kalkulasi. ✨*
