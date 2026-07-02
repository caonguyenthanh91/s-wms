-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Jun 15, 2026 at 10:14 AM
-- Server version: 5.7.41-cll-lve
-- PHP Version: 7.2.34

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `fdbrhflu_pms-db`
--

-- --------------------------------------------------------

--
-- Table structure for table `export_temp`
--

CREATE TABLE `export_temp` (
  `id` int(11) NOT NULL,
  `command` varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL,
  `for_product` varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL,
  `product_id` varchar(50) COLLATE utf8_unicode_ci NOT NULL,
  `total_qty` int(11) NOT NULL,
  `bucket_qty` int(11) NOT NULL,
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `export_temp`
--
ALTER TABLE `export_temp`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `sku` (`product_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `export_temp`
--
ALTER TABLE `export_temp`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
