-- Safe to run more than once. Existing indexes with the same leading column
-- are kept, so this does not create redundant copies of primary indexes.
SET @database_name = DATABASE();

SET @index_exists = (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = @database_name
      AND table_name = 'keuangan'
      AND column_name = 'jenis'
      AND seq_in_index = 1
    LIMIT 1
);
SET @index_sql = IF(
    @index_exists = 0,
    'ALTER TABLE `keuangan` ADD INDEX `idx_keuangan_jenis` (`jenis`)',
    'SELECT ''Index keuangan.jenis sudah tersedia'' LIMIT 1'
);
PREPARE index_statement FROM @index_sql;
EXECUTE index_statement;
DEALLOCATE PREPARE index_statement;

SET @index_exists = (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = @database_name
      AND table_name = 'keuangan'
      AND column_name = 'pekan_ke'
      AND seq_in_index = 1
    LIMIT 1
);
SET @index_sql = IF(
    @index_exists = 0,
    'ALTER TABLE `keuangan` ADD INDEX `idx_keuangan_pekan_ke` (`pekan_ke`)',
    'SELECT ''Index keuangan.pekan_ke sudah tersedia'' LIMIT 1'
);
PREPARE index_statement FROM @index_sql;
EXECUTE index_statement;
DEALLOCATE PREPARE index_statement;

SET @index_exists = (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = @database_name
      AND table_name = 'keuangan'
      AND column_name = 'bulan'
      AND seq_in_index = 1
    LIMIT 1
);
SET @index_sql = IF(
    @index_exists = 0,
    'ALTER TABLE `keuangan` ADD INDEX `idx_keuangan_bulan` (`bulan`)',
    'SELECT ''Index keuangan.bulan sudah tersedia'' LIMIT 1'
);
PREPARE index_statement FROM @index_sql;
EXECUTE index_statement;
DEALLOCATE PREPARE index_statement;

SET @index_exists = (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = @database_name
      AND table_name = 'jadwal_jumat'
      AND column_name = 'status_tayang'
      AND seq_in_index = 1
    LIMIT 1
);
SET @index_sql = IF(
    @index_exists = 0,
    'ALTER TABLE `jadwal_jumat` ADD INDEX `idx_jadwal_jumat_status_tayang` (`status_tayang`)',
    'SELECT ''Index jadwal_jumat.status_tayang sudah tersedia'' LIMIT 1'
);
PREPARE index_statement FROM @index_sql;
EXECUTE index_statement;
DEALLOCATE PREPARE index_statement;

SET @index_exists = (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = @database_name
      AND table_name = 'jadwal_jumat'
      AND column_name = 'tanggal'
      AND seq_in_index = 1
    LIMIT 1
);
SET @index_sql = IF(
    @index_exists = 0,
    'ALTER TABLE `jadwal_jumat` ADD INDEX `idx_jadwal_jumat_tanggal` (`tanggal`)',
    'SELECT ''Index jadwal_jumat.tanggal sudah tersedia'' LIMIT 1'
);
PREPARE index_statement FROM @index_sql;
EXECUTE index_statement;
DEALLOCATE PREPARE index_statement;
