# Catatan Pengembangan: Segmentasi Program Kheedma Academy Web

## 1. Latar Belakang

Berdasarkan hasil meeting Kheedma Academy Web pada **8 Juli 2026**, ditemukan bahwa struktur program pada web saat ini masih belum membedakan jenis program berdasarkan status user.

Saat ini, seluruh program masih tersimpan dan ditampilkan dalam satu alur yang sama. Sistem belum membedakan apakah sebuah program ditujukan untuk:

- User umum yang baru join atau belum pernah mengikuti kelas.
- Member yang sudah pernah mengikuti kelas.
- Member yang sudah masuk ke program komunitas atau affiliate community.

Dari hasil meeting, mulai dipahami bahwa program untuk user baru dan program untuk member yang sudah pernah mengikuti kelas tidak bisa digabung begitu saja. Keduanya memiliki tujuan, akses, dan tingkat kedalaman yang berbeda.

## 2. Temuan Utama

Kheedma Academy membutuhkan pemisahan antara **program perkenalan / pra-program** dan **program komunitas affiliate**.

### Program Perkenalan / Pra-Program

Program ini ditujukan untuk user umum yang belum pernah mengikuti kelas.

Karakteristiknya:

- Menjadi entry point awal untuk user baru.
- Bersifat promosional.
- Tidak terlalu detail atau mendalam.
- Bisa terdiri dari beberapa kelas perkenalan.
- Bertujuan mengenalkan Kheedma Academy dan mengarahkan user ke tahap berikutnya.

### Program Kheedma Affiliate Community

Program ini ditujukan untuk user yang sudah pernah mengikuti dan menyelesaikan program atau kelas sebelumnya.

Karakteristiknya:

- Bersifat lebih intensif.
- Memiliki pendampingan, misalnya program 30 hari.
- Ditujukan untuk member yang sudah memenuhi syarat.
- Tidak boleh tercampur dengan program perkenalan.
- Membutuhkan logic akses berdasarkan riwayat keikutsertaan user.

## 3. Masalah pada Sistem Saat Ini

Masalah utama saat ini adalah sistem belum memiliki pembeda yang jelas antara jenis program dan status user.

Akibatnya:

- Program untuk user baru dan program untuk member lama masih berpotensi tercampur.
- User bisa melihat program yang belum sesuai dengan status mereka.
- Admin belum memiliki kontrol yang cukup detail untuk menentukan siapa yang boleh mengakses program tertentu.
- Sistem belum memiliki konsep eligibility atau syarat akses berdasarkan riwayat user.
- Belum ada dasar yang jelas untuk membedakan user yang hanya baru join dengan user yang sudah pernah mengikuti dan menyelesaikan kelas.

## 4. Konsep Perubahan yang Dibutuhkan

Web app perlu mulai mengenali tahapan perjalanan user.

Secara sederhana, alurnya dapat dibagi menjadi:

1. **User baru / belum pernah ikut kelas**  
   User hanya bisa mengakses program perkenalan atau pra-program.

2. **User sudah mendaftar program**  
   Sistem mulai mencatat bahwa user pernah masuk ke salah satu program.

3. **User sudah menyelesaikan program**  
   User dapat dianggap eligible untuk mengakses program lanjutan atau komunitas affiliate.

4. **User masuk ke affiliate community**  
   User mendapatkan akses ke program komunitas yang lebih intensif dan spesifik.

## 5. Kebutuhan UI Frontend

Dari sisi frontend, tampilan program perlu menyesuaikan status user.

Untuk user yang belum pernah mengikuti kelas:

- Program perkenalan dapat ditampilkan dan bisa diakses.
- Program affiliate community boleh tetap ditampilkan sebagai promosi atau teaser.
- Tombol program affiliate dapat dibuat abu-abu / disabled.
- Jika diklik, sistem dapat menampilkan popup berisi penjelasan bahwa program tersebut hanya tersedia untuk member yang sudah menyelesaikan program tertentu.

Untuk user yang sudah menyelesaikan program:

- Program lanjutan dan affiliate community dapat mulai dibuka.
- Sistem dapat menampilkan tombol daftar atau akses program.
- User dapat melihat program yang sesuai dengan statusnya.

