# Athar — Arsitektur Request Engine (Milestone 1)

Dokumen ini menjelaskan **apa yang sudah dibangun** dan alasannya. Bagian bertanda _(belum dibangun)_ sudah
dirancang supaya skema dan API tetap koheren, tetapi belum ada kodenya.

## 1. Prinsip

1. **Sistem pencatat dulu, kecerdasan belakangan.** Tidak ada AI di V1. Tapi semuanya dirancang agar AI agent
   kelak hanya perlu memakai API yang sama.
2. **Setiap permintaan menjadi objek kerja:** siapa yang meminta, apa, kenapa, kategori, kapan, siapa
   penanggung jawab, status, apa yang terjadi padanya, dan kapan selesai.
3. **Tidak ada informasi operasional yang hanya hidup di chat.**
4. **Tidak ada hard delete.** Request diarsipkan (`deleted_at`), user dinonaktifkan, riwayat dipertahankan.

## 2. Lapisan (logika bisnis tidak ada di UI maupun controller)

```
Halaman web (React) ─┐
Mobile / WhatsApp    ├─►  Controller tipis     web: RequestPageController (Inertia)
AI agent kelak      ─┘                         api: Api\V1\* (JSON, Sanctum)
                                   │
                                   ▼
                     app/Actions/Requests/*    satu use-case = satu transaksi DB;
                                   │           SATU-SATUNYA jalan mengubah data
              ┌────────────────────┼─────────────────────┐
              ▼                    ▼                     ▼
      Workflow + Enums     Policies + Scope        Eloquent models
      (tabel transisi)     (siapa boleh apa)
```

- **Otorisasi diperiksa di dalam Action**, bukan di controller. Jadi tidak ada jalan pintas lewat API, web,
  command, atau (kelak) AI.
- **Server memberi tahu UI aksi apa yang boleh** (`permissions` dan `allowed_transitions` pada respons request).
  React menampilkan tombol dari situ dan tidak menduplikasi aturan apa pun.
- **Web dan API memakai `Resource`, `FormRequest`, dan `RequestListQuery` yang sama**, sehingga keduanya tidak
  mungkin berbeda pendapat. Ada test yang membuktikan daftar web = daftar API.

## 3. Skema database

```
departments 1───* users *───* roles          (role_user)
                    │
                    *───* teams              (team_user)

request_categories 1───* requests *───1 departments
users (requester) 1───* requests
requests 1───* assignments      riwayat penugasan; tepat satu baris "current"
requests 1───* activity_logs    append-only
counters                        urutan atomik untuk nomor request
```

_(belum dibangun)_ `comments`, `attachments` (berversi), `reviews`, `notifications`.

| Keputusan                                                                                            | Alasan                                                                                                                                               |
| ---------------------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Nomor request** `ATH-000001` dari `counters` dengan `lockForUpdate()` di dalam transaksi pembuatan | Tidak ada balapan antar pembuat; jika transaksi dibatalkan nomor tidak hangus (tanpa lubang). Unik di DB, dan **tidak bisa diubah** (model menolak). |
| `requests.assigned_team_id/assigned_user_id` mencerminkan penugasan aktif                            | Cepat untuk filter. Riwayat sebenarnya ada di `assignments`.                                                                                         |
| `assignments.is_current` bernilai `1` atau `NULL`, dengan unique `(request_id, is_current)`          | Database sendiri menjamin paling banyak **satu** penugasan aktif per request.                                                                        |
| `status` dan `priority` berupa `VARCHAR` + `CHECK`, bukan ENUM MySQL                                 | Portabel, dan nilai ngawur ditolak database.                                                                                                         |
| Foreign key **RESTRICT** di mana-mana                                                                | Riwayat mengunci request dan orangnya; menghapus baris induk ditolak database.                                                                       |
| Waktu disimpan **UTC**, ditampilkan di `Asia/Jakarta` (`config/athar.php`)                           | Tampilan bergantung zona; data tidak. Tenggat "30 Sep" berarti akhir hari 30 Sep WIB.                                                                |
| Kategori berupa **data** (kolom `name_id/name_en`, `guidance_*`)                                     | Admin bisa mengubahnya tanpa deploy. `guidance` = petunjuk khusus kategori di form (progressive disclosure).                                         |

