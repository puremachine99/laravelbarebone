/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

CREATE TABLE `batals` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `batal_prediksi` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `batal_tahun` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batal_jenis` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batal_ukw` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batal_pmo` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batal_provinsi` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batal_kabupaten` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batal_instansi` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batal_bidang` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batal_kontak` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batal_kegiatan` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batal_bulan` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batal_tgl` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batal_alasan` longtext COLLATE utf8mb4_unicode_ci,
  `batal_status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batal_nominal` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1661 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `bidangs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_instansi` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `bidang` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `id_kontak` longtext COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_bidang_nama` (`bidang`)
) ENGINE=InnoDB AUTO_INCREMENT=112678 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `error_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `error_code` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `severity` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'error',
  `http_status` smallint DEFAULT NULL,
  `http_method` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `route_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `url` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `message` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `stack_trace` longtext COLLATE utf8mb4_unicode_ci,
  `user_id` bigint unsigned DEFAULT NULL,
  `user_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `request_input` json DEFAULT NULL,
  `context` json DEFAULT NULL,
  `triggered_by` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'auto',
  `is_resolved` tinyint(1) NOT NULL DEFAULT '0',
  `resolved_by` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `resolved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `error_logs_error_code_unique` (`error_code`),
  KEY `error_logs_http_status_created_at_index` (`http_status`,`created_at`),
  KEY `error_logs_user_id_created_at_index` (`user_id`,`created_at`),
  KEY `error_logs_type_created_at_index` (`type`,`created_at`),
  KEY `error_logs_is_resolved_index` (`is_resolved`)
) ENGINE=InnoDB AUTO_INCREMENT=25053 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `failed_jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `images` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `img` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `instansis` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_provinsi` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `id_kabupaten` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `instansi` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `id_urusan` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=19344 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts` tinyint unsigned NOT NULL,
  `reserved_at` int unsigned DEFAULT NULL,
  `available_at` int unsigned NOT NULL,
  `created_at` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `kabupatens` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `kode_provinsi` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `kode` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `kabupaten` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ukw` bigint DEFAULT NULL,
  `pic` bigint DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `kabupatens_kode_unique` (`kode`),
  KEY `idx_kabupaten_pic` (`pic`)
) ENGINE=InnoDB AUTO_INCREMENT=679 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `kontaks` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `kontak_nama` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `kontak_sumber` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `kontak_ukw` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `kontak_pic` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `kontak_kategori` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `kontak_provinsi` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `kontak_kabupaten` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `kontak_jabatan` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `kontak_subjabatan` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `kontak_instansi` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `kontak_unit` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `kontak_email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `kontak_hp` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `kontak_status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `kontak_is_responed` tinyint NOT NULL DEFAULT '0',
  `kontak_kerjasama` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `kontak_nominal_anggaran` bigint DEFAULT NULL,
  `kontak_kegiatan` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `kontaks_kontak_hp_unique` (`kontak_hp`),
  KEY `idx_kontak_pic_created_at` (`kontak_pic`,`created_at`),
  KEY `idx_kontak_subjabatan` (`kontak_subjabatan`),
  KEY `idx_kontak_pic` (`kontak_pic`),
  KEY `idx_kontak_instansi` (`kontak_instansi`),
  CONSTRAINT `kontaks_chk_1` CHECK (json_valid(`kontak_kerjasama`))
) ENGINE=InnoDB AUTO_INCREMENT=23879 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `lkbhasils` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tahun_hasil` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `bulan_hasil` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `id_user` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `id_okr` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `target` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `realisasi` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `capaian` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_kegiatan` bigint NOT NULL,
  `ubahan` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `before` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `after` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=583 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `migrations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=210 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `notifications` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `notifiable_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `notifiable_id` bigint unsigned NOT NULL,
  `data` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `notifications_notifiable_type_notifiable_id_index` (`notifiable_type`,`notifiable_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `notifs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_kegiatan` bigint NOT NULL,
  `desc` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `read` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5605 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `okrs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `okr_kode` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `okr_jenis` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `okr_kinerja` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `okr_indikator` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `okr_list` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `okrs_okr_kode_unique` (`okr_kode`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `outputs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `pelaksana` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `kategori` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `id_divisi` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `divisi` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `id_pl` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `pl` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `id_output` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `output` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `id_urusan` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status_output` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `outputs_id_output_unique` (`id_output`)
) ENGINE=InnoDB AUTO_INCREMENT=827 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `outputtemps` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `temp_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `temp_divisi` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `temp_pl` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `temp_pelaksana` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `temp_layanan` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `temp_output` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `temp_kode` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `temp_check` int NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=183 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `password_resets` (
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  KEY `password_resets_email_index` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `pergerakans` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_kegiatan` bigint NOT NULL,
  `jenis` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status_awal` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status_akhir` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=239 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `personal_access_tokens` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tokenable_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tokenable_id` bigint unsigned NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `abilities` text COLLATE utf8mb4_unicode_ci,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `pitchings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `pit_tahun` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `pit_ukw` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `pit_tglberangkat` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `pit_tglpulang` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `pit_spv` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `pit_tim` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `pit_daerah` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `pit_lokasi` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `pit_opd` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `pit_kegiatan` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `pit_renanggaran` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `pit_optanggaran` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pit_renpengeluaran` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pit_realpengeluaran` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pit_accmkeu` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pit_accceo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pit_suratperdin` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pit_keterangan` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `prediksi_status_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `prediksi_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `old_status` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `new_status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `prediksi_status_logs_prediksi_id_foreign` (`prediksi_id`),
  KEY `prediksi_status_logs_user_id_foreign` (`user_id`),
  CONSTRAINT `prediksi_status_logs_prediksi_id_foreign` FOREIGN KEY (`prediksi_id`) REFERENCES `prediksis` (`id`) ON DELETE CASCADE,
  CONSTRAINT `prediksi_status_logs_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=415 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `prediksis` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `rencana_pitching_id` bigint unsigned DEFAULT NULL,
  `tahun` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `jenis` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `id_ukw` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `id_pmo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `id_provinsi` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `id_kabupaten` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `id_instansi` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `id_bidang` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `id_kontak` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `kegiatan` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pekan` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `id_rekap` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bulan` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bulan_awal` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bulan_akhir` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bulan_pencairan` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tgl_bimtek` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sumber_informasi` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nama_instansi` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nama_mitra` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `no_hp` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `profiling_market` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `waktu_dapat` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tgl_fu` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `hasil_fu` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `hasil` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `alasan_closing` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `closing` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deal` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `optimis` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `moderate` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `proyeksi` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `lost` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tanggal_closing` timestamp NULL DEFAULT NULL,
  `ta` longtext COLLATE utf8mb4_unicode_ci,
  `kerangka` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ba` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tipe` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `jml_peserta` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `link_acc` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sumber` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `monev_proyeksi` longtext COLLATE utf8mb4_unicode_ci,
  `monev_budget` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `monev_authority` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `monev_needs` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `monev_timeline` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `monev_kontrak` longtext COLLATE utf8mb4_unicode_ci,
  `verifikasi_manager` int DEFAULT NULL,
  `pj_produk` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bulan_produk` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ta_produk` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ketua_produk` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `anggota_produk` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `qao_produk` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nominal_finance` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bulan_finance` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `kepala_finance` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pic_finance` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `designer_branding` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `link_branding` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_prediksis_tahun_ukw_pmo` (`tahun`,`id_ukw`,`id_pmo`),
  KEY `idx_prediksis_tahun_bulan` (`tahun`,`bulan`),
  KEY `idx_prediksis_tahun_jenis` (`tahun`,`jenis`),
  KEY `idx_prediksis_kontak` (`id_kontak`),
  KEY `idx_prediksis_kegiatan` (`kegiatan`),
  KEY `idx_prediksis_instansi` (`id_instansi`),
  KEY `idx_prediksis_bidang` (`id_bidang`),
  KEY `idx_prediksis_ordering` (`id_provinsi`,`id_kabupaten`,`bulan`),
  KEY `idx_prediksi_pmo_tahun` (`id_pmo`,`tahun`),
  KEY `idx_prediksi_ukw` (`id_ukw`),
  KEY `prediksis_rencana_pitching_id_index` (`rencana_pitching_id`)
) ENGINE=InnoDB AUTO_INCREMENT=125413 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `provinsis` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `kode` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `provinsi` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `provinsis_kode_unique` (`kode`)
) ENGINE=InnoDB AUTO_INCREMENT=42 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `proyeksibulans` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `ukw_tahun` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ukw_bulan` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ukw_ukw` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ukw_closing` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ukw_deal` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ukw_optimis` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ukw_moderate` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ukw_pesimis` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=73 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `proyeksipics` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `pic_tahun` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `pic_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `pic_ukw` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `pic_closing` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pic_deal` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pic_optimis` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pic_moderate` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pic_pesimis` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pic_total` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pic_chat` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pic_sds` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pic_zoom` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pic_pitching` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pic_pmo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pic_mitra` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pic_ta` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pic_proyeksi` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=79 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `proyeksipmos` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `pmo_tahun` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `pmo_pic` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pmo_ukw` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pmo_status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `pmo_jan` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pmo_feb` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pmo_mar` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pmo_apr` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pmo_mei` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pmo_jun` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pmo_jul` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pmo_agt` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pmo_sep` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pmo_okt` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pmo_nov` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pmo_des` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=354 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `proyeksis` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `proyeksi_tahun` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `proyeksi_prov` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `proyeksi_pic` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `proyeksi_ukw` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `proyeksi_status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_prov` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `proyeksi_jan` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `proyeksi_feb` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `proyeksi_mar` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `proyeksi_apr` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `proyeksi_mei` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `proyeksi_jun` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `proyeksi_jul` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `proyeksi_agt` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `proyeksi_sep` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `proyeksi_okt` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `proyeksi_nov` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `proyeksi_des` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=196 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `rekaps` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `rekap_id_master` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rekap_tahun` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `rekap_jenis` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `rekap_ukw` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `rekap_pmo` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `rekap_provinsi` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rekap_kabupaten` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rekap_instansi` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rekap_bidang` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rekap_kontak` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rekap_target` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rekap_tgl` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rekap_tgl_selesai` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rekap_tgl_date` date DEFAULT NULL,
  `rekap_proyeksi` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rekap_terlaksana` longtext COLLATE utf8mb4_unicode_ci,
  `rekap_tdk_terlaksana` longtext COLLATE utf8mb4_unicode_ci,
  `rekap_metode` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rekap_skema` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rekap_pengikut` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rekap_pit_puas` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rekap_pit_tertarik` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rekap_pit_yakin` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rekap_pit_lanjut` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rekap_fol_tertarik` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rekap_fol_yakin` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rekap_fol_lanjut` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rekap_produk` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tujuan_chat` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `hasil_chat` longtext COLLATE utf8mb4_unicode_ci,
  `hasil_pitching` longtext COLLATE utf8mb4_unicode_ci,
  `link_pitching` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `jenis_proposal` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `respon_mitra` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `link_proposal` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tujuan_fu` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `hasil_fu` longtext COLLATE utf8mb4_unicode_ci,
  `rekap_dept` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rekap_pic_dept` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rekap_keperluan` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rekap_hasil_koord` longtext COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `rekap_ukw` (`rekap_ukw`),
  KEY `idx_rekap_pmo_jenis_date` (`rekap_pmo`,`rekap_jenis`,`rekap_tgl_date`)
) ENGINE=InnoDB AUTO_INCREMENT=64879 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `renaksis` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `renaksi_tahun` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `renaksi_pic` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `renaksi_rencana` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `renaksi_other` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `renaksi_target` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `renaksi_minggu1` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `renaksi_minggu2` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `renaksi_minggu3` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `renaksi_minggu4` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `renaksi_check1` int DEFAULT NULL,
  `renaksi_check2` int DEFAULT NULL,
  `renaksi_check3` int DEFAULT NULL,
  `renaksi_check4` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `rencana_pitching_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `rencana_pitching_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `old_status` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `new_status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `rencana_pitching_logs_rencana_pitching_id_foreign` (`rencana_pitching_id`),
  KEY `rencana_pitching_logs_user_id_foreign` (`user_id`),
  CONSTRAINT `rencana_pitching_logs_rencana_pitching_id_foreign` FOREIGN KEY (`rencana_pitching_id`) REFERENCES `rencana_pitchings` (`id`) ON DELETE CASCADE,
  CONSTRAINT `rencana_pitching_logs_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3386 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `rencana_pitchings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tahun` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `provinsi_kode` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `kabupaten_kode` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `opd_id` bigint unsigned NOT NULL,
  `kontak_id` bigint unsigned NOT NULL,
  `proyeksi_potensi` text COLLATE utf8mb4_unicode_ci,
  `sumber_proyeksi` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `anggaran_proyeksi` bigint NOT NULL,
  `tanggal_pitching` date NOT NULL,
  `pekan` int NOT NULL,
  `metode` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `anggaran_pitching` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `hasil` text COLLATE utf8mb4_unicode_ci,
  `hasil_pitching` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `alasan_negative` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bulan_closing` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pekan_closing` int DEFAULT NULL,
  `status_closing` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `link_dokumen` text COLLATE utf8mb4_unicode_ci,
  `link_bukti` text COLLATE utf8mb4_unicode_ci,
  `status_pelaksanaan` enum('rencana','terlaksana','tidak_terlaksana') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'rencana',
  `alasan_tidak_terlaksana` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_verified` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `rencana_pitchings_kabupaten_kode_foreign` (`kabupaten_kode`),
  KEY `rencana_pitchings_opd_id_foreign` (`opd_id`),
  KEY `rencana_pitchings_kontak_id_foreign` (`kontak_id`),
  KEY `rencana_pitchings_provinsi_kode_foreign` (`provinsi_kode`),
  KEY `rencana_pitchings_created_by_foreign` (`created_by`),
  CONSTRAINT `rencana_pitchings_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `rencana_pitchings_kabupaten_kode_foreign` FOREIGN KEY (`kabupaten_kode`) REFERENCES `kabupatens` (`kode`) ON DELETE CASCADE,
  CONSTRAINT `rencana_pitchings_kontak_id_foreign` FOREIGN KEY (`kontak_id`) REFERENCES `kontaks` (`id`) ON DELETE CASCADE,
  CONSTRAINT `rencana_pitchings_opd_id_foreign` FOREIGN KEY (`opd_id`) REFERENCES `instansis` (`id`) ON DELETE CASCADE,
  CONSTRAINT `rencana_pitchings_provinsi_kode_foreign` FOREIGN KEY (`provinsi_kode`) REFERENCES `provinsis` (`kode`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1376 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `sasarans` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tahun` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `target` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `surats` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `srt_tahun` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `srt_iduser` bigint NOT NULL,
  `srt_jenis` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `srt_tgl` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `srt_opd` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `srt_daerah` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `srt_kegiatan` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `srt_tglacara` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `srt_waktuacara` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `srt_tempatacara` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `srt_cp` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `srt_tembusan` longtext COLLATE utf8mb4_unicode_ci,
  `srt_pelaksana` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `srt_harga` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `srt_pembiayaan` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `srt_pejabat` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `srt_nip` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `srt_ttd` bigint DEFAULT NULL,
  `srt_nomor` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `targets` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tahun` int NOT NULL,
  `bulan` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `bulan_selesai` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `selesai_finance` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `provinsi` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `kabupaten` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `unit` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `pelaksana` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `divisi` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pl` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `kode_kegiatan` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `kegiatan` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `output` longtext COLLATE utf8mb4_unicode_ci,
  `pic` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nama_mitra` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `metode` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mitra_eksisting` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pengawal` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `id_ukw` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tgl_status` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `keterangan` longtext COLLATE utf8mb4_unicode_ci,
  `link` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `link_dpa` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `total_kab` bigint DEFAULT NULL,
  `closing` bigint DEFAULT NULL,
  `deal` bigint DEFAULT NULL,
  `optimis` bigint DEFAULT NULL,
  `moderate` bigint DEFAULT NULL,
  `pesimis` bigint DEFAULT NULL,
  `kategori` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `kategori_reform` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `kategori_course` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tipe` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `anggaran` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `kualifikasi` longtext COLLATE utf8mb4_unicode_ci,
  `catatan` longtext COLLATE utf8mb4_unicode_ci,
  `narsum` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `koord_ta` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ta_pengarah` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `anggota_ta` longtext COLLATE utf8mb4_unicode_ci,
  `ketupel` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `anggota` longtext COLLATE utf8mb4_unicode_ci,
  `desainer` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pao` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `qao` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pmo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cek_product` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cek_finance` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cek_partnership` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `penyelesaian` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `link_keg` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pelaksanaan` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=17273 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `urusans` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `kode` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `urusan` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `urusans_kode_unique` (`kode`)
) ENGINE=InnoDB AUTO_INCREMENT=102 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `nama` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ukw` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `spv` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `analisis_mkkw` longtext COLLATE utf8mb4_unicode_ci,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=1000 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;