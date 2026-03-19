<?php
// Funções auxiliares para AJAX do bloco PDFCounter

define('BLOCK_PDFCOUNTER_MAX_PER_CALL', 1); // Só avalia 1 por chamada AJAX

/**
 * Regista um erro do bloco pdfcounter em ficheiro de log.
 *
 * Só é escrito quando chamado explicitamente em casos de erro.
 * Os ficheiros são gravados em $CFG->dataroot . '/pdfcounter_logs/'.
 *
 * @param string $message Mensagem principal do erro.
 * @param array|null $data Dados adicionais (serão codificados em JSON).
 */
function block_pdfcounter_log_error(string $message, ?array $data = null): void {
    global $CFG, $USER;

    try {
        $logdir = $CFG->dataroot . '/pdfcounter_logs';
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
        // Nunca deixar o logging provocar novos erros na execução principal.
    }
}

function block_pdfcounter_get_pending_pdfs($courseid) {
    global $DB, $CFG;
    $pending = [];
    // Buscar todos os PDFs visíveis no curso (resource, folder, page, url)
    $sql = "SELECT f.filename, f.contenthash, ctx.id as contextid
        FROM {course_modules} cm
        JOIN {modules} m ON m.id = cm.module
        LEFT JOIN {resource} r ON (m.name = 'resource' AND r.id = cm.instance)
        LEFT JOIN {folder} fo ON (m.name = 'folder' AND fo.id = cm.instance)
        JOIN {context} ctx ON ctx.instanceid = cm.id
        JOIN {files} f ON f.contextid = ctx.id
        WHERE cm.course = :courseid
        AND cm.deletioninprogress = 0
        AND cm.visible = 1
        AND m.name IN ('resource', 'folder')
        AND f.component IN ('mod_resource', 'mod_folder')
        AND f.filearea = 'content'
        AND f.filename != '.'";
    $files = $DB->get_records_sql($sql, array('courseid' => $courseid));
    foreach ($files as $file) {
        if (substr($file->filename, -4) === '.pdf') {
            $filehash = $file->contenthash;
            $pdfrecord = $DB->get_record('block_pdfaccessibility_pdf_files', [
                'filehash' => $filehash,
                'courseid' => $courseid
            ]);
            // Debug logging disabled for production (previously wrote lookup info to debug_pdf_ajax.txt).
            if (!$pdfrecord) {
                $pending[] = [
                    'filename' => $file->filename,
                    'filehash' => $filehash,
                    'filepath' => $CFG->dataroot . '/filedir/' . substr($filehash, 0, 2) . '/' . substr($filehash, 2, 2) . '/' . $filehash
                ];
            }
        }
    }
    // Buscar PDFs em links de página (mod_page)
    $sqlpages = "SELECT cm.id as cmid, p.content, ctx.id as contextid
        FROM {course_modules} cm
        JOIN {modules} m ON m.id = cm.module
        JOIN {page} p ON (m.name = 'page' AND p.id = cm.instance)
        JOIN {context} ctx ON ctx.instanceid = cm.id
        WHERE cm.course = :courseid
        AND cm.deletioninprogress = 0
        AND cm.visible = 1
        AND m.name = 'page'";
    $pages = $DB->get_records_sql($sqlpages, array('courseid' => $courseid));
    foreach ($pages as $page) {
        // Extrair links <a href="...pdf"> do conteúdo da página
        if (preg_match_all('/<a[^>]+href=\"([^\"]+\.pdf)\"[^>]*>/i', $page->content, $matches)) {
            foreach ($matches[1] as $pdfurl) {
                $filename = basename($pdfurl);
                $filehash = null;
                // Tenta buscar arquivo local se for pluginfile.php
                if (preg_match('/(@@PLUGINFILE@@|\/pluginfile\.php\/)/', $pdfurl)) {
                    $filename_decoded = urldecode($filename);
                    $fsql = "SELECT contenthash FROM {files} WHERE filename = :filename AND contextid = :contextid";
                    $frec = $DB->get_record_sql($fsql, [
                        'filename' => $filename_decoded,
                        'contextid' => $page->contextid
                    ]);
                    if ($frec) {
                        $filehash = $frec->contenthash;
                    }
                }
                // Se não achou hash, é link externo
                if (!$filehash && preg_match('/^https?:\/\/.+\.pdf($|\?)/i', $pdfurl)) {
                    $filehash = sha1($pdfurl); // Hash simples do URL
                }
                // Só adiciona se não estiver na base
                $pdfrecord = $DB->get_record('block_pdfaccessibility_pdf_files', [
                    'filehash' => $filehash,
                    'courseid' => $courseid
                ]);
                // Debug logging disabled for production (page link lookups).
                if (!$pdfrecord && $filehash) {
                    $pending[] = [
                        'filename' => $filename,
                        'filehash' => $filehash,
                        'filepath' => $filehash ? ($filehash && strlen($filehash) === 40 ? $CFG->dataroot . '/filedir/' . substr($filehash, 0, 2) . '/' . substr($filehash, 2, 2) . '/' . $filehash : null) : null,
                        'url' => $pdfurl
                    ];
                }
            }
        }
    }
    // Buscar PDFs em links externos de mod_url
    $sqlurls = "SELECT cm.id as cmid, u.externalurl, ctx.id as contextid
        FROM {course_modules} cm
        JOIN {modules} m ON m.id = cm.module
        JOIN {url} u ON (m.name = 'url' AND u.id = cm.instance)
        JOIN {context} ctx ON ctx.instanceid = cm.id
        WHERE cm.course = :courseid
        AND cm.deletioninprogress = 0
        AND cm.visible = 1
        AND m.name = 'url'";
    $urls = $DB->get_records_sql($sqlurls, array('courseid' => $courseid));
    foreach ($urls as $url) {
        if (preg_match('/\.pdf($|\?)/i', $url->externalurl)) {
            // Normaliza URL: remove parâmetros após .pdf
            $urlbase = preg_replace('/(\.pdf)(\?.*)?$/i', '.pdf', $url->externalurl);
            $filename = basename($urlbase);
            $filehash = sha1($urlbase);
            // Debug logging disabled for pending external URLs in production.
            $params = [
                'filehash' => (string)$filehash,
                'courseid' => $courseid
            ];
            // Debug SQL/param logging removed for production.
            $pdfrecord = $DB->get_record('block_pdfaccessibility_pdf_files', $params);
            // Debug logging of URL lookup result disabled for production.
            if (!$pdfrecord) {
                $pending[] = [
                    'filename' => $filename,
                    'filehash' => $filehash,
                    'filepath' => null,
                    'url' => $url->externalurl
                ];
            }
        }
    }
    return $pending;
}