### Activity log tidak bisa diubah (tiga lapis)

1. Tidak ada route untuk mengubah/menghapusnya.
2. Model `ActivityLog` melempar `ImmutableRecordException` saat `update`/`delete`.
3. **Trigger MySQL** `BEFORE UPDATE/DELETE` menolak walau lewat SQL mentah. Dibuat oleh `App\Support\AuditGuards`;
   periksa dengan `php artisan athar:audit-guards`. Jika server tidak mengizinkan trigger, migrasi tetap
   berhasil dan lapis 1–2 tetap berlaku (lihat README).

Setiap baris menyimpan: pelaku, aksi, field, **nilai lama**, **nilai baru**, catatan, waktu, dan `is_internal`
(requester tidak pernah melihat baris internal). Nilai lama/baru berupa teks yang terbaca (kode status, nama tim,
nama orang), sehingga riwayat tetap masuk akal walau nama diganti kemudian.

## 4. Workflow

```
NEW ─► TRIAGED ─► ASSIGNED ─► IN_PROGRESS ─► REVIEW ─► COMPLETED
                                   ▲            │
                                   └─ revisi ───┘   (lewat Review, belum dibangun)
ASSIGNED / IN_PROGRESS / REVIEW ─► BLOCKED ─► kembali ke status asalnya
NEW / TRIAGED / ASSIGNED / IN_PROGRESS / REVIEW / BLOCKED ─► CANCELLED
COMPLETED dan CANCELLED bersifat final.
```

Tabel transisi ada di satu tempat: `App\Enums\RequestStatus::transitions()`. `RequestWorkflow` menambahkan bagian yang
bergantung pada request tertentu (BLOCKED kembali ke `blocked_from_status`).

Penjaga:

- `IN_PROGRESS` dan `REVIEW` **wajib punya PIC**.
- `BLOCKED` dan `CANCELLED` **wajib ada alasan** (tercatat di activity log, terlihat oleh requester).
- Perpindahan mustahil (mis. `IN_PROGRESS → TRIAGED`) dijawab **422**, bukan 403.
- Menugaskan request `NEW` otomatis memilahnya dulu: dua entri log (`NEW→TRIAGED`, `TRIAGED→ASSIGNED`), jadi setiap
  transisi tetap eksplisit.
- Tiga perpindahan valid tetapi **dimiliki use-case lain**, jadi endpoint status menolaknya:
  `TRIAGED→ASSIGNED` (lewat penugasan) serta `REVIEW→COMPLETED` dan `REVIEW→IN_PROGRESS` (lewat Review).

Test `WorkflowTest` menguji **seluruh 64 pasangan status** terhadap tabel yang ditulis tangan dari spec (bukan
diturunkan dari kode).

## 5. Otorisasi

Peran: `REQUESTER`, `STAFF`, `TEAM_LEAD`, `ADMIN`, `MANAGEMENT` (satu user boleh punya beberapa).
Cakupan tim STAFF/TEAM_LEAD berasal dari keanggotaan tim. Semua keputusan ada di `ServiceRequestPolicy`.

**Visibilitas** (`ServiceRequest::scopeVisibleTo`, dipakai untuk daftar **dan** akses tunggal):

| Peran             | Melihat                                                        |
| ----------------- | -------------------------------------------------------------- |
| ADMIN, MANAGEMENT | semua request                                                  |
| TEAM_LEAD         | request timnya + request yang belum punya tim (antrean triage) |
| STAFF             | request yang ditugaskan ke dirinya atau timnya                 |
| siapa pun         | request yang ia buat sendiri                                   |

Request yang tidak boleh dilihat dijawab **404**, sama persis dengan request yang tidak ada, sehingga
keberadaannya tidak bocor. Yang terlihat tetapi tidak boleh diaksi dijawab **403**.

**Aksi** (_manager_ = ADMIN, atau TEAM_LEAD dari tim request itu; TEAM_LEAD mana pun bila request belum punya tim):

