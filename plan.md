# Plan Development — Invoice Generator System (AS Stuff)

## 1. Ringkasan Project

Sistem web internal untuk generate invoice digital, dengan format mengikuti contoh invoice "AS Stuff" yang sudah ada. Sistem memiliki 2 level user (**Admin** dan **Super Admin**), nomor invoice unik, serta QR code untuk verifikasi keaslian invoice secara publik.

**Asumsi teknis (mohon konfirmasi bila tidak sesuai):**
- Frontend: HTML + CSS + JavaScript murni (tanpa framework, sesuai permintaan "sederhana")
- Backend: **PHP native** (bawaan XAMPP, tanpa framework besar seperti Laravel) — paling ringan untuk dijalankan di XAMPP
- Database: MySQL/MariaDB (bawaan XAMPP)
- QR Code: library JS `qrcode.js` (generate QR di sisi browser saat invoice ditampilkan/dicetak)
- Web server dev: Apache (XAMPP) dengan SSL/TLS diaktifkan secara lokal

---

## 2. Role & Hak Akses

| Fitur | Admin | Super Admin |
|---|---|---|
| Login | ✅ | ✅ |
| Generate invoice | ✅ | ✅ |
| Lihat history invoice milik sendiri | ✅ | ✅ |
| Lihat history invoice **semua admin** | ❌ | ✅ |
| Tambah/hapus/nonaktifkan user admin | ❌ | ✅ |
| Ubah data perusahaan (nama, alamat, logo, payment info) | ❌ | ✅ |
| Verifikasi invoice via QR (halaman publik) | Siapa saja (tanpa login) | Siapa saja (tanpa login) |

---

## 3. Struktur Folder

```
/invoice-system
├── /config
│   ├── config.php          # secret key, base URL, env setting (TIDAK boleh public)
│   └── database.php        # koneksi PDO
├── /includes
│   ├── auth.php             # session & role middleware
│   ├── functions.php        # helper (format rupiah, sanitasi, dsb)
│   ├── invoice-number.php   # generator nomor invoice unik
│   └── signature.php        # generate & verifikasi HMAC signature
├── /public                  # document root (yang diakses browser)
│   ├── index.php            # halaman login
│   ├── logout.php
│   ├── dashboard.php
│   ├── generate-invoice.php
│   ├── history.php
│   ├── invoice-print.php    # tampilan invoice final + QR
│   ├── verify.php           # halaman verifikasi publik (tanpa login)
│   ├── /superadmin
│   │   ├── users.php
│   │   ├── company-settings.php
│   │   └── all-history.php
│   └── /assets
│       ├── /css
│       ├── /js
│       │   └── qrcode.min.js
│       └── /uploads
│           └── /logo
├── /sql
│   └── schema.sql
└── plan.md
```

---

## 4. Desain Tampilan (UI Style)

Tampilan dibuat **simple & monochrome** (hitam, putih, abu-abu — tanpa warna mencolok) supaya terlihat profesional dan ringan untuk dikerjakan.

