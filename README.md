# PABWE 2026 — Studi Kasus P7: Aplikasi Stock Barang (Laravel 13 & Livewire 4)

Aplikasi inventaris stok barang dengan arsitektur **Feature-First** (pola sama dengan latihan Todolist):
Auth, Stock (CRUD + adjust quantity + cover + Markdown), Excel import/export (PhpSpreadsheet),
AI generator (OpenAI), Profil, Admin read-only, rate limit `1000-per-second`, `FORCE_HTTPS`, Jenkins CI.

## Setup

```bash
composer install
cp .env.example .env            # sesuaikan DB_* (pabwe_p7_stock) & OPENAI_*
php artisan key:generate
mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS pabwe_p7_stock CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
php artisan migrate:fresh --seed
php artisan storage:link
npm install --ignore-scripts && npm run build
php artisan serve
```

Akun seeder: `admin` / `admin` dan `member` / `member` (+ contoh stok untuk member).

## Rute

| Method | URI | Keterangan |
| --- | --- | --- |
| GET | `/` | Guest → login; admin → `/admin/users`; member → `/stocks` |
| GET | `/login`, `/register` | Guest (alias `/auth/login`, `/auth/register` juga langsung 200) |
| POST | `/logout` | Auth |
| GET | `/stocks` | Daftar stok member (`owns.stock`) |
| GET | `/stocks/{stock}` | Detail stok (`owns.stock`) |
| GET | `/profile` | Profil |
| GET | `/admin/users` | Admin: daftar user + ringkasan stok |
| GET | `/admin/users/{user}/stocks` | Admin: stok user, read-only |

## Selektor form login

| Elemen | id |
| --- | --- |
| Input username/email | `login-email-input` |
| Input password | `login-password-input` |
| Tombol submit (Masuk) | `login-submit-button` |

## Status stok

`available` / `low_stock` / `out_of_stock` dihitung dari `quantity` vs `min_stock`
(0 → habis, ≤ min_stock → menipis). Adjust quantity (+/−) selalu menyinkronkan status;
pada form create/edit, status "Otomatis" (kosong) juga dihitung, atau bisa dipilih manual.

## Pengujian

```bash
composer test     # PHPUnit + coverage (butuh Xdebug atau PCOV)
composer audit
```
