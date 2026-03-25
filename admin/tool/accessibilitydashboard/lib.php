<?php
// Shared logging helpers for the Accessibility Dashboard admin tool.

defined('MOODLE_INTERNAL') || die();

/**
 * Regista mensagens de erro/debug do Accessibility Dashboard em ficheiros dentro
 * de admin/tool/accessibilitydashboard/debug.
 *
 * @param string $message Mensagem principal de log.
 * @param array $data Dados adicionais serializados em JSON.
 * @param string $filename Nome do ficheiro de log dentro da pasta debug.
 */
function tool_accessibilitydashboard_log_error(string $message, array $data = [], string $filename = 'accessibilitydashboard_debug.log'): void {
    global $CFG;

    if (empty($CFG) || empty($CFG->dirroot)) {
        // Fallback para não falhar em contextos sem $CFG apropriado.
        error_log('AccessibilityDashboard DEBUG (fallback) - ' . $message . ' ' . json_encode($data));
        return;
    }

    $debugdir = $CFG->dirroot . '/admin/tool/accessibilitydashboard/debug';
    if (!is_dir($debugdir)) {
        @mkdir($debugdir, 0775, true);
    }

    $logfile = $debugdir . '/' . $filename;
    $entry = '[' . date('Y-m-d H:i:s') . "] " . $message;
    if (!empty($data)) {
        $entry .= ' ' . json_encode($data);
    }
    $entry .= PHP_EOL;

    @file_put_contents($logfile, $entry, FILE_APPEND);
}
