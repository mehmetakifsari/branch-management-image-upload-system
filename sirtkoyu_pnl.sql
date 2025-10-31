-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Anamakine: localhost:3306
-- Üretim Zamanı: 31 Eki 2025, 19:52:50
-- Sunucu sürümü: 10.6.21-MariaDB-cll-lve
-- PHP Sürümü: 8.4.13

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Veritabanı: `sirtkoyu_pnl`
--

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `logs`
--

CREATE TABLE `logs` (
  `id` bigint(20) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `username` varchar(150) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `details` text DEFAULT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `device` varchar(120) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Tablo döküm verisi `logs`
--

INSERT INTO `logs` (`id`, `user_id`, `username`, `action`, `details`, `ip`, `user_agent`, `device`, `created_at`) VALUES
(1, 3, 'admin', 'upload', '{\"isemri\":\"40708083\",\"plaka\":\"12ABC53\",\"branch_code\":\"4\",\"files\":[\"2025-40708083-12ABC53-1.jpg\",\"2025-40708083-12ABC53-3.jpg\",\"2025-40708083-12ABC53-6.jpg\",\"2025-40708083-12ABC53-10.jpg\"]}', '46.1.133.192', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 OPR/122.0.0.0', 'Desktop', '2025-10-29 23:53:12'),
(2, 3, 'admin', 'upload', '{\"isemri\":\"41014104\",\"plaka\":\"34EA5353\",\"branch_code\":\"4\",\"files\":[\"2025-41014104-34EA5353-1.jpg\",\"2025-41014104-34EA5353-3.jpg\",\"2025-41014104-34EA5353-6.jpg\"]}', '46.1.133.192', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 OPR/122.0.0.0', 'Desktop', '2025-10-30 00:03:29'),
(3, 3, 'admin', 'upload', '{\"isemri\":\"41120101\",\"plaka\":\"53EA53\",\"branch_code\":\"4\",\"files\":[\"2025-41120101-53EA53-1.jpg\",\"2025-41120101-53EA53-3.jpg\",\"2025-41120101-53EA53-6.jpg\"]}', '46.1.133.192', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 OPR/122.0.0.0', 'Desktop', '2025-10-30 00:13:08'),
(4, 3, 'admin', 'upload', '{\"isemri\":\"40708181\",\"plaka\":\"48EA48\",\"branch_code\":\"4\",\"files\":[\"2025-40708181-48EA48-1.jpg\",\"2025-40708181-48EA48-3.jpg\",\"2025-40708181-48EA48-6.jpg\",\"2025-40708181-48EA48-10.jpg\"]}', '46.1.133.192', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 OPR/122.0.0.0', 'Desktop', '2025-10-30 00:51:55'),
(5, 3, 'admin', 'upload', '{\"isemri\":\"41201702\",\"plaka\":\"11AEA11\",\"branch_code\":\"4\",\"files\":[\"2025-41201702-11AEA11-1.jpg\",\"2025-41201702-11AEA11-3.jpg\",\"2025-41201702-11AEA11-6.jpg\",\"2025-41201702-11AEA11-10.jpg\"]}', '46.1.133.192', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 OPR/122.0.0.0', 'Desktop', '2025-10-30 00:57:08'),
(6, 3, 'admin', 'upload', '{\"isemri\":\"41023014\",\"plaka\":\"34MZY436\",\"branch_code\":\"4\",\"files\":[\"2025-41023014-34MZY436-1.jpg\"]}', '213.74.206.74', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 'Desktop', '2025-10-30 16:29:49'),
(7, NULL, NULL, 'upload', '{\"isemri\":\"41030001\",\"plaka\":\"34FVC272\",\"branch_code\":\"4\",\"files\":[\"2025-41030001-34FVC272-1.jpg\",\"2025-41030001-34FVC272-3.jpg\",\"2025-41030001-34FVC272-6.jpg\",\"2025-41030001-34FVC272-10.jpg\",\"2025-41030001-34FVC272-15.jpg\",\"2025-41030001-34FVC272-21.jpg\",\"2025-41030001-34FVC272-28.jpg\",\"2025-41030001-34FVC272-36.jpg\",\"2025-41030001-34FVC272-45.jpg\",\"2025-41030001-34FVC272-55.jpg\",\"2025-41030001-34FVC272-66.jpg\",\"2025-41030001-34FVC272-78.jpg\",\"2025-41030001-34FVC272-91.jpeg\",\"2025-41030001-34FVC272-105.jpeg\"]}', '46.1.133.192', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Mobile Safari/537.36', 'Mobile', '2025-10-30 19:04:26');

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `settings`
--

CREATE TABLE `settings` (
  `id` int(11) NOT NULL,
  `site_title` varchar(255) DEFAULT NULL,
  `header_text` varchar(255) DEFAULT NULL,
  `logo_path` varchar(512) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL,
  `favicon_path` varchar(512) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Tablo döküm verisi `settings`
--

INSERT INTO `settings` (`id`, `site_title`, `header_text`, `logo_path`, `created_at`, `updated_at`, `favicon_path`) VALUES
(1, 'Koçaslanlar Otomotiv', 'Garanti Görsel  Yükleme Sistemi', '/assets/img/logo.png', '2025-10-29 23:24:53', '2025-10-30 01:35:08', '/assets/img/favicon.png');

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `tickets`
--

CREATE TABLE `tickets` (
  `id` bigint(20) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `status` enum('open','closed','inprogress') DEFAULT 'open',
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `uploads`
--

CREATE TABLE `uploads` (
  `id` bigint(20) NOT NULL,
  `plaka` varchar(32) NOT NULL,
  `isemri` char(8) NOT NULL,
  `branch_code` char(1) NOT NULL,
  `files_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`files_json`)),
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Tablo döküm verisi `uploads`
--

INSERT INTO `uploads` (`id`, `plaka`, `isemri`, `branch_code`, `files_json`, `created_at`) VALUES
(2, '34EAD234', '40708080', '4', '[{\"original\":\"05,.jpg\",\"stored\":\"1761767805_6b73ef37ca21_05_.jpg\",\"ext\":\"jpg\",\"path\":\"\\/pnl2\\/uploads\\/1761767805_6b73ef37ca21_05_.jpg\"}]', '2025-10-29 22:56:45'),
(3, '34EAD53', '40601010', '4', '[{\"original\":\"1,.jpg\",\"stored\":\"1761767820_116455cb5404_1_.jpg\",\"ext\":\"jpg\",\"path\":\"\\/pnl2\\/uploads\\/1761767820_116455cb5404_1_.jpg\"}]', '2025-10-29 22:57:00'),
(4, '34EAD53', '40601010', '4', '[{\"original\":\"01.jpg\",\"stored\":\"1761767906_b2d3fca17ee5_01.jpg\",\"ext\":\"jpg\",\"path\":\"\\/pnl2\\/uploads\\/1761767906_b2d3fca17ee5_01.jpg\"}]', '2025-10-29 22:58:26'),
(5, '34EAD53', '40601010', '4', '[{\"original\":\"34 hbu vip jop kart 2.pdf\",\"stored\":\"1761767942_44b6dc2846f6_34_hbu_vip_jop_kart_2.pdf\",\"ext\":\"pdf\",\"path\":\"\\/pnl2\\/uploads\\/1761767942_44b6dc2846f6_34_hbu_vip_jop_kart_2.pdf\"}]', '2025-10-29 22:59:02'),
(6, '34EAD53', '40601010', '4', '[{\"original\":\"34 hbu vip jop kart 2.pdf\",\"stored\":\"1761768532_261d0adbb158_34_hbu_vip_jop_kart_2.pdf\",\"ext\":\"pdf\",\"path\":\"\\/pnl2\\/uploads\\/1761768532_261d0adbb158_34_hbu_vip_jop_kart_2.pdf\"}]', '2025-10-29 23:08:52'),
(7, '12ABC53', '40708083', '4', '[{\"original\":\"CIMG0131.JPG\",\"stored\":\"2025-40708083-12ABC53-1.jpg\",\"ext\":\"jpg\",\"path\":\"\\/pnl2\\/uploads\\/2025\\/40708083\\/12ABC53\\/2025-40708083-12ABC53-1.jpg\"},{\"original\":\"CIMG0132.JPG\",\"stored\":\"2025-40708083-12ABC53-3.jpg\",\"ext\":\"jpg\",\"path\":\"\\/pnl2\\/uploads\\/2025\\/40708083\\/12ABC53\\/2025-40708083-12ABC53-3.jpg\"},{\"original\":\"CIMG0133.JPG\",\"stored\":\"2025-40708083-12ABC53-6.jpg\",\"ext\":\"jpg\",\"path\":\"\\/pnl2\\/uploads\\/2025\\/40708083\\/12ABC53\\/2025-40708083-12ABC53-6.jpg\"},{\"original\":\"CIMG0134.JPG\",\"stored\":\"2025-40708083-12ABC53-10.jpg\",\"ext\":\"jpg\",\"path\":\"\\/pnl2\\/uploads\\/2025\\/40708083\\/12ABC53\\/2025-40708083-12ABC53-10.jpg\"}]', '2025-10-29 23:53:12'),
(8, '34EA5353', '41014104', '4', '[{\"original_name\":\"CIMG0151.JPG\",\"stored\":\"2025-41014104-34EA5353-1.jpg\",\"ext\":\"jpg\",\"path\":\"\\/pnl2\\/uploads\\/2025\\/41014104\\/34EA5353\\/2025-41014104-34EA5353-1.jpg\",\"thumb\":\"\\/pnl2\\/uploads\\/2025\\/41014104\\/34EA5353\\/thumbs\\/thumb-2025-41014104-34EA5353-1.jpg\"},{\"original_name\":\"CIMG0156.JPG\",\"stored\":\"2025-41014104-34EA5353-3.jpg\",\"ext\":\"jpg\",\"path\":\"\\/pnl2\\/uploads\\/2025\\/41014104\\/34EA5353\\/2025-41014104-34EA5353-3.jpg\",\"thumb\":\"\\/pnl2\\/uploads\\/2025\\/41014104\\/34EA5353\\/thumbs\\/thumb-2025-41014104-34EA5353-3.jpg\"},{\"original_name\":\"CIMG0165.JPG\",\"stored\":\"2025-41014104-34EA5353-6.jpg\",\"ext\":\"jpg\",\"path\":\"\\/pnl2\\/uploads\\/2025\\/41014104\\/34EA5353\\/2025-41014104-34EA5353-6.jpg\",\"thumb\":\"\\/pnl2\\/uploads\\/2025\\/41014104\\/34EA5353\\/thumbs\\/thumb-2025-41014104-34EA5353-6.jpg\"}]', '2025-10-30 00:03:29'),
(9, '53EA53', '41120101', '4', '[{\"original_name\":\"CIMG0183.JPG\",\"stored\":\"2025-41120101-53EA53-1.jpg\",\"ext\":\"jpg\",\"path\":\"\\/pnl2\\/uploads\\/2025\\/41120101\\/53EA53\\/2025-41120101-53EA53-1.jpg\",\"thumb\":\"\\/pnl2\\/uploads\\/2025\\/41120101\\/53EA53\\/thumbs\\/thumb-2025-41120101-53EA53-1.jpg\"},{\"original_name\":\"CIMG0187.JPG\",\"stored\":\"2025-41120101-53EA53-3.jpg\",\"ext\":\"jpg\",\"path\":\"\\/pnl2\\/uploads\\/2025\\/41120101\\/53EA53\\/2025-41120101-53EA53-3.jpg\",\"thumb\":\"\\/pnl2\\/uploads\\/2025\\/41120101\\/53EA53\\/thumbs\\/thumb-2025-41120101-53EA53-3.jpg\"},{\"original_name\":\"CIMG0192_düzenlendi-1.JPG\",\"stored\":\"2025-41120101-53EA53-6.jpg\",\"ext\":\"jpg\",\"path\":\"\\/pnl2\\/uploads\\/2025\\/41120101\\/53EA53\\/2025-41120101-53EA53-6.jpg\",\"thumb\":\"\\/pnl2\\/uploads\\/2025\\/41120101\\/53EA53\\/thumbs\\/thumb-2025-41120101-53EA53-6.jpg\"}]', '2025-10-30 00:13:08'),
(10, '48EA48', '40708181', '4', '[{\"original_name\":\"CIMG0224.JPG\",\"stored\":\"2025-40708181-48EA48-1.jpg\",\"ext\":\"jpg\",\"path\":\"\\/pnl2\\/uploads\\/2025\\/40708181\\/48EA48\\/2025-40708181-48EA48-1.jpg\",\"thumb\":\"\\/pnl2\\/uploads\\/2025\\/40708181\\/48EA48\\/thumbs\\/thumb-2025-40708181-48EA48-1.jpg\"},{\"original_name\":\"CIMG0225.JPG\",\"stored\":\"2025-40708181-48EA48-3.jpg\",\"ext\":\"jpg\",\"path\":\"\\/pnl2\\/uploads\\/2025\\/40708181\\/48EA48\\/2025-40708181-48EA48-3.jpg\",\"thumb\":\"\\/pnl2\\/uploads\\/2025\\/40708181\\/48EA48\\/thumbs\\/thumb-2025-40708181-48EA48-3.jpg\"},{\"original_name\":\"CIMG0226.JPG\",\"stored\":\"2025-40708181-48EA48-6.jpg\",\"ext\":\"jpg\",\"path\":\"\\/pnl2\\/uploads\\/2025\\/40708181\\/48EA48\\/2025-40708181-48EA48-6.jpg\",\"thumb\":\"\\/pnl2\\/uploads\\/2025\\/40708181\\/48EA48\\/thumbs\\/thumb-2025-40708181-48EA48-6.jpg\"},{\"original_name\":\"CIMG0227.JPG\",\"stored\":\"2025-40708181-48EA48-10.jpg\",\"ext\":\"jpg\",\"path\":\"\\/pnl2\\/uploads\\/2025\\/40708181\\/48EA48\\/2025-40708181-48EA48-10.jpg\",\"thumb\":\"\\/pnl2\\/uploads\\/2025\\/40708181\\/48EA48\\/thumbs\\/thumb-2025-40708181-48EA48-10.jpg\"}]', '2025-10-30 00:51:55'),
(11, '11AEA11', '41201702', '4', '[{\"original_name\":\"CIMG0251.JPG\",\"stored\":\"2025-41201702-11AEA11-1.jpg\",\"ext\":\"jpg\",\"path\":\"\\/pnl2\\/uploads\\/2025\\/41201702\\/11AEA11\\/2025-41201702-11AEA11-1.jpg\",\"thumb\":\"\\/pnl2\\/uploads\\/2025\\/41201702\\/11AEA11\\/thumbs\\/thumb-2025-41201702-11AEA11-1.jpg\"},{\"original_name\":\"CIMG0252.JPG\",\"stored\":\"2025-41201702-11AEA11-3.jpg\",\"ext\":\"jpg\",\"path\":\"\\/pnl2\\/uploads\\/2025\\/41201702\\/11AEA11\\/2025-41201702-11AEA11-3.jpg\",\"thumb\":\"\\/pnl2\\/uploads\\/2025\\/41201702\\/11AEA11\\/thumbs\\/thumb-2025-41201702-11AEA11-3.jpg\"},{\"original_name\":\"CIMG0255.JPG\",\"stored\":\"2025-41201702-11AEA11-6.jpg\",\"ext\":\"jpg\",\"path\":\"\\/pnl2\\/uploads\\/2025\\/41201702\\/11AEA11\\/2025-41201702-11AEA11-6.jpg\",\"thumb\":\"\\/pnl2\\/uploads\\/2025\\/41201702\\/11AEA11\\/thumbs\\/thumb-2025-41201702-11AEA11-6.jpg\"},{\"original_name\":\"CIMG0257_düzenlendi-1.JPG\",\"stored\":\"2025-41201702-11AEA11-10.jpg\",\"ext\":\"jpg\",\"path\":\"\\/pnl2\\/uploads\\/2025\\/41201702\\/11AEA11\\/2025-41201702-11AEA11-10.jpg\",\"thumb\":\"\\/pnl2\\/uploads\\/2025\\/41201702\\/11AEA11\\/thumbs\\/thumb-2025-41201702-11AEA11-10.jpg\"}]', '2025-10-30 00:57:08'),
(12, '34MZY436', '41023014', '4', '[{\"original_name\":\"ESKİ OCV 1.jpg\",\"stored\":\"2025-41023014-34MZY436-1.jpg\",\"ext\":\"jpg\",\"path\":\"\\/pnl2\\/uploads\\/2025\\/41023014\\/34MZY436\\/2025-41023014-34MZY436-1.jpg\",\"thumb\":\"\\/pnl2\\/uploads\\/2025\\/41023014\\/34MZY436\\/thumbs\\/thumb-2025-41023014-34MZY436-1.jpg\"}]', '2025-10-30 16:29:49'),
(13, '34FVC272', '41030001', '4', '[{\"original_name\":\"IMG-20251030-WA0076.jpg\",\"stored\":\"2025-41030001-34FVC272-1.jpg\",\"ext\":\"jpg\",\"path\":\"\\/pnl2\\/uploads\\/2025\\/41030001\\/34FVC272\\/2025-41030001-34FVC272-1.jpg\",\"thumb\":\"\\/pnl2\\/uploads\\/2025\\/41030001\\/34FVC272\\/thumbs\\/thumb-2025-41030001-34FVC272-1.jpg\"},{\"original_name\":\"IMG-20251030-WA0074.jpg\",\"stored\":\"2025-41030001-34FVC272-3.jpg\",\"ext\":\"jpg\",\"path\":\"\\/pnl2\\/uploads\\/2025\\/41030001\\/34FVC272\\/2025-41030001-34FVC272-3.jpg\",\"thumb\":\"\\/pnl2\\/uploads\\/2025\\/41030001\\/34FVC272\\/thumbs\\/thumb-2025-41030001-34FVC272-3.jpg\"},{\"original_name\":\"IMG-20251030-WA0075.jpg\",\"stored\":\"2025-41030001-34FVC272-6.jpg\",\"ext\":\"jpg\",\"path\":\"\\/pnl2\\/uploads\\/2025\\/41030001\\/34FVC272\\/2025-41030001-34FVC272-6.jpg\",\"thumb\":\"\\/pnl2\\/uploads\\/2025\\/41030001\\/34FVC272\\/thumbs\\/thumb-2025-41030001-34FVC272-6.jpg\"},{\"original_name\":\"IMG-20251030-WA0073.jpg\",\"stored\":\"2025-41030001-34FVC272-10.jpg\",\"ext\":\"jpg\",\"path\":\"\\/pnl2\\/uploads\\/2025\\/41030001\\/34FVC272\\/2025-41030001-34FVC272-10.jpg\",\"thumb\":\"\\/pnl2\\/uploads\\/2025\\/41030001\\/34FVC272\\/thumbs\\/thumb-2025-41030001-34FVC272-10.jpg\"},{\"original_name\":\"IMG-20251030-WA0068.jpg\",\"stored\":\"2025-41030001-34FVC272-15.jpg\",\"ext\":\"jpg\",\"path\":\"\\/pnl2\\/uploads\\/2025\\/41030001\\/34FVC272\\/2025-41030001-34FVC272-15.jpg\",\"thumb\":\"\\/pnl2\\/uploads\\/2025\\/41030001\\/34FVC272\\/thumbs\\/thumb-2025-41030001-34FVC272-15.jpg\"},{\"original_name\":\"IMG-20251030-WA0072.jpg\",\"stored\":\"2025-41030001-34FVC272-21.jpg\",\"ext\":\"jpg\",\"path\":\"\\/pnl2\\/uploads\\/2025\\/41030001\\/34FVC272\\/2025-41030001-34FVC272-21.jpg\",\"thumb\":\"\\/pnl2\\/uploads\\/2025\\/41030001\\/34FVC272\\/thumbs\\/thumb-2025-41030001-34FVC272-21.jpg\"},{\"original_name\":\"IMG-20251030-WA0066.jpg\",\"stored\":\"2025-41030001-34FVC272-28.jpg\",\"ext\":\"jpg\",\"path\":\"\\/pnl2\\/uploads\\/2025\\/41030001\\/34FVC272\\/2025-41030001-34FVC272-28.jpg\",\"thumb\":\"\\/pnl2\\/uploads\\/2025\\/41030001\\/34FVC272\\/thumbs\\/thumb-2025-41030001-34FVC272-28.jpg\"},{\"original_name\":\"IMG-20251030-WA0064.jpg\",\"stored\":\"2025-41030001-34FVC272-36.jpg\",\"ext\":\"jpg\",\"path\":\"\\/pnl2\\/uploads\\/2025\\/41030001\\/34FVC272\\/2025-41030001-34FVC272-36.jpg\",\"thumb\":\"\\/pnl2\\/uploads\\/2025\\/41030001\\/34FVC272\\/thumbs\\/thumb-2025-41030001-34FVC272-36.jpg\"},{\"original_name\":\"IMG-20251030-WA0065.jpg\",\"stored\":\"2025-41030001-34FVC272-45.jpg\",\"ext\":\"jpg\",\"path\":\"\\/pnl2\\/uploads\\/2025\\/41030001\\/34FVC272\\/2025-41030001-34FVC272-45.jpg\",\"thumb\":\"\\/pnl2\\/uploads\\/2025\\/41030001\\/34FVC272\\/thumbs\\/thumb-2025-41030001-34FVC272-45.jpg\"},{\"original_name\":\"IMG-20251030-WA0063.jpg\",\"stored\":\"2025-41030001-34FVC272-55.jpg\",\"ext\":\"jpg\",\"path\":\"\\/pnl2\\/uploads\\/2025\\/41030001\\/34FVC272\\/2025-41030001-34FVC272-55.jpg\",\"thumb\":\"\\/pnl2\\/uploads\\/2025\\/41030001\\/34FVC272\\/thumbs\\/thumb-2025-41030001-34FVC272-55.jpg\"},{\"original_name\":\"IMG-20251030-WA0062.jpg\",\"stored\":\"2025-41030001-34FVC272-66.jpg\",\"ext\":\"jpg\",\"path\":\"\\/pnl2\\/uploads\\/2025\\/41030001\\/34FVC272\\/2025-41030001-34FVC272-66.jpg\",\"thumb\":\"\\/pnl2\\/uploads\\/2025\\/41030001\\/34FVC272\\/thumbs\\/thumb-2025-41030001-34FVC272-66.jpg\"},{\"original_name\":\"IMG-20251030-WA0061.jpg\",\"stored\":\"2025-41030001-34FVC272-78.jpg\",\"ext\":\"jpg\",\"path\":\"\\/pnl2\\/uploads\\/2025\\/41030001\\/34FVC272\\/2025-41030001-34FVC272-78.jpg\",\"thumb\":\"\\/pnl2\\/uploads\\/2025\\/41030001\\/34FVC272\\/thumbs\\/thumb-2025-41030001-34FVC272-78.jpg\"},{\"original_name\":\"IMG-20251030-WA0056.jpeg\",\"stored\":\"2025-41030001-34FVC272-91.jpeg\",\"ext\":\"jpeg\",\"path\":\"\\/pnl2\\/uploads\\/2025\\/41030001\\/34FVC272\\/2025-41030001-34FVC272-91.jpeg\",\"thumb\":\"\\/pnl2\\/uploads\\/2025\\/41030001\\/34FVC272\\/thumbs\\/thumb-2025-41030001-34FVC272-91.jpg\"},{\"original_name\":\"IMG-20251030-WA0055.jpeg\",\"stored\":\"2025-41030001-34FVC272-105.jpeg\",\"ext\":\"jpeg\",\"path\":\"\\/pnl2\\/uploads\\/2025\\/41030001\\/34FVC272\\/2025-41030001-34FVC272-105.jpeg\",\"thumb\":\"\\/pnl2\\/uploads\\/2025\\/41030001\\/34FVC272\\/thumbs\\/thumb-2025-41030001-34FVC272-105.jpg\"}]', '2025-10-30 19:04:26');

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `fullname` varchar(200) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `branch_code` char(1) DEFAULT NULL,
  `avatar` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `is_admin` tinyint(1) NOT NULL DEFAULT 0,
  `phone` varchar(30) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Tablo döküm verisi `users`
--

INSERT INTO `users` (`id`, `username`, `fullname`, `password_hash`, `branch_code`, `avatar`, `created_at`, `is_admin`, `phone`) VALUES
(1, 'MrTurko53', 'Mehmet Akif SARI', '$2y$10$UBCLhrYjHOgqfT7HwfiFwurnro5fgczqZke.XN.g.lbT.pDFebhw6', '4', NULL, '2025-10-29 21:42:24', 0, '5446995924'),
(3, 'admin', 'Sistem Yöneticisi', '$2y$10$xOWfCu3qs10Zg1ayfMby.etbYOn3MxpQ5QXU.c7uMK7pnp2kSn8VG', NULL, NULL, '2025-10-29 21:53:18', 1, NULL),
(4, 'sezginkarakurt', 'Sezgin Karakurt', '$2y$10$6hW5ku.bmZOI67UIg6T2i.8Wp4U96XjVhj7l2i6kF.u.PrxgWx9Wm', '1', NULL, '2025-10-30 01:29:14', 0, '5373706248'),
(5, 'hilal', 'Hilal DAL', '$2y$10$6OwXTHbgQOc9XSo3PeVzsuSlS9eUnUvMcRr9soQKRew24OwVwM8Je', '5', NULL, '2025-10-30 01:29:47', 0, '5469714744'),
(6, 'cihan', 'Cihan Makal', '$2y$10$CEdiffVbc/k71dW.eoNP7ujSU7pdTISzU.OoxP3S1FRoZLiF3BM12', '3', NULL, '2025-10-30 01:30:36', 0, '5446503537'),
(7, 'safak', 'Şafak Altun', '$2y$10$B/oyISS9XKVjHeDIGjFrt.ejHg7jgu2yB3gDAK5mzKoF6cSTh3.CG', '2', NULL, '2025-10-30 01:31:23', 0, '5330911445');

--
-- Dökümü yapılmış tablolar için indeksler
--

--
-- Tablo için indeksler `logs`
--
ALTER TABLE `logs`
  ADD PRIMARY KEY (`id`);

--
-- Tablo için indeksler `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`);

--
-- Tablo için indeksler `tickets`
--
ALTER TABLE `tickets`
  ADD PRIMARY KEY (`id`);

--
-- Tablo için indeksler `uploads`
--
ALTER TABLE `uploads`
  ADD PRIMARY KEY (`id`);

--
-- Tablo için indeksler `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Dökümü yapılmış tablolar için AUTO_INCREMENT değeri
--

--
-- Tablo için AUTO_INCREMENT değeri `logs`
--
ALTER TABLE `logs`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- Tablo için AUTO_INCREMENT değeri `tickets`
--
ALTER TABLE `tickets`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `uploads`
--
ALTER TABLE `uploads`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- Tablo için AUTO_INCREMENT değeri `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
