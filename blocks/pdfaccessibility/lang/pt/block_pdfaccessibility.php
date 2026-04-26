<?php
// Portuguese language strings for block_pdfaccessibility.

$string['pluginname'] = 'Verificador de Acessibilidade de PDF';
$string['summary'] = 'Resumo';
$string['report'] = 'Relatório detalhado';
$string['nocourse'] = 'Não há nenhum curso disponível para mostrar este bloco.';
$string['pdfs_found'] = 'PDFs encontrados:';
$string['no_pdfs_found'] = 'Nenhum PDF encontrado.';
$string['pdfs_in_draft'] = 'PDFs em rascunho:';
$string['add_pdf_to_evaluate'] = 'Adicione um PDF para ser avaliado.';
$string['context_not_found'] = 'Contexto não encontrado na base de dados. O bloco só funciona em páginas de curso ou recursos já criados.';
$string['not_evaluated'] = 'Não avaliado';
$string['error_analyzing'] = 'Erro ao analisar PDF.';
$string['network_error'] = 'Erro de rede ou do servidor ao analisar o PDF.';
$string['analyzing'] = 'A analisar a acessibilidade do PDF, por favor aguarde...';
$string['status_pass'] = 'Passou';
$string['status_fail'] = 'Falhou';
$string['status_nonapplicable'] = 'Não aplicável';
$string['status_not_tagged'] = 'PDF não marcado';
$string['tests_passed_label'] = 'passou';
$string['tests_failed_label'] = 'falhou';
$string['not_tagged_help'] = 'Este PDF não está marcado. Não é possível verificar a acessibilidade deste conteúdo.';

// Rótulos dos testes, descrições e texto de ajuda (utilizados no relatório detalhado)
$string['test_title_label'] = 'Verificação do título do documento';
$string['test_title_desc'] = 'Verifica se o PDF tem um título real definido nas suas propriedades.';

$string['test_languagesmatch_label'] = 'Verificação de consistência do idioma';
$string['test_languagesmatch_desc'] = 'Garante que o idioma definido no documento corresponde ao conteúdo real.';

$string['test_pdfonlyimage_label'] = 'Verificação de aplicação de OCR';
$string['test_pdfonlyimage_desc'] = 'Verifica se o PDF é apenas uma imagem digitalizada de texto.';

$string['test_linksvalid_label'] = 'Verificação da validade das hiperligações';
$string['test_linksvalid_desc'] = 'Verifica se todas as hiperligações funcionam e estão corretamente marcadas.';

$string['test_figuresalt_label'] = 'Verificação de texto alternativo das imagens';
$string['test_figuresalt_desc'] = 'Verifica se as imagens têm descrições de "Texto Alternativo".';

$string['test_lists_label'] = 'Verificação de marcação de listas';
$string['test_lists_desc'] = 'Garante que as listas visuais estão corretamente marcadas no código.';

$string['test_tableheaders_label'] = 'Verificação de cabeçalhos de tabela';
$string['test_tableheaders_desc'] = 'Verifica se as tabelas de dados têm cabeçalhos definidos.';

$string['test_howtofix'] = 'Como corrigir?';

$string['test_title_url'] = 'https://moodle.ciencias.ulisboa.pt/mod/resource/view.php?id=321689';
$string['test_languagesmatch_url'] = 'https://moodle.ciencias.ulisboa.pt/mod/resource/view.php?id=321689';
$string['test_pdfonlyimage_url'] = 'https://moodle.ciencias.ulisboa.pt/mod/resource/view.php?id=321689';
$string['test_linksvalid_url'] = 'https://moodle.ciencias.ulisboa.pt/mod/resource/view.php?id=321689';
$string['test_figuresalt_url'] = 'https://moodle.ciencias.ulisboa.pt/mod/resource/view.php?id=321689';
$string['test_lists_url'] = 'https://moodle.ciencias.ulisboa.pt/mod/resource/view.php?id=321689';
$string['test_tableheaders_url'] = 'https://moodle.ciencias.ulisboa.pt/mod/resource/view.php?id=321689';