## 6. Kebutuhan Admin Panel

Admin panel perlu mendukung pengelolaan program yang lebih detail.

Beberapa kebutuhan yang kemungkinan diperlukan:

- Field kategori atau tipe program.
- Pengaturan target user untuk setiap program.
- Pengaturan apakah program terbuka untuk semua user atau hanya member tertentu.
- Pengaturan apakah program membutuhkan penyelesaian program sebelumnya.
- Status keikutsertaan user terhadap sebuah program.
- Status apakah user sudah selesai mengikuti program.
- Opsi untuk menampilkan program terkunci sebagai teaser.
- Pesan popup atau informasi akses jika user belum memenuhi syarat.

Untuk tahap awal, sistem tidak harus langsung kompleks. Admin cukup bisa mengelola tipe program dan status akses secara manual terlebih dahulu.

## 7. Data / Logic yang Perlu Dipertimbangkan

Beberapa struktur data atau logic yang perlu dipertimbangkan dalam codebase:

### Pada Program

- Program type / category.
- Target user.
- Access rule.
- Required previous program.
- Require completion status.
- Show as teaser.
- Locked message / popup message.
- Active / inactive status.

### Pada User

- Status member.
- Riwayat program yang pernah diikuti.
- Program yang sedang diikuti.
- Program yang sudah diselesaikan.
- Eligibility untuk program lanjutan.
- Eligibility untuk affiliate community.

### Pada Program Registration / Enrollment

- User ID.
- Program ID.
- Registration status.
- Attendance / participation status.
- Completion status.
- Completed at.
- Admin notes.

## 8. Rekomendasi Implementasi Awal

Untuk development awal, perubahan dapat dibuat bertahap agar tidak terlalu besar.

### Phase 1: Segmentasi Program

Fokus pada pemisahan program berdasarkan tipe:

- Program perkenalan.
- Program member.
- Program affiliate community.

Frontend mulai menampilkan program berdasarkan tipe dan status akses.

### Phase 2: Access Rule Sederhana

Tambahkan logic sederhana untuk menentukan apakah user boleh mengakses program tertentu.

Contoh logic:

- Semua user boleh melihat program perkenalan.
- User yang belum menyelesaikan program perkenalan belum bisa mengakses affiliate community.
- Program affiliate tetap bisa tampil sebagai teaser, tetapi tombolnya disabled.

### Phase 3: Completion Manual oleh Admin

Sebelum fitur absensi dibuat, admin dapat menandai user sebagai sudah selesai mengikuti program secara manual.

Status completion ini dapat menjadi dasar untuk membuka akses ke program berikutnya.

### Phase 4: Absensi / Completion Tracking

Jika dibutuhkan di tahap berikutnya, sistem dapat dikembangkan untuk mencatat absensi atau progress user sebagai dasar completion yang lebih valid.

## 9. Catatan untuk Diskusi dengan Claude Code

Dokumen ini perlu digunakan sebagai bahan diskusi dengan Claude Code untuk membaca struktur web app yang sudah ada saat ini.

Mohon analisis codebase yang sekarang, lalu bantu brainstorming berdasarkan struktur existing:

- Model, table, atau entity apa saja yang sudah tersedia dan bisa digunakan untuk mendukung segmentasi program ini.
- Bagian mana yang sebaiknya dimodifikasi tanpa membuat perubahan terlalu besar.
- Apakah lebih baik menambahkan field pada table program yang sudah ada, atau membuat table baru untuk program category / access rule.
- Bagaimana cara paling sederhana untuk membedakan program perkenalan, program member, dan program affiliate community.
- Bagaimana logic frontend sebaiknya dibuat agar program terkunci tetap bisa tampil sebagai teaser.
- Bagaimana admin panel dapat mengelola status user, program, dan eligibility dengan perubahan minimal.
- Apakah perlu membuat enrollment / registration status baru, atau cukup memakai struktur yang sudah tersedia.
- Ide implementasi bertahap yang paling aman berdasarkan codebase saat ini.

Tujuan utama brainstorming ini adalah mencari pendekatan paling realistis untuk menyesuaikan fitur program Kheedma Academy dengan kebutuhan baru, tanpa merombak sistem terlalu besar di tahap awal.