- **Font**: [Plus Jakarta Sans](https://fonts.google.com/specimen/Plus+Jakarta+Sans) (via Google Fonts atau file font lokal di `/assets/fonts` agar tetap jalan walau offline)
- **Palet warna**: hitam (`#111111`) untuk teks utama, putih (`#FFFFFF`) untuk background, abu-abu (`#6B6B6B`, `#E5E5E5`, `#F5F5F5`) untuk border/section/teks sekunder — tanpa warna lain (tombol, badge, tabel semua memakai skala abu-abu ini)
- **Komponen UI** (login, dashboard, form, tabel history) dan **template invoice** memakai font & palet yang sama, supaya konsisten satu sistem
- **Layout**: minim ornamen, banyak whitespace, border tipis (1px abu-abu) untuk pemisah, tanpa shadow/gradient berlebihan
- Logo perusahaan tetap tampil dengan warna aslinya (tidak di-grayscale), karena itu identitas brand pada invoice — hanya elemen UI/teks di sekitarnya yang monochrome

---

## 5. Desain Database

### Tabel `users`
| Kolom | Tipe | Keterangan |
|---|---|---|
| id | INT PK AI | |
| username | VARCHAR(50) UNIQUE | |
| password_hash | VARCHAR(255) | bcrypt/argon2 via `password_hash()` |
| full_name | VARCHAR(100) | |
| role | ENUM('admin','superadmin') | |
| is_active | TINYINT(1) | nonaktifkan tanpa hapus data |
| created_at | DATETIME | |
| last_login | DATETIME NULL | |

### Tabel `company_settings`
| Kolom | Tipe | Keterangan |
|---|---|---|
| id | INT PK | biasanya hanya 1 baris aktif |
| company_name | VARCHAR(150) | |
| address | TEXT | |
| phone | VARCHAR(50) | |
| email | VARCHAR(100) | |
| website | VARCHAR(100) | |
| logo_path | VARCHAR(255) | |
| bank_name | VARCHAR(50) | |
| bank_account_number | VARCHAR(50) | |
| bank_account_name | VARCHAR(100) | |
| updated_at | DATETIME | |
| updated_by | INT FK → users.id | |

### Tabel `invoices`
| Kolom | Tipe | Keterangan |
|---|---|---|
| id | INT PK AI | |
| invoice_number | VARCHAR(60) UNIQUE | format lihat bagian 5 |
| customer_name | VARCHAR(150) | |
| customer_address | TEXT | |
| customer_phone | VARCHAR(50) | |
| invoice_date | DATE | |
| total_qty | INT | |
| total_amount | DECIMAL(15,2) | |
| discount | DECIMAL(15,2) | |
| grand_total | DECIMAL(15,2) | |
| data_snapshot | JSON / LONGTEXT | snapshot lengkap data invoice saat dibuat (lihat bagian 6) |
| signature | VARCHAR(255) | HMAC dari data_snapshot, untuk anti-tamper |
| status | ENUM('active','void') | super admin bisa void bila ada kesalahan |
| created_by | INT FK → users.id | |
| created_at | DATETIME | |

### Tabel `invoice_items`
| Kolom | Tipe |
|---|---|
| id | INT PK AI |
| invoice_id | INT FK → invoices.id |
| no_urut | INT |
| barcode | VARCHAR(50) |
| kategori | VARCHAR(100) |
| nama_barang | VARCHAR(150) |
| qty | INT |
| satuan | VARCHAR(20) |
| harga | DECIMAL(15,2) |
| total | DECIMAL(15,2) |

### Tabel `activity_logs`
| Kolom | Tipe | Keterangan |
|---|---|---|
| id | INT PK AI | |
| user_id | INT FK | |
| action | VARCHAR(100) | misal: "generate_invoice", "edit_company", "add_user" |
| description | TEXT | |
| ip_address | VARCHAR(45) | |
| created_at | DATETIME | |

### Tabel `verification_logs` (opsional, untuk monitoring scan QR)
| Kolom | Tipe |
|---|---|
| id | INT PK AI |
| invoice_id | INT FK |
| scanned_at | DATETIME |
| ip_address | VARCHAR(45) |
| user_agent | VARCHAR(255) |
| result | ENUM('valid','invalid','not_found') |

---

## 6. Format Nomor Invoice Unik

Mengikuti pola dari invoice contoh (`INV-ACC/ASSIG-20260616107`), disarankan format:

```
INV/{KODE_PERUSAHAAN}-{YYYYMMDD}-{NOMOR_URUT_HARIAN 4 digit}
Contoh: INV/ASSIG-20260616-0007
```

- Nomor urut harian di-reset setiap hari, diambil dari `COUNT(invoices) + 1` pada tanggal yang sama, dicek ulang dengan query `SELECT...FOR UPDATE` agar tidak collision saat 2 admin generate bersamaan.
- Nomor ini **bukan** satu-satunya pengaman keaslian — keamanan utama ada di signature (lihat bagian 6).

---

## 7. Anti-Pemalsuan & Sistem Verifikasi QR

Agar invoice "susah diduplikasi" dan datanya terjamin, sistem tidak hanya mengandalkan QR berisi nomor invoice, tapi pakai **signature kriptografis**:

1. Saat invoice dibuat, sistem menyimpan **`data_snapshot`**: salinan lengkap data invoice saat itu juga (nama pembeli, semua item barang, total, info perusahaan saat itu, nama admin yang generate, waktu generate). Snapshot ini disimpan permanen — meskipun nanti super admin mengubah alamat/logo perusahaan, invoice lama tetap menampilkan data sesuai saat dicetak.
2. Sistem menghitung **`signature = HMAC-SHA256(data_snapshot + secret_key)`**. `secret_key` hanya disimpan di file `config.php` di server, **tidak pernah** dikirim ke client maupun disimpan di database.
3. QR code di invoice berisi URL publik, contoh:
   `https://domain-anda.com/verify.php?inv=INV/ASSIG-20260616-0007&t=ab12cd34` (`t` = 8 karakter pertama dari signature, sebagai token pendek di URL)
4. Saat di-scan, halaman `verify.php`:
   - Mengambil data invoice dari database berdasarkan `invoice_number`
   - Menghitung ulang signature dari `data_snapshot` yang tersimpan
   - Membandingkan dengan `signature` di database dan token `t` di URL
   - Jika cocok → tampil **"✅ Invoice Asli & Tidak Diubah"** beserta ringkasan data (nomor invoice, tanggal, pembeli, grand total, status)
   - Jika tidak cocok / data sudah diubah manual di database / nomor tidak ditemukan → **"❌ Invoice Tidak Valid"**
5. Karena `secret_key` tidak ada di database, seseorang yang berhasil mengubah data langsung di database (tanpa akses ke file config server) tidak akan bisa menghasilkan signature yang valid — sehingga ketahuan saat di-scan.

---

## 8. Keamanan (Security Plan)

- **Password**: hash dengan `password_hash()` (bcrypt/argon2), tidak pernah simpan plaintext
- **SQL Injection**: semua query database wajib pakai PDO prepared statement
- **Session**: `session_regenerate_id()` setiap login, cookie diset `HttpOnly`, `Secure`, `SameSite=Strict`
- **CSRF**: token unik di setiap form (login, generate invoice, ubah data perusahaan, dll)
- **Rate limiting login**: lockout sementara setelah beberapa kali gagal login berturut-turut
- **Validasi input**: validasi & sanitasi server-side untuk semua input, tidak hanya mengandalkan validasi JS
- **Upload logo**: validasi ekstensi & MIME type asli (bukan hanya cek nama file), rename file agar tidak bisa dieksekusi langsung
- **TLS**: 
  - Aktifkan `mod_ssl` di XAMPP untuk testing HTTPS lokal (self-signed certificate)
  - Konfigurasi `SSLProtocol` agar hanya mengizinkan **TLS 1.2 dan TLS 1.3** (matikan TLS 1.0/1.1/SSLv3)
  - Pakai cipher suite modern (ECDHE + AES-GCM)
  - Redirect otomatis HTTP → HTTPS
  - Tambahkan header `Strict-Transport-Security`
- **Header keamanan tambahan**: `X-Frame-Options`, `X-Content-Type-Options: nosniff`, `Content-Security-Policy`
- **Production**: matikan `display_errors`, matikan directory listing, backup database terjadwal (`mysqldump`)

---

## 9. Checklist Development

### FASE 0 — Persiapan Environment
- [x] Install/update XAMPP (PHP 8.x, MariaDB terbaru, Apache + mod_ssl)
- [x] Aktifkan `mod_ssl` & `mod_rewrite`
- [x] Generate self-signed certificate untuk HTTPS lokal
- [x] Taruh folder project di `htdocs` (folder `invoce_genarate`), akses lewat `http://localhost/invoce_genarate/`
- [x] Cek port SSL Apache di `httpd-ssl.conf` (baris `Listen`, default `443` tapi bisa custom misal `4433` kalau port HTTP juga sudah diubah ke `8080`), lalu aktifkan HTTPS lewat port itu (akses jadi `https://localhost:<port_ssl>/invoce_genarate/`)
- [x] Buat struktur folder project sesuai bagian 3
- [x] Inisialisasi git repository & push ke GitHub (`git@github.com:IchsanUI/invoce_genarate.git`)
- [x] Buat `.gitignore` (config sensitif, folder uploads, vendor)

**Catatan Fase 0:**
- Repo git ada di folder project sendiri (`.git` di root `invoce_genarate/`), bukan nested di parent
- Folder `config/`, `includes/`, `sql/` dilindungi `.htaccess` agar tidak bisa diakses browser
- Folder `public/assets/uploads/` tidak bisa eksekusi PHP (aman dari upload shell)
- PHP 8.0.30 terdeteksi di CLI
- Library `qrcode.js` (untuk QR code) belum di-download — akan dipasang di Fase 8

### FASE 1 — Database
- [x] Buat database `invoice_system`
- [x] Buat tabel `users`, `company_settings`, `invoices`, `invoice_items`, `activity_logs`, `verification_logs`
- [x] Tambahkan index pada `invoice_number`, `username`, dan semua foreign key
- [x] Insert data awal: 1 user super admin (password sudah di-hash), 1 baris default `company_settings`
- [x] Export `schema.sql` sebagai dokumentasi/backup

**Catatan Fase 1:**
- `sql/schema.sql` — CREATE DATABASE + 6 tabel + index + foreign keys
- `sql/seed.sql` — INSERT default super admin & company_settings
- `install.php` — CLI/browser installer yang jalanin schema.sql + seed.sql
- Login default: username `superadmin`, password `superadmin123` (WAJIB ganti setelah login pertama!)
- `data_snapshot` di tabel invoices disimpan sebagai JSON (MariaDB akan auto-cast ke LONGTEXT — tetap valid untuk `JSON_VALID()`)
- `company_settings.id = 1` (single-row pattern, di-update via `id` tetap)
- Semua FK pakai `ON DELETE RESTRICT` kecuali `invoice_items → invoices` yang `ON DELETE CASCADE`
- `install.php` HANYA untuk setup sekali — hapus setelah berhasil

### FASE 2 — Core Backend
- [x] `config.php`: kredensial DB, secret key HMAC, base URL (letakkan di luar document root bila memungkinkan)
- [x] `database.php`: koneksi PDO + helper query
- [x] `functions.php`: helper umum (format rupiah, sanitasi input, dsb)
- [x] `auth.php`: session handling + middleware cek role
- [x] Setting session aman (`HttpOnly`, `Secure`, `SameSite=Strict`, regenerate ID saat login)
- [x] `signature.php`: HMAC-SHA256 sign/verify + verify URL builder
- [x] `invoice-number.php`: generator nomor invoice `INV/ASSIG-YYYYMMDD-NNNN`
- [x] `tests/backend-test.php`: 35-test smoke test (semua PASS)

**Catatan Fase 2:**
- `config.php` dilindungi `.htaccess` (tidak bisa diakses browser)
- `db()` singleton — koneksi PDO dipakai ulang per request
- PDO: `ERRMODE_EXCEPTION`, `EMULATE_PREPARES = false`, `FETCH_ASSOC`
- Session: cookie `HttpOnly`, `SameSite=Strict` (`Secure=false` di dev, true di Fase 9 setelah HTTPS)
- CSRF: 64-char hex via `random_bytes(32)`, validated dengan `hash_equals()` (timing-safe)
- Invoice number: pattern `INV/{COMPANY_CODE}-{YYYYMMDD}-{NNNN}`
  - `generate_invoice_number()` → compute candidate (read-only, may collide on race)
  - `reserve_invoice_number($user_id)` → atomic INSERT placeholder + retry on UNIQUE collision
- `tests/backend-test.php` bisa dijalankan via `php tests/backend-test.php` (35/35 PASS)

### FASE 3 — Autentikasi & Role
- [x] Halaman login (validasi server-side)
- [x] Hash password dengan `password_hash()`
- [x] Rate-limit / lockout sementara setelah gagal login berulang
- [x] Middleware cek role di setiap halaman terlindungi (admin vs superadmin)
- [x] Halaman logout (destroy session)
- [x] CSRF token di semua form

**Catatan Fase 3:**
- `index.php` — halaman login (GET = form, POST = proses)
- `logout.php` — destroy session + redirect ke login
- `dashboard.php` — landing page untuk admin
- `superadmin/dashboard.php` — landing page untuk super admin (w/ stat cards)
- `auth.php` augmented dengan: `attempt_login()`, `get_failed_attempts()`, `is_locked_out()`, `lockout_seconds_remaining()`, `log_login_success()`, `log_login_failure()`
- Rate-limit: 5 failed attempts dalam 15 menit → lockout 15 menit (configurable via `MAX_LOGIN_ATTEMPTS` & `LOCKOUT_MINUTES`)
- Login success: `session_regenerate_id()` + `set_login_session()` + update `last_login`
- Login failure: log ke `activity_logs` dengan action `login_failed` (no password logged)
- Timing attack prevention: `password_verify()` selalu dijalankan (dengan dummy hash) walau user tidak ditemukan
- Redirect logic: admin → `dashboard.php`, super admin → `superadmin/dashboard.php`, anon → `index.php`
- Cookie: `HttpOnly`, `SameSite=Strict`, `Secure=false` (akan jadi `true` di Fase 9)
- CSS monochrome (hitam/putih/abu-abu) + Plus Jakarta Sans via Google Fonts CDN

### FASE 4 — Fitur Super Admin
- [ ] Dashboard ringkasan (jumlah invoice, jumlah admin aktif, dll)
- [ ] CRUD user admin (tambah, edit, nonaktifkan, reset password)
- [ ] Halaman ubah profil perusahaan (nama, alamat, telp, email, website)
- [ ] Upload & ganti logo (validasi tipe file & ukuran, simpan ke `/assets/uploads/logo`)
- [ ] Halaman ubah info pembayaran (bank, no rekening, atas nama)
- [ ] Halaman history **semua invoice** dengan filter (tanggal, nama admin, nomor invoice)
- [ ] Halaman log aktivitas user
- [ ] Super admin bisa generate invoice sendiri (reuse komponen dari fase 5)

### FASE 5 — Fitur Admin (Generate Invoice)
- [ ] Form input data pembeli (nama, alamat, no telp)
- [ ] Form tambah item barang dinamis (JS: tambah/hapus baris)
- [ ] Hitung otomatis total per item, total barang, diskon, grand total (JS)
- [ ] Generate nomor invoice unik otomatis
- [ ] Simpan invoice + items via database transaction (rollback jika gagal)
- [ ] Simpan `data_snapshot` + `signature` (lihat bagian 6)
- [ ] Halaman history invoice milik admin sendiri
- [ ] Fitur cari/filter invoice (nomor, nama pembeli, tanggal)

### FASE 6 — Template Invoice & Cetak
- [ ] Setup font Plus Jakarta Sans (Google Fonts/lokal) & palet warna monochrome sesuai bagian 4, terapkan di seluruh UI + template invoice
- [ ] Buat template HTML/CSS mengikuti format invoice contoh (header logo, info perusahaan & pembeli, tabel barang, total/diskon/grand total, note, payment info, "Dicetak oleh")
- [ ] Render data dinamis dari database ke template
- [ ] Tambahkan area QR code (misal pojok kanan bawah, dekat info pembayaran)
- [ ] CSS print-friendly (`@media print`, ukuran A4)
- [ ] Tombol "Cetak / Simpan PDF" (`window.print()`, atau library PDF bila perlu file PDF asli)

### FASE 7 — Nomor Invoice & Signature
- [ ] Implementasi generator nomor invoice unik (cek collision per hari)
- [ ] Implementasi pembuatan `data_snapshot` (JSON lengkap)
- [ ] Implementasi `signature` (HMAC-SHA256 + secret key)
- [ ] Pastikan secret key hanya ada di file config server, tidak pernah dikirim ke client

### FASE 8 — QR Code & Halaman Verifikasi Publik
- [ ] Integrasi `qrcode.js` untuk generate QR dari URL verifikasi (client-side)
- [ ] Buat halaman `verify.php` (publik, tanpa login)
- [ ] Logic verifikasi: ambil invoice, hitung ulang signature, bandingkan dengan signature tersimpan & token di URL
- [ ] Tampilkan hasil verifikasi (valid/tidak valid/tidak ditemukan) + ringkasan data invoice
- [ ] Simpan setiap aktivitas scan ke `verification_logs`
- [ ] Rate-limit halaman verifikasi (cegah brute-force nomor invoice)

### FASE 9 — Hardening Keamanan
- [ ] Pastikan semua query pakai PDO prepared statement
- [ ] Validasi & sanitasi semua input di server-side
- [ ] Tambahkan header: `Content-Security-Policy`, `X-Frame-Options`, `X-Content-Type-Options`, `Strict-Transport-Security`
- [ ] Redirect otomatis HTTP → HTTPS
- [ ] `SSLProtocol`: hanya izinkan TLS 1.2 & TLS 1.3
- [ ] Pilih cipher suite modern (ECDHE + AES-GCM) di `httpd-ssl.conf`
- [ ] Matikan directory listing & `display_errors` di production
- [ ] Setup backup database terjadwal (`mysqldump`)
- [ ] Validasi ekstensi & MIME asli untuk upload logo

### FASE 10 — Testing
- [ ] Test login (berhasil, gagal, lockout)
- [ ] Test permission per role (admin tidak bisa akses halaman super admin)
- [ ] Test generate invoice end-to-end + cetak
- [ ] Test scan QR dari invoice asli → harus valid
- [ ] Test ubah data manual di database lalu scan ulang → harus terdeteksi tidak valid
- [ ] Test ganti logo/data perusahaan dari super admin → invoice baru pakai data baru, invoice lama tetap pakai snapshot lama
- [ ] Test tampilan di beberapa browser

### FASE 11 — Catatan Deployment (setelah dev selesai)
- [ ] Ganti self-signed certificate dengan certificate resmi (Let's Encrypt/CA) saat live
- [ ] Pisahkan konfigurasi environment dev vs production
- [ ] Set permission file & folder server production secukupnya (least privilege)

---

## 10. Catatan Tambahan
- Plan ini bisa dikembangkan iteratif — boleh kerjakan Fase 0–5 dulu (sistem inti berjalan), lalu Fase 6–8 (template & QR), baru terakhir hardening (Fase 9) sebelum dipakai serius.
- Setelah plan ini disetujui, saya bisa langsung bantu mulai coding (misal mulai dari `schema.sql` dan struktur folder).
