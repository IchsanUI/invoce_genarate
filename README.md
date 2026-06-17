# Invoice Generator — AS Stuff

Sistem web internal untuk generate invoice digital, dengan verifikasi QR publik.
Dibangun dengan PHP native + MySQL (XAMPP), frontend HTML/CSS/JS murni.

📄 Detail lengkap ada di [`plan.md`](./plan.md).  
🔗 Repo: https://github.com/IchsanUI/invoce_genarate

## Status Development

| Fase | Tahap | Status |
|------|-------|--------|
| 0 | Persiapan Environment | ✅ Selesai |
| 1 | Database | ✅ Selesai |
| 2 | Core Backend | ✅ Selesai |
| 3 | Autentikasi & Role | ⏳ Belum |
| 4 | Fitur Super Admin | ⏳ Belum |
| 5 | Fitur Admin (Generate Invoice) | ⏳ Belum |
| 6 | Template Invoice & Cetak | ⏳ Belum |
| 7 | Nomor Invoice & Signature | ⏳ Belum |
| 8 | QR Code & Halaman Verifikasi | ⏳ Belum |
| 9 | Hardening Keamanan | ⏳ Belum |
| 10 | Testing | ⏳ Belum |
| 11 | Deployment | ⏳ Belum |

## Struktur Folder

```
invoce_genarate/
├── config/                # DB & secret (tidak boleh diakses publik)
├── includes/              # Library PHP (helper, auth, signature)
├── public/                # Aset statis (CSS, JS, uploads)
├── sql/                   # Schema & migration
├── .htaccess              # Security default + block akses sensitif
├── plan.md                # Dokumen rencana lengkap
└── README.md              # File ini
```

## Setup Lokal (XAMPP)

1. **Letakkan folder** di `htdocs/invoice-system/` (sudah ✅ di `D:\Storage\PROG\xampp\htdocs\invoce_genarate\`)
2. **Aktifkan Apache + MySQL** lewat XAMPP Control Panel
3. **Akses** lewat `http://localhost/invoce_genarate/` (belum ada halaman — file `index.php` akan dibuat di Fase 3)
4. **Aktifkan SSL** (lihat Fase 0 di `plan.md`)

### URL Patterns

- Aplikasi utama: `http://localhost/invoce_genarate/`
- Verifikasi publik: `http://localhost/invoce_genarate/verify.php?inv=INV-...&t=token`
- Aset statis: `http://localhost/invoce_genarate/public/assets/...`

## Keamanan Default (sudah aktif di Fase 0)

- Folder `/config`, `/includes`, `/sql` diblok dari akses browser via `.htaccess`
- Folder `/public/assets/uploads` diblok eksekusi PHP
- Directory listing dimatikan (`Options -Indexes`)
- Security headers dasar (`X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`)
- `secret_key` & HMAC key tidak di-commit ke git (di-`gitignore`)
- Session cookies: `HttpOnly`, `SameSite=Strict` (akan menjadi `Secure` di Fase 9)

## Lisensi

Internal — tidak untuk distribusi publik.