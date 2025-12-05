<?php
// Funções auxiliares para AJAX do bloco PDFCounter

define('BLOCK_PDFCOUNTER_MAX_PER_CALL', 1); // Só avalia 1 por chamada AJAX

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
            // Debug: mostrar resultado da busca
            $debugfile = $CFG->dirroot . '/blocks/pdfcounter/debug/debug_pdf_ajax.txt';
            $msg = "[DEBUG] Busca registro: filehash=$filehash, courseid=$courseid, resultado=" . json_encode($pdfrecord) . "\n";
            file_put_contents($debugfile, $msg, FILE_APPEND);
            // Dump all records for this course after lookup
            $allpdfs = $DB->get_records('block_pdfaccessibility_pdf_files', ['courseid' => $courseid]);
            file_put_contents($debugfile, "[DEBUG] Todos registros após lookup: " . json_encode($allpdfs) . "\n", FILE_APPEND);
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
                // Debug: mostrar resultado da busca
                $debugfile = $CFG->dirroot . '/blocks/pdfcounter/debug/debug_pdf_ajax.txt';
                $msg = "[DEBUG] Busca registro (page): filehash=$filehash, courseid=$courseid, resultado=" . json_encode($pdfrecord) . "\n";
                file_put_contents($debugfile, $msg, FILE_APPEND);
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
            $debugfile = $CFG->dirroot . '/blocks/pdfcounter/debug/debug_pdf_ajax.txt';
            file_put_contents($debugfile, "[DEBUG] PENDING: url=" . $url->externalurl . ", normalized=" . $urlbase . ", hash=" . $filehash . "\n", FILE_APPEND);
            // Debug: log SQL, params, and types
            $params = [
                'filehash' => (string)$filehash,
                'courseid' => $courseid
            ];
            $debugfile = $CFG->dirroot . '/blocks/pdfcounter/debug/debug_pdf_ajax.txt';
            $msg = "[DEBUG] Lookup SQL: SELECT * FROM block_pdfaccessibility_pdf_files WHERE filehash='" . $filehash . "' AND courseid='" . $courseid . "'\n";
            $msg .= "[DEBUG] Lookup params: " . json_encode($params) . "\n";
            $msg .= "[DEBUG] Lookup param types: filehash=" . gettype($filehash) . ", courseid=" . gettype($courseid) . "\n";
            file_put_contents($debugfile, $msg, FILE_APPEND);
            $pdfrecord = $DB->get_record('block_pdfaccessibility_pdf_files', $params);
            $msg = "[DEBUG] Busca registro (url): filehash=$filehash, courseid=$courseid, resultado=" . json_encode($pdfrecord) . "\n";
            file_put_contents($debugfile, $msg, FILE_APPEND);
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
    $debugfile = $CFG->dirroot . '/blocks/pdfcounter/debug/debug_pdf_ajax.txt';
        $allpdfs_nofilter = $DB->get_records('block_pdfaccessibility_pdf_files');
        $hashes = [];
        foreach ($allpdfs_nofilter as $rec) {
            $hashes[] = ['filehash' => $rec->filehash, 'filename' => $rec->filename, 'courseid' => $rec->courseid];
        }
        file_put_contents($debugfile, "[DEBUG] Hashes in table after insert: " . json_encode($hashes) . "\n", FILE_APPEND);
    
    // Log types of values being inserted
    $msg = "[DEBUG] Insert types: filehash=" . gettype($pdfinfo['filehash']) . ", courseid=" . gettype($courseid) . ", userid=" . gettype($userid) . ", filename=" . gettype($pdfinfo['filename']) . "\n";
    file_put_contents($debugfile, $msg, FILE_APPEND);
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
        $debugfile = $CFG->dirroot . '/blocks/pdfcounter/debug/debug_pdf_ajax.txt';
        file_put_contents($debugfile, "[DEBUG] EVAL: url=" . $pdfinfo['url'] . ", normalized=" . $normalized_url . ", hash=" . $hash . "\n", FILE_APPEND);
        $subdir1 = substr($hash, 0, 2);
        $subdir2 = substr($hash, 2, 2);
        $targetdir = $CFG->dataroot . '/filedir/' . $subdir1 . '/' . $subdir2;
        if (!is_dir($targetdir)) {
            mkdir($targetdir, 0777, true);
        }
        $targetfile = $targetdir . '/' . $hash;
        file_put_contents($debugfile, "[mod_url] Baixando PDF externo: " . $pdfinfo['url'] . " para $targetfile\n", FILE_APPEND);
        $pdfdata = @file_get_contents($pdfinfo['url']);
        if ($pdfdata === false) {
            file_put_contents($debugfile, "[mod_url] Falha ao baixar PDF externo: " . $pdfinfo['url'] . "\n", FILE_APPEND);
            return ['error' => 'Falha ao baixar PDF externo'];
        }
        file_put_contents($targetfile, $pdfdata);
        $localpath = $targetfile;
        // Atualiza o filehash para o normalizado
        $pdfinfo['filehash'] = $hash;
    }
    if (!$localpath || !file_exists($localpath)) {
        file_put_contents($debugfile, "[mod_url] Arquivo não encontrado: $localpath\n", FILE_APPEND);
        return ['error' => 'Arquivo não encontrado'];
    }
    $script = $CFG->dirroot . '/blocks/pdfaccessibility/pdf_accessibility.py';
    $python_commands = ['python3', 'python', 'py', 'python.exe'];
    $result = null;
    foreach ($python_commands as $python) {
        $command = escapeshellarg($python) . ' ' . escapeshellarg($script) . ' ' . escapeshellarg($localpath);
        file_put_contents($debugfile, "[mod_url] Executando: $command\n", FILE_APPEND);
        $output = shell_exec($command . ' 2>&1');
        $decoded = json_decode($output, true);
        if ($decoded && is_array($decoded)) {
            $result = $decoded;
            break;
        }
    }
    // Não apaga o arquivo temporário para evitar reavaliação repetida
    if (!$result || !is_array($result)) {
        file_put_contents($debugfile, "[mod_url] Falha na análise Python\n", FILE_APPEND);
        return ['error' => 'Falha na análise Python'];
    }
    $pdfrecord = new stdClass();
    $pdfrecord->courseid = $courseid;
    $pdfrecord->userid = $userid;
    $pdfrecord->filehash = $pdfinfo['filehash'];
    $pdfrecord->filename = $pdfinfo['filename'];
    $pdfrecord->timecreated = time();
    // Debug: log objeto antes do insert
    $debugfile = $CFG->dirroot . '/blocks/pdfcounter/debug/debug_pdf_ajax.txt';
    $msg = "[DEBUG] Antes do insert: " . json_encode($pdfrecord) . "\n";
    file_put_contents($debugfile, $msg, FILE_APPEND);
    $pdfid = $DB->insert_record('block_pdfaccessibility_pdf_files', $pdfrecord, true);
    // Debug: log result of insert
    $msg = "[DEBUG] insert_record result: pdfid=$pdfid, filehash={$pdfinfo['filehash']}, courseid={$courseid}, filename={$pdfinfo['filename']}\n";
    file_put_contents($debugfile, $msg, FILE_APPEND);
    // Log all records with this filehash after insert
    $allpdfs_filehash = $DB->get_records('block_pdfaccessibility_pdf_files', ['filehash' => $pdfinfo['filehash']]);
    file_put_contents($debugfile, "[DEBUG] Registros com filehash após insert: " . json_encode($allpdfs_filehash) . "\n", FILE_APPEND);
    // Dump all records for this course
    $allpdfs = $DB->get_records('block_pdfaccessibility_pdf_files', ['courseid' => $courseid]);
    file_put_contents($debugfile, "[DEBUG] Todos registros após insert: " . json_encode($allpdfs) . "\n", FILE_APPEND);
    // Log all records in the table (no filter)
    $allpdfs_nofilter = $DB->get_records('block_pdfaccessibility_pdf_files');
    file_put_contents($debugfile, "[DEBUG] Todos registros tabela (sem filtro): " . json_encode($allpdfs_nofilter) . "\n", FILE_APPEND);
    // Log all records with this filehash after insert
    $allpdfs_filehash = $DB->get_records('block_pdfaccessibility_pdf_files', ['filehash' => $pdfinfo['filehash']]);
    file_put_contents($debugfile, "[DEBUG] Todos registros com filehash após insert: " . json_encode($allpdfs_filehash) . "\n", FILE_APPEND);
    pdf_accessibility_config::process_and_store_results($DB, $result, $pdfid);
    return ['success' => true, 'pdfid' => $pdfid];
}
