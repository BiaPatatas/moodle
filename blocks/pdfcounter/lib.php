<?php
// Funções auxiliares para AJAX do bloco PDFCounter

define('BLOCK_PDFCOUNTER_MAX_PER_CALL', 1); // Só avalia 1 por chamada AJAX

/**
 * Resolve a writable debug directory for PdfCounter logs.
 *
 * @return string|null Writable directory path or null if none is available.
 */
function block_pdfcounter_get_writable_debug_dir() {
    global $CFG;

    if (empty($CFG)) {
        return null;
    }

    $candidatedirs = [
        (!empty($CFG->dirroot) ? $CFG->dirroot . '/blocks/pdfcounter/debug' : null),
        (!empty($CFG->dataroot) ? $CFG->dataroot . '/temp/block_pdfcounter/debug' : null),
        sys_get_temp_dir() . '/block_pdfcounter_debug',
    ];

    foreach ($candidatedirs as $dir) {
        if (empty($dir)) {
            continue;
        }
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        if (is_dir($dir) && is_writable($dir)) {
            return $dir;
        }
    }

    return null;
}

/**
 * Regista mensagens de debug do PdfCounter em ficheiros dentro de blocks/pdfcounter/debug.
 *
 * @param string $message Mensagem principal de log.
 * @param array $data Dados adicionais a serializar em JSON.
 * @param string $filename Nome do ficheiro de log dentro da pasta debug.
 */
function block_pdfcounter_debug_log(string $message, array $data = [], string $filename = 'pdfcounter_debug.log'): void {
    if (empty($GLOBALS['CFG'])) {
        // Se por algum motivo $CFG não estiver disponível, faz fallback para error_log.
        error_log('PdfCounter DEBUG (fallback) - ' . $message . ' ' . json_encode($data));
        return;
    }

    $debugdir = block_pdfcounter_get_writable_debug_dir();
    if ($debugdir === null) {
        error_log('PdfCounter DEBUG (no writable log dir) - ' . $message . ' ' . json_encode($data));
        return;
    }

    $safefilename = basename($filename);
    $logfile = $debugdir . '/' . $safefilename;
    $entry = '[' . date('Y-m-d H:i:s') . "] " . $message;
    if (!empty($data)) {
        $entry .= ' ' . json_encode($data);
    }
    $entry .= PHP_EOL;

    $written = @file_put_contents($logfile, $entry, FILE_APPEND);
    if ($written === false) {
        error_log('PdfCounter DEBUG (file_put_contents failed) - ' . $message . ' ' . json_encode($data));
    }
}

/**
 * Igual a block_pdfcounter_debug_log, mas garante que só é registado uma vez
 * por identificador lógico (por exemplo, por combinação url/curso/utilizador).
 *
 * @param string $id Identificador único lógico para o evento (será usado num ficheiro .flag).
 * @param string $message Mensagem principal de log.
 * @param array $data Dados adicionais a serializar em JSON.
 * @param string $filename Nome do ficheiro de log dentro da pasta debug.
 */
function block_pdfcounter_debug_log_once(string $id, string $message, array $data = [], string $filename = 'pdfcounter_debug.log'): void {
    if (empty($GLOBALS['CFG'])) {
        // Fallback para não falhar em contextos estranhos.
        block_pdfcounter_debug_log($message, $data, $filename);
        return;
    }

    $debugdir = block_pdfcounter_get_writable_debug_dir();
    if ($debugdir === null) {
        block_pdfcounter_debug_log($message, $data, $filename);
        return;
    }

    $safeid = preg_replace('/[^a-zA-Z0-9_-]/', '_', $id);
    $marker = $debugdir . '/.once_' . $safeid . '.flag';
    if (file_exists($marker)) {
        return; // Já foi registado anteriormente.
    }

    @file_put_contents($marker, '1');
    block_pdfcounter_debug_log($message, $data, $filename);
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
        // Debug logging de download externo (apenas uma vez por url/curso/utilizador).
        $pdfdata = @file_get_contents($pdfinfo['url']);
        if ($pdfdata === false) {
            $onceid = sha1('external_fail|' . $pdfinfo['url'] . '|' . $courseid . '|' . $userid);
            block_pdfcounter_debug_log_once($onceid, 'Falha ao baixar PDF externo', [
                'url' => $pdfinfo['url'],
                'courseid' => $courseid,
                'userid' => $userid,
            ], 'pdfcounter_debug.log');
            return ['error' => 'Falha ao baixar PDF externo'];
        }
        file_put_contents($targetfile, $pdfdata);
        $localpath = $targetfile;
        // Atualiza o filehash para o normalizado
        $pdfinfo['filehash'] = $hash;
    }
    if (!$localpath || !file_exists($localpath)) {
        block_pdfcounter_debug_log('Arquivo PDF não encontrado para avaliação', [
            'localpath' => $localpath,
            'courseid' => $courseid,
            'userid' => $userid,
            'filehash' => $pdfinfo['filehash'] ?? null,
        ], 'pdfcounter_debug.log');
        return ['error' => 'Arquivo PDF não encontrado para avaliação'];
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
        $onceid = sha1('python_fail|' . $localpath . '|' . $courseid . '|' . $userid . '|' . ($pdfinfo['filehash'] ?? ''));
        block_pdfcounter_debug_log_once($onceid, 'Falha na análise Python do PDF', [
            'localpath' => $localpath,
            'courseid' => $courseid,
            'userid' => $userid,
            'filehash' => $pdfinfo['filehash'] ?? null,
            'rawoutput' => isset($output) ? $output : null,
        ], 'pdfcounter_debug.log');
        return ['error' => 'Falha na análise Python do PDF'];
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