| Aksi                                         | Siapa                                                                                   |
| -------------------------------------------- | --------------------------------------------------------------------------------------- |
| Membuat request                              | REQUESTER, STAFF, TEAM_LEAD, ADMIN (MANAGEMENT hanya mengamati)                         |
| Ubah judul/deskripsi/kategori/tenggat        | pemilik selama `NEW`; manager selama belum final                                        |
| Ubah prioritas                               | pemilik selama `NEW`; **ADMIN** (override) selama belum final. TEAM_LEAD **tidak**      |
| Memilah (`NEW→TRIAGED`)                      | manager                                                                                 |
| Menugaskan / menugaskan ulang                | manager                                                                                 |
| Mulai, kirim ke review, blokir/lanjutkan     | PIC-nya, atau manager                                                                   |
| Membatalkan                                  | manager; atau pemilik selama `NEW`/`TRIAGED`/`ASSIGNED`. PIC **tidak** bisa membatalkan |
| Melihat catatan internal & riwayat penugasan | ADMIN, MANAGEMENT, TEAM_LEAD, STAFF — selain pemilik request itu                        |
| Semua perubahan                              | MANAGEMENT **tidak bisa** (hanya baca)                                                  |

Aturan penugasan: PIC harus **anggota aktif tim tujuan** dan berperan STAFF/TEAM_LEAD. Memindahkan ke tim lain saat
pekerjaan berjalan wajib menyebut PIC baru sekaligus. Penugasan hanya-orang (tanpa tim) diperbolehkan bila orang itu
tepat di satu tim (timnya disimpulkan); selain itu tim wajib disebut.

Akun yang dinonaktifkan langsung terkunci: tidak bisa login; sesi web yang masih berlaku dikeluarkan, dan token API yang masih berlaku ditolak (403).

## 6. API (`/api/v1`)

Autentikasi: `POST /api/v1/auth/token` (email+password → token Bearer 12 jam, dibatasi 5x/menit per email+IP),
lalu `Authorization: Bearer <token>`. Bahasa: header `Accept-Language: id|en`.

| Method & path                        | Fungsi                                                                                                                             |
| ------------------------------------ | ---------------------------------------------------------------------------------------------------------------------------------- |
| `POST /auth/token`                   | masuk, mendapat token                                                                                                              |
| `GET /auth/me` · `POST /auth/logout` | profil · cabut token                                                                                                               |
| `GET /categories` · `GET /teams`     | daftar untuk form                                                                                                                  |
| `GET /users?team_id=`                | orang yang bisa diberi tugas (ADMIN / TEAM_LEAD)                                                                                   |
| `GET /requests`                      | daftar yang boleh dilihat. Filter: `status, priority, category_id, team_id, mine, assigned_to_me, overdue, q, sort, dir, per_page` |
| `POST /requests`                     | buat request (mengembalikan `ATH-…`, `201`)                                                                                        |
| `GET /requests/{ref}`                | detail + `permissions` + `allowed_transitions`                                                                                     |
| `PATCH /requests/{ref}`              | ubah field (audit per field)                                                                                                       |
| `POST /requests/{ref}/assign`        | `{team_id?, user_id?, note?}`                                                                                                      |
| `POST /requests/{ref}/status`        | `{to_status, note?}`                                                                                                               |
| `GET /requests/{ref}/activity`       | riwayat (baris internal disembunyikan dari requester)                                                                              |
| `GET /requests/{ref}/assignments`    | riwayat penugasan (sisi pelaksana)                                                                                                 |

`{ref}` = id numerik **atau** nomor request (`ATH-000124`). Kesalahan validasi = `422` dengan pesan sesuai bahasa.
Daftar route lengkap: `php artisan route:list --path=api`.

Akun dengan **2FA aktif tidak bisa mendapat token lewat password saja** (akan melewati faktor kedua). Untuk
integrasi/AI agent kelak disarankan akun layanan dengan peran terbatas.

## 7. AI agent (masa depan, tidak ada di V1)

Agent hanyalah klien API lain dengan akun layanan berperan terbatas. Ia tidak menyentuh database. Tindakan berdampak
tinggi (menugaskan, membatalkan, menyelesaikan) kelak memerlukan persetujuan manusia di lapisan Action.

