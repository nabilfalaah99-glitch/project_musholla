<?php

function alarm_audio_file_columns()
{
    return [
        'sound_file',
        'nama_file',
        'file_audio',
        'audio_file',
        'alarm_file',
        'nama_audio',
        'audio',
        'sound',
        'nama_sound',
        'sound_alarm',
    ];
}

function alarm_audio_column($connection)
{
    if (!($connection instanceof mysqli)) {
        return null;
    }

    try {
        $columns_result = mysqli_query($connection, "SHOW COLUMNS FROM app_timer_iqomah");
        if (!$columns_result) {
            return null;
        }

        while ($column = mysqli_fetch_assoc($columns_result)) {
            if (in_array($column['Field'], alarm_audio_file_columns(), true)) {
                return $column['Field'];
            }
        }
    } catch (mysqli_sql_exception $exception) {
        error_log('Could not inspect app_timer_iqomah audio column: ' . $exception->getMessage());
    }

    return null;
}

function current_alarm_audio($connection, $audio_directory)
{
    $column_name = alarm_audio_column($connection);
    if ($column_name !== null) {
        try {
            $audio_result = mysqli_query(
                $connection,
                "SELECT `$column_name` FROM app_timer_iqomah WHERE id=1 LIMIT 1"
            );
            if ($audio_result && ($audio_row = mysqli_fetch_assoc($audio_result))) {
                $audio_name = $audio_row[$column_name];
                if (
                    is_string($audio_name)
                    && preg_match('/\A(?:alarm\.mp3|alarm_[0-9]+_[a-f0-9]+\.mp3)\z/', $audio_name)
                    && is_file($audio_directory . $audio_name)
                ) {
                    return $audio_name;
                }
            }
        } catch (mysqli_sql_exception $exception) {
            error_log('Could not read alarm audio setting: ' . $exception->getMessage());
        }
    }

    $manifest_path = $audio_directory . 'current_alarm.txt';
    if (is_file($manifest_path)) {
        $manifest_content = file_get_contents($manifest_path);
        $audio_name = is_string($manifest_content) ? trim($manifest_content) : '';
        if (
            preg_match('/\A(?:alarm\.mp3|alarm_[0-9]+_[a-f0-9]+\.mp3)\z/', $audio_name)
            && is_file($audio_directory . $audio_name)
        ) {
            return $audio_name;
        }
    }

    $audio_files = glob($audio_directory . 'alarm_*.mp3') ?: [];
    if ($audio_files) {
        usort($audio_files, static function ($first, $second) {
            return filemtime($second) <=> filemtime($first);
        });
        return basename($audio_files[0]);
    }

    return 'alarm.mp3';
}
