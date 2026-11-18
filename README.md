# Garuda-Hub (Laravel MVC)

Website lelang proyek IT mahasiswa: klien memasang proyek, developer mengajukan penawaran, dan klien memilih pemenang. Sistem ini telah direfactor dari prototipe PHP Native menjadi arsitektur **MVC (Model-View-Controller)** menggunakan framework **Laravel** dan **Eloquent ORM** dengan skema database relasional.

## PERINGATAN

- **Hanya untuk komputer lokal atau lingkungan belajar.** Jangan dipasang di server publik atau internet.
- Seluruh data awal merupakan data dummy fiktif. Jangan memasukkan data pribadi atau password asli.

## Fitur

- Daftar dan login dengan peran `klien`, `developer`, dan `admin`
- Klien memasang proyek (judul, deskripsi, kategori, budget min–maks, batas lelang)
- Daftar, pencarian (judul/kategori), dan detail proyek beserta seluruh penawaran
- Developer mengajukan penawaran (harga, durasi, proposal) dengan validasi batas lelang
- Klien memilih pemenang (menggunakan *database transaction* dan *row locking*)
- Dasbor sesuai peran (`Proyek Saya` untuk klien, `Penawaran Saya` untuk developer)
- Panel admin untuk mengelola pengguna (ubah peran, hapus user) dan proyek (hapus proyek)

## Arsitektur Database Relasional

Sistem menggunakan 3 tabel utama dengan *foreign key constraints* dan *cascade delete*:

1. **`users`**: `id`, `nama`, `email` (unique), `password`, `role` (`enum: klien, developer, admin`), timestamps.
2. **`projects`**: `id`, `client_id` (FK -> `users.id`), `judul`, `deskripsi`, `kategori`, `budget_min`, `budget_max`, `batas_lelang`, `status` (`enum: terbuka, ditutup`), timestamps.
3. **`bids`**: `id`, `project_id` (FK -> `projects.id`), `developer_id` (FK -> `users.id`), `harga`, `durasi`, `proposal`, `is_winner`, timestamps, dengan *unique constraint* pada `(project_id, developer_id)`.

## Kebutuhan

- PHP 8.2 atau lebih baru (ekstensi `pdo_sqlite` / `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`)
- Composer
- SQLite (bawaan) atau MySQL/MariaDB

## Instalasi

1. Klon repositori ini dan pindah ke branch proyek:
   ```bash
   git clone https://github.com/Valirc-cpu/garudaHub-252410102048.git
   cd garudaHub-252410102048
   git checkout 252410102048
   ```
2. Pasang dependensi Composer:
   ```bash
   composer install
   ```
3. Salin file `.env.example` menjadi `.env` dan buat *application key*:
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```
   *(Secara bawaan aplikasi menggunakan SQLite. Jika ingin menggunakan MySQL, sesuaikan `DB_CONNECTION=mysql` dan kredensial di file `.env`.)*
4. Jalankan migrasi database beserta data dummy awal (*seeder*):
   ```bash
   php artisan migrate:fresh --seed
   ```
5. Jalankan server pengembangan lokal:
   ```bash
   php artisan serve
   ```
6. Buka `http://127.0.0.1:8000` di browser.

## Akun Dummy (Seeder)

| Peran | Email | Password |
| --- | --- | --- |
| Admin | `admin@example.com` | `admin123` |
| Klien | `budi@example.com`, `siti@example.com`, `agus@example.com` | `klien123` |
| Developer | `dewi@example.com`, `eko@example.com`, `fitri@example.com`, `gilang@example.com` | `dev123` |

## Struktur Utama MVC

- **Models (`app/Models/`)**: `User.php`, `Project.php`, `Bid.php` (Eloquent ORM & relasi)
- **Controllers (`app/Http/Controllers/`)**: `AuthController.php`, `ProjectController.php`, `BidController.php`, `DashboardController.php`, `AdminController.php`
- **Middleware (`app/Http/Middleware/`)**: `RoleMiddleware.php`
- **Views (`resources/views/`)**: Template Blade yang mempertahankan desain UI asli secara utuh
- **Migrations & Seeders (`database/`)**: Skema relasional dan data dummy awal
- **Routes (`routes/web.php`)**: Definisi rute aplikasi dan proteksi peran

## Pengujian Otomatis

Untuk menjalankan seluruh *feature test* (relasi ORM, middleware/akses peran, CRUD, dan render view):
```bash
php artisan test
```

## Lisensi

MIT. Lihat [LICENSE](LICENSE).