## 8. Penyimpangan dari spec & pertanyaan terbuka

Penyimpangan yang disengaja (perlu persetujuan pemilik produk):

1. **Requester belum bisa menyetujui penyelesaian.** Reviewer = manajer tim (team lead/admin) yang bukan PIC.
2. **Workflow didefinisikan di kode**, belum bisa diubah admin dari UI (spec §7 menyebut "Manage workflows").
   Prioritas juga tetap (LOW/NORMAL/HIGH/CRITICAL). Kategori, tim, dan departemen adalah data, tetapi layar
   adminnya belum ada.
3. **STAFF melihat pekerjaan timnya** (spec §7 hanya menyebut "assigned work"), karena dashboard staf butuh "Team Work".

Pertanyaan untuk pemilik produk:

- Bolehkah **requester menyetujui penyelesaian** ("Confirm completion where applicable")? Belum diasumsikan.
- Siapa **reviewer**? Usulan: TEAM_LEAD tim tersebut atau ADMIN, dan bukan PIC-nya sendiri.
- Bolehkah staf **mengambil sendiri** pekerjaan tim yang belum punya PIC (self-assign)? Saat ini tidak.
- Daftar **departemen** dan **tim** sungguhan Tazkia (yang ada hanyalah contoh awal).
- Perlukah kategori punya **tim tujuan bawaan** agar triage lebih cepat?

## 8b. Milestone 2–4 (sudah dibangun)

**Skema tambahan:** `comments`, `attachments`, `reviews` (satu migrasi), tabel `notifications` bawaan Laravel.
`comments` dan `attachments` append-only (model menolak ubah/hapus); `reviews` hanya boleh diputuskan **sekali**.
`attachments.kind` dan `reviews.decision` dijaga `CHECK` di database.

**Aksi (semuanya lewat Action + Policy, sama untuk web dan API):**

| Aksi                             | Aturan                                                                                                                                                                                                                                                                           |
| -------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `AddComment`                     | pemilik atau sisi pelaksana, selama request belum final. Catatan **internal** hanya oleh sisi pelaksana. Log mencatat _bahwa_ ada komentar, tanpa menyalin isinya                                                                                                                |
| `AddAttachment`                  | pemilik, PIC, atau manajer. Draf/revisi/hasil akhir hanya oleh PIC atau manajer. `FileGuard` memeriksa ekstensi + isi file + ukuran (`config/athar.php`). Path penyimpanan dibuat sistem (UUID), tidak mengandung input pengguna. Gagal di tengah jalan = file yatim dibersihkan |
| Unduh                            | `AttachmentDownloadController`: dicek per request; berkas internal untuk requester dijawab **404**; selalu `attachment` + `nosniff`                                                                                                                                              |
| `ChangeRequestStatus` → `REVIEW` | membuka putaran review baru (`reviews.round`) dan log `REVIEW_REQUESTED`. Melanjutkan request yang terhambat di REVIEW tidak membuat putaran baru                                                                                                                                |
| `DecideReview`                   | `reviewDecision`: request sedang `REVIEW`, pelaku adalah manajer, dan **bukan PIC**. Setujui → `COMPLETED`. Minta revisi (alasan wajib) → `IN_PROGRESS`. Putaran yang sudah diputuskan tidak bisa diubah                                                                         |

`REVIEW→COMPLETED` dan `REVIEW→IN_PROGRESS` kini hanya lewat `DecideReview`; endpoint status generik tetap menolaknya.

**Notifikasi.** Action hanya memancarkan `RequestNotable` setelah transaksi berhasil (`DB::afterCommit`); `SendRequestNotifications`
menentukan penerima (aturan di satu file); `RequestNotification` dikirim lewat kanal di `config('athar.notifications.channels')`
(`database`, `mail`). Pelaku tidak diberi tahu tentang aksinya sendiri, akun nonaktif dilewati, dan kegagalan kirim
hanya dilaporkan, tidak pernah membatalkan pekerjaan. Isi disimpan sebagai kode + parameter, sehingga tiap pembaca melihatnya
dalam bahasanya (email memakai `users.locale` penerima). Pengingat tenggat: `athar:notify-deadlines` (per jam; kunci dedupe
per tenggat/hari).

