-- CBT-HERO Academic Schema Preflight (READ ONLY)
-- Jalankan pada database CBT-HERO yang sedang dipakai.
-- Script ini HANYA membaca information_schema; tidak menjalankan ALTER/CREATE/DROP/UPDATE.
-- Hasil MISSING berarti perlu ditinjau terhadap asal database dan upgrade yang pernah diterapkan.
-- JANGAN menjalankan semua file *_UPGRADE.sql secara borongan.

SELECT DATABASE() AS selected_database;

SELECT DISTINCT
    expected.object_type,
    expected.table_name,
    expected.object_name,
    CASE
        WHEN expected.object_type = 'TABLE' AND t.table_name IS NOT NULL THEN 'PRESENT'
        WHEN expected.object_type = 'COLUMN' AND c.column_name IS NOT NULL THEN 'PRESENT'
        WHEN expected.object_type = 'INDEX' AND s.index_name IS NOT NULL THEN 'PRESENT'
        ELSE 'MISSING'
    END AS object_status,
    CASE
        WHEN expected.object_type = 'COLUMN' AND c.column_name IS NOT NULL
            THEN CONCAT(c.column_type, ' / nullable=', c.is_nullable)
        WHEN expected.object_type = 'INDEX' AND s.index_name IS NOT NULL
            THEN CONCAT('non_unique=', s.non_unique)
        ELSE NULL
    END AS actual_definition
FROM (
    SELECT 'TABLE' AS object_type, 'credential_operations' AS table_name, '-' AS object_name
    UNION ALL SELECT 'TABLE', 'jadwal_type_selection', '-'
    UNION ALL SELECT 'TABLE', 'jadwal_peserta_target', '-'
    UNION ALL SELECT 'TABLE', 'jadwal_operations', '-'
    UNION ALL SELECT 'TABLE', 'prepared_assignment', '-'
    UNION ALL SELECT 'TABLE', 'prepared_assignment_item', '-'
    UNION ALL SELECT 'TABLE', 'prepared_assignment_media', '-'
    UNION ALL SELECT 'TABLE', 'participant_operations', '-'
    UNION ALL SELECT 'TABLE', 'attempt', '-'
    UNION ALL SELECT 'TABLE', 'attempt_active_lock', '-'
    UNION ALL SELECT 'TABLE', 'attempt_response', '-'
    UNION ALL SELECT 'TABLE', 'attempt_control_operations', '-'
    UNION ALL SELECT 'TABLE', 'result_snapshot', '-'
    UNION ALL SELECT 'TABLE', 'result_item_snapshot', '-'
    UNION ALL SELECT 'TABLE', 'official_result_pointer', '-'
    UNION ALL SELECT 'TABLE', 'live_scoring_config', '-'

    UNION ALL SELECT 'COLUMN', 'peserta', 'credential_revision'
    UNION ALL SELECT 'COLUMN', 'peserta_kegiatan', 'assignment_source'
    UNION ALL SELECT 'COLUMN', 'peserta_kegiatan', 'assignment_scope'
    UNION ALL SELECT 'COLUMN', 'bank_type_config', 'question_count'
    UNION ALL SELECT 'COLUMN', 'bank_type_config', 'option_count'
    UNION ALL SELECT 'COLUMN', 'jadwal', 'urutan_ujian'
    UNION ALL SELECT 'COLUMN', 'token_control', 'next_token'
    UNION ALL SELECT 'COLUMN', 'attempt_response', 'effective_score'
    UNION ALL SELECT 'COLUMN', 'result_snapshot', 'is_final'

    UNION ALL SELECT 'INDEX', 'peserta_kegiatan', 'idx_pk_kegiatan_source'
    UNION ALL SELECT 'INDEX', 'jadwal', 'idx_jadwal_slot_order'
) AS expected
LEFT JOIN information_schema.TABLES AS t
    ON expected.object_type = 'TABLE'
    AND t.table_schema = DATABASE()
    AND t.table_name = expected.table_name
LEFT JOIN information_schema.COLUMNS AS c
    ON expected.object_type = 'COLUMN'
    AND c.table_schema = DATABASE()
    AND c.table_name = expected.table_name
    AND c.column_name = expected.object_name
LEFT JOIN information_schema.STATISTICS AS s
    ON expected.object_type = 'INDEX'
    AND s.table_schema = DATABASE()
    AND s.table_name = expected.table_name
    AND s.index_name = expected.object_name
ORDER BY
    FIELD(expected.object_type, 'TABLE', 'COLUMN', 'INDEX'),
    expected.table_name,
    expected.object_name;
