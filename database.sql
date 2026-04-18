-- --------------------------------------------------------
-- Struktur untuk tabel `karyawan`
-- --------------------------------------------------------

CREATE TABLE `karyawan` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nik` varchar(20) NOT NULL,
  `nama` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `no_hp` varchar(20) DEFAULT NULL,
  `gender` enum('Pria','Wanita') DEFAULT NULL,
  `tgl_lahir` date DEFAULT NULL,
  `tgl_bergabung` date DEFAULT NULL,
  `status_pegawai` varchar(50) DEFAULT NULL,
  `awal_kontrak` date DEFAULT NULL,
  `akhir_probation` date DEFAULT NULL,
  `penempatan` varchar(150) DEFAULT NULL,
  `tipe_kerja` varchar(20) DEFAULT NULL,
  `level_jabatan` varchar(50) DEFAULT NULL,
  `posisi` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `nik` (`nik`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Data awal untuk tabel `karyawan`
-- (Password dibuat unik untuk masing-masing user tanpa hashing)
-- --------------------------------------------------------

INSERT INTO `karyawan` (`nik`, `nama`, `email`, `password`, `no_hp`, `gender`, `tgl_lahir`, `tgl_bergabung`, `status_pegawai`, `awal_kontrak`, `akhir_probation`, `penempatan`, `tipe_kerja`, `level_jabatan`, `posisi`) VALUES
('HR001', 'M. Fadli Kurniawan', 'mfadlikurniawann@gmail.com', 'FadliHR001!', '82289188605', 'Pria', '2003-01-18', '2026-04-13', 'Probation (2 Bulan)', '2026-04-13', '2026-06-13', 'HO', 'WFO', 'SPV', 'HCG'),
('BT001', 'Adel Nurwidya', 'adelwidya199@gmail.com', 'AdelBT001!', '85789925086', 'Wanita', '2006-03-03', '2026-04-13', 'Probation (2 Bulan)', '2026-04-13', '2026-06-13', 'HO', 'WFO', 'Staff', 'Beauty Therapist'),
('BT002', 'Vika Aguera Pujasmara', 'vikapujaa@gmail.com', 'VikaBT002!', '82177548199', 'Wanita', '2005-08-11', '2026-04-13', 'Probation (2 Bulan)', '2026-04-13', '2026-06-13', 'HO', 'WFO', 'Staff', 'Beauty Therapist');

INSERT INTO `karyawan` (`nik`, `nama`, `email`, `password`, `no_hp`, `gender`, `tgl_lahir`, `tgl_bergabung`, `status_pegawai`, `awal_kontrak`, `akhir_probation`, `penempatan`, `tipe_kerja`, `level_jabatan`, `posisi`) 
VALUES (
    'OWN001', 
    'Meilinda Juwita Sari', 
    'meilinda.owner@gmail.com', 
    'OwnerLBQueen123!', 
    '081234567890', 
    'Wanita', 
    '1995-05-05', 
    '2022-01-01', 
    'Karyawan Tetap', 
    NULL, 
    NULL, 
    'HO', 
    'WFO', 
    'Owner', 
    'Owner / Founder'
);
-- --------------------------------------------------------
-- Struktur untuk tabel `absensi`
-- --------------------------------------------------------

CREATE TABLE `absensi` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nik` varchar(20) NOT NULL,
  `waktu` datetime NOT NULL,
  `jenis` enum('Check In','Check Out') NOT NULL,
  `lokasi` varchar(150) DEFAULT NULL,
  `status` enum('Hadir','Telat','Tidak Hadir','-') DEFAULT '-',
  `foto` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `nik` (`nik`),
  CONSTRAINT `fk_karyawan_absen` FOREIGN KEY (`nik`) REFERENCES `karyawan` (`nik`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Data dummy awal untuk tabel `absensi` 
-- --------------------------------------------------------

INSERT INTO `absensi` (`nik`, `waktu`, `jenis`, `lokasi`, `status`, `foto`) VALUES
('HR001', '2026-04-14 08:50:00', 'Check In', 'HO', 'Hadir', NULL),
('HR001', '2026-04-14 17:05:00', 'Check Out', 'HO', '-', NULL),
('BT001', '2026-04-14 09:20:00', 'Check In', 'HO', 'Telat', NULL),
('BT001', '2026-04-14 17:10:00', 'Check Out', 'HO', '-', NULL);

CREATE TABLE `perjalanan_dinas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nik` varchar(20) NOT NULL,
  `tujuan` varchar(150) NOT NULL,
  `tgl_berangkat` date NOT NULL,
  `tgl_kembali` date NOT NULL,
  `keterangan` text NOT NULL,
  `status` enum('Pending','Disetujui','Ditolak') DEFAULT 'Pending',
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`nik`) REFERENCES `karyawan` (`nik`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `reimburse` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nik` varchar(20) NOT NULL,
  `kategori` varchar(100) NOT NULL,
  `nominal` int(11) NOT NULL,
  `keterangan` text NOT NULL,
  `foto_nota` longtext NOT NULL,
  `status` enum('Pending','Disetujui','Ditolak') DEFAULT 'Pending',
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`nik`) REFERENCES `karyawan` (`nik`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `lembur` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nik` varchar(20) NOT NULL,
  `tanggal` date NOT NULL,
  `jam_mulai` time NOT NULL,
  `jam_selesai` time NOT NULL,
  `keterangan` text NOT NULL,
  `status` enum('Pending','Disetujui','Ditolak') DEFAULT 'Disetujui',
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`nik`) REFERENCES `karyawan` (`nik`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;