**Dashboard.** `DashboardQuery` mendefinisikan tiap kartu sebagai _filter daftar_; angkanya dihitung oleh `RequestListQuery` yang
sama dengan halaman daftar, jadi kartu dan daftar tujuan tidak mungkin berbeda (dijaga test untuk 9 kombinasi peran/perspektif).
"Jatuh tempo hari ini" memakai batas hari di zona `Asia/Jakarta`.

**Endpoint tambahan:** `GET|POST /requests/{ref}/comments`, `GET|POST /requests/{ref}/attachments`,
`GET /requests/{ref}/attachments/{id}/download`, `GET /requests/{ref}/reviews`, `POST /requests/{ref}/review`,
`GET /notifications`, `POST /notifications/{id}/read`, `POST /notifications/read-all`, `GET /dashboards/{requester|staff|management}`.

## 8c. Pengguna, prioritas berwarna, upload saat buka tiket, beban kerja

**Pengguna (`Actions/Admin/*`, `UserPolicy`).** `CreateUser`, `UpdateUser`, `ResetUserPassword`; hanya ADMIN aktif (Policy dicek di dalam
Action, sama untuk web `/admin/users` dan API `/api/v1/admin/users`). `UserRules` menjaga koherensi: minimal satu peran; MANAGEMENT
berdiri sendiri (peran khusus-lihat); STAFF/TEAM_LEAD wajib punya tim aktif; tim harus ada dan aktif. Pagar pengaman: tidak bisa
menonaktifkan diri sendiri, dan sistem tidak pernah tanpa admin aktif. Menonaktifkan/reset password langsung mencabut **token API dan
sesi web** orang itu. Password otomatis (14 karakter) dikembalikan **sekali** dan tidak ditulis ke audit. Tabel `audit_logs`
(`actor`, `subject`, `action`, `old_values`, `new_values` JSON) append-only: penjaga model + trigger MySQL (`AuditGuards` kini menjaga
`activity_logs` **dan** `audit_logs`; `athar:audit-guards` memeriksa keduanya).

**Prioritas berwarna.** Murni tampilan: LOW hijau, NORMAL kuning, HIGH oranye, CRITICAL merah (`lib/urgency.ts`, `PriorityStripe`,
`PriorityBanner`). Prioritas tetap dipilih manusia (spec §6); tidak ada perhitungan otomatis, dan warna tidak pernah menjadi satu-satunya
penanda (label tertulis ada).

**Upload saat membuka tiket.** `POST /requests` menerima `files[]` (maks. 5). `CreateRequest` memeriksa semua berkas dengan `FileGuard`
**sebelum** menyimpan apa pun, lalu membuat request + lampiran (jenis `BRIEF`) dalam satu transaksi. Jika gagal di tengah, baris di-rollback
dan berkas yang sudah tertulis dihapus; nomor request tidak terbuang.

**Beban kerja (`WorkloadBoard`).** Terbuka untuk semua pengguna login, tetapi payload hanya berisi **jumlah dan nama kategori**, tidak pernah
judul atau nomor request, sehingga transparansi kapasitas tidak membocorkan isi permintaan orang lain (test memastikannya). Tingkat warna
dari `config('athar.workload')`: hijau < 3, kuning 3-4, oranye 5-6, **merah ≥ 7 (lebih dari 6)**. Warna hanya informasi: `AssignRequest` tidak
pernah menolak penugasan karena beban. Panel penugasan menampilkan titik warna + jumlah tugas tiap orang. Endpoint: `GET /api/v1/workload`.

## 9. Urutan berikutnya

1. Lampiran (penyimpanan privat, validasi tipe/ukuran, berversi)
2. Komentar (publik vs internal)
3. Review + riwayat revisi (membuka `REVIEW → COMPLETED / IN_PROGRESS`)
4. Notifikasi (antarmuka penyedia: in-app dulu, lalu email, WhatsApp kelak)
5. Dashboard (Requester / Staff / Management)
6. Layar admin (user, tim, kategori) dan audit untuk perubahan admin