function block_pdfcounter_evaluate_pdf($pdfinfo, $courseid, $userid) {
        // Log all filehash and filename values for every record in the table after insert
    global $DB, $CFG;
    // Extensive debug logging removed for production; keep core evaluation logic only.
    $localpath = $pdfinfo['filepath'];
    $is_external = isset($pdfinfo['url']) && preg_match('/^https?:\/+/', $pdfinfo['url']);
    $tempfile = null;
    if ($is_external) {
        // Normaliza a URL para o hash (remove parâmetros após .pdf)
        $normalized_url = $pdfinfo['url'];
        if (preg_match('/\.pdf/i', $normalized_url, $matches, PREG_OFFSET_CAPTURE)) {
            $pos = $matches[0][1] + 4;
            $normalized_url = substr($normalized_url, 0, $pos);
        }
        $hash = sha1($normalized_url);
        // Debug logging of URL normalization disabled for production.
        $subdir1 = substr($hash, 0, 2);
        $subdir2 = substr($hash, 2, 2);
        $targetdir = $CFG->dataroot . '/filedir/' . $subdir1 . '/' . $subdir2;
        if (!is_dir($targetdir)) {
            mkdir($targetdir, 0777, true);
        }
        $targetfile = $targetdir . '/' . $hash;
        // Debug logging of external download disabled for production.
        $pdfdata = @file_get_contents($pdfinfo['url']);
        if ($pdfdata === false) {
            // Debug logging of external download failure disabled for production.
            block_pdfcounter_log_error('Falha ao baixar PDF externo', [
                'url' => $pdfinfo['url'] ?? null,
                'courseid' => $courseid,
                'userid' => $userid,
            ]);
            return ['error' => 'Falha ao baixar PDF externo'];
        }
        file_put_contents($targetfile, $pdfdata);
        $localpath = $targetfile;
        // Atualiza o filehash para o normalizado
        $pdfinfo['filehash'] = $hash;
    }
    if (!$localpath || !file_exists($localpath)) {
        // Debug logging of missing local file disabled for production.
        block_pdfcounter_log_error('Arquivo PDF não encontrado para avaliação', [
            'localpath' => $localpath,
            'courseid' => $courseid,
            'userid' => $userid,
            'filehash' => $pdfinfo['filehash'] ?? null,
        ]);
        return ['error' => 'Arquivo não encontrado'];
    }
    $script = $CFG->dirroot . '/blocks/pdfaccessibility/pdf_accessibility.py';
    $python_commands = ['python3', 'python', 'py', 'python.exe'];
    $result = null;
    foreach ($python_commands as $python) {
        $command = escapeshellarg($python) . ' ' . escapeshellarg($script) . ' ' . escapeshellarg($localpath);
        $output = shell_exec($command . ' 2>&1');
        $decoded = json_decode($output, true);
        if ($decoded && is_array($decoded)) {
            $result = $decoded;
            break;
        }
    }
    // Não apaga o arquivo temporário para evitar reavaliação repetida
    if (!$result || !is_array($result)) {
        // Debug logging of Python analysis failure disabled for production.
        block_pdfcounter_log_error('Falha na análise Python do PDF', [
            'localpath' => $localpath,
            'courseid' => $courseid,
            'userid' => $userid,
            'filehash' => $pdfinfo['filehash'] ?? null,
            'rawoutput' => isset($output) ? (string)$output : null,
        ]);
        return ['error' => 'Falha na análise Python'];
    }
    $pdfrecord = new stdClass();
    $pdfrecord->courseid = $courseid;
    $pdfrecord->userid = $userid;
    $pdfrecord->filehash = $pdfinfo['filehash'];
    $pdfrecord->filename = $pdfinfo['filename'];
    $pdfrecord->timecreated = time();
    // Debug logging of insert payload disabled for production.
    $pdfid = $DB->insert_record('block_pdfaccessibility_pdf_files', $pdfrecord, true);
    // All post-insert debug dumps disabled for production.
    pdf_accessibility_config::process_and_store_results($DB, $result, $pdfid);
    return ['success' => true, 'pdfid' => $pdfid];
}

