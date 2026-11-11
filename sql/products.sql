-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- ホスト: 127.0.0.1
-- 生成日時: 2026-11-04 04:05:08
-- サーバのバージョン： 10.4.32-MariaDB
-- PHP のバージョン: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- データベース: `playduo`
--

-- --------------------------------------------------------

--
-- テーブルの構造 `products`
--

CREATE TABLE `products` (
  `product_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `title_en` varchar(100) DEFAULT NULL,
  `price` int(11) NOT NULL,
  `description` text NOT NULL,
  `genre` varchar(50) NOT NULL,
  `capacity` decimal(5,1) NOT NULL,
  `age_rating` varchar(20) NOT NULL,
  `developer` varchar(100) NOT NULL,
  `release_date` date NOT NULL,
  `image` varchar(255) NOT NULL,
  `screenshot1` varchar(255) NOT NULL,
  `screenshot2` varchar(255) NOT NULL,
  `screenshot3` varchar(255) NOT NULL,
  `min_players` int(11) NOT NULL,
  `max_players` int(11) NOT NULL,
  `play_style` varchar(10) NOT NULL,
  `crossplay_flag` tinyint(4) NOT NULL DEFAULT 0,
  `os_windows` tinyint(4) NOT NULL,
  `os_mac` tinyint(4) NOT NULL,
  `play_time` varchar(20) NOT NULL,
  `recommend_order` int(11) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `public_flag` tinyint(4) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- ダンプしたテーブルのインデックス
--

--
-- テーブルのインデックス `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`product_id`);

--
-- ダンプしたテーブルの AUTO_INCREMENT
--

--
-- テーブルの AUTO_INCREMENT `products`
--
ALTER TABLE `products`
  MODIFY `product_id` int(11) NOT NULL AUTO_INCREMENT;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
