-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Nov 19, 2026 at 02:19 AM
-- Server version: 8.0.30
-- PHP Version: 8.4.27

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `db_nusantara_peak`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin_ops_notes`
--

CREATE TABLE `admin_ops_notes` (
  `id` int NOT NULL,
  `gunung_enc` text NOT NULL,
  `judul_enc` text NOT NULL,
  `isi_laporan_enc` longtext NOT NULL,
  `prioritas_enc` text NOT NULL,
  `encrypted_aes_key` text NOT NULL,
  `aes_iv` varchar(64) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `admin_ops_notes`
--

INSERT INTO `admin_ops_notes` (`id`, `gunung_enc`, `judul_enc`, `isi_laporan_enc`, `prioritas_enc`, `encrypted_aes_key`, `aes_iv`, `created_at`) VALUES
(1, '8KNQ+iAk8PIsr6ibEl3OCx6YwXXyCiZxyBS9UCHH8Wc=', 'wyQIX0vFflLqD1OXT6px2RHGYCJn5U3RFSx7qT3TN1G0zSXWYGsIr1Wm7PtDdg2G', 'Yg5S31O5zIuWD5ufNlTbPgTxW1Vx4kng0IfTwVKqBhgOZvfHKee38jVmXED/GMyb1ugU+ZQ2etLDj25BH9nZ1NtKz8VI/BYjr6W+Tngj8oRUBb+V/IZ/Pd1uLJC2VCfhWhpsPNdrRfBTAJQtiUQE6g==', 'YJdONZNF1OjymqhZITb1Xg==', 'Mb6AfIIeIqPSv5G0F706IPyi09XS3D3LJkCgxc+5GVujRXnOtMAqZWu0hE5Fy7/F6H8U9lwgAKamQH+VV1hpF9q/YkX+QH4gHpS0+lM/ePPBcJI3KiYaDMExku/HjId0gqjJNa0KxvuyNXe0c6fwLELtyvKwcIwQfZA2IUizqVyhkif0eu4gOkJiWNw4EqIDd0qGEuElb+z/Ry/PcFer5vOHMuyw6Y+YY0uF1sIU7TehK2J5QXUeswvfh9ZbTOnxwNbuz+qiNwoW8+ehkjuTcxPJ77q3BvBnjiyx384roj4KzHZ8f8Z8E7Jx0jwsi/WfB1btPCUYikuY8ONvqtGEsg==', 'Yu+Y/K3WgV13agJhcWI3Xw==', '2026-11-19 01:25:24');

-- --------------------------------------------------------

--
-- Table structure for table `bookings`
--

CREATE TABLE `bookings` (
  `id` int NOT NULL,
  `kode_booking_enc` text NOT NULL,
  `nama_pendaki_enc` text NOT NULL,
  `nik_enc` text NOT NULL,
  `telepon_enc` text NOT NULL,
  `gunung_enc` text NOT NULL,
  `jalur_enc` text NOT NULL,
  `tanggal_enc` text NOT NULL,
  `jumlah_orang_enc` text NOT NULL,
  `status_enc` text NOT NULL,
  `catatan_admin_enc` text NOT NULL,
  `encrypted_aes_key` text NOT NULL,
  `aes_iv` varchar(64) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `bookings`
--

INSERT INTO `bookings` (`id`, `kode_booking_enc`, `nama_pendaki_enc`, `nik_enc`, `telepon_enc`, `gunung_enc`, `jalur_enc`, `tanggal_enc`, `jumlah_orang_enc`, `status_enc`, `catatan_admin_enc`, `encrypted_aes_key`, `aes_iv`, `created_at`) VALUES
(1, 'ZNc34fwTRBY7mhLseIzYXA==', '2MGfxyB8GkQ/mznYU1B4Yg==', 'uYVQEA5V55EDfCNaxsj51nIfFikx1kU6sNGkUoSfq3I=', 'sS4VNPhVHaSDW8Ia8URPJA==', 'b20k9Hvh0qc4sYl3amfImGLSOBuWe9UVrf2PBoZctyU=', 'oUKoQECeASxn5DPiCFXqFQ==', 'DQugSsJOdYl3Pi9n3RXCyQ==', 'lwflPE+XfLehz6BMKz6kcg==', '39pAHMKDkUFLDtF8Ulx4dg==', 'YMx8njgI3ZRL9b/c6oQwok4sCxIsrVi+kCCXvSlc5Z+3nRdfU72D9QnOidxZWQkgIwEM4uTWhrouOC4VuXiv135DyQ4mv02pKEHvcXedP2w=', 'X8TAui7E//I4iN2EFMIqjx36JIeHXIiaEVO0O6qa3u00I97TEmxcwWDNWQv75cfd6v1FUh+aeiCBIrJ89iBhEwFoFCtf7wyHTNnokFAGgWME67zdjSMNAJoAnMFjy/Dr7w+zEaQ7J7mVUOOLf9QPIhnFaG21gtkUDMLWInvt99pv+yTvoRVOesfxeWjICfKAPppY6U85a+ksN5pudxN2ktEZXeeQgU05blkgtxyLyOifHmkftpaJ7aMpbBZ4xR4jfa4iR5nr6wdMd5NP20msK5fFI0Uo0CsGOHCpVPe8e57O8iUqwsXsywKgyDYj+yQTL3wcR/B+kRDEn/Jzbcjk/g==', '+o1DMBSfID/rpltAZq28lA==', '2026-11-19 01:25:24'),
(2, 'bhplXo4CvTjeYIC8DuUdIg==', 'ryC/fKo8OL4yEpr6ofDdow==', '0LqJp02PzZw6pLGe+anzD5dPCTZg/pxPfbq5ixqTLbU=', 'EuHQ+N4Euib0EFLREtAamw==', 'v9sFJxwXOh8Zsg4mqfVDWw==', 'K2YqRAq3QWDZOyJa67Uo+A==', '+kXbi4JZRP8Qwh77phHsmQ==', '9Qi6Ic1MLNnURtMlWp/UAg==', 'XLL3ZtgiF20QhCj6Ba9FSw==', 'scLQPw266h8i5+3mOA98SKds95UiK8EltwsMe1hHsDyz5s/sZSN6glaR6JAxbNUG', 'p0SIycz6WSXIheF+wSzK0K6wk8w8R1HMUC3293b6UEbRscD0LCkxzNwUq+jfcbVcTpf0DQT+wXcLUe8lxZNFX8bOdFEycUHf+CmTR5AfGBXUKCG9ZLzs+RfyEcXpV4kfuRLS6D0aPZQsB7ohFlXAZgVuHbDqyRz0+agc8uilqTzutUyPRZp5cd4nUvrJUiS72m5C3RrPiOtl0/6kS6iaXK6dNchlHMrMXZ43CI0oY2Uu6X3j6iTw+/oSHUw465EW+FCwwg7wn+rQvw/01H+7/DqMlfWf6cNJXU97b66602UpkX8md3HF/SIYL++FJcM2Ufdwg+0AWXm/fCA87LolAg==', 'L7NHVDObT7N3i+ll6YE0mg==', '2026-11-19 01:25:24'),
(3, '6txjKpToOj9YSdcaZq0hmd4fYMURMMYDv68T5UyB/b4=', 'zz3biEXC9L6HEsEfZXj+V/FwHRIEafZk3tVnZTUmhl8=', 'O89ScjSLnNtONVvrteY9jHARW3lpl7C55BjbK6etzVY=', 'szxGH/jhfmUjdb1127hiCg==', '3s0zV+WgslRrHAGVhJ6ohLwjsiWQJRJCXjruhvzxDbk=', '+YehhMXhAlUEtu4p89/etA==', 'A6cfwNuYUkiL8PLuSPcvtg==', 'FdyKk/L/TGlC0T6nE/o3Ug==', 'Sh17yQCxYmz2zVCZ8BnT6g==', 'ueFwSSg575MvP/Lnc5y4QA==', 'DES9uBkt63VKpPwvNCiI9ZTDKj/VKhvzyGxFhrxyOX+iOUeidgVigu6hJVXNwfMM502A2SXnnFmxWT91+Ak0B8nDZ2ylfy7QSddBBzDuSZhze06dVt+8FxLQHdDs8Y/oBQ/SfA9FyVbtKetexc9l8HockRmBmtA5gt5WdT1UU7h8Mc7zeYQVfY89oOSxuEUSOZ7aE1x38eaesW8TUyxJz+inv0SXukl4nJJqE4uQ+KdP1jKLakiJtsiAc6xEJShKdtTi9q5j4+nETH51666YIi0OqYI4nX805hddZ2F4hP/yAoPV1UilKl0M/M2fG9EmfWBdvA9OkUTpw8ToGHD88w==', '5nfJelXhuLOnfqCXNpWBug==', '2026-11-19 01:58:53');

-- --------------------------------------------------------

--
-- Table structure for table `crypto_config`
--

CREATE TABLE `crypto_config` (
  `id` int NOT NULL,
  `key_name` varchar(50) NOT NULL,
  `public_key` text NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `crypto_config`
--

INSERT INTO `crypto_config` (`id`, `key_name`, `public_key`, `created_at`) VALUES
(1, 'admin_master', '-----BEGIN PUBLIC KEY-----\nMIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEArWBkRDZiz3Bad/t4omle\nR1d8jwxGIDmxuF2V6c38hBG4h3SodAX/xTMKttL37BWYBUTQf5ODk8IKrC3R+qUU\ndweRNX29ZHbJbkR7Va6NVJ1KHdI4N05Fp0Mx0i/jz6UAodt89jgMwnstCAilHPvD\n0klmJnyHKE7wJ37s2n+nw++YKcvKzeC31Z9KZq+yvKB0kOQAWthLuqJzA7Uvd0zm\n8H63iuCb4/lVYBQgHlG6dzfDUgYtb4/2Xts3ujwd8dG95WivREwyQzKzoAJRVW2t\nxIJhIBseJVHNB4vb0iilty0pRVaa6MJCYv8/vTue2dVpaPqK1GQU9+cDbKOGu20+\nFQIDAQAB\n-----END PUBLIC KEY-----\n', '2026-11-19 01:25:24');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin_ops_notes`
--
ALTER TABLE `admin_ops_notes`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `bookings`
--
ALTER TABLE `bookings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `crypto_config`
--
ALTER TABLE `crypto_config`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `key_name` (`key_name`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin_ops_notes`
--
ALTER TABLE `admin_ops_notes`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `bookings`
--
ALTER TABLE `bookings`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `crypto_config`
--
ALTER TABLE `crypto_config`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
