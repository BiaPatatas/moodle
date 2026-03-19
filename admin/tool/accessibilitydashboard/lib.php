<?php
// Shared helpers for the Accessibility Dashboard admin tool.

defined('MOODLE_INTERNAL') || die();

/**
 * Regista erros do tool_accessibilitydashboard em ficheiros de log.
 *
 * Só escreve quando chamada explicitamente em situações de erro.
 * Os ficheiros são gravados em $CFG->dataroot . '/accessibilitydashboard_logs/'.
 *
 * @param string $message Mensagem principal do erro.
 * @param array|null $data Dados adicionais (serão guardados em JSON).
 */
function tool_accessibilitydashboard_log_error(string $message, ?array $data = null): void {
    global $CFG, $USER;

    try {
        $logdir = $CFG->dataroot . '/accessibilitydashboard_logs';
        if (!is_dir($logdir)) {
            @mkdir($logdir, $CFG->directorypermissions ?? 0777, true);
        }

        $logfile = $logdir . '/error-' . date('Ymd') . '.log';
        $parts = [];
        $parts[] = date('Y-m-d H:i:s');
        if (!empty($USER) && !empty($USER->id)) {
            $parts[] = 'user=' . $USER->id;
        }
        $parts[] = $message;
        if (!empty($data)) {
            $parts[] = 'data=' . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $line = implode(' | ', $parts) . PHP_EOL;
        @file_put_contents($logfile, $line, FILE_APPEND);
    } catch (Throwable $e) {
        // Nunca deixar o logging provocar erros adicionais.
    }
}
