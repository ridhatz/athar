# Athar (أثر) — Request Engine

> Dari amanah menjadi aksi. _From amanah to action._

Athar adalah sistem **manajemen pekerjaan & layanan**: setiap permintaan (desain, konten, IT support, dll.)
menjadi objek kerja yang bernomor, punya penanggung jawab, status, dan riwayat yang tidak bisa diubah.
Menggantikan permintaan yang tercecer di WhatsApp, email, dan pesan lisan.

**Status: Milestone 1 (vertical slice) selesai dan teruji.** Lihat [Apa yang sudah jadi](#apa-yang-sudah-jadi)
dan [Yang belum dikerjakan](#yang-belum-dikerjakan).

Stack: **Laravel 13**, MySQL, Inertia + React + Tailwind (starter kit resmi Laravel), Pest.

---

## Menjalankan di laptop ini (Windows + WAMP)

`php` **tidak ada di PATH** Windows kamu (PATH menunjuk `C:\xampp\php` yang tidak ada), dan Node baru
dipasang. Jalankan skrip ini di setiap jendela PowerShell baru; ia hanya mengubah jendela itu, bukan sistem:

```powershell
. .\scripts\dev-env.ps1
```

### 1. Nyalakan MySQL

Cara biasa: buka **WampServer** sampai ikonnya hijau.

Jika service MySQL WAMP tidak mau start (butuh hak Administrator), jalankan langsung tanpa admin:

```powershell
& 'C:\wamp64\bin\mysql\mysql9.1.0\bin\mysqld.exe' --defaults-file='C:\wamp64\bin\mysql\mysql9.1.0\my.ini' --console
```

Biarkan jendela itu terbuka. Menghentikannya dengan rapi:

```powershell
& 'C:\wamp64\bin\mysql\mysql9.1.0\bin\mysqladmin.exe' -uroot shutdown
```

### 2. Pertama kali saja

```powershell
composer install
npm ci
copy .env.example .env
php artisan key:generate
```

Lalu isi `DB_PASSWORD` di `.env`, dan buat database + user khusus (jangan pakai `root`):

```sql
CREATE DATABASE athar CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE athar_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'athar'@'localhost' IDENTIFIED BY 'GANTI-DENGAN-PASSWORD-KUAT';
CREATE USER 'athar'@'127.0.0.1' IDENTIFIED BY 'GANTI-DENGAN-PASSWORD-KUAT';
GRANT ALL ON athar.* TO 'athar'@'localhost', 'athar'@'127.0.0.1';
GRANT ALL ON athar_test.* TO 'athar'@'localhost', 'athar'@'127.0.0.1';
```

```powershell
php artisan migrate --seed
npm run build
```

`--seed` mengisi data referensi (role, 9 kategori, 5 tim, departemen) **dan, hanya jika `APP_ENV=local`**, akun demo.

### 3. Sehari-hari

```powershell
php artisan serve          # lalu buka http://127.0.0.1:8000
npm run dev                # opsional: hot-reload saat mengubah tampilan (biasanya cukup: npm run build)
```

### Akun demo (hanya di lokal; password semuanya `password`)

| Email                                 | Peran                      | Tim      |
| ------------------------------------- | -------------------------- | -------- |
| `sari@athar.test`                     | Requester (Humas)          | –        |
| `hendra@athar.test`                   | Requester (Akademik)       | –        |
| `admin@athar.test`                    | Admin                      | –        |
| `rina@athar.test`                     | Team Lead                  | Creative |
| `agus@athar.test`                     | Team Lead                  | IT       |
| `ahmad@athar.test`, `budi@athar.test` | Staff / PIC                | Creative |
| `dewi@athar.test`                     | Staff / PIC                | IT       |
| `manajemen@athar.test`                | Management (hanya melihat) | –        |

> Seeder demo **menolak berjalan** kalau `APP_ENV` bukan `local`, supaya akun berpassword umum tidak pernah
> ikut ke server sungguhan.

**Coba alurnya:** login Sari → _Buat permintaan_ → login Admin → tugaskan ke Creative + Ahmad →
login Ahmad → _Mulai dikerjakan_ → _Kirim untuk direview_ → login Sari dan lihat statusnya.

---

## Membuat pengguna sungguhan

Tidak ada pendaftaran publik. Akun dibuat administrator:

```powershell
php artisan athar:create-user ahmad@tazkia.ac.id --name="Ahmad Fauzi" --role=STAFF --team=Creative --department=Humas
```

Password ditanyakan lewat prompt (tidak lewat opsi, supaya tidak tersimpan di riwayat terminal).
Peran: `REQUESTER`, `STAFF`, `TEAM_LEAD`, `ADMIN`, `MANAGEMENT` (bisa diulang: `--role=STAFF --role=TEAM_LEAD`).

Menonaktifkan akun (pengguna **tidak dihapus** agar riwayat tetap menunjuk ke orang sungguhan):

```powershell
php artisan tinker --execute="App\Models\User::where('email','ahmad@tazkia.ac.id')->update(['is_active'=>false]);"
```

Layar admin untuk kelola user/tim/kategori belum ada (lihat bagian bawah).

---

## Pengujian

```powershell
php artisan test        # 406 test, memakai database athar_test (bukan database kerja)
npm run types:check     # TypeScript
npm run check           # lint + format
php vendor\bin\pint     # gaya kode PHP
```

Test memakai MySQL sungguhan (bukan SQLite) karena penguncian nomor request dan trigger audit harus diuji di
mesin yang sama dengan produksi.

## Pemeriksaan keamanan audit trail

```powershell
php artisan athar:audit-guards            # cek apakah database menolak UPDATE/DELETE pada activity_logs
php artisan athar:audit-guards --install  # pasang trigger yang hilang
```

MySQL dengan _binary logging_ aktif menolak user non-SUPER membuat trigger (error 1419). Jika itu terjadi di
server produksi, migrasi **tidak gagal**: pelindung di level aplikasi tetap aktif dan hal ini dicatat di log.
Minta admin database menyalakan `log_bin_trust_function_creators=1` atau menjalankan perintah `--install` sekali.

---

## Peta kode

```
app/
  Enums/          RequestStatus (tabel transisi workflow), RequestPriority, RoleCode, ActivityAction
  Models/         ServiceRequest (tabel `requests`), Assignment, ActivityLog, User, Team, ...
  Workflow/       RequestWorkflow  — aturan workflow murni (tanpa database/HTTP)
  Policies/       ServiceRequestPolicy — SEMUA keputusan "siapa boleh apa"
  Actions/Requests/  CreateRequest, UpdateRequest, AssignRequest, ChangeRequestStatus
                     (satu use-case = satu transaksi; satu-satunya jalan mengubah data)
  Services/       RequestNumberGenerator (ATH-000001), ActivityLogger
  Queries/        RequestListQuery, ActivityFeed
  Http/
    Controllers/Api/V1/   REST API   (/api/v1/...)
    Controllers/          RequestPageController — halaman web (Inertia)
    Resources/            bentuk data request, dipakai web DAN API
resources/js/     Halaman React: pages/requests/*, components/athar/*
lang/             id.json, en.json — satu kamus untuk backend & React
database/         migrations, seeders (ReferenceDataSeeder, DemoDataSeeder)
docs/ARCHITECTURE.md   desain lengkap: skema, workflow, matriks izin, kontrak API
```

Aturan emas: **controller tidak boleh berisi aturan bisnis**. Aturan ada di `Actions` dan `Policies`, sehingga
web, API, mobile, dan kelak AI agent memakai aturan yang sama persis.

---

## Apa yang sudah jadi

Seluruh alur spec §25/§26, teruji otomatis dan dicoba di browser sungguhan:

login → buat request → `ATH-000001` → Admin menugaskan tim/PIC → PIC mengerjakan → **kirim untuk review** →
reviewer **setujui atau minta revisi** → selesai. Requester melihat progres, dan seluruh riwayat tersimpan.

- **Lampiran:** disimpan di disk privat (bukan `public/`), hanya bisa diunduh lewat rute yang dicek izinnya;
  ekstensi **dan** isi file sebenarnya divalidasi (skrip yang diganti nama jadi `.png` ditolak; SVG/HTML tidak
  diizinkan); file dengan nama sama menjadi versi berikutnya, tidak pernah menimpa.
- **Diskusi:** komentar publik dan **catatan internal** (tidak terlihat requester), boleh bersama satu berkas.
- **Review:** setiap pengiriman ke review = satu _putaran_; riwayat revisi tidak pernah ditimpa. Reviewer harus
  manajer tim (team lead/admin) dan **bukan PIC-nya sendiri**.
- **Notifikasi:** lonceng di header + email, untuk penugasan, perubahan status, permintaan review/revisi,
  selesai, dan pengingat tenggat. Kanal diatur di `config/athar.php`, jadi WhatsApp kelak cukup menambah satu kanal.
- **Dashboard:** tiga sudut pandang (Requester, Staff, Management). Angka di kartu dihitung oleh query yang sama
  dengan daftar tujuannya, sehingga selalu cocok saat diklik.
- Peran & visibilitas (404 untuk yang tidak boleh lihat), riwayat penugasan, audit trail tak bisa diubah,
  API `/api/v1`, UI dua bahasa (ID/EN).

- **Pengguna (khusus ADMIN):** menu **Pengguna** untuk menambah orang dengan peran yang sesuai (Pemohon, Staf/PIC,
  Ketua Tim, Admin, Manajemen), mengubah peran/tim/departemen, menonaktifkan-mengaktifkan akun, dan reset password.
  Password dibuatkan otomatis dan **tampil sekali**; tidak pernah disimpan dalam bentuk terbaca. Aturan: Manajemen
  tidak boleh digabung peran lain, Staf/Ketua Tim wajib punya tim, admin tidak bisa menonaktifkan dirinya, dan selalu
  tersisa minimal satu admin aktif. Setiap perubahan tercatat di `audit_logs` (tak bisa diubah, tanpa password).
- **Bar warna prioritas:** setiap tugas berbar hijau (Rendah), kuning (Normal), oranye (Tinggi), merah (Kritis) di daftar
  dan halaman detail. Warna mengikuti prioritas yang **dipilih manusia** dan selalu disertai tulisan.
- **Upload saat membuka tiket:** form buat request menerima sampai 5 berkas (PDF, dokumen, gambar, dst.) untuk semua
  kategori. Semua diperiksa dulu; satu berkas ditolak = request tidak dibuat sama sekali (tanpa nomor terbuang).
- **Beban kerja (menu "Beban kerja"):** semua orang bisa melihat siapa mengerjakan apa (hanya **kategori**, mis.
  "Website, Desain", bukan isi request). Bar hijau/kuning/oranye/**merah untuk lebih dari 6 tugas**. Merah adalah
  peringatan, bukan batas: tugas tetap bisa diminta dan diberikan. Batas warna: `ATHAR_WORKLOAD_*_FROM` di `.env`.

### Pengingat tenggat (scheduler)

Pengingat "segera jatuh tempo" dan "terlambat" dikirim oleh `php artisan athar:notify-deadlines` (dijadwalkan
tiap jam, satu pengingat per tenggat, "terlambat" sekali sehari). Agar berjalan, scheduler Laravel harus hidup:

```powershell
php artisan schedule:work        # saat pengembangan
```

Di server: cron `* * * * * php /path/ke/athar/artisan schedule:run`. Email terkirim sungguhan setelah SMTP diatur di `.env`
(`MAIL_MAILER=smtp`, dst.); selama `MAIL_MAILER=log`, email hanya ditulis ke log. Batas kanal: `ATHAR_NOTIFY_CHANNELS=database`
untuk mematikan email.

## Yang belum dikerjakan

1. **Layar admin untuk tim, kategori, dan departemen** (pengguna sudah ada). Sementara ini lewat database.
2. **Terjemahan dua bahasa** untuk halaman **Pengaturan** dan lupa/reset password (masih teks bawaan starter kit).
3. Belum ada: WhatsApp, AI agent, analitik lanjutan, aplikasi mobile (di luar V1 menurut spec).

Asumsi yang saya ambil dan mudah diubah (lihat `docs/ARCHITECTURE.md` §8): requester **belum** bisa menyetujui
penyelesaian; staf **belum** bisa mengambil sendiri pekerjaan tim; departemen dan tim di seeder hanya contoh.

## Catatan penting

- **Folder ini ada di OneDrive.** `vendor/` dan `node_modules/` berisi puluhan ribu file yang akan ikut disinkronkan.
  Database aman karena MySQL berada di luar OneDrive. Sebaiknya jadikan repo Git dan jangan bergantung pada sinkronisasi OneDrive.
- **Backup:** yang harus dicadangkan adalah database `athar` (mis. `mysqldump`) dan file `.env`.
- **`.env` tidak boleh masuk Git** (sudah di `.gitignore`). Isinya password database dan `APP_KEY`.
- **Git:** database Git dipisah dari OneDrive (`git init --separate-git-dir`), jadi aman dari korupsi sinkronisasi.