/**
 * Fetch overall accessibility information from QualWeb for the configured monitoring registry.
 *
 * This uses the REST API exposed by the QualWeb container. Configuration is
 * provided via the block's admin settings:
 * - qualweb_api_baseurl
 * - qualweb_apikey
 * - qualweb_monitoring_id
 *
 * The function returns a small associative array ready to be sent to JS or
 * rendered in the block, or null if QualWeb is not configured or not reachable.
 *
 * @return array|null
 */
function block_pdfcounter_get_qualweb_summary() {
    global $CFG;

    $monitoringid = get_config('block_pdfcounter', 'qualweb_monitoring_id');
    if (empty($monitoringid)) {
        return null;
    }

    $baseurl = get_config('block_pdfcounter', 'qualweb_api_baseurl');
    if (empty($baseurl)) {
        $baseurl = 'http://localhost:8081/api';
    }
    $baseurl = rtrim($baseurl, '/');

    require_once($CFG->libdir . '/filelib.php');

    $curl = new curl();
    $headers = ['Accept: application/json'];
    $apikey = get_config('block_pdfcounter', 'qualweb_apikey');
    if (!empty($apikey)) {
        $headers[] = 'X-API-Key: ' . $apikey;
    }
    $options = [
        'CURLOPT_HTTPHEADER' => $headers,
        'CURLOPT_TIMEOUT' => 10,
    ];

    $score = null;
    $issues = null;

    try {
        $response = $curl->get($baseurl . '/monitoring/' . urlencode($monitoringid) . '/score', [], $options);
        $data = json_decode($response, true);
        if (is_array($data) && array_key_exists('score', $data)) {
            $score = (float)$data['score'];
        }
    } catch (Exception $e) {
        // Falha ao obter score; retorna null para evitar quebrar o bloco.
        return null;
    }

    try {
        $response2 = $curl->get($baseurl . '/monitoring/' . urlencode($monitoringid) . '/issues-stats', [], $options);
        $data2 = json_decode($response2, true);
        if (is_array($data2)) {
            $issues = [
                'passed' => isset($data2['passed']) ? (int)$data2['passed'] : 0,
                'warnings' => isset($data2['warnings']) ? (int)$data2['warnings'] : 0,
                'failed' => isset($data2['failed']) ? (int)$data2['failed'] : 0,
                'inapplicable' => isset($data2['inapplicable']) ? (int)$data2['inapplicable'] : 0,
            ];
        }
    } catch (Exception $e) {
        // Em caso de falha, mantém apenas o score.
    }

    if ($score === null && $issues === null) {
        return null;
    }

    return [
        'score' => $score,
        'issues' => $issues,
    ];
}

