<p align="center">
  <img src="public/images/kheedma-academy-horizontal.png" width="440" alt="Kheedma Academy">
</p>

<h3 align="center">Serving with Purpose, Growing with Barakah</h3>

<p align="center">
  Muslim growth partner yang membimbing pemula tumbuh menjadi<br>
  affiliate marketer yang amanah dan profesional.
</p>

<p align="center">
  <a href="https://kheedma.hyperscore.cloud"><img src="https://img.shields.io/badge/live%20preview-kheedma.hyperscore.cloud-14786e?style=for-the-badge&labelColor=05312b" alt="Live preview"></a>
</p>

<p align="center">
  <img src="https://img.shields.io/badge/Laravel-13-FF2D20?style=flat-square&logo=laravel&logoColor=white" alt="Laravel 13">
  <img src="https://img.shields.io/badge/PHP-8.4-777BB4?style=flat-square&logo=php&logoColor=white" alt="PHP 8.4">
  <img src="https://img.shields.io/badge/Vue-3-4FC08D?style=flat-square&logo=vuedotjs&logoColor=white" alt="Vue 3">
  <img src="https://img.shields.io/badge/Tailwind%20CSS-4-06B6D4?style=flat-square&logo=tailwindcss&logoColor=white" alt="Tailwind CSS 4">
  <img src="https://img.shields.io/badge/Vite-8-646CFF?style=flat-square&logo=vite&logoColor=white" alt="Vite 8">
  <img src="https://img.shields.io/badge/status-v1%20preview-f59c26?style=flat-square" alt="Status">
</p>

---

## ✦ Tentang

**Kheedma Academy** adalah bagian dari Kheedma, sebuah agency berbasis nilai Islam.
Academy hadir untuk membimbing individu, dari nol, melewati masa observasi terbimbing,
lalu mengantar yang sungguh-sungguh untuk berkembang dengan cara yang **halal, terukur,
dan berkah**. Bukan kursus "cepat kaya", tetapi sebuah proses yang dibimbing.

> Khidmat · Amanah · Itqan · Barakah

## ✦ Yang ada di v1

- 🌿 **Situs promosi** yang menceritakan kisah dan nilai Kheedma Academy
- 📝 **Formulir pendaftaran** dengan tugas pra-seleksi (menyaring kesungguhan, bukan uang)
- 🛠️ **Panel admin** untuk mencatat pelamar, cohort, mentor, dan perjalanan setiap peserta
- 🗂️ **Fondasi data relasional** sebagai sumber kebenaran sejak hari pertama

## ✦ Roadmap

| Tahap | Fokus | Status |
|:-----:|-------|:------:|
| **Layer 1** | Situs promosi + formulir pendaftaran | 🚧 Berjalan |
| **Layer 2** | Panel admin (operasional cohort) | ⏳ Berikutnya |
| **Layer 3+** | Penyempurnaan iteratif setelah cohort nyata | 🔭 Nanti |

## ✦ Teknologi

Dibangun di atas **Laravel 13** dengan situs publik server-rendered (**Blade + Tailwind v4**)
dan panel admin sebagai **SPA Vue 3**. Aset dibundel dengan **Vite**, identitas visual
mengikuti *Brand Guidelines* Kheedma (teal & orange, Syncopate & Montserrat).

## ✦ Menjalankan secara lokal

Proyek ini berjalan di atas **Laragon**.

```bash
composer install
npm install
cp .env.example .env && php artisan key:generate
php artisan migrate
npm run dev
```

Akses di **http://kheedma-academy.local** (publik) dan **/admin** (panel admin).

## ✦ Deployment

Deploy ke VPS memakai pola atomic (zero-downtime). Lihat **[`docs/deploy/README.md`](docs/deploy/README.md)**.

---

<p align="center">
  <sub>© Kheedma Academy · Khidmat · Amanah · Itqan · Barakah</sub>
</p>